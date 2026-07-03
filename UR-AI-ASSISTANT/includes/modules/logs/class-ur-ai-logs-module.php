<?php
/**
 * UR AI Assistant Logs Module
 *
 * 問答紀錄模組啟動器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Logs_Module
 */
class UR_AI_Logs_Module {

    /**
     * Log Service.
     *
     * @var UR_AI_Log_Service|null
     */
    private $service = null;

    /**
     * Log Admin.
     *
     * @var UR_AI_Log_Admin|null
     */
    private $admin = null;

    /**
     * 註冊 WordPress hooks。
     *
     * @return void
     */
    public function register() {
        if (is_admin()) {
            add_action('admin_init', array($this, 'handle_admin_actions'));
        }
    }

    /**
     * 啟動模組。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Log_Service')) {
            $this->service = new UR_AI_Log_Service();
        }

        if (is_admin() && class_exists('UR_AI_Log_Admin')) {
            $this->admin = new UR_AI_Log_Admin();
        }
    }

    /**
     * 處理後台問答紀錄操作。
     *
     * @return void
     */
    public function handle_admin_actions() {
        if (!$this->is_logs_admin_request()) {
            return;
        }

        if (!$this->admin instanceof UR_AI_Log_Admin && class_exists('UR_AI_Log_Admin')) {
            $this->admin = new UR_AI_Log_Admin();
        }

        if (!$this->admin instanceof UR_AI_Log_Admin) {
            return;
        }

        if (method_exists($this->admin, 'handle_actions')) {
            $this->admin->handle_actions();
        }
    }

    /**
     * 判斷是否為問答紀錄後台請求。
     *
     * @return bool
     */
    private function is_logs_admin_request() {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        if ('ur-ai-assistant-logs' === $page) {
            return true;
        }

        $post_action = isset($_POST['ur_ai_action'])
            ? sanitize_key(wp_unslash($_POST['ur_ai_action']))
            : '';

        $post_actions = array(
            'delete_log',
            'bulk_logs',
            'convert_log_to_faq',
        );

        if (in_array($post_action, $post_actions, true)) {
            return true;
        }

        $get_action = isset($_GET['ur_action'])
            ? sanitize_key(wp_unslash($_GET['ur_action']))
            : '';

        $get_actions = array(
            'export_logs_csv',
        );

        return in_array($get_action, $get_actions, true);
    }

    /**
     * 取得 Log Service。
     *
     * @return UR_AI_Log_Service|null
     */
    public function get_service() {
        return $this->service;
    }

    /**
     * 取得 Log Admin。
     *
     * @return UR_AI_Log_Admin|null
     */
    public function get_admin() {
        return $this->admin;
    }
}