<?php
/**
 * UR AI Assistant Quiz Module
 *
 * 知識大考驗模組主類別（沿用既有模組 register()/boot() 兩段式架構）。
 *
 * 負責：
 * - shortcode [ur_ai_quiz]（作答挑戰）與 [ur_ai_quiz_leaderboard]（排行榜）。
 * - 前台專屬資產（僅在使用 shortcode 的頁面才 enqueue）。
 * - 前台 AJAX 開始挑戰／送出作答。
 * - 後台「知識大考驗」題庫管理、審核、設定與排行榜管理頁。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Quiz_Module
 */
class UR_AI_Quiz_Module {

    const SHORTCODE             = 'ur_ai_quiz';
    const LEADERBOARD_SHORTCODE = 'ur_ai_quiz_leaderboard';
    const STYLE_HANDLE          = 'ur-ai-quiz';
    const SCRIPT_HANDLE         = 'ur-ai-quiz';
    const ADMIN_MENU_SLUG       = 'ur-ai-assistant-quiz';
    const PARENT_SLUG           = 'ur-ai-assistant';

    /**
     * Service。
     *
     * @var UR_AI_Quiz_Service
     */
    private $service;

    /**
     * AJAX 處理器。
     *
     * @var UR_AI_Quiz_Ajax
     */
    private $ajax;

    /**
     * Admin 處理器。
     *
     * @var UR_AI_Quiz_Admin
     */
    private $admin;

    /**
     * 排行榜 shortcode 處理器。
     *
     * @var UR_AI_Quiz_Leaderboard_Shortcode
     */
    private $leaderboard_shortcode;

    /**
     * 建構並組裝相依物件。
     */
    public function __construct() {
        $this->service               = class_exists('UR_AI_Quiz_Service') ? new UR_AI_Quiz_Service() : null;
        $this->ajax                  = class_exists('UR_AI_Quiz_Ajax') ? new UR_AI_Quiz_Ajax($this->service) : null;
        $this->admin                 = class_exists('UR_AI_Quiz_Admin') ? new UR_AI_Quiz_Admin($this->service) : null;
        $this->leaderboard_shortcode = class_exists('UR_AI_Quiz_Leaderboard_Shortcode')
            ? new UR_AI_Quiz_Leaderboard_Shortcode($this->service)
            : null;
    }

    /**
     * 註冊掛鉤。
     *
     * @return void
     */
    public function register() {
        if ($this->ajax instanceof UR_AI_Quiz_Ajax) {
            $this->ajax->register();
        }

        // 前台。
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_shortcode(self::LEADERBOARD_SHORTCODE, array($this, 'render_leaderboard_shortcode'));

        // 後台（priority 20 確保父選單已建立）。
        add_action('admin_menu', array($this, 'register_admin_page'), 20);

        if ($this->admin instanceof UR_AI_Quiz_Admin) {
            add_action('admin_init', array($this->admin, 'handle_actions'));
        }
    }

    /**
     * 啟動：補齊設定預設。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Quiz_Settings')) {
            UR_AI_Quiz_Settings::maybe_install_defaults();
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
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/css/quiz.css',
            array(),
            UR_AI_ASSISTANT_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/js/quiz.js',
            array(),
            UR_AI_ASSISTANT_VERSION,
            true
        );
    }

    /**
     * [ur_ai_quiz] shortcode 輸出。
     *
     * @param array|string $atts shortcode 屬性。
     * @return string
     */
    public function render_shortcode($atts = array()) {
        if (!class_exists('UR_AI_Quiz_Settings') || !UR_AI_Quiz_Settings::is_enabled()) {
            return $this->render_disabled_notice();
        }

        if (!$this->service instanceof UR_AI_Quiz_Service) {
            return $this->render_missing_service_notice();
        }

        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);
        $this->localize();

        $args = array(
            'title'          => UR_AI_Quiz_Settings::get_title(),
            'question_count' => UR_AI_Quiz_Settings::get_question_count(),
            'question_bank'  => $this->service->count_active_questions(),
        );

        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'public/views/quiz-view.php';

        if (!file_exists($view)) {
            return '';
        }

        ob_start();
        include $view;

