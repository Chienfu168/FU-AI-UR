<?php
/**
 * UR AI Assistant Calculator Leads Schema
 *
 * 都更分回試算「名單」資料表 Schema。
 *
 * 沿用既有 schema 慣例：提供 get_table_name() 與 get_sql()，
 * 由 UR_AI_Schema_Manager::create_tables() 透過 dbDelta 建立。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Calculator_Leads
 */
class UR_AI_Schema_Calculator_Leads {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_calculator_leads';
    }

    /**
     * 取得資料表 SQL（dbDelta 格式）。
     *
     * @return string
     */
    public static function get_sql() {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            name VARCHAR(100) DEFAULT '',
            tel VARCHAR(50) DEFAULT '',
            email VARCHAR(190) DEFAULT '',
            message TEXT NULL,
            consent TINYINT(1) NOT NULL DEFAULT 0,

            city VARCHAR(30) DEFAULT '',
            track VARCHAR(20) DEFAULT '',
            result_summary VARCHAR(190) DEFAULT '',
            context_json LONGTEXT NULL,

            source_url VARCHAR(255) DEFAULT '',
            ip_hash VARCHAR(64) DEFAULT '',
            cf7_form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

            status VARCHAR(20) DEFAULT 'new',
            admin_note TEXT NULL,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,

            PRIMARY KEY  (id),
            KEY city (city),
            KEY track (track),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";
    }

    /**
     * 欄位補強定義（供未來升級比對）。
     *
     * @return array
     */
    public static function get_columns() {
        return array(
            'name'           => "VARCHAR(100) DEFAULT ''",
            'tel'            => "VARCHAR(50) DEFAULT ''",
            'email'          => "VARCHAR(190) DEFAULT ''",
            'message'        => "TEXT NULL",
            'consent'        => "TINYINT(1) NOT NULL DEFAULT 0",
            'city'           => "VARCHAR(30) DEFAULT ''",
            'track'          => "VARCHAR(20) DEFAULT ''",
            'result_summary' => "VARCHAR(190) DEFAULT ''",
            'context_json'   => "LONGTEXT NULL",
            'source_url'     => "VARCHAR(255) DEFAULT ''",
            'ip_hash'        => "VARCHAR(64) DEFAULT ''",
            'cf7_form_id'    => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'status'         => "VARCHAR(20) DEFAULT 'new'",
            'admin_note'     => "TEXT NULL",
            'created_at'     => "DATETIME NOT NULL",
            'updated_at'     => "DATETIME NULL",
        );
    }

    /**
     * 狀態選項。
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            'new'       => __('新名單', 'ur-ai-assistant'),
            'contacted' => __('已聯繫', 'ur-ai-assistant'),
            'closed'    => __('已結案', 'ur-ai-assistant'),
            'invalid'   => __('無效', 'ur-ai-assistant'),
        );
    }
}
