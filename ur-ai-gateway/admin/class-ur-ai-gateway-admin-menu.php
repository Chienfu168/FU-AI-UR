<?php
/**
 * UR AI Gateway Admin Menu
 *
 * 後台選單註冊：授權碼管理、設定頁。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Admin_Menu
 */
class UR_AI_Gateway_Admin_Menu {

    /**
     * 選單 slug。
     *
     * @var string
     */
    const MENU_SLUG = 'ur-ai-gateway';

    /**
     * 註冊掛鉤。
     *
     * @return void
     */
    public function register() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }

    /**
     * 註冊後台選單。
     *
     * @return void
     */
    public function register_menu() {
        add_menu_page(
            __('AI 代管服務', 'ur-ai-gateway'),
            __('AI 代管服務', 'ur-ai-gateway'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_licenses_page'),
            'dashicons-admin-network',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('授權碼管理', 'ur-ai-gateway'),
            __('授權碼管理', 'ur-ai-gateway'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_licenses_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('設定', 'ur-ai-gateway'),
            __('設定', 'ur-ai-gateway'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * 處理後台表單送出（新增／更新／刪除授權碼、儲存設定）。
     *
     * @return void
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['ur_ai_gateway_action'])) {
            return;
        }

        if (class_exists('UR_AI_Gateway_Security')) {
            UR_AI_Gateway_Security::verify_admin_form_nonce_or_die();
        } else {
            check_admin_referer('ur_ai_gateway_admin_action', 'ur_ai_gateway_nonce');
        }

        $action = sanitize_key(wp_unslash($_POST['ur_ai_gateway_action']));

        switch ($action) {
            case 'save_settings':
                $this->handle_save_settings();
                break;

            case 'create_manual_license':
                $this->handle_create_manual_license();
                break;

            case 'update_license_status':
                $this->handle_update_license_status();
                break;
        }
    }

    /**
     * 儲存設定。
     *
     * @return void
     */
    private function handle_save_settings() {
        if (!class_exists('UR_AI_Gateway_Settings')) {
            return;
        }

        UR_AI_Gateway_Settings::update_many(
            array(
                'openai_api_key'      => isset($_POST['openai_api_key']) ? wp_unslash($_POST['openai_api_key']) : '',
                'default_daily_limit' => isset($_POST['default_daily_limit']) ? wp_unslash($_POST['default_daily_limit']) : '',
            )
        );

        $this->redirect_with_message(self::MENU_SLUG . '-settings', 'settings_saved');
    }

    /**
     * 手動建立一組授權碼。
     *
     * @return void
     */
    private function handle_create_manual_license() {
        if (!class_exists('UR_AI_Gateway_License_Service')) {
            return;
        }

        $email       = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $plan        = isset($_POST['plan']) ? sanitize_text_field(wp_unslash($_POST['plan'])) : '';
        $daily_limit = isset($_POST['daily_limit']) ? absint(wp_unslash($_POST['daily_limit'])) : 0;
        $admin_note  = isset($_POST['admin_note']) ? sanitize_textarea_field(wp_unslash($_POST['admin_note'])) : '';

        $service = new UR_AI_Gateway_License_Service();
        $service->create_manual($email, $plan, $daily_limit, $admin_note);

        $this->redirect_with_message(self::MENU_SLUG, 'license_created');
    }

    /**
     * 手動更新授權碼狀態（暫停／恢復／終止）。
     *
     * @return void
     */
    private function handle_update_license_status() {
        if (!class_exists('UR_AI_Gateway_License_Repository')) {
            return;
        }

        $license_id = isset($_POST['license_id']) ? absint(wp_unslash($_POST['license_id'])) : 0;
        $status     = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';

        $valid_statuses = class_exists('UR_AI_Gateway_Schema_Licenses')
            ? array_keys(UR_AI_Gateway_Schema_Licenses::get_statuses())
            : array('active', 'suspended', 'revoked', 'expired');

        if ($license_id <= 0 || !in_array($status, $valid_statuses, true)) {
            $this->redirect_with_message(self::MENU_SLUG, 'invalid_request', 'error');
        }

        $repository = new UR_AI_Gateway_License_Repository();
        $repository->update($license_id, array('status' => $status));

        $this->redirect_with_message(self::MENU_SLUG, 'license_updated');
    }

    /**
     * 導回後台頁並顯示訊息。
     *
     * @param string $page 頁面 slug。
     * @param string $message 訊息代碼。
     * @param string $type 訊息類型。
     * @return void
     */
    private function redirect_with_message($page, $message, $type = 'updated') {
        $url = add_query_arg(
            array(
                'page'        => $page,
                'ur_message'  => sanitize_key($message),
                'ur_msg_type' => sanitize_key($type),
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    /**
     * 渲染授權碼管理頁。
     *
     * @return void
     */
    public function render_licenses_page() {
        $view = UR_AI_GATEWAY_PLUGIN_DIR . 'admin/pages/licenses-page.php';

        if (file_exists($view)) {
            include $view;
        }
    }

    /**
     * 渲染設定頁。
     *
     * @return void
     */
    public function render_settings_page() {
        $view = UR_AI_GATEWAY_PLUGIN_DIR . 'admin/pages/settings-page.php';

        if (file_exists($view)) {
            include $view;
        }
    }
}
