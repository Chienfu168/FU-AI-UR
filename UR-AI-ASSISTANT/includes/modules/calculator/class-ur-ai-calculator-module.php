<?php
/**
 * UR AI Assistant Calculator Module
 *
 * 都更分回試算模組主類別。沿用既有模組 register()/boot() 兩段式架構。
 *
 * 負責：
 * - 串接計算服務、名單 repository、AJAX、CF7 橋接。
 * - 前台資產 enqueue 與 localize。
 * - shortcode [ur_ai_calculator]。
 * - 後台「試算名單」頁與狀態更新。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Calculator_Module
 */
class UR_AI_Calculator_Module {

    const SHORTCODE       = 'ur_ai_calculator';
    const STYLE_HANDLE    = 'ur-ai-calculator';
    const SCRIPT_HANDLE   = 'ur-ai-calculator';
    const ADMIN_MENU_SLUG = 'ur-ai-assistant-leads';
    const SETTINGS_MENU_SLUG = 'ur-ai-assistant-calc-settings';
    const PARENT_SLUG     = 'ur-ai-assistant';
    const LEAD_UPDATE_ACTION = 'ur_ai_calc_lead_update';
    const SETTINGS_SAVE_ACTION = 'ur_ai_calc_settings_save';

    /**
     * 計算服務。
     *
     * @var UR_AI_Calculator_Service
     */
    private $service;

    /**
     * 名單 repository。
     *
     * @var UR_AI_Calculator_Lead_Repository
     */
    private $repository;

    /**
     * AJAX 處理器。
     *
     * @var UR_AI_Calculator_Ajax
     */
    private $ajax;

    /**
     * CF7 橋接。
     *
     * @var UR_AI_Calculator_CF7
     */
    private $cf7;

    /**
     * 建構並組裝相依物件。
     */
    public function __construct() {
        $this->service    = new UR_AI_Calculator_Service();
        $this->repository = new UR_AI_Calculator_Lead_Repository();
        $this->ajax       = new UR_AI_Calculator_Ajax($this->service);
        $this->cf7        = new UR_AI_Calculator_CF7($this->repository);
    }

