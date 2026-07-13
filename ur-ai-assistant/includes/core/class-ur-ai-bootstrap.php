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
     * @return void
     */
    public function run() {
        $this->register_core_hooks();
        $this->boot_modules();
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
     * @return void
     */
    private function boot_modules() {
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