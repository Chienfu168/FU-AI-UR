<?php
/**
 * UR AI Assistant Partners Schema
 *
 * 「相關產業名錄」資料表 Schema（都市更新專業服務與相關組織名錄）。
 *
 * 對應原 UR_SYS `partners` 資料表；名錄僅供資訊查詢與自行洽詢參考，
 * 不代表本平台之認證或背書，資料以政府公開名冊／專業公會／單位官方
 * 網站整理為主，並標示資料來源與最後核對日期。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Partners
 */
class UR_AI_Schema_Partners {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_partners';
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

            name VARCHAR(255) NOT NULL DEFAULT '',
            category VARCHAR(30) NOT NULL DEFAULT 'other',
            region VARCHAR(50) DEFAULT '',
            tagline VARCHAR(255) DEFAULT '',
            description TEXT NULL,
            contact_person VARCHAR(100) DEFAULT '',
            phone VARCHAR(50) DEFAULT '',
            email VARCHAR(255) DEFAULT '',
            website_url VARCHAR(500) DEFAULT '',
            logo_url VARCHAR(500) DEFAULT '',

            tier VARCHAR(20) NOT NULL DEFAULT 'standard',
            weight INT NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            sort_order INT NOT NULL DEFAULT 0,

            source_type VARCHAR(30) DEFAULT '',
            source_url VARCHAR(500) DEFAULT '',
            verified_at DATE NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            featured_until DATE NULL,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,

            PRIMARY KEY  (id),
            KEY category (category),
            KEY status (status),
            KEY tier (tier),
            KEY is_verified (is_verified)
        ) {$charset_collate};";
    }

    /**
     * 欄位定義（供未來升級比對）。
     *
     * @return array
     */
    public static function get_columns() {
        return array(
            'name'           => "VARCHAR(255) NOT NULL DEFAULT ''",
            'category'       => "VARCHAR(30) NOT NULL DEFAULT 'other'",
            'region'         => "VARCHAR(50) DEFAULT ''",
            'tagline'        => "VARCHAR(255) DEFAULT ''",
            'description'    => 'TEXT NULL',
            'contact_person' => "VARCHAR(100) DEFAULT ''",
            'phone'          => "VARCHAR(50) DEFAULT ''",
            'email'          => "VARCHAR(255) DEFAULT ''",
            'website_url'    => "VARCHAR(500) DEFAULT ''",
            'logo_url'       => "VARCHAR(500) DEFAULT ''",
            'tier'           => "VARCHAR(20) NOT NULL DEFAULT 'standard'",
            'weight'         => 'INT NOT NULL DEFAULT 1',
            'status'         => "VARCHAR(20) NOT NULL DEFAULT 'active'",
            'sort_order'     => 'INT NOT NULL DEFAULT 0',
            'source_type'    => "VARCHAR(30) DEFAULT ''",
            'source_url'     => "VARCHAR(500) DEFAULT ''",
            'verified_at'    => 'DATE NULL',
            'is_verified'    => 'TINYINT(1) NOT NULL DEFAULT 0',
            'featured_until' => 'DATE NULL',
            'created_at'     => 'DATETIME NOT NULL',
            'updated_at'     => 'DATETIME NULL',
        );
    }
}
