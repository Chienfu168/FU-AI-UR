<?php
/**
 * UR AI Assistant Module Manager
 *
 * 外掛模組管理器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Module_Manager
 */
class UR_AI_Module_Manager {

    /**
     * 已註冊模組。
     *
     * @var array
     */
    private $modules = array();

    /**
     * 已啟動模組實例。
     *
     * @var array
     */
    private $instances = array();

    /**
     * 註冊預設模組。
     *
     * 注意：
     * admin、public、ajax 是外掛能否正常運作的基礎模組，
     * 必須優先註冊。
     *
     * @return void
     */
    public function register_default_modules() {
        /*
         * 後台模組：
         * 負責後台選單、後台頁面、後台 CSS / JS。
         */
        $this->register_module(
            'admin',
            'UR_AI_Admin_Module',
            true
        );

        /*
         * 前台模組：
         * 負責 shortcode、前台畫面、前台 CSS / JS。
         */
        $this->register_module(
            'public',
            'UR_AI_Public_Module',
            true
        );

        /*
         * AJAX 模組：
         * 負責前台提問、回饋、點擊紀錄等 AJAX action。
         */
        $this->register_module(
            'ajax',
            'UR_AI_Ajax_Module',
            true
        );

        /*
         * AI 問答核心模組：
         * 負責 Answer Service 與 AI 問答流程。
         */
        $this->register_module(
            'assistant',
            'UR_AI_Assistant_Module',
            true
        );

        /*
         * FAQ 知識庫模組。
         */
        $this->register_module(
            'faq',
            'UR_AI_FAQ_Module',
            true
        );

        /*
         * 問答紀錄模組。
         */
        $this->register_module(
            'logs',
            'UR_AI_Logs_Module',
            true
        );

        /*
         * 相關頁面推薦模組。
         */
        $this->register_module(
            'related_pages',
            'UR_AI_Related_Pages_Module',
            true
        );

        /*
         * 熱門問題模組。
         */
        $this->register_module(
            'popular_questions',
            'UR_AI_Popular_Questions_Module',
            true
        );

        /*
         * 回饋分析模組。
         */
        $this->register_module(
            'feedback',
            'UR_AI_Feedback_Module',
            true
        );

        /*
         * 都更分回試算模組（v1.1.0 新增）。
         * 提供前台試算 shortcode、leads 名單擷取與後台名單頁。
         */
        $this->register_module(
            'calculator',
            'UR_AI_Calculator_Module',
            true
        );

        /*
         * 行情參考模組（v1.8.0 新增）。
         * 提供雙北成屋行情（老屋現況／新成屋）查詢 shortcode 與後台匯入功能。
         */
        $this->register_module(
            'market_price',
            'UR_AI_Market_Price_Module',
            true
        );

        /*
         * 知識大考驗模組（v1.9.0 新增）。
         * 提供依 FAQ 出題的隨機挑戰 shortcode、排行榜與後台題庫管理。
         */
        $this->register_module(
            'quiz',
            'UR_AI_Quiz_Module',
            true
        );

        /*
         * 後台 AI 對話模組（v1.26.0 新增）。
         * 讓管理者能與 AI 助理多輪對話腦力激盪知識庫內容，並可將對話
         * 整理成 FAQ 草稿（停用／待審核，需人工審核後再啟用）。
         */
        $this->register_module(
            'admin_chat',
            'UR_AI_Admin_Chat_Module',
            true
        );
    }

    /**
     * 註冊單一模組。
     *
     * @param string $key 模組 key。
     * @param string $class_name 模組 class 名稱。
     * @param bool   $enabled 是否啟用。
     * @return void
     */
    public function register_module($key, $class_name, $enabled = true) {
        $key        = sanitize_key($key);
        $class_name = is_string($class_name) ? trim($class_name) : '';

        if ('' === $key || '' === $class_name) {
            return;
        }

        $this->modules[$key] = array(
            'class_name' => $class_name,
            'enabled'    => (bool) $enabled,
        );
    }

    /**
     * 啟動所有已註冊模組。
     *
     * @return void
     */
    public function boot_modules() {
        foreach ($this->modules as $key => $module) {
            if (empty($module['enabled'])) {
                continue;
            }

            $this->boot_module($key);
        }
    }

    /**
     * 啟動單一模組。
     *
     * @param string $key 模組 key。
     * @return object|null
     */
    public function boot_module($key) {
        $key = sanitize_key($key);

        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (empty($this->modules[$key]['class_name'])) {
            return null;
        }

        $class_name = $this->modules[$key]['class_name'];

        if (!class_exists($class_name)) {
            return null;
        }

        $instance = new $class_name();

        /*
         * register() 用於註冊 hooks。
         * 例如 add_action、add_shortcode、wp_ajax 等。
         */
        if (method_exists($instance, 'register')) {
            $instance->register();
        }

        /*
         * boot() 用於建立 service、admin controller、matcher 等內部物件。
         */
        if (method_exists($instance, 'boot')) {
            $instance->boot();
        }

        $this->instances[$key] = $instance;

        return $instance;
    }

    /**
     * 取得已註冊模組設定。
     *
     * @return array
     */
    public function get_modules() {
        return $this->modules;
    }

    /**
     * 取得已啟動模組實例。
     *
     * @return array
     */
    public function get_instances() {
        return $this->instances;
    }

    /**
     * 取得單一模組實例。
     *
     * @param string $key 模組 key。
     * @return object|null
     */
    public function get_instance($key) {
        $key = sanitize_key($key);

        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        return null;
    }

    /**
     * 判斷模組是否已註冊。
     *
     * @param string $key 模組 key。
     * @return bool
     */
    public function has_module($key) {
        $key = sanitize_key($key);

        return isset($this->modules[$key]);
    }

    /**
     * 判斷模組是否已啟動。
     *
     * @param string $key 模組 key。
     * @return bool
     */
    public function is_booted($key) {
        $key = sanitize_key($key);

        return isset($this->instances[$key]);
    }

    /**
     * 停用指定模組。
     *
     * @param string $key 模組 key。
     * @return void
     */
    public function disable_module($key) {
        $key = sanitize_key($key);

        if (isset($this->modules[$key])) {
            $this->modules[$key]['enabled'] = false;
        }
    }

    /**
     * 啟用指定模組。
     *
     * @param string $key 模組 key。
     * @return void
     */
    public function enable_module($key) {
        $key = sanitize_key($key);

        if (isset($this->modules[$key])) {
            $this->modules[$key]['enabled'] = true;
        }
    }
}