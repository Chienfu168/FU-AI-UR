<?php
/**
 * UR AI Assistant Permissions
 *
 * 外掛共用權限管理類別。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Permissions
 */
class UR_AI_Permissions {

    /**
     * 檢視總覽頁權限。
     *
     * @var string
     */
    const CAP_VIEW_DASHBOARD = 'manage_options';

    /**
     * 管理設定權限。
     *
     * @var string
     */
    const CAP_MANAGE_SETTINGS = 'manage_options';

    /**
     * 管理 FAQ 權限。
     *
     * @var string
     */
    const CAP_MANAGE_FAQS = 'manage_options';

    /**
     * 檢視問答紀錄權限。
     *
     * @var string
     */
    const CAP_VIEW_LOGS = 'manage_options';

    /**
     * 管理相關頁面推薦權限。
     *
     * @var string
     */
    const CAP_MANAGE_RELATED_PAGES = 'manage_options';

    /**
     * 管理熱門問題權限。
     *
     * @var string
     */
    const CAP_MANAGE_POPULAR_QUESTIONS = 'manage_options';

    /**
     * 使用後台「AI 對話」權限。
     *
     * @var string
     */
    const CAP_MANAGE_ADMIN_CHAT = 'manage_options';

    /**
     * 檢查使用者是否可檢視總覽頁。
     *
     * @return bool
     */
    public static function can_view_dashboard() {
        return current_user_can(self::CAP_VIEW_DASHBOARD);
    }

    /**
     * 檢查使用者是否可管理設定。
     *
     * @return bool
     */
    public static function can_manage_settings() {
        return current_user_can(self::CAP_MANAGE_SETTINGS);
    }

    /**
     * 檢查使用者是否可管理 FAQ。
     *
     * @return bool
     */
    public static function can_manage_faqs() {
        return current_user_can(self::CAP_MANAGE_FAQS);
    }

    /**
     * 檢查使用者是否可檢視問答紀錄。
     *
     * @return bool
     */
    public static function can_view_logs() {
        return current_user_can(self::CAP_VIEW_LOGS);
    }

    /**
     * 檢查使用者是否可管理相關頁面。
     *
     * @return bool
     */
    public static function can_manage_related_pages() {
        return current_user_can(self::CAP_MANAGE_RELATED_PAGES);
    }

    /**
     * 檢查使用者是否可管理熱門問題。
     *
     * @return bool
     */
    public static function can_manage_popular_questions() {
        return current_user_can(self::CAP_MANAGE_POPULAR_QUESTIONS);
    }

    /**
     * 檢查使用者是否可使用後台「AI 對話」。
     *
     * @return bool
     */
    public static function can_manage_admin_chat() {
        return current_user_can(self::CAP_MANAGE_ADMIN_CHAT);
    }

    /**
     * 要求檢視總覽頁權限。
     *
     * @return void
     */
    public static function require_view_dashboard() {
        self::require_capability(self::CAP_VIEW_DASHBOARD);
    }

    /**
     * 要求管理設定權限。
     *
     * @return void
     */
    public static function require_manage_settings() {
        self::require_capability(self::CAP_MANAGE_SETTINGS);
    }

    /**
     * 要求 FAQ 管理權限。
     *
     * @return void
     */
    public static function require_manage_faqs() {
        self::require_capability(self::CAP_MANAGE_FAQS);
    }

    /**
     * 要求檢視問答紀錄權限。
     *
     * @return void
     */
    public static function require_view_logs() {
        self::require_capability(self::CAP_VIEW_LOGS);
    }

    /**
     * 要求相關頁面管理權限。
     *
     * @return void
     */
    public static function require_manage_related_pages() {
        self::require_capability(self::CAP_MANAGE_RELATED_PAGES);
    }

    /**
     * 要求熱門問題管理權限。
     *
     * @return void
     */
    public static function require_manage_popular_questions() {
        self::require_capability(self::CAP_MANAGE_POPULAR_QUESTIONS);
    }

    /**
     * 要求後台「AI 對話」使用權限。
     *
     * @return void
     */
    public static function require_manage_admin_chat() {
        self::require_capability(self::CAP_MANAGE_ADMIN_CHAT);
    }

    /**
     * 要求指定權限。
     *
     * @param string $capability WordPress capability.
     * @return void
     */
    public static function require_capability($capability) {
        $capability = sanitize_key($capability);

        if (current_user_can($capability)) {
            return;
        }

        wp_die(
            esc_html__('您沒有權限執行此操作。', 'ur-ai-assistant'),
            esc_html__('權限不足', 'ur-ai-assistant'),
            array(
                'response' => 403,
            )
        );
    }

    /**
     * 取得指定功能的 capability。
     *
     * @param string $area 功能區域。
     * @return string
     */
    public static function get_capability($area) {
        $area = sanitize_key($area);

        $map = array(
            'dashboard'         => self::CAP_VIEW_DASHBOARD,
            'settings'          => self::CAP_MANAGE_SETTINGS,
            'faqs'              => self::CAP_MANAGE_FAQS,
            'logs'              => self::CAP_VIEW_LOGS,
            'related_pages'     => self::CAP_MANAGE_RELATED_PAGES,
            'popular_questions' => self::CAP_MANAGE_POPULAR_QUESTIONS,
            'admin_chat'        => self::CAP_MANAGE_ADMIN_CHAT,
        );

        return isset($map[$area]) ? $map[$area] : 'manage_options';
    }

    /**
     * 判斷目前使用者是否可進入外掛後台。
     *
     * @return bool
     */
    public static function can_access_admin() {
        return self::can_view_dashboard()
            || self::can_manage_settings()
            || self::can_manage_faqs()
            || self::can_view_logs()
            || self::can_manage_related_pages()
            || self::can_manage_popular_questions();
    }

    /**
     * AJAX 權限檢查。
     *
     * @param string $area 功能區域。
     * @return void
     */
    public static function ajax_require($area = 'dashboard') {
        $capability = self::get_capability($area);

        if (current_user_can($capability)) {
            return;
        }

        wp_send_json_error(
            array(
                'message' => __('您沒有權限執行此操作。', 'ur-ai-assistant'),
            ),
            403
        );
    }
}