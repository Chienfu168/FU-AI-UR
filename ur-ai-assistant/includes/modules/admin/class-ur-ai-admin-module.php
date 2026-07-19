<?php
/**
 * UR AI Assistant Admin Module
 *
 * 後台管理模組啟動器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Admin_Module
 */
class UR_AI_Admin_Module {

    /**
     * 後台選單頁面管理器。
     *
     * @var UR_AI_Admin_Menu|null
     */
    private $menu = null;

    /**
     * 後台資源管理器。
     *
     * @var UR_AI_Admin_Assets|null
     */
    private $assets = null;

    /**
     * 註冊 WordPress hooks。
     *
     * @return void
     */
    public function register() {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_post_ur_ai_repair_db_indexes', array($this, 'handle_repair_db_indexes'));
    }

    /**
     * 啟動模組。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Admin_Menu')) {
            $this->menu = new UR_AI_Admin_Menu();
        }

        if (class_exists('UR_AI_Admin_Assets')) {
            $this->assets = new UR_AI_Admin_Assets();
        }
    }

    /**
     * 註冊後台選單。
     *
     * @return void
     */
    public function register_admin_menu() {
        if (!$this->menu instanceof UR_AI_Admin_Menu && class_exists('UR_AI_Admin_Menu')) {
            $this->menu = new UR_AI_Admin_Menu();
        }

        if (!$this->menu instanceof UR_AI_Admin_Menu) {
            return;
        }

        $this->menu->register();
    }

    /**
     * 載入後台 CSS / JS。
     *
     * @param string $hook_suffix 後台頁面 hook。
     * @return void
     */
    public function enqueue_admin_assets($hook_suffix) {
        if (!$this->assets instanceof UR_AI_Admin_Assets && class_exists('UR_AI_Admin_Assets')) {
            $this->assets = new UR_AI_Admin_Assets();
        }

        if (!$this->assets instanceof UR_AI_Admin_Assets) {
            return;
        }

        $this->assets->enqueue($hook_suffix);
    }

    /**
     * 修復資料庫索引（admin-post）。
     *
     * 見 UR_AI_Schema_Manager::repair_missing_indexes() 的說明：直接下
     * `ALTER TABLE ... ADD INDEX`，不重新呼叫 dbDelta()（那正是索引
     * 一開始沒有正確建立的來源）。結果暫存在 transient（依當前使用者
     * ID 區隔，避免多位管理者同時操作互相覆蓋），導回總覽頁後讀取
     * 顯示，不透過網址參數傳遞（結果內容可能較長，且含錯誤訊息）。
     *
     * @return void
     */
    public function handle_repair_db_indexes() {
        if (class_exists('UR_AI_Permissions')) {
            UR_AI_Permissions::require_manage_settings();
        } elseif (!current_user_can('manage_options')) {
            wp_die(esc_html__('權限不足。', 'ur-ai-assistant'));
        }

        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::verify_admin_form_nonce_or_die();
        } else {
            check_admin_referer('ur_ai_assistant_admin_action', 'ur_ai_nonce');
        }

        $result = class_exists('UR_AI_Schema_Manager') ? UR_AI_Schema_Manager::repair_missing_indexes() : array();

        set_transient('ur_ai_db_repair_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS * 5);

        wp_safe_redirect(admin_url('admin.php?page=' . UR_AI_Admin_Menu::MENU_SLUG . '&ur_db_repaired=1'));
        exit;
    }

    /**
     * 取得後台選單管理器。
     *
     * @return UR_AI_Admin_Menu|null
     */
    public function get_menu() {
        return $this->menu;
    }

    /**
     * 取得後台資源管理器。
     *
     * @return UR_AI_Admin_Assets|null
     */
    public function get_assets() {
        return $this->assets;
    }
}