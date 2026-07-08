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