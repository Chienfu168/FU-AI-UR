<?php
/**
 * UR AI Assistant Quiz Attempts Schema
 *
 * 知識大考驗作答紀錄／排行榜資料表 Schema。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Quiz_Attempts
 */
class UR_AI_Schema_Quiz_Attempts {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_quiz_attempts';
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

            nickname VARCHAR(60) DEFAULT '',
            nickname_key VARCHAR(60) DEFAULT '',

            score INT UNSIGNED NOT NULL DEFAULT 0,
            total_questions INT UNSIGNED NOT NULL DEFAULT 0,
            correct_count INT UNSIGNED NOT NULL DEFAULT 0,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,

            ip_hash VARCHAR(64) DEFAULT '',

            created_at DATETIME NOT NULL,

            PRIMARY KEY  (id),
            KEY nickname_key (nickname_key),
            KEY score (score),
            KEY ip_hash (ip_hash),
            KEY created_at (created_at)
        ) {$charset_collate};";
    }

    /**
     * 取得欄位補強定義。
     *
     * @return array
     */
    public static function get_columns() {
        return array(
            'nickname'         => "VARCHAR(60) DEFAULT ''",
            'nickname_key'     => "VARCHAR(60) DEFAULT ''",

            'score'            => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'total_questions'  => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'correct_count'    => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'duration_seconds' => 'INT UNSIGNED NOT NULL DEFAULT 0',

            'ip_hash'          => "VARCHAR(64) DEFAULT ''",

            'created_at'       => 'DATETIME NOT NULL',
        );
    }
}
