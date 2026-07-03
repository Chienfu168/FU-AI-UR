<?php
/**
 * UR AI Assistant Deactivator
 *
 * 外掛停用處理器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Deactivator
 */
class UR_AI_Deactivator {

    /**
     * 外掛停用時執行。
     *
     * @return void
     */
    public static function deactivate() {
        self::clear_scheduled_events();
        self::clear_transients();
        self::flush_rewrite_rules();
    }

    /**
     * 清除外掛排程事件。
     *
     * 目前第一版尚未建立排程，但先保留結構，
     * 未來可用於每日統計、資料清潔、報表產生等功能。
     *
     * @return void
     */
    private static function clear_scheduled_events() {
        $events = array(
            'ur_ai_assistant_daily_cleanup',
            'ur_ai_assistant_daily_summary',
            'ur_ai_assistant_weekly_report',
        );

        foreach ($events as $event_hook) {
            $timestamp = wp_next_scheduled($event_hook);

            while ($timestamp) {
                wp_unschedule_event($timestamp, $event_hook);
                $timestamp = wp_next_scheduled($event_hook);
            }
        }
    }

    /**
     * 清除暫存資料。
     *
     * 只清除 transient，不刪除正式資料。
     *
     * @return void
     */
    private static function clear_transients() {
        global $wpdb;

        /**
         * 僅刪除 UR AI Assistant 自己建立的 transient。
         */
        $transient_like = $wpdb->esc_like('_transient_ur_ai_assistant_') . '%';
        $timeout_like   = $wpdb->esc_like('_transient_timeout_ur_ai_assistant_') . '%';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $transient_like,
                $timeout_like
            )
        );
    }

    /**
     * Flush rewrite rules。
     *
     * 第一版目前沒有 rewrite rules，但保留此流程。
     *
     * @return void
     */
    private static function flush_rewrite_rules() {
        flush_rewrite_rules(false);
    }
}