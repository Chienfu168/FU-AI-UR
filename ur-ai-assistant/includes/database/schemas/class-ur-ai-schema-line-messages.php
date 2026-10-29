<?php
/**
 * UR AI Assistant Schema: LINE Messages
 *
 * LINE 官方帳號客服模組的對話紀錄表：供「帶最近 N 則對話脈絡給 AI」與
 * 專人模式期間的訊息留存使用。與網站問答紀錄（ur_ai_logs）分開存放，
 * 因為兩者的識別方式（LINE userId vs. WP 使用者／IP）與用途不同。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Line_Messages
 */
class UR_AI_Schema_Line_Messages {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_line_messages';
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
            chat_id VARCHAR(80) NOT NULL DEFAULT '',
            line_user_id VARCHAR(64) NOT NULL DEFAULT '',
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            source VARCHAR(20) NOT NULL DEFAULT '',
            content TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY chat_created (chat_id, created_at),
            KEY created_at (created_at)
        ) {$charset_collate};";
    }
}