        return ob_get_clean();
    }

    /**
     * [ur_ai_quiz_leaderboard] shortcode 輸出。
     *
     * @param array|string $atts shortcode 屬性。
     * @return string
     */
    public function render_leaderboard_shortcode($atts = array()) {
        if (!$this->leaderboard_shortcode instanceof UR_AI_Quiz_Leaderboard_Shortcode) {
            return '';
        }

        wp_enqueue_style(self::STYLE_HANDLE);

        return $this->leaderboard_shortcode->render($atts);
    }

    /**
     * localize 前台 JS。
     *
     * @return void
     */
    private function localize() {
        wp_localize_script(
            self::SCRIPT_HANDLE,
            'UR_AI_QUIZ',
            array(
                'ajax_url'     => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('ur_ai_assistant_public_nonce'),
                'action_start' => UR_AI_Quiz_Ajax::ACTION_START,
                'action_submit' => UR_AI_Quiz_Ajax::ACTION_SUBMIT,
                'i18n'         => array(
                    'start_button'      => __('開始挑戰', 'ur-ai-assistant'),
                    'loading'           => __('題目準備中…', 'ur-ai-assistant'),
                    'error'             => __('發生錯誤，請稍後再試。', 'ur-ai-assistant'),
                    'next_question'     => __('下一題', 'ur-ai-assistant'),
                    'submit_button'     => __('送出成績', 'ur-ai-assistant'),
                    'nickname_label'    => __('留下您的名號（可留空匿名）', 'ur-ai-assistant'),
                    'nickname_placeholder' => __('例如：土城王小明（選填）', 'ur-ai-assistant'),
                    'result_title'      => __('挑戰結束！', 'ur-ai-assistant'),
                    /* translators: 1: correct count 2: total questions */
                    'result_detail'     => __('答對 %1$s / %2$s 題', 'ur-ai-assistant'),
                    'new_best'          => __('已刷新您的最佳成績，成功上榜！', 'ur-ai-assistant'),
                    'not_best'          => __('已作答完成，但未超過您先前留存的最佳成績，排行榜維持原紀錄。', 'ur-ai-assistant'),
                    'retry_button'      => __('再挑戰一次', 'ur-ai-assistant'),
                    'question_progress' => __('第 %1$s / %2$s 題', 'ur-ai-assistant'),
                    'please_answer'     => __('請先選擇一個答案。', 'ur-ai-assistant'),
                    'review_title'      => __('作答回顧', 'ur-ai-assistant'),
                    'review_correct'    => __('✓ 答對了', 'ur-ai-assistant'),
                    /* translators: %1$s: 正確答案文字 */
                    'review_incorrect'  => __('✕ 答錯了，正確答案：%1$s', 'ur-ai-assistant'),
                    /* translators: 1: 題號 2: 題目文字 */
                    'review_question'   => __('第 %1$s 題．%2$s', 'ur-ai-assistant'),
                    /* translators: 1: FAQ 分類（前台以 JS 帶入） 2: FAQ 問題（前台以 JS 帶入） 3: 目前產業別的品牌名稱 */
                    'review_faq'        => sprintf(
                        __('相關 FAQ（%1$s）：%2$s，可至「%3$s」搜尋此問題看完整說明。', 'ur-ai-assistant'),
                        '%1$s',
                        '%2$s',
                        class_exists('UR_AI_Industry_Profiles') ? UR_AI_Industry_Profiles::get_active_brand_name() : __('都更AI助理', 'ur-ai-assistant')
                    ),
                    /* translators: %1$s: FAQ 分類（前台以 JS 帶入）。連結文字（FAQ 問題）由前台另外附加。 */
                    'review_article_prefix' => __('這則問題有更完整的文章可以複習（%1$s）：', 'ur-ai-assistant'),
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

        return '<div class="ur-ai-quiz"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant 知識大考驗功能目前已停用。管理員可至後台「知識大考驗」設定重新啟用。', 'ur-ai-assistant')
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

        return '<div class="ur-ai-quiz"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant 知識大考驗服務類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')
            . '</div></div>';
    }

    /* ---------------------------------------------------------------------
     * 後台
     * ------------------------------------------------------------------- */

    /**
     * 註冊後台「知識大考驗」選單頁。
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
            __('知識大考驗', 'ur-ai-assistant'),
            __('知識大考驗', 'ur-ai-assistant'),
            $capability,
            self::ADMIN_MENU_SLUG,
            array($this, 'render_admin_page')
        );
    }

    /**
     * 渲染後台「知識大考驗」頁。
     *
     * @return void
     */
    public function render_admin_page() {
        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'admin/pages/quiz-page.php';

        if (file_exists($view)) {
            include $view;
        }
    }
}
