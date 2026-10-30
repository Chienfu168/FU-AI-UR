<?php
/**
 * UR AI Assistant LINE Customer Repository
 *
 * 客戶狀態（bot/human 模式）資料存取層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Customer_Repository
 */
class UR_AI_Line_Customer_Repository {

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
        $this->table_name = class_exists('UR_AI_Schema_Line_Customers')
            ? UR_AI_Schema_Line_Customers::get_table_name()
            : $GLOBALS['wpdb']->prefix . 'ur_ai_line_customers';
    }

    /**
     * 自我修復：資料表不存在就建立（繞過 dbDelta 在部分主機未建表的問題）。
     *
     * @return void
     */
    private function ensure_schema() {
        global $wpdb;

        if (self::$schema_ready) {
            return;
        }

        self::$schema_ready = true;

        $table  = $this->table_name;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($exists !== $table && class_exists('UR_AI_Schema_Line_Customers')) {
            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }

            dbDelta(UR_AI_Schema_Line_Customers::get_sql());
        }
    }

    /**
     * 依 line_user_id 取得客戶，不存在則建立。
     *
     * @param string $line_user_id LINE userId。
     * @param string $chat_id      對話 id（1:1 為 userId、群組為 groupId）。
     * @return object|null
     */
    public function get_or_create($line_user_id, $chat_id = '') {
        global $wpdb;

        $line_user_id = trim((string) $line_user_id);

        if ('' === $line_user_id) {
            return null;
        }

        $this->ensure_schema();

        $existing = $this->find_by_line_user_id($line_user_id);

        if ($existing) {
            return $existing;
        }

        $now = current_time('mysql');

        $wpdb->insert(
            $this->table_name,
            array(
                'line_user_id' => $line_user_id,
                'chat_id'      => (string) $chat_id,
                'mode'         => 'bot',
                'created_at'   => $now,
                'updated_at'   => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        return $this->find_by_line_user_id($line_user_id);
    }

    /**
     * 依 line_user_id 查找。
     *
     * @param string $line_user_id LINE userId。
     * @return object|null
     */
    public function find_by_line_user_id($line_user_id) {
        global $wpdb;

        $line_user_id = trim((string) $line_user_id);

        if ('' === $line_user_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE line_user_id = %s", $line_user_id)
        );
    }

    /**
     * 設定處理模式（bot／human）。切到 human 時記錄時間戳。
     *
     * @param string $line_user_id LINE userId。
     * @param string $mode         bot｜human。
     * @return void
     */
    public function set_mode($line_user_id, $mode) {
        global $wpdb;

        $line_user_id = trim((string) $line_user_id);
        $mode         = ('human' === $mode) ? 'human' : 'bot';

        if ('' === $line_user_id) {
            return;
        }

        $data = array(
            'mode'        => $mode,
            'human_since' => ('human' === $mode) ? current_time('mysql') : null,
            'updated_at'  => current_time('mysql'),
        );

        $wpdb->update($this->table_name, $data, array('line_user_id' => $line_user_id));
    }

    /**
     * 查詢客戶列表（後台對話管理頁用）。
     *
     * @param array $args mode／search／limit／offset。
     * @return array
     */
    public function query($args = array()) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            array('mode' => '', 'search' => '', 'limit' => 20, 'offset' => 0)
        );

        $this->ensure_schema();

        $where  = array('1=1');
        $values = array();

        if ('' !== $args['mode']) {
            $where[]  = 'mode = %s';
            $values[] = sanitize_key($args['mode']);
        }

        if ('' !== $args['search']) {
            $like     = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[]  = '(line_user_id LIKE %s OR display_name LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $limit  = absint($args['limit']);
        $offset = absint($args['offset']);

        if ($limit <= 0) {
            $limit = 20;
        }

        $sql  = "SELECT * FROM {$this->table_name} WHERE " . implode(' AND ', $where);
        $sql .= ' ORDER BY (last_message_at IS NULL), last_message_at DESC, id DESC LIMIT %d OFFSET %d';

        $values[] = $limit;
        $values[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * 計算符合條件的客戶筆數。
     *
     * @param array $args mode／search。
     * @return int
     */
    public function count($args = array()) {
        global $wpdb;

        $args = wp_parse_args($args, array('mode' => '', 'search' => ''));

        $where  = array('1=1');
        $values = array();

        if ('' !== $args['mode']) {
            $where[]  = 'mode = %s';
            $values[] = sanitize_key($args['mode']);
        }

        if ('' !== $args['search']) {
            $like     = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[]  = '(line_user_id LIKE %s OR display_name LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE " . implode(' AND ', $where);

        if (!empty($values)) {
            return absint($wpdb->get_var($wpdb->prepare($sql, $values)));
        }

        return absint($wpdb->get_var($sql));
    }

    /**
     * 更新顯示名稱與最後訊息時間。
     *
     * @param string $line_user_id LINE userId。
     * @param string $display_name 顯示名稱（可空）。
     * @return void
     */
    public function touch($line_user_id, $display_name = '') {
        global $wpdb;

        $line_user_id = trim((string) $line_user_id);

        if ('' === $line_user_id) {
            return;
        }

        $data = array(
            'last_message_at' => current_time('mysql'),
            'updated_at'      => current_time('mysql'),
        );

        if ('' !== trim((string) $display_name)) {
            $data['display_name'] = sanitize_text_field((string) $display_name);
        }

        $wpdb->update($this->table_name, $data, array('line_user_id' => $line_user_id));
    }
}
