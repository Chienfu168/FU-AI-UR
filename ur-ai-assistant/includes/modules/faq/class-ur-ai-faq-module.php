<?php
/**
 * UR AI Assistant FAQ Module
 *
 * FAQ 知識庫模組啟動器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_Module
 */
class UR_AI_FAQ_Module {

    /**
     * FAQ Service.
     *
     * @var UR_AI_FAQ_Service|null
     */
    private $service = null;

    /**
     * FAQ Admin.
     *
     * @var UR_AI_FAQ_Admin|null
     */
    private $admin = null;

    /**
     * FAQ Matcher.
     *
     * @var UR_AI_FAQ_Matcher|null
     */
    private $matcher = null;

    /**
     * FAQ AJAX 處理器。
     *
     * @var UR_AI_FAQ_Ajax|null
     */
    private $ajax = null;

    /**
     * 註冊 WordPress hooks。
     *
     * @return void
     */
    public function register() {
        if (is_admin()) {
            add_action('admin_init', array($this, 'handle_admin_actions'));

            $this->ajax = class_exists('UR_AI_FAQ_Ajax') ? new UR_AI_FAQ_Ajax() : null;

            if ($this->ajax instanceof UR_AI_FAQ_Ajax) {
                $this->ajax->register();
            }
        }
    }

    /**
     * 啟動模組。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_FAQ_Service')) {
            $this->service = new UR_AI_FAQ_Service();
        }

        if (class_exists('UR_AI_FAQ_Matcher')) {
            $this->matcher = new UR_AI_FAQ_Matcher();
        }

        if (is_admin() && class_exists('UR_AI_FAQ_Admin')) {
            $this->admin = new UR_AI_FAQ_Admin();
        }
    }

    /**
     * 處理 FAQ 後台操作。
     *
     * @return void
     */
    public function handle_admin_actions() {
        if (!$this->is_faq_admin_request()) {
            return;
        }

        if (!$this->admin instanceof UR_AI_FAQ_Admin && class_exists('UR_AI_FAQ_Admin')) {
            $this->admin = new UR_AI_FAQ_Admin();
        }

        if (!$this->admin instanceof UR_AI_FAQ_Admin) {
            return;
        }

        if (method_exists($this->admin, 'handle_actions')) {
            $this->admin->handle_actions();
        }
    }

    /**
     * 判斷是否為 FAQ 後台請求。
     *
     * @return bool
     */
    private function is_faq_admin_request() {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        if ('ur-ai-assistant-faqs' === $page) {
            return true;
        }

        $post_action = isset($_POST['ur_ai_action'])
            ? sanitize_key(wp_unslash($_POST['ur_ai_action']))
            : '';

        $post_actions = array(
            'create_faq',
            'update_faq',
            'delete_faq',
            'bulk_faqs',
            'convert_log_to_faq',
        );

        if (in_array($post_action, $post_actions, true)) {
            return true;
        }

        $get_action = isset($_GET['ur_action'])
            ? sanitize_key(wp_unslash($_GET['ur_action']))
            : '';

        $get_actions = array(
            'export_faqs_csv',
        );

        return in_array($get_action, $get_actions, true);
    }

    /**
     * 取得 FAQ Service。
     *
     * @return UR_AI_FAQ_Service|null
     */
    public function get_service() {
        return $this->service;
    }

    /**
     * 取得 FAQ Admin。
     *
     * @return UR_AI_FAQ_Admin|null
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * 取得 FAQ Matcher。
     *
     * @return UR_AI_FAQ_Matcher|null
     */
    public function get_matcher() {
        return $this->matcher;
    }
}