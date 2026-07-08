<?php
/**
 * UR AI Assistant Related Pages Schema
 *
 * 相關頁面推薦資料表 Schema。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Related_Pages
 */
class UR_AI_Schema_Related_Pages {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_related_pages';
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
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT NULL,
            keywords TEXT NULL,

            status VARCHAR(30) DEFAULT 'inactive',
            source VARCHAR(30) DEFAULT 'manual',
            source_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

            sort_order INT UNSIGNED NOT NULL DEFAULT 100,
            show_count INT UNSIGNED NOT NULL DEFAULT 0,
            click_count INT UNSIGNED NOT NULL DEFAULT 0,

            admin_note TEXT NULL,

            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,

            PRIMARY KEY  (id),
            KEY category (category),
            KEY status (status),
            KEY source (source),
            KEY source_post_id (source_post_id),
            KEY sort_order (sort_order),
            KEY show_count (show_count),
            KEY click_count (click_count),
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
            'category'       => "VARCHAR(100) DEFAULT ''",
            'title'          => "TEXT NOT NULL",
            'url'            => "TEXT NOT NULL",
            'description'    => "TEXT NULL",
            'keywords'       => "TEXT NULL",

            'status'         => "VARCHAR(30) DEFAULT 'inactive'",
            'source'         => "VARCHAR(30) DEFAULT 'manual'",
            'source_post_id' => "BIGINT UNSIGNED NOT NULL DEFAULT 0",

            'sort_order'     => "INT UNSIGNED NOT NULL DEFAULT 100",
            'show_count'     => "INT UNSIGNED NOT NULL DEFAULT 0",
            'click_count'    => "INT UNSIGNED NOT NULL DEFAULT 0",

            'admin_note'     => "TEXT NULL",

            'created_by'     => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'updated_by'     => "BIGINT UNSIGNED NOT NULL DEFAULT 0",

            'created_at'     => "DATETIME NOT NULL",
            'updated_at'     => "DATETIME NULL",
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
            'manual' => __('手動建立', 'ur-ai-assistant'),
            'post'   => __('WordPress 文章匯入', 'ur-ai-assistant'),
            'page'   => __('WordPress 頁面匯入', 'ur-ai-assistant'),
            'import' => __('CSV 匯入', 'ur-ai-assistant'),
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
            'id'             => __('ID', 'ur-ai-assistant'),
            'category'       => __('分類', 'ur-ai-assistant'),
            'title'          => __('推薦標題', 'ur-ai-assistant'),
            'url'            => __('推薦網址', 'ur-ai-assistant'),
            'description'    => __('簡短說明', 'ur-ai-assistant'),
            'keywords'       => __('關鍵字', 'ur-ai-assistant'),
            'status'         => __('狀態', 'ur-ai-assistant'),
            'source'         => __('來源', 'ur-ai-assistant'),
            'source_post_id' => __('來源文章 ID', 'ur-ai-assistant'),
            'sort_order'     => __('排序', 'ur-ai-assistant'),
            'show_count'     => __('曝光次數', 'ur-ai-assistant'),
            'click_count'    => __('點擊次數', 'ur-ai-assistant'),
            'ctr'            => __('CTR', 'ur-ai-assistant'),
            'admin_note'     => __('管理備註', 'ur-ai-assistant'),
            'created_at'     => __('建立時間', 'ur-ai-assistant'),
            'updated_at'     => __('更新時間', 'ur-ai-assistant'),
        );
    }

    /**
     * 計算 CTR。
     *
     * @param int $show_count 曝光次數。
     * @param int $click_count 點擊次數。
     * @return float
     */
    public static function calculate_ctr($show_count, $click_count) {
        $show_count  = absint($show_count);
        $click_count = absint($click_count);

        if ($show_count <= 0) {
            return 0.0;
        }

        return round(($click_count / $show_count) * 100, 2);
    }

    /**
     * 取得推薦頁面成效狀態。
     *
     * @param int $show_count 曝光次數。
     * @param int $click_count 點擊次數。
     * @return string
     */
    public static function get_performance_status($show_count, $click_count) {
        $show_count  = absint($show_count);
        $click_count = absint($click_count);
        $ctr         = self::calculate_ctr($show_count, $click_count);

        if (0 === $show_count) {
            return 'not_shown';
        }

        if ($show_count > 0 && 0 === $click_count) {
            return 'shown_no_click';
        }

        if ($show_count >= 20 && $ctr < 3) {
            return 'low_ctr';
        }

        if ($show_count >= 10 && $ctr >= 10) {
            return 'good';
        }

        return 'observing';
    }

    /**
     * 取得成效狀態標籤。
     *
     * @param string $status 成效狀態。
     * @return string
     */
    public static function get_performance_label($status) {
        $labels = array(
            'not_shown'      => __('尚未曝光', 'ur-ai-assistant'),
            'shown_no_click' => __('有曝光無點擊', 'ur-ai-assistant'),
            'low_ctr'        => __('低 CTR', 'ur-ai-assistant'),
            'good'           => __('表現良好', 'ur-ai-assistant'),
            'observing'      => __('觀察中', 'ur-ai-assistant'),
        );

        return isset($labels[$status]) ? $labels[$status] : __('未分類', 'ur-ai-assistant');
    }

    /**
     * 取得維護建議。
     *
     * @param int $show_count 曝光次數。
     * @param int $click_count 點擊次數。
     * @return string
     */
    public static function get_maintenance_suggestion($show_count, $click_count) {
        $status = self::get_performance_status($show_count, $click_count);

        $suggestions = array(
            'not_shown'      => __('尚未曝光，可先檢查關鍵字是否足夠，或等待更多使用紀錄。', 'ur-ai-assistant'),
            'shown_no_click' => __('已有曝光但尚未被點擊，建議檢查標題、摘要與問題關聯性。', 'ur-ai-assistant'),
            'low_ctr'        => __('曝光較高但點擊率偏低，建議調整關鍵字、標題、摘要或排序。', 'ur-ai-assistant'),
            'good'           => __('推薦成效良好，可考慮提高排序或延伸更多相關文章。', 'ur-ai-assistant'),
            'observing'      => __('資料仍在累積中，可持續觀察。', 'ur-ai-assistant'),
        );

        return isset($suggestions[$status]) ? $suggestions[$status] : __('請持續觀察推薦成效。', 'ur-ai-assistant');
    }
}