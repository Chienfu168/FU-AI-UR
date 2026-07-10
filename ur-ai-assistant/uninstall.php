<?php
/**
 * UR AI Assistant Uninstall
 *
 * 外掛解除安裝清理檔。
 *
 * @package UR_AI_Assistant
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * 是否在解除安裝時刪除所有資料表。
 *
 * 安全起見，預設 false。
 * 若正式確認要完整清除資料，可改為 true。
 */
$delete_all_data = false;

/**
 * 也可以透過 option 控制是否刪除資料。
 *
 * 若後台未來增加「解除安裝時刪除所有資料」設定，
 * 可將 option 設為 1。
 */
$settings = get_option('ur_ai_assistant_settings', array());

if (is_array($settings) && !empty($settings['delete_data_on_uninstall'])) {
    $delete_all_data = true;
}

/**
 * 刪除外掛設定。
 */
delete_option('ur_ai_assistant_settings');
delete_option('ur_ai_assistant_db_version');
delete_option('ur_ai_quiz_settings');

/**
 * 清除每日提問限制 transient。
 */
global $wpdb;

$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_ur_ai_daily_%'
        OR option_name LIKE '_transient_timeout_ur_ai_daily_%'"
);

/**
 * 預設不刪除資料表，避免誤刪正式營運資料。
 */
if (!$delete_all_data) {
    return;
}

/**
 * 刪除資料表。
 */
$tables = array(
    $wpdb->prefix . 'ur_ai_faqs',
    $wpdb->prefix . 'ur_ai_logs',
    $wpdb->prefix . 'ur_ai_related_pages',
    $wpdb->prefix . 'ur_ai_popular_questions',
    $wpdb->prefix . 'ur_ai_calculator_leads',
    $wpdb->prefix . 'ur_ai_market_prices',
    $wpdb->prefix . 'ur_ai_quiz_questions',
    $wpdb->prefix . 'ur_ai_quiz_attempts',
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}