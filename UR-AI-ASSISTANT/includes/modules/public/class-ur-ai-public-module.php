<?php
/**
 * UR AI Assistant Public Module
 *
 * 前台模組啟動器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Public_Module
 */
class UR_AI_Public_Module {

    /**
     * Public Assets.
     *
     * @var UR_AI_Public_Assets|null
     */
    private $assets = null;

    /**
     * Shortcode.
     *
     * @var UR_AI_Shortcode|null
     */
    private $shortcode = null;

    /**
     * 註冊 WordPress hooks。
     *
     * @return void
     */
    public function register() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_shortcode('ur_ai_assistant', array($this, 'render_shortcode'));
    }

    /**
     * 啟動模組。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Public_Assets')) {
            $this->assets = new UR_AI_Public_Assets();
        }

        if (class_exists('UR_AI_Shortcode')) {
            $this->shortcode = new UR_AI_Shortcode();
        }
    }

    /**
     * 註冊前台資源。
     *
     * 注意：這裡只註冊，不一定立即載入。
     * 真正載入由 Shortcode render 時呼叫 enqueue。
     *
     * @return void
     */
    public function register_assets() {
        if (!$this->assets instanceof UR_AI_Public_Assets && class_exists('UR_AI_Public_Assets')) {
            $this->assets = new UR_AI_Public_Assets();
        }

        if (!$this->assets instanceof UR_AI_Public_Assets) {
            return;
        }

        if (method_exists($this->assets, 'register')) {
            $this->assets->register();
        }
    }

    /**
     * Shortcode 輸出。
     *
     * @param array|string $atts Shortcode 屬性。
     * @return string
     */
    public function render_shortcode($atts = array()) {
        if (!$this->is_frontend_enabled()) {
            return $this->render_disabled_notice();
        }

        if (!$this->shortcode instanceof UR_AI_Shortcode && class_exists('UR_AI_Shortcode')) {
            $this->shortcode = new UR_AI_Shortcode();
        }

        if (!$this->shortcode instanceof UR_AI_Shortcode) {
            return $this->render_missing_shortcode_notice();
        }

        if (!$this->assets instanceof UR_AI_Public_Assets && class_exists('UR_AI_Public_Assets')) {
            $this->assets = new UR_AI_Public_Assets();
        }

        if ($this->assets instanceof UR_AI_Public_Assets && method_exists($this->assets, 'enqueue')) {
            $this->assets->enqueue();
        }

        return $this->shortcode->render($atts);
    }

    /**
     * 判斷前台問答是否啟用。
     *
     * @return bool
     */
    private function is_frontend_enabled() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::is_frontend_enabled();
        }

        return true;
    }

    /**
     * 前台停用提示。
     *
     * @return string
     */
    private function render_disabled_notice() {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="ur-ai-assistant"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant 前台問答目前已停用。管理員可至後台功能設定重新啟用。', 'ur-ai-assistant')
            . '</div></div>';
    }

    /**
     * Shortcode 類別缺漏提示。
     *
     * @return string
     */
    private function render_missing_shortcode_notice() {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="ur-ai-assistant"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant Shortcode 類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')
            . '</div></div>';
    }

    /**
     * 取得 Public Assets。
     *
     * @return UR_AI_Public_Assets|null
     */
    public function get_assets() {
        return $this->assets;
    }

    /**
     * 取得 Shortcode。
     *
     * @return UR_AI_Shortcode|null
     */
    public function get_shortcode() {
        return $this->shortcode;
    }
}