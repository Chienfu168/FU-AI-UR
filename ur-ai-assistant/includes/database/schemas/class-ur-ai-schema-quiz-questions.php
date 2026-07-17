<?php
/**
 * UR AI Assistant Quiz Questions Schema
 *
 * 知識大考驗題庫資料表 Schema。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Quiz_Questions
 */
class UR_AI_Schema_Quiz_Questions {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_quiz_questions';
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

            question TEXT NOT NULL,
            option_a TEXT NOT NULL,
            option_b TEXT NOT NULL,
            option_c TEXT NOT NULL,
            option_d TEXT NOT NULL,
            correct_option VARCHAR(1) NOT NULL DEFAULT 'a',
            explanation TEXT NULL,

            difficulty VARCHAR(20) DEFAULT 'medium',
            category VARCHAR(100) DEFAULT '',
            source_faq_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

            status VARCHAR(20) DEFAULT 'inactive',
            review_status VARCHAR(20) DEFAULT 'draft',
            source VARCHAR(20) DEFAULT 'manual',

            admin_note TEXT NULL,

            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,

            PRIMARY KEY  (id),
            KEY status (status),
            KEY review_status (review_status),
            KEY category (category),
            KEY difficulty (difficulty),
            KEY source_faq_id (source_faq_id)
        ) {$charset_collate};";
    }

    /**
     * 取得欄位補強定義。
     *
     * @return array
     */
    public static function get_columns() {
        return array(
            'question'       => 'TEXT NOT NULL',
            'option_a'       => 'TEXT NOT NULL',
            'option_b'       => 'TEXT NOT NULL',
            'option_c'       => 'TEXT NOT NULL',
            'option_d'       => 'TEXT NOT NULL',
            'correct_option' => "VARCHAR(1) NOT NULL DEFAULT 'a'",
            'explanation'    => 'TEXT NULL',

            'difficulty'     => "VARCHAR(20) DEFAULT 'medium'",
            'category'       => "VARCHAR(100) DEFAULT ''",
            'source_faq_id'  => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',

            'status'         => "VARCHAR(20) DEFAULT 'inactive'",
            'review_status'  => "VARCHAR(20) DEFAULT 'draft'",
            'source'         => "VARCHAR(20) DEFAULT 'manual'",

            'admin_note'     => 'TEXT NULL',

            'created_by'     => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'updated_by'     => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',

            'created_at'     => 'DATETIME NOT NULL',
            'updated_at'     => 'DATETIME NULL',
        );
    }

    /**
     * 取得難度選項。
     *
     * @return array
     */
    public static function get_difficulties() {
        return array(
            'easy'   => __('簡單', 'ur-ai-assistant'),
            'medium' => __('中等', 'ur-ai-assistant'),
            'hard'   => __('困難', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得狀態選項。
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            'active'   => __('啟用', 'ur-ai-assistant'),
            'inactive' => __('停用', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得審核狀態選項。
     *
     * @return array
     */
    public static function get_review_statuses() {
        return array(
            'draft'    => __('草稿待審核', 'ur-ai-assistant'),
            'approved' => __('已審核', 'ur-ai-assistant'),
            'rejected' => __('退回', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得來源選項。
     *
     * @return array
     */
    public static function get_sources() {
        return array(
            'manual'     => __('手動建立', 'ur-ai-assistant'),
            'ai_faq'     => __('AI 依 FAQ 產生', 'ur-ai-assistant'),
            'ai_article' => __('AI 依文章產生', 'ur-ai-assistant'),
        );
    }
}
