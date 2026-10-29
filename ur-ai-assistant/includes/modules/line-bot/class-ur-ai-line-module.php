<?php
/**
 * UR AI Assistant LINE Module
 *
 * LINE 官方帳號客服模組主類別：註冊 webhook REST 路由與每日清理排程。
 * 沿用既有模組的 register()/boot() 兩段式架構。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Module
 */
class UR_AI_Line_Module {

    /**
     * 每日清理排程 hook 名稱。
     */
    const CLEANUP_HOOK = 'ur_ai_line_daily_cleanup';

    /**
     * 註冊 hooks。
     *
     * @return void
     */
    public function register() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        add_action(self::CLEANUP_HOOK, array($this, 'run_cleanup'));

        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    /**
     * 註冊 REST 路由。
     *
     * @return void
     */
    public function register_rest_routes() {
        if (class_exists('UR_AI_Line_Webhook_Controller')) {
            $controller = new UR_AI_Line_Webhook_Controller();
            $controller->register_routes();
        }
    }

    /**
     * 每日清理逾期對話紀錄。
     *
     * @return void
     */
    public function run_cleanup() {
        if (!class_exists('UR_AI_Line_Settings') || !class_exists('UR_AI_Line_Message_Repository')) {
            return;
        }

        $days = UR_AI_Line_Settings::get_retention_days();

        if ($days > 0) {
            $repository = new UR_AI_Line_Message_Repository();
            $repository->delete_older_than($days);
        }
    }

    /**
     * 啟動服務（本模組無須預先建立服務）。
     *
     * @return void
     */
    public function boot() {
    }
}
