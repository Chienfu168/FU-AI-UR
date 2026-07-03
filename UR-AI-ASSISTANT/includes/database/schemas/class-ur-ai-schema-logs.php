<?php
/**
 * UR AI Assistant Logs Schema
 *
 * 問答紀錄資料表 Schema。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Logs
 */
class UR_AI_Schema_Logs {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_logs';
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
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            session_id VARCHAR(100) DEFAULT '',
            ip_address VARCHAR(100) DEFAULT '',
            user_agent TEXT NULL,

            question TEXT NOT NULL,
            answer LONGTEXT NULL,
            answer_source VARCHAR(30) DEFAULT '',
            answer_title VARCHAR(100) DEFAULT '',
            model VARCHAR(100) DEFAULT '',
            tokens_used INT UNSIGNED NOT NULL DEFAULT 0,

            faq_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            faq_match_score INT UNSIGNED NOT NULL DEFAULT 0,
            faq_matched_keywords TEXT NULL,
            converted_faq_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

            has_related_pages TINYINT(1) NOT NULL DEFAULT 0,
            related_page_ids TEXT NULL,

            feedback VARCHAR(30) DEFAULT '',
            feedback_reason VARCHAR(100) DEFAULT '',
            feedback_comment TEXT NULL,

            status VARCHAR(30) DEFAULT 'success',
            error_code VARCHAR(100) DEFAULT '',
            error_message TEXT NULL,

            request_meta LONGTEXT NULL,
            response_meta LONGTEXT NULL,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,

            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY ip_address (ip_address),
            KEY answer_source (answer_source),
            KEY faq_id (faq_id),
            KEY converted_faq_id (converted_faq_id),
            KEY has_related_pages (has_related_pages),
            KEY feedback (feedback),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";
    }

    /**
     * 取得欄位補強定義。
     *
     * key 是欄位名稱，value 是 ALTER TABLE ADD 後面的欄位定義。
     *
     * @return array
     */
    public static function get_columns() {
        return array(
            'user_id'              => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'session_id'           => "VARCHAR(100) DEFAULT ''",
            'ip_address'           => "VARCHAR(100) DEFAULT ''",
            'user_agent'           => "TEXT NULL",

            'question'             => "TEXT NOT NULL",
            'answer'               => "LONGTEXT NULL",
            'answer_source'        => "VARCHAR(30) DEFAULT ''",
            'answer_title'         => "VARCHAR(100) DEFAULT ''",
            'model'                => "VARCHAR(100) DEFAULT ''",
            'tokens_used'          => "INT UNSIGNED NOT NULL DEFAULT 0",

            'faq_id'               => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'faq_match_score'      => "INT UNSIGNED NOT NULL DEFAULT 0",
            'faq_matched_keywords' => "TEXT NULL",
            'converted_faq_id'     => "BIGINT UNSIGNED NOT NULL DEFAULT 0",

            'has_related_pages'    => "TINYINT(1) NOT NULL DEFAULT 0",
            'related_page_ids'     => "TEXT NULL",

            'feedback'             => "VARCHAR(30) DEFAULT ''",
            'feedback_reason'      => "VARCHAR(100) DEFAULT ''",
            'feedback_comment'     => "TEXT NULL",

            'status'               => "VARCHAR(30) DEFAULT 'success'",
            'error_code'           => "VARCHAR(100) DEFAULT ''",
            'error_message'        => "TEXT NULL",

            'request_meta'         => "LONGTEXT NULL",
            'response_meta'        => "LONGTEXT NULL",

            'created_at'           => "DATETIME NOT NULL",
            'updated_at'           => "DATETIME NULL",
        );
    }

    /**
     * 取得預設索引資訊。
     *
     * 第一版主要由 dbDelta 建立索引。
     * 此方法保留給未來 Migration 使用。
     *
     * @return array
     */
    public static function get_indexes() {
        return array(
            'user_id',
            'session_id',
            'ip_address',
            'answer_source',
            'faq_id',
            'converted_faq_id',
            'has_related_pages',
            'feedback',
            'status',
            'created_at',
        );
    }

    /**
     * 取得可匯出的欄位。
     *
     * @return array
     */
    public static function get_export_columns() {
        return array(
            'id'                   => __('ID', 'ur-ai-assistant'),
            'created_at'           => __('建立時間', 'ur-ai-assistant'),
            'user_id'              => __('使用者 ID', 'ur-ai-assistant'),
            'ip_address'           => __('IP 位址', 'ur-ai-assistant'),
            'question'             => __('問題', 'ur-ai-assistant'),
            'answer'               => __('回答', 'ur-ai-assistant'),
            'answer_source'        => __('回答來源', 'ur-ai-assistant'),
            'model'                => __('模型', 'ur-ai-assistant'),
            'tokens_used'          => __('Token 使用量', 'ur-ai-assistant'),
            'faq_id'               => __('FAQ ID', 'ur-ai-assistant'),
            'faq_match_score'      => __('FAQ 命中分數', 'ur-ai-assistant'),
            'faq_matched_keywords' => __('命中關鍵字', 'ur-ai-assistant'),
            'converted_faq_id'     => __('已轉 FAQ ID', 'ur-ai-assistant'),
            'has_related_pages'    => __('是否有推薦頁面', 'ur-ai-assistant'),
            'related_page_ids'     => __('推薦頁面 ID', 'ur-ai-assistant'),
            'feedback'             => __('使用者回饋', 'ur-ai-assistant'),
            'feedback_reason'      => __('沒幫助原因', 'ur-ai-assistant'),
            'feedback_comment'     => __('回饋補充', 'ur-ai-assistant'),
            'status'               => __('狀態', 'ur-ai-assistant'),
            'error_code'           => __('錯誤代碼', 'ur-ai-assistant'),
            'error_message'        => __('錯誤訊息', 'ur-ai-assistant'),
        );
    }
}