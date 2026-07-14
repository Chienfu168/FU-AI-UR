<?php
/**
 * UR AI Assistant Market Price Module
 *
 * 行情參考模組主類別（沿用既有模組 register()/boot() 兩段式架構）。
 *
 * 負責：
 * - shortcode [ur_ai_market_price]（雙北成屋行情參考，老屋現況／新成屋並排比較）。
 * - 前台專屬資產（僅在使用 shortcode 的頁面才 enqueue，不掛在既有 public.js/css）。
 * - 前台 AJAX 查詢。
 * - 後台「行情匯入」頁與設定儲存。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Module
 */
class UR_AI_Market_Price_Module {

    const SHORTCODE            = 'ur_ai_market_price';
    const RANKING_SHORTCODE    = 'ur_ai_market_price_ranking';
    const STYLE_HANDLE         = 'ur-ai-market-price';
    const SCRIPT_HANDLE        = 'ur-ai-market-price';
    const ADMIN_MENU_SLUG      = 'ur-ai-assistant-market-price';
    const PARENT_SLUG          = 'ur-ai-assistant';
    const IMPORT_ACTION        = 'ur_ai_market_price_import';
    const FETCH_ACTION         = 'ur_ai_market_price_fetch';
    const SETTINGS_SAVE_ACTION = 'ur_ai_market_price_settings_save';

    /**
     * Service。
     *
     * @var UR_AI_Market_Price_Service
     */
    private $service;

    /**
     * AJAX 處理器。
     *
     * @var UR_AI_Market_Price_Ajax
     */
    private $ajax;

    /**
     * Admin 處理器。
     *
     * @var UR_AI_Market_Price_Admin
     */
    private $admin;

    /**
     * 排行榜 shortcode 處理器。
     *
     * @var UR_AI_Market_Price_Ranking_Shortcode
     */
    private $ranking_shortcode;

    /**
     * 建構並組裝相依物件。
     */
    public function __construct() {
        $this->service           = class_exists('UR_AI_Market_Price_Service') ? new UR_AI_Market_Price_Service() : null;
        $this->ajax              = class_exists('UR_AI_Market_Price_Ajax') ? new UR_AI_Market_Price_Ajax($this->service) : null;
        $this->admin             = class_exists('UR_AI_Market_Price_Admin') ? new UR_AI_Market_Price_Admin($this->service) : null;
        $this->ranking_shortcode = class_exists('UR_AI_Market_Price_Ranking_Shortcode')
            ? new UR_AI_Market_Price_Ranking_Shortcode($this->service)
            : null;
    }

    /**
     * 註冊掛鉤。
     *
     * @return void
     */
    public function register() {
        if ($this->ajax instanceof UR_AI_Market_Price_Ajax) {
            $this->ajax->register();
        }

        // 前台。
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_shortcode(self::RANKING_SHORTCODE, array($this, 'render_ranking_shortcode'));

        // 後台（priority 20 確保父選單已建立）。
        add_action('admin_menu', array($this, 'register_admin_page'), 20);
        add_action('admin_post_' . self::IMPORT_ACTION, array($this, 'handle_import'));
        add_action('admin_post_' . self::FETCH_ACTION, array($this, 'handle_fetch'));
        add_action('admin_post_' . self::SETTINGS_SAVE_ACTION, array($this, 'handle_settings_save'));
    }

    /**
     * 啟動：補齊設定預設。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Market_Price_Settings')) {
            UR_AI_Market_Price_Settings::maybe_install_defaults();
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
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/css/market-price.css',
            array(),
            UR_AI_ASSISTANT_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/js/market-price.js',
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
        if (!class_exists('UR_AI_Market_Price_Settings') || !UR_AI_Market_Price_Settings::is_enabled()) {
            return $this->render_disabled_notice();
        }

        if (!$this->service instanceof UR_AI_Market_Price_Service) {
            return $this->render_missing_service_notice();
        }

        $atts = shortcode_atts(
            array(
                'title' => '',
            ),
            is_array($atts) ? $atts : array(),
            self::SHORTCODE
        );

        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);
        $this->localize();

        $title = sanitize_text_field($atts['title']);

        if ('' === trim($title)) {
            $title = __('雙北成屋行情參考', 'ur-ai-assistant');
        }

        $args = array(
            'title'   => $title,
            'cities'  => $this->service->get_supported_cities(),
        );

        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'public/views/market-price-view.php';

        if (!file_exists($view)) {
            return '';
        }

        ob_start();
        include $view;

        return ob_get_clean();
    }

    /**
     * 排行榜 shortcode 輸出。
     *
     * 純伺服器端渲染，不需要 AJAX／JS，只 enqueue CSS（沿用 widget 的
     * 樣式檔案，class 前綴不同不會互相干擾）。
     *
     * @param array|string $atts shortcode 屬性。
     * @return string
     */
    public function render_ranking_shortcode($atts = array()) {
        if (!$this->ranking_shortcode instanceof UR_AI_Market_Price_Ranking_Shortcode) {
            return '';
        }

        wp_enqueue_style(self::STYLE_HANDLE);

        return $this->ranking_shortcode->render($atts);
    }

