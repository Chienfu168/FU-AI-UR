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
     * 建構子。
     */
    public function __construct() {
        $this->table_name = class_exists('UR_AI_Schema_Line_Messages')
            ? UR_AI_Schema_Line_Messages::get_table_name()
            : $GLOBALS['wpdb']->prefix . 'ur_ai_line_messages';
    }

    /**
     * 新增一則訊息。
     *
     * @param string $chat_id      對話 id。
     * @param string $line_user_id LINE userId。
     * @param string $role         user｜assistant。
     * @param string $content      內容。
     * @return int 新 id 或 0。
     */
    public function add($chat_id, $line_user_id, $role, $content) {
        global $wpdb;

        $content = (string) $content;

        if ('' === trim($content)) {
            return 0;
        }

        $ok = $wpdb->insert(
            $this->table_name,
            array(
                'chat_id'      => (string) $chat_id,
                'line_user_id' => (string) $line_user_id,
                'role'         => ('assistant' === $role) ? 'assistant' : 'user',
                'content'      => $content,
                'created_at'   => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
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
