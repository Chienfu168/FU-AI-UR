<?php
/**
 * UR AI Assistant Related Pages Module
 *
 * 相關頁面推薦模組啟動器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Related_Pages_Module
 */
class UR_AI_Related_Pages_Module {

    /**
     * Related Page Service.
     *
     * @var UR_AI_Related_Page_Service|null
     */
    private $service = null;

    /**
     * Related Page Admin.
     *
     * @var UR_AI_Related_Page_Admin|null
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

        add_action('wp_ajax_ur_ai_related_page_click', array($this, 'handle_click_ajax'));
        add_action('wp_ajax_nopriv_ur_ai_related_page_click', array($this, 'handle_click_ajax'));
    }

    /**
     * 啟動模組。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Related_Page_Service')) {
            $this->service = new UR_AI_Related_Page_Service();
        }

        if (is_admin() && class_exists('UR_AI_Related_Page_Admin')) {
            $this->admin = new UR_AI_Related_Page_Admin();
        }
    }

    /**
     * 處理後台操作。
     *
     * @return void
     */
    public function handle_admin_actions() {
        if (!$this->is_related_pages_admin_request()) {
            return;
        }

        if (!$this->admin instanceof UR_AI_Related_Page_Admin && class_exists('UR_AI_Related_Page_Admin')) {
            $this->admin = new UR_AI_Related_Page_Admin();
        }

        if (!$this->admin instanceof UR_AI_Related_Page_Admin) {
            return;
        }

        if (method_exists($this->admin, 'handle_actions')) {
            $this->admin->handle_actions();
        }
    }

    /**
     * 處理前台推薦頁面點擊統計。
     *
     * @return void
     */
    public function handle_click_ajax() {
        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::ajax_verify_public_nonce_or_die();
        } else {
            $nonce = isset($_POST['nonce'])
                ? sanitize_text_field(wp_unslash($_POST['nonce']))
                : '';

            if (!wp_verify_nonce($nonce, 'ur_ai_assistant_public_nonce')) {
                wp_send_json_error(
                    array(
                        'message' => __('安全驗證失敗。', 'ur-ai-assistant'),
                    ),
                    403
                );
            }
        }

        $page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;

        if ($page_id <= 0) {
            wp_send_json_error(
                array(
                    'message' => __('推薦頁面 ID 不正確。', 'ur-ai-assistant'),
                ),
                400
            );
        }

        if (!$this->service instanceof UR_AI_Related_Page_Service && class_exists('UR_AI_Related_Page_Service')) {
            $this->service = new UR_AI_Related_Page_Service();
        }

        if (!$this->service instanceof UR_AI_Related_Page_Service) {
            wp_send_json_error(
                array(
                    'message' => __('推薦頁面服務尚未載入。', 'ur-ai-assistant'),
                ),
                500
            );
        }

        $updated = $this->service->increase_click_count($page_id);

        wp_send_json_success(
            array(
                'updated' => (bool) $updated,
            )
        );
    }

    /**
     * 判斷目前是否為相關頁面推薦後台請求。
     *
     * @return bool
     */
    private function is_related_pages_admin_request() {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        if ('ur-ai-assistant-related-pages' === $page) {
            return true;
        }

        $action = isset($_POST['ur_ai_action'])
            ? sanitize_key(wp_unslash($_POST['ur_ai_action']))
            : '';

        $related_actions = array(
            'create_related_page',
            'update_related_page',
            'delete_related_page',
            'bulk_related_pages',
            'import_related_page_from_post',
            'bulk_import_related_pages',
        );

        if (in_array($action, $related_actions, true)) {
            return true;
        }

        $get_action = isset($_GET['ur_action'])
            ? sanitize_key(wp_unslash($_GET['ur_action']))
            : '';

        $get_actions = array(
            'export_related_pages_csv',
        );

        return in_array($get_action, $get_actions, true);
    }

    /**
     * 取得 Related Page Service。
     *
     * @return UR_AI_Related_Page_Service|null
     */
    public function get_service() {
        return $this->service;
    }

    /**
     * 取得 Related Page Admin。
     *
     * @return UR_AI_Related_Page_Admin|null
     */
    public function get_admin() {
        return $this->admin;
    }
}