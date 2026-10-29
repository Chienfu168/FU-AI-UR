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
     * 建構子。
     */
    public function __construct() {
        $this->table_name = class_exists('UR_AI_Schema_Line_Customers')
            ? UR_AI_Schema_Line_Customers::get_table_name()
            : $GLOBALS['wpdb']->prefix . 'ur_ai_line_customers';
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
