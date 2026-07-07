<?php
/**
 * UR AI Assistant Market Price Schema
 *
 * 「行情參考」資料表 Schema（實價登錄開放資料，僅供歷史成交行情參考，非估價）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Market_Prices
 */
class UR_AI_Schema_Market_Prices {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_market_prices';
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

            city VARCHAR(30) DEFAULT '',
            district VARCHAR(50) DEFAULT '',
            zone VARCHAR(50) DEFAULT '',
            zone_raw VARCHAR(190) DEFAULT '',
            building_type VARCHAR(100) DEFAULT '',
            address_raw VARCHAR(255) DEFAULT '',

            transaction_date DATE NULL,
            built_year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            building_age_years SMALLINT UNSIGNED NOT NULL DEFAULT 0,

            building_area_sqm DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_price BIGINT UNSIGNED NOT NULL DEFAULT 0,
            parking_price BIGINT UNSIGNED NOT NULL DEFAULT 0,
            unit_price_per_ping DECIMAL(12,2) NOT NULL DEFAULT 0,

            is_special_relationship TINYINT(1) NOT NULL DEFAULT 0,

            source_record_id VARCHAR(64) DEFAULT '',
            import_batch VARCHAR(60) DEFAULT '',

            created_at DATETIME NOT NULL,

            PRIMARY KEY  (id),
            UNIQUE KEY source_record_id (source_record_id),
            KEY city_district (city, district),
            KEY zone (zone),
            KEY building_age_years (building_age_years),
            KEY transaction_date (transaction_date)
        ) {$charset_collate};";
    }

    /**
     * 欄位補強定義（供未來升級比對）。
     *
     * @return array
     */
    public static function get_columns() {
        return array(
            'city'                     => "VARCHAR(30) DEFAULT ''",
            'district'                 => "VARCHAR(50) DEFAULT ''",
            'zone'                     => "VARCHAR(50) DEFAULT ''",
            'zone_raw'                 => "VARCHAR(190) DEFAULT ''",
            'building_type'            => "VARCHAR(100) DEFAULT ''",
            'address_raw'              => "VARCHAR(255) DEFAULT ''",
            'transaction_date'         => 'DATE NULL',
            'built_year'               => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
            'building_age_years'       => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
            'building_area_sqm'        => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
            'total_price'              => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'parking_price'            => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'unit_price_per_ping'      => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'is_special_relationship'  => 'TINYINT(1) NOT NULL DEFAULT 0',
            'source_record_id'         => "VARCHAR(64) DEFAULT ''",
            'import_batch'             => "VARCHAR(60) DEFAULT ''",
            'created_at'               => 'DATETIME NOT NULL',
        );
    }

    /**
     * 支援的縣市（第一階段僅雙北）。
     *
     * @return array city key => label
     */
    public static function get_supported_cities() {
        return array(
            'taipei'     => '台北市',
            'new_taipei' => '新北市',
        );
    }

    /**
     * 屋齡分桶定義（key 對應設定值可調整之門檻）。
     *
     * @return array
     */
    public static function get_age_buckets() {
        return array(
            'old'    => '老屋現況行情',
            'middle' => '中屋齡',
            'new'    => '新成屋行情',
        );
    }

    /**
     * 取得指定縣市已知的行政區名稱清單。
     *
     * 供匯入時做「上傳檔案是否真的屬於該縣市」的檢查，避免手滑上傳錯縣市的檔案
     * 卻沒發現（例如把其他縣市資料標成台北市匯入）。
     *
     * @param string $city 縣市 key（taipei / new_taipei）。
     * @return array
     */
    public static function get_known_districts($city) {
        $map = array(
            'taipei'     => array(
                '中正區', '大同區', '中山區', '松山區', '大安區', '萬華區',
                '信義區', '士林區', '北投區', '內湖區', '南港區', '文山區',
            ),
            'new_taipei' => array(
                '板橋區', '三重區', '中和區', '永和區', '新莊區', '新店區',
                '樹林區', '鶯歌區', '三峽區', '淡水區', '汐止區', '瑞芳區',
                '土城區', '蘆洲區', '五股區', '泰山區', '林口區', '深坑區',
                '石碇區', '坪林區', '三芝區', '石門區', '八里區', '平溪區',
                '雙溪區', '貢寮區', '金山區', '萬里區', '烏來區',
            ),
        );

        return isset($map[$city]) ? $map[$city] : array();
    }
}
