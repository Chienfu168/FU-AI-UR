<?php
/**
 * UR AI Assistant Schema: LINE Customers
 *
 * LINE 官方帳號客服模組的客戶狀態表：記錄每位 LINE 使用者目前是由
 * AI（bot）還是專人（human）處理，以及進入專人模式的時間（供 12 小時
 * 自動恢復判斷）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Line_Customers
 */
class UR_AI_Schema_Line_Customers {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_line_customers';
    }

    /**
     * 取得資料表 SQL。
     *
     * @return string
     */
    public static function get_sql() {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            line_user_id VARCHAR(64) NOT NULL,
            chat_id VARCHAR(80) NOT NULL DEFAULT '',
            display_name VARCHAR(190) NOT NULL DEFAULT '',
            mode VARCHAR(20) NOT NULL DEFAULT 'bot',
            human_since DATETIME NULL,
            last_message_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY line_user_id (line_user_id),
            KEY mode (mode)
        ) {$charset_collate};";
    }
}
