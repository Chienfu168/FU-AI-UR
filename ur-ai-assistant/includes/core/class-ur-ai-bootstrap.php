<?php
/**
 * UR AI Assistant Bootstrap
 *
 * 外掛啟動核心。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Bootstrap
 */
class UR_AI_Bootstrap {

    /**
     * Module Manager.
     *
     * @var UR_AI_Module_Manager|null
     */
    private $module_manager = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->load_core_components();
    }

    /**
     * 載入核心元件。
     *
     * @return void
     */
    private function load_core_components() {
        if (class_exists('UR_AI_Module_Manager')) {
            $this->module_manager = new UR_AI_Module_Manager();
        }
    }

    /**
     * 執行外掛。
     *
     * 這個方法本身是由 `ur_ai_assistant_run()` 掛在 plugins_loaded 觸發
     * 的（見 ur-ai-assistant.php），時間點早於 WordPress 的 `init`。
     * `boot_modules()` 刻意延到 `init` 才真正執行（見下方 boot_modules()
     * 的說明），避免各模組 boot() 內部（例如
     * `UR_AI_Industry_Profiles::profiles()` 裡大量的 `__()` 呼叫）在
     * `init` 之前就觸發翻譯字串載入，導致 WordPress 6.7+ 記錄
     * 「_load_textdomain_just_in_time 呼叫過早」的 Notice。
     *
     * @return void
     */
    public function run() {
        $this->register_core_hooks();
        add_action('init', array($this, 'boot_modules'), 0);
        $this->init_updater();
    }

    /**
     * 註冊核心 hooks。
     *
     * 目前核心 hooks 保持最小化。
     * 後台選單、前台 shortcode、AJAX action 皆交由各 Module 處理：
     *
     * - UR_AI_Admin_Module
     * - UR_AI_Public_Module
     * - UR_AI_Ajax_Module
     *
     * @return void
     */
    private function register_core_hooks() {
        add_action('plugins_loaded', array($this, 'maybe_upgrade_database'), 20);
    }

    /**
     * 啟動所有模組。
     *
     * 掛在 `init`（優先權 0，盡量在其他 init 掛鉤之前執行，確保各模組
     * 註冊的 admin_menu／wp_enqueue_scripts／shortcode 等後續掛鉤仍有
     * 充足時間被 WordPress 正確觸發），而不是直接在 plugins_loaded
     * 當下同步執行——各模組的 boot() 內部常會讀取
     * `UR_AI_Industry_Profiles::profiles()`（內含大量 `__()` 多語系
     * 字串呼叫）來決定預設值，若在 plugins_loaded（早於 init）就觸發
     * 翻譯字串載入，WordPress 6.7 起會記錄「翻譯載入時機過早」的
     * Notice；當主機的 PHP 顯示錯誤設定會把這類 Notice 直接輸出到
     * 頁面時，若剛好夾雜在 WordPress 後台（例如區塊編輯器「新增
     * 頁面」）內部發出的 REST API 請求回應中，會讓原本應該是純 JSON
     * 的回應內容被夾帶進不相干的 HTML 文字、解析失敗，導致畫面顯示
     * 空白、需要重新整理才能恢復正常。延到 init 才執行可以從根本上
     * 避免觸發這個時機過早的問題。
     *
     * @return void
     */
    public function boot_modules() {
        if (!$this->module_manager instanceof UR_AI_Module_Manager) {
            return;
        }

        $this->module_manager->register_default_modules();
        $this->module_manager->boot_modules();
    }

    /**
     * 檢查資料庫是否需要升級。
     *
     * @return void
     */
    public function maybe_upgrade_database() {
        if (class_exists('UR_AI_Schema_Manager')) {
            UR_AI_Schema_Manager::maybe_upgrade();
        }
    }

    /**
     * 取得 Module Manager。
     *
     * @return UR_AI_Module_Manager|null
     */
    public function get_module_manager() {
        return $this->module_manager;
    }

    /**
     * 初始化外掛更新檢查（讓後台「外掛」頁能顯示新版本並一鍵更新，
     * 詳見 UR_AI_Updater）。
     *
     * @return void
     */
    private function init_updater() {
        if (class_exists('UR_AI_Updater')) {
            UR_AI_Updater::init();
        }
    }
}