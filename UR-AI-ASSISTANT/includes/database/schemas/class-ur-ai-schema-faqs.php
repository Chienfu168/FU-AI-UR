<?php
/**
 * UR AI Assistant FAQs Schema
 *
 * FAQ 知識庫資料表 Schema。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_FAQs
 */
class UR_AI_Schema_FAQs {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_faqs';
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

            category VARCHAR(100) DEFAULT '',
            question TEXT NOT NULL,
            answer LONGTEXT NOT NULL,
            keywords TEXT NULL,

            status VARCHAR(30) DEFAULT 'inactive',
            source VARCHAR(30) DEFAULT 'manual',
            source_log_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_popular_question_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

            sort_order INT UNSIGNED NOT NULL DEFAULT 100,
            hit_count INT UNSIGNED NOT NULL DEFAULT 0,

            admin_note TEXT NULL,
            review_status VARCHAR(30) DEFAULT 'draft',
            reviewed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            reviewed_at DATETIME NULL,

            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,

            PRIMARY KEY  (id),
            KEY category (category),
            KEY status (status),
            KEY source (source),
            KEY source_log_id (source_log_id),
            KEY source_popular_question_id (source_popular_question_id),
            KEY sort_order (sort_order),
            KEY hit_count (hit_count),
            KEY review_status (review_status),
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
            'category'                   => "VARCHAR(100) DEFAULT ''",
            'question'                   => "TEXT NOT NULL",
            'answer'                     => "LONGTEXT NOT NULL",
            'keywords'                   => "TEXT NULL",

            'status'                     => "VARCHAR(30) DEFAULT 'inactive'",
            'source'                     => "VARCHAR(30) DEFAULT 'manual'",
            'source_log_id'              => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'source_popular_question_id' => "BIGINT UNSIGNED NOT NULL DEFAULT 0",

            'sort_order'                 => "INT UNSIGNED NOT NULL DEFAULT 100",
            'hit_count'                  => "INT UNSIGNED NOT NULL DEFAULT 0",

            'admin_note'                 => "TEXT NULL",
            'review_status'              => "VARCHAR(30) DEFAULT 'draft'",
            'reviewed_by'                => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'reviewed_at'                => "DATETIME NULL",

            'created_by'                 => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'updated_by'                 => "BIGINT UNSIGNED NOT NULL DEFAULT 0",

            'created_at'                 => "DATETIME NOT NULL",
            'updated_at'                 => "DATETIME NULL",
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
     * 取得來源選項。
     *
     * @return array
     */
    public static function get_sources() {
        return array(
            'manual'           => __('手動建立', 'ur-ai-assistant'),
            'ai_log'           => __('AI 問答轉出', 'ur-ai-assistant'),
            'popular_question' => __('熱門問題轉出', 'ur-ai-assistant'),
            'import'           => __('匯入建立', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得審核狀態選項。
     *
     * 第一版先保留欄位與選項，未來可擴充正式審核流程。
     *
     * @return array
     */
    public static function get_review_statuses() {
        return array(
            'draft'    => __('草稿', 'ur-ai-assistant'),
            'pending'  => __('待審核', 'ur-ai-assistant'),
            'approved' => __('已審核', 'ur-ai-assistant'),
            'rejected' => __('退回', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得建議分類。
     *
     * @return array
     */
    public static function get_default_categories() {
        return array(
            '都市更新',
            '危老重建',
            '更新會',
            '自主更新',
            '權利變換',
            '協議合建',
            '行政救濟',
            '同意與程序',
            '信託與資金控管',
            '估價與分配',
            '共同負擔',
            '其他',
            '待分類',
        );
    }

    /**
     * 取得可匯出的欄位。
     *
     * @return array
     */
    public static function get_export_columns() {
        return array(
            'id'                          => __('ID', 'ur-ai-assistant'),
            'category'                    => __('分類', 'ur-ai-assistant'),
            'question'                    => __('標準問題', 'ur-ai-assistant'),
            'answer'                      => __('固定回答', 'ur-ai-assistant'),
            'keywords'                    => __('關鍵字', 'ur-ai-assistant'),
            'status'                      => __('狀態', 'ur-ai-assistant'),
            'source'                      => __('來源', 'ur-ai-assistant'),
            'source_log_id'               => __('來源問答紀錄 ID', 'ur-ai-assistant'),
            'source_popular_question_id'  => __('來源熱門問題 ID', 'ur-ai-assistant'),
            'sort_order'                  => __('排序', 'ur-ai-assistant'),
            'hit_count'                   => __('命中次數', 'ur-ai-assistant'),
            'review_status'               => __('審核狀態', 'ur-ai-assistant'),
            'admin_note'                  => __('管理備註', 'ur-ai-assistant'),
            'created_at'                  => __('建立時間', 'ur-ai-assistant'),
            'updated_at'                  => __('更新時間', 'ur-ai-assistant'),
        );
    }
}