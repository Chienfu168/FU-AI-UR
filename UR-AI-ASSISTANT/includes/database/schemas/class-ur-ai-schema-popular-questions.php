<?php
/**
 * UR AI Assistant Popular Questions Schema
 *
 * 熱門問題資料表 Schema。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Popular_Questions
 */
class UR_AI_Schema_Popular_Questions {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_popular_questions';
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
            submit_question TEXT NULL,
            description TEXT NULL,

            status VARCHAR(30) DEFAULT 'inactive',
            source VARCHAR(30) DEFAULT 'manual',
            faq_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

            sort_order INT UNSIGNED NOT NULL DEFAULT 100,
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
            KEY faq_id (faq_id),
            KEY sort_order (sort_order),
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
            'category'        => "VARCHAR(100) DEFAULT ''",
            'question'        => "TEXT NOT NULL",
            'submit_question' => "TEXT NULL",
            'description'     => "TEXT NULL",

            'status'          => "VARCHAR(30) DEFAULT 'inactive'",
            'source'          => "VARCHAR(30) DEFAULT 'manual'",
            'faq_id'          => "BIGINT UNSIGNED NOT NULL DEFAULT 0",

            'sort_order'      => "INT UNSIGNED NOT NULL DEFAULT 100",
            'click_count'     => "INT UNSIGNED NOT NULL DEFAULT 0",

            'admin_note'      => "TEXT NULL",

            'created_by'      => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'updated_by'      => "BIGINT UNSIGNED NOT NULL DEFAULT 0",

            'created_at'      => "DATETIME NOT NULL",
            'updated_at'      => "DATETIME NULL",
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
            'faq'    => __('從 FAQ 匯入', 'ur-ai-assistant'),
            'import' => __('匯入建立', 'ur-ai-assistant'),
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
        );
    }

    /**
     * 取得可匯出的欄位。
     *
     * @return array
     */
    public static function get_export_columns() {
        return array(
            'id'              => __('ID', 'ur-ai-assistant'),
            'category'        => __('分類', 'ur-ai-assistant'),
            'question'        => __('前台顯示問題', 'ur-ai-assistant'),
            'submit_question' => __('實際送出問題', 'ur-ai-assistant'),
            'description'     => __('簡短說明', 'ur-ai-assistant'),
            'status'          => __('狀態', 'ur-ai-assistant'),
            'source'          => __('來源', 'ur-ai-assistant'),
            'faq_id'          => __('對應 FAQ ID', 'ur-ai-assistant'),
            'sort_order'      => __('排序', 'ur-ai-assistant'),
            'click_count'     => __('點擊次數', 'ur-ai-assistant'),
            'content_status'  => __('內容狀態', 'ur-ai-assistant'),
            'admin_note'      => __('管理備註', 'ur-ai-assistant'),
            'created_at'      => __('建立時間', 'ur-ai-assistant'),
            'updated_at'      => __('更新時間', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得熱門問題內容狀態。
     *
     * @param int    $click_count 點擊次數。
     * @param int    $faq_id 對應 FAQ ID。
     * @param string $faq_status FAQ 狀態。
     * @param bool   $faq_exists FAQ 是否存在。
     * @return string
     */
    public static function get_content_status($click_count, $faq_id, $faq_status = '', $faq_exists = false) {
        $click_count = absint($click_count);
        $faq_id      = absint($faq_id);

        if ($faq_id > 0 && !$faq_exists) {
            return 'faq_not_found';
        }

        if ($faq_id > 0 && 'active' !== $faq_status) {
            return 'faq_inactive';
        }

        if ($faq_id > 0 && $faq_exists && 'active' === $faq_status) {
            return 'linked';
        }

        if (0 === $faq_id && $click_count >= 5) {
            return 'high_click_unlinked';
        }

        return 'unlinked';
    }

    /**
     * 取得內容狀態標籤。
     *
     * @param string $status 狀態代碼。
     * @return string
     */
    public static function get_content_status_label($status) {
        $labels = array(
            'linked'              => __('已對應 FAQ', 'ur-ai-assistant'),
            'unlinked'            => __('未對應 FAQ', 'ur-ai-assistant'),
            'high_click_unlinked' => __('高點擊未對應', 'ur-ai-assistant'),
            'faq_inactive'        => __('FAQ 停用中', 'ur-ai-assistant'),
            'faq_not_found'       => __('FAQ 不存在', 'ur-ai-assistant'),
        );

        return isset($labels[$status]) ? $labels[$status] : __('未分類', 'ur-ai-assistant');
    }

    /**
     * 取得內容維護建議。
     *
     * @param string $status 狀態代碼。
     * @return string
     */
    public static function get_content_status_suggestion($status) {
        $suggestions = array(
            'linked'              => __('已有正式 FAQ 支撐，可持續觀察點擊與 FAQ 命中情形。', 'ur-ai-assistant'),
            'unlinked'            => __('尚未對應 FAQ，可先觀察點擊次數，必要時補上 FAQ。', 'ur-ai-assistant'),
            'high_click_unlinked' => __('此問題點擊較高但尚未對應 FAQ，建議優先整理成 FAQ 或補充網站文章。', 'ur-ai-assistant'),
            'faq_inactive'        => __('已對應 FAQ，但 FAQ 目前停用，建議檢查內容後啟用或重新指定。', 'ur-ai-assistant'),
            'faq_not_found'       => __('已設定 FAQ ID，但找不到該 FAQ，建議重新指定或清除關聯。', 'ur-ai-assistant'),
        );

        return isset($suggestions[$status]) ? $suggestions[$status] : __('請持續觀察此熱門問題的使用狀況。', 'ur-ai-assistant');
    }
}