    /**
     * 註冊掛鉤。
     *
     * @return void
     */
    public function register() {
        // AJAX 與 CF7（皆在前台與後台請求週期可能觸發）。
        $this->ajax->register();
        $this->cf7->register();

        // 前台。
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));

        // 後台名單頁（priority 20 確保父選單已建立）。
        add_action('admin_menu', array($this, 'register_admin_page'), 20);
        add_action('admin_post_' . self::LEAD_UPDATE_ACTION, array($this, 'handle_lead_update'));
        add_action('admin_post_' . self::SETTINGS_SAVE_ACTION, array($this, 'handle_settings_save'));
    }

    /**
     * 啟動：補齊設定預設。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Calculator_Settings')) {
            UR_AI_Calculator_Settings::maybe_install_defaults();
        }
    }

    /* ---------------------------------------------------------------------
     * 前台
     * ------------------------------------------------------------------- */

    /**
     * 註冊前台資產（延後到實際輸出時 enqueue）。
     *
     * @return void
     */
    public function register_assets() {
        wp_register_style(
            self::STYLE_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/css/calculator.css',
            array(),
            UR_AI_ASSISTANT_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/js/calculator.js',
            array(),
            UR_AI_ASSISTANT_VERSION,
            true
        );
    }

    /**
     * shortcode 輸出。
     *
     * @param array $atts shortcode 屬性。
     * @return string
     */
    public function render_shortcode($atts) {
        if (!UR_AI_Calculator_Settings::is_enabled()) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'mode' => 'owner', // owner = 地主版；pro = 含整合公司進階。
            ),
            is_array($atts) ? $atts : array(),
            self::SHORTCODE
        );

        // 實際 enqueue（僅在使用 shortcode 的頁面）。
        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);
        $this->localize();

        $cities      = UR_AI_Calculator_Settings::get_city_choices();
        $settings    = UR_AI_Calculator_Settings::get_all();
        $bonus_opts  = UR_AI_Calculator_Settings::get_other_bonus_options();
        $show_pro    = ('pro' === $atts['mode']);

        // 各縣市分區清單（供前台下拉，依縣市切換）。
        $zones_by_city = array();
        foreach (array_keys($cities) as $city_key) {
            $zones_by_city[$city_key] = array_keys(UR_AI_Calculator_Settings::get_zones($city_key));
        }

        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'public/views/calculator-view.php';

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
        $cf7_id = UR_AI_Calculator_Settings::get_cf7_form_id();

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'UR_AI_CALC',
            array(
                'ajax_url'    => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('ur_ai_assistant_public_nonce'),
                'action'      => UR_AI_Calculator_Ajax::ACTION_COMPUTE,
                'cf7_form_id' => $cf7_id,
                'i18n'        => array(
                    'calculating' => __('計算中…', 'ur-ai-assistant'),
                    'error'       => __('試算失敗，請稍後再試。', 'ur-ai-assistant'),
                    'need_input'  => __('請先填寫必要欄位。', 'ur-ai-assistant'),
                ),
            )
        );
    }

    /* ---------------------------------------------------------------------
     * 後台名單
     * ------------------------------------------------------------------- */

    /**
     * 註冊後台名單頁。
     *
     * @return void
     */
    public function register_admin_page() {
        $capability = 'manage_options';

        if (class_exists('UR_AI_Permissions') && method_exists('UR_AI_Permissions', 'get_capability')) {
            $maybe = UR_AI_Permissions::get_capability('dashboard');
            if (is_string($maybe) && '' !== $maybe) {
                $capability = $maybe;
            }
        }

        add_submenu_page(
            self::PARENT_SLUG,
            __('試算名單', 'ur-ai-assistant'),
            __('試算名單', 'ur-ai-assistant'),
            $capability,
            self::ADMIN_MENU_SLUG,
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            self::PARENT_SLUG,
            __('試算器設定', 'ur-ai-assistant'),
            __('試算器設定', 'ur-ai-assistant'),
            $capability,
            self::SETTINGS_MENU_SLUG,
            array($this, 'render_settings_page')
        );
    }

    /**
     * 渲染試算器設定頁。
     *
     * @return void
     */
    public function render_settings_page() {
        $this->require_admin_capability();

        $settings = UR_AI_Calculator_Settings::get_all();
        $params   = UR_AI_Calculator_Settings::get_city('taipei'); // 單一模式：以此組為準。
        $saved    = isset($_GET['updated']) && '1' === $_GET['updated'];

        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'admin/pages/calculator-settings-page.php';

        if (file_exists($view)) {
            include $view;
        }
    }

    /**
     * 處理試算器設定儲存。
     *
     * @return void
     */
    public function handle_settings_save() {
        $this->require_admin_capability();

        check_admin_referer(self::SETTINGS_SAVE_ACTION);

        // 計算參數（百分比欄位轉小數）。
        $general = isset($_POST['general_bonus']) ? (float) $_POST['general_bonus'] : 50;
        $build   = isset($_POST['build_factor']) ? (float) $_POST['build_factor'] : 1.5;
        $rlow    = isset($_POST['owner_ratio_low']) ? (float) $_POST['owner_ratio_low'] : 50;
        $rhigh   = isset($_POST['owner_ratio_high']) ? (float) $_POST['owner_ratio_high'] : 55;

        $city_params = array(
            'general_bonus'    => $general / 100,
            'build_factor'     => $build,
            'owner_ratio_low'  => $rlow / 100,
            'owner_ratio_high' => $rhigh / 100,
        );

        $values = array(
            'enabled'             => !empty($_POST['enabled']) ? 1 : 0,
            'cf7_form_id'         => isset($_POST['cf7_form_id']) ? absint($_POST['cf7_form_id']) : 0,
            'lead_hook_title'     => isset($_POST['lead_hook_title']) ? wp_unslash($_POST['lead_hook_title']) : '',
            'lead_hook_subtitle'  => isset($_POST['lead_hook_subtitle']) ? wp_unslash($_POST['lead_hook_subtitle']) : '',
            'public_ratio_notice' => isset($_POST['public_ratio_notice']) ? wp_unslash($_POST['public_ratio_notice']) : '',
            'disclaimer'          => isset($_POST['disclaimer']) ? wp_unslash($_POST['disclaimer']) : '',
            // 進階評估係數。
            'adv_a_multiplier'         => isset($_POST['adv_a_multiplier']) ? (float) $_POST['adv_a_multiplier'] : 1.5,
            'adv_b_legal_ratio'        => isset($_POST['adv_b_legal_ratio']) ? ((float) $_POST['adv_b_legal_ratio']) / 100 : 0.3,
            'adv_c_multiplier'         => isset($_POST['adv_c_multiplier']) ? (float) $_POST['adv_c_multiplier'] : 1.2,
            'adv_c_multiplier_special' => isset($_POST['adv_c_multiplier_special']) ? (float) $_POST['adv_c_multiplier_special'] : 1.3,
            'adv_cap_multiplier'       => isset($_POST['adv_cap_multiplier']) ? (float) $_POST['adv_cap_multiplier'] : 2.0,
            // 樓層／高度概估。
            'massing_floor_height'  => isset($_POST['massing_floor_height']) ? (float) $_POST['massing_floor_height'] : 3.2,
            'massing_coverage_hint' => isset($_POST['massing_coverage_hint']) ? wp_unslash($_POST['massing_coverage_hint']) : '',
            // 單一模式：同一組計算參數寫入台北與新北，保持結構一致。
            'cities'              => array(
                'taipei'     => $city_params,
                'new_taipei' => $city_params,
            ),
        );

        UR_AI_Calculator_Settings::update($values);

        wp_safe_redirect(
            admin_url('admin.php?page=' . self::SETTINGS_MENU_SLUG . '&updated=1')
        );
        exit;
    }

    /**
     * 後台權限守門（沿用共用權限層，含正確簽名）。
     *
     * @return void
     */
    private function require_admin_capability() {
        $capability = 'manage_options';

        if (class_exists('UR_AI_Permissions') && method_exists('UR_AI_Permissions', 'get_capability')) {
            $maybe = UR_AI_Permissions::get_capability('dashboard');
            if (is_string($maybe) && '' !== $maybe) {
                $capability = $maybe;
            }
        }

        if (!current_user_can($capability)) {
            wp_die(esc_html__('權限不足。', 'ur-ai-assistant'));
        }
    }

    /**
     * 渲染後台名單頁。
     *
     * @return void
     */
    public function render_admin_page() {
        $capability = 'manage_options';

        if (class_exists('UR_AI_Permissions') && method_exists('UR_AI_Permissions', 'get_capability')) {
            $maybe = UR_AI_Permissions::get_capability('dashboard');
            if (is_string($maybe) && '' !== $maybe) {
                $capability = $maybe;
            }
        }

        if (!current_user_can($capability)) {
            wp_die(esc_html__('權限不足。', 'ur-ai-assistant'));
        }

        $paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $city   = isset($_GET['city']) ? sanitize_key(wp_unslash($_GET['city'])) : '';

        $query = $this->repository->query(
            array(
                'paged'    => $paged,
                'per_page' => 20,
                'status'   => $status,
                'city'     => $city,
            )
        );

        $statuses     = UR_AI_Schema_Calculator_Leads::get_statuses();
        $city_choices = UR_AI_Calculator_Settings::get_city_choices();
        $counts       = $this->repository->count_by_status();

        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'admin/pages/calculator-leads-page.php';

        if (file_exists($view)) {
            include $view;
        }
    }

    /**
     * 處理名單狀態／備註更新。
     *
     * @return void
     */
    public function handle_lead_update() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('權限不足。', 'ur-ai-assistant'));
        }

        check_admin_referer(self::LEAD_UPDATE_ACTION);

        $id     = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        $note   = isset($_POST['admin_note']) ? sanitize_textarea_field(wp_unslash($_POST['admin_note'])) : '';

        if ($id > 0) {
            $this->repository->update_status($id, $status, $note);
        }

        $redirect = isset($_POST['_wp_http_referer'])
            ? esc_url_raw(wp_unslash($_POST['_wp_http_referer']))
            : admin_url('admin.php?page=' . self::ADMIN_MENU_SLUG);

        wp_safe_redirect($redirect);
        exit;
    }
}
