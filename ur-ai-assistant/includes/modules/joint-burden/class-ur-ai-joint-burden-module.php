<?php
/**
 * UR AI Assistant Joint Burden Estimator Module
 *
 * 都市更新「共同負擔」提列估算模組主類別（第一階段，依新北市提列基準）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Joint_Burden_Module
 */
class UR_AI_Joint_Burden_Module {

    const SHORTCODE            = 'ur_ai_joint_burden';
    const STYLE_HANDLE         = 'ur-ai-joint-burden';
    const SCRIPT_HANDLE        = 'ur-ai-joint-burden';
    const ADMIN_MENU_SLUG      = 'ur-ai-assistant-joint-burden';
    const PARENT_SLUG          = 'ur-ai-assistant';
    const SETTINGS_SAVE_ACTION = 'ur_ai_joint_burden_settings_save';

    /**
     * 估算服務。
     *
     * @var UR_AI_Joint_Burden_Service|null
     */
    private $service;

    /**
     * AJAX 處理器。
     *
     * @var UR_AI_Joint_Burden_Ajax|null
     */
    private $ajax;

    /**
     * 建構並組裝相依物件。
     */
    public function __construct() {
        $this->service = class_exists('UR_AI_Joint_Burden_Service') ? new UR_AI_Joint_Burden_Service() : null;
        $this->ajax    = class_exists('UR_AI_Joint_Burden_Ajax') ? new UR_AI_Joint_Burden_Ajax($this->service) : null;
    }

    /**
     * 註冊掛鉤。
     *
     * @return void
     */
    public function register() {
        if ($this->ajax instanceof UR_AI_Joint_Burden_Ajax) {
            $this->ajax->register();
        }

        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));

        add_action('admin_menu', array($this, 'register_admin_page'), 20);
        add_action('admin_post_' . self::SETTINGS_SAVE_ACTION, array($this, 'handle_settings_save'));
    }

    /**
     * 啟動：補齊設定預設。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Joint_Burden_Settings')) {
            UR_AI_Joint_Burden_Settings::maybe_install_defaults();
        }
    }

    /* ---------------------------------------------------------------------
     * 前台
     * ------------------------------------------------------------------- */

    /**
     * 註冊前台資產。
     *
     * @return void
     */
    public function register_assets() {
        wp_register_style(
            self::STYLE_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/css/joint-burden.css',
            array(),
            UR_AI_ASSISTANT_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/js/joint-burden.js',
            array(),
            UR_AI_ASSISTANT_VERSION,
            true
        );
    }

    /**
     * shortcode 輸出。
     *
     * @param array|string $atts shortcode 屬性。
     * @return string
     */
    public function render_shortcode($atts = array()) {
        if (!class_exists('UR_AI_Joint_Burden_Settings') || !UR_AI_Joint_Burden_Settings::is_enabled()) {
            return $this->render_disabled_notice();
        }

        if (!$this->service instanceof UR_AI_Joint_Burden_Service) {
            return $this->render_missing_service_notice();
        }

        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);
        $this->localize();

        $disclaimer = UR_AI_Joint_Burden_Settings::get('disclaimer', '');

        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'public/views/joint-burden-view.php';

        if (!file_exists($view)) {
            return '';
        }

        ob_start();
        include $view;

        return ob_get_clean();
    }

    /**
     * localize 前台 JS。
     *
     * @return void
     */
    private function localize() {
        wp_localize_script(
            self::SCRIPT_HANDLE,
            'UR_AI_JOINT_BURDEN',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ur_ai_assistant_public_nonce'),
                'action'   => UR_AI_Joint_Burden_Ajax::ACTION_COMPUTE,
                'i18n'     => array(
                    'calculating' => __('估算中…', 'ur-ai-assistant'),
                    'error'       => __('估算失敗，請稍後再試。', 'ur-ai-assistant'),
                ),
            )
        );
    }

    /**
     * 未啟用提示。
     *
     * @return string
     */
    private function render_disabled_notice() {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="ur-ai-jb"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant 共同負擔估算功能目前已停用。管理員可至後台「共同負擔估算」設定重新啟用。', 'ur-ai-assistant')
            . '</div></div>';
    }

    /**
     * 服務缺漏提示。
     *
     * @return string
     */
    private function render_missing_service_notice() {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="ur-ai-jb"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant 共同負擔估算服務尚未正確載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')
            . '</div></div>';
    }

    /* ---------------------------------------------------------------------
     * 後台
     * ------------------------------------------------------------------- */

    /**
     * 註冊後台「共同負擔估算」設定頁。
     *
     * @return void
     */
    public function register_admin_page() {
        $capability = $this->resolve_capability();

        add_submenu_page(
            self::PARENT_SLUG,
            __('共同負擔估算設定', 'ur-ai-assistant'),
            __('共同負擔估算', 'ur-ai-assistant'),
            $capability,
            self::ADMIN_MENU_SLUG,
            array($this, 'render_admin_page')
        );
    }

    /**
     * 解析所需權限。
     *
     * @return string
     */
    private function resolve_capability() {
        $capability = 'manage_options';

        if (class_exists('UR_AI_Permissions') && method_exists('UR_AI_Permissions', 'get_capability')) {
            $maybe = UR_AI_Permissions::get_capability('dashboard');
            if (is_string($maybe) && '' !== $maybe) {
                $capability = $maybe;
            }
        }

        return $capability;
    }

    /**
     * 渲染後台設定頁。
     *
     * @return void
     */
    public function render_admin_page() {
        if (!current_user_can($this->resolve_capability())) {
            wp_die(esc_html__('權限不足。', 'ur-ai-assistant'));
        }

        $enabled    = UR_AI_Joint_Burden_Settings::is_enabled();
        $disclaimer = UR_AI_Joint_Burden_Settings::get('disclaimer', '');
        $saved      = isset($_GET['updated']) && '1' === $_GET['updated'];

        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'admin/pages/joint-burden-settings-page.php';

        if (file_exists($view)) {
            include $view;
        }
    }

    /**
     * 處理設定儲存。
     *
     * @return void
     */
    public function handle_settings_save() {
        if (!current_user_can($this->resolve_capability())) {
            wp_die(esc_html__('權限不足。', 'ur-ai-assistant'));
        }

        check_admin_referer(self::SETTINGS_SAVE_ACTION);

        UR_AI_Joint_Burden_Settings::update(
            array(
                'enabled'    => !empty($_POST['enabled']) ? 1 : 0,
                'disclaimer' => isset($_POST['disclaimer']) ? wp_unslash($_POST['disclaimer']) : '',
            )
        );

        wp_safe_redirect(
            admin_url('admin.php?page=' . self::ADMIN_MENU_SLUG . '&updated=1')
        );
        exit;
    }
}