    /**
     * localize 前台 JS。
     *
     * @return void
     */
    private function localize() {
        wp_localize_script(
            self::SCRIPT_HANDLE,
            'UR_AI_MARKET_PRICE',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ur_ai_assistant_public_nonce'),
                'action'   => UR_AI_Market_Price_Ajax::ACTION_QUERY,
                'i18n'     => array(
                    'loading'          => __('查詢中…', 'ur-ai-assistant'),
                    'error'            => __('查詢失敗，請稍後再試。', 'ur-ai-assistant'),
                    'need_district'    => __('請選擇行政區。', 'ur-ai-assistant'),
                    'insufficient'     => __('本區樣本數不足，暫不提供統計，建議放寬篩選條件。', 'ur-ai-assistant'),
                    'sample_count'     => __('樣本 %s 筆', 'ur-ai-assistant'),
                    'avg_age'          => __('平均屋齡 %s 年', 'ur-ai-assistant'),
                    'old_title'        => __('老屋現況行情（%s 年以上）', 'ur-ai-assistant'),
                    'new_title'        => __('新成屋行情（%s 年內）', 'ur-ai-assistant'),
                    'range_label'      => __('常見區間', 'ur-ai-assistant'),
                    'range_note'       => __('（反映同區域內不同樓層、屋況、地點的價格落差，已排除少數極端案例）', 'ur-ai-assistant'),
                    'per_ping'         => __('每坪', 'ur-ai-assistant'),
                    'examples_label'   => __('參考案例（依單價由低到高）', 'ur-ai-assistant'),
                    /* translators: 1: 屋齡 2: 坪數 3: 建物型態 */
                    'example_feature'  => __('屋齡 %1$s 年、%2$s 坪、%3$s', 'ur-ai-assistant'),
                    'example_price'    => __('單價約 %s/坪', 'ur-ai-assistant'),
                    'uplift_label'     => __('都更後行情變化約 %s', 'ur-ai-assistant'),
                    'trend_label'      => __('近一年成長 %s', 'ur-ai-assistant'),
                    'recent_label'     => __('近一年行情', 'ur-ai-assistant'),
                    'total_records_label' => __('資料庫累計 %s 筆歷史成交紀錄', 'ur-ai-assistant'),
                    /* translators: %s: 偵測到的 App 名稱，例如 LINE */
                    'inapp_notice'     => __('偵測到您正在 %s 內建瀏覽器開啟本頁面，下拉選單可能無法正常使用。建議點選右上角「⋯」選單，選擇「在瀏覽器中開啟」以獲得最佳使用體驗。', 'ur-ai-assistant'),
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

        return '<div class="ur-ai-market-price"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant 行情參考功能目前已停用。管理員可至後台「行情參考」設定重新啟用。', 'ur-ai-assistant')
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

        return '<div class="ur-ai-market-price"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant 行情參考服務類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')
            . '</div></div>';
    }

    /* ---------------------------------------------------------------------
     * 後台
     * ------------------------------------------------------------------- */

    /**
     * 註冊後台「行情參考」選單頁。
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
            __('行情參考', 'ur-ai-assistant'),
            __('行情參考', 'ur-ai-assistant'),
            $capability,
            self::ADMIN_MENU_SLUG,
            array($this, 'render_admin_page')
        );
    }

    /**
     * 渲染後台「行情參考」頁。
     *
     * @return void
     */
    public function render_admin_page() {
        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'admin/pages/market-price-import-page.php';

        if (file_exists($view)) {
            include $view;
        }
    }

    /**
     * 處理行情資料匯入（admin-post）。
     *
     * @return void
     */
    public function handle_import() {
        if ($this->admin instanceof UR_AI_Market_Price_Admin) {
            $this->admin->handle_import();
            return;
        }

        wp_die(esc_html__('行情參考管理服務尚未正確載入。', 'ur-ai-assistant'));
    }

    /**
     * 處理自內政部開放資料端點下載並匯入（admin-post）。
     *
     * @return void
     */
    public function handle_fetch() {
        if ($this->admin instanceof UR_AI_Market_Price_Admin) {
            $this->admin->handle_fetch();
            return;
        }

        wp_die(esc_html__('行情參考管理服務尚未正確載入。', 'ur-ai-assistant'));
    }

    /**
     * 處理設定儲存（admin-post）。
     *
     * @return void
     */
    public function handle_settings_save() {
        if ($this->admin instanceof UR_AI_Market_Price_Admin) {
            $this->admin->handle_settings_save();
            return;
        }

        wp_die(esc_html__('行情參考管理服務尚未正確載入。', 'ur-ai-assistant'));
    }
}
