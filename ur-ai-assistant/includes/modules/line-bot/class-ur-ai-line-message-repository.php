<?php
/**
 * UR AI Assistant LINE Message Repository
 *
 * LINE 對話紀錄資料存取層：寫入每則訊息、讀取某對話最近 N 則（供 AI
 * 脈絡）、清理逾期紀錄。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Message_Repository
 */
class UR_AI_Line_Message_Repository {

    /**
     * 資料表名稱。
     *
     * @var string
     */
    private $table_name;

    /**
     * 本次請求是否已檢查過資料表結構。
     *
     * @var bool
     */
    private static $schema_ready = false;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->table_name = class_exists('UR_AI_Schema_Line_Messages')
            ? UR_AI_Schema_Line_Messages::get_table_name()
            : $GLOBALS['wpdb']->prefix . 'ur_ai_line_messages';
    }

    /**
     * 自我修復資料表結構：資料表不存在就建立、缺 source 欄位就補上。
     *
     * 背景：dbDelta() 在部分主機環境可能沒把資料表或新欄位正確建立／
     * 升級（本專案 Schema_Manager 已有相同註記），一旦缺 source 欄位，
     * 寫入（含 source）會整筆失敗，造成「機器人有回覆、但對話完全沒
     * 記錄」。這裡在第一次讀寫時直接檢查並以 ALTER 補欄位，繞過 dbDelta
     * 的相容性問題，每個請求只檢查一次。
     *
     * @return void
     */
    private function ensure_schema() {
        global $wpdb;

        if (self::$schema_ready) {
            return;
        }

        self::$schema_ready = true;

        $table = $this->table_name;

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($exists !== $table) {
            if (class_exists('UR_AI_Schema_Line_Messages')) {
                if (!function_exists('dbDelta')) {
                    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                }

                dbDelta(UR_AI_Schema_Line_Messages::get_sql());
            }

            // 新建立的資料表已含 source 欄位，無須再補。
            return;
        }

        $has_source = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'source'));

        if (empty($has_source)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- 表名為內部常數，無使用者輸入。
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT '' AFTER role");
        }
    }

    /**
     * 新增一則訊息。
     *
     * @param string $chat_id      對話 id。
     * @param string $line_user_id LINE userId。
     * @param string $role         user｜assistant。
     * @param string $content      內容。
     * @param string $source       回答來源（faq｜ai｜system；user 訊息留空）。
     * @return int 新 id 或 0。
     */
    public function add($chat_id, $line_user_id, $role, $content, $source = '') {
        global $wpdb;

        $content = (string) $content;

        if ('' === trim($content)) {
            return 0;
        }

        $this->ensure_schema();

        $ok = $wpdb->insert(
            $this->table_name,
            array(
                'chat_id'      => (string) $chat_id,
                'line_user_id' => (string) $line_user_id,
                'role'         => ('assistant' === $role) ? 'assistant' : 'user',
                'source'       => sanitize_key((string) $source),
                'content'      => $content,
                'created_at'   => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $ok ? absint($wpdb->insert_id) : 0;
    }

    /**
     * 取得某對話最近 N 則（由舊到新排列，直接可當 AI messages 脈絡）。
     *
     * @param string $chat_id 對話 id。
     * @param int    $limit   筆數。
     * @return array 每筆 array{role, content}
     */
    public function recent_for_chat($chat_id, $limit = 10) {
        global $wpdb;

        $chat_id = (string) $chat_id;
        $limit   = max(1, absint($limit));

        if ('' === $chat_id) {
            return array();
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content FROM {$this->table_name} WHERE chat_id = %s ORDER BY id DESC LIMIT %d",
                $chat_id,
                $limit
            )
        );

        if (!is_array($rows)) {
            return array();
        }

        $rows = array_reverse($rows);

        $messages = array();

        foreach ($rows as $row) {
            $messages[] = array(
                'role'    => ('assistant' === $row->role) ? 'assistant' : 'user',
                'content' => (string) $row->content,
            );
        }

        return $messages;
    }

    /**
     * 取得某對話的完整訊息（由舊到新，含時間；後台對話檢視用）。
     *
     * @param string $chat_id 對話 id。
     * @param int    $limit   最多筆數。
     * @return array 每筆物件含 role／content／created_at。
     */
    public function list_for_chat($chat_id, $limit = 200) {
        global $wpdb;

        $chat_id = (string) $chat_id;
        $limit   = max(1, absint($limit));

        if ('' === $chat_id) {
            return array();
        }

        $this->ensure_schema();

        // 用 SELECT * 以免某欄位（如升級未完成的 source）不存在時整句失敗；
        // 顯示端以 isset() 容錯。
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE chat_id = %s ORDER BY id ASC LIMIT %d",
                $chat_id,
                $limit
            )
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * 刪除早於指定天數的紀錄。
     *
     * @param int $days 保留天數。
     * @return int 刪除筆數。
     */
    public function delete_older_than($days) {
        global $wpdb;

        $days = absint($days);

        if ($days <= 0) {
            return 0;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

        return (int) $wpdb->query(
            $wpdb->prepare("DELETE FROM {$this->table_name} WHERE created_at < %s", $cutoff)
        );
    }
}
