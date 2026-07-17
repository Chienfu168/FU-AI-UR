<?php
/**
 * UR AI Gateway Schema: Licenses
 *
 * 授權碼資料表定義：每一組授權碼對應一筆 WooCommerce 訂閱（或手動發放），
 * 客戶端的 UR AI Assistant 外掛用這組授權碼當作 Bearer 憑證呼叫代管服務。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Schema_Licenses
 */
class UR_AI_Gateway_Schema_Licenses {

    /**
     * 資料表名稱（不含 prefix）。
     *
     * @var string
     */
    const TABLE_NAME = 'ur_ai_gateway_licenses';

    /**
     * 取得完整資料表名稱。
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * 取得建表 SQL（dbDelta 格式）。
     *
     * @return string
     */
    public static function get_sql() {
        global $wpdb;

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            license_key VARCHAR(64) NOT NULL,
            customer_email VARCHAR(190) NOT NULL DEFAULT '',
            site_url VARCHAR(255) NOT NULL DEFAULT '',
            plan VARCHAR(100) NOT NULL DEFAULT '',
            order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            subscription_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            daily_limit INT UNSIGNED NOT NULL DEFAULT 200,
            daily_usage_count INT UNSIGNED NOT NULL DEFAULT 0,
            daily_usage_reset_at DATETIME NULL,
            total_usage_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            admin_note TEXT NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY license_key (license_key),
            KEY status (status),
            KEY subscription_id (subscription_id),
            KEY order_id (order_id)
        ) {$charset_collate};";
    }

    /**
     * 授權狀態列表（給後台下拉選單與顯示標籤用）。
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            'active'    => __('啟用中', 'ur-ai-gateway'),
            'suspended' => __('暫停（例如訂閱逾期未繳）', 'ur-ai-gateway'),
            'revoked'   => __('已終止', 'ur-ai-gateway'),
            'expired'   => __('已過期', 'ur-ai-gateway'),
        );
    }
}
