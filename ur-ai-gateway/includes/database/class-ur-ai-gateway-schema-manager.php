<?php
/**
 * UR AI Gateway Schema Manager
 *
 * 資料庫結構管理器：外掛啟用或版本升級時建立／更新資料表。
 *
 * 這個外掛目前只有一張資料表（授權碼表），因此不採用 ur-ai-assistant
 * 那種「schema class 清單＋逐一 dbDelta()」的通用架構，直接處理單一
 * 資料表即可，避免不必要的抽象層。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Schema_Manager
 */
class UR_AI_Gateway_Schema_Manager {

    /**
     * 檢查是否需要升級，需要的話才動作。
     *
     * @return void
     */
    public static function maybe_upgrade() {
        $installed_version = get_option(UR_AI_GATEWAY_OPTION_DB_VERSION, '');

        if ($installed_version === UR_AI_GATEWAY_DB_VERSION) {
            return;
        }

        self::install();
    }

    /**
     * 建立／更新資料表。
     *
     * @return void
     */
    public static function install() {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        if (!class_exists('UR_AI_Gateway_Schema_Licenses')) {
            return;
        }

        // 授權碼表（含 v1.1.0 新增的 total_tokens 欄位，dbDelta 會自動補欄位）。
        dbDelta(UR_AI_Gateway_Schema_Licenses::get_sql());

        // 用量明細表（v1.1.0 新增）。
        if (class_exists('UR_AI_Gateway_Schema_Usage')) {
            dbDelta(UR_AI_Gateway_Schema_Usage::get_sql());
        }

        $licenses_ok = self::table_exists(UR_AI_Gateway_Schema_Licenses::table_name());
        $usage_ok    = !class_exists('UR_AI_Gateway_Schema_Usage') || self::table_exists(UR_AI_Gateway_Schema_Usage::table_name());

        if ($licenses_ok && $usage_ok) {
            update_option(UR_AI_GATEWAY_OPTION_DB_VERSION, UR_AI_GATEWAY_DB_VERSION);
        }
    }

    /**
     * 判斷資料表是否存在。
     *
     * @param string $table_name 資料表名稱。
     * @return bool
     */
    public static function table_exists($table_name) {
        global $wpdb;

        $table_name = (string) $table_name;

        if ('' === $table_name) {
            return false;
        }

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        return $exists === $table_name;
    }
}
