<?php
/**
 * UR AI Assistant Admin Menu
 *
 * 後台選單管理器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Admin_Menu
 */
class UR_AI_Admin_Menu {

    /**
     * 主選單 slug。
     *
     * @var string
     */
    const MENU_SLUG = 'ur-ai-assistant';

    /**
     * 註冊後台選單。
     *
     * @return void
     */
    public function register() {
        add_menu_page(
            __('都更 AI 助理', 'ur-ai-assistant'),
            __('都更 AI 助理', 'ur-ai-assistant'),
            $this->get_dashboard_capability(),
            self::MENU_SLUG,
            array($this, 'render_dashboard_page'),
            'dashicons-format-chat',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('總覽', 'ur-ai-assistant'),
            __('總覽', 'ur-ai-assistant'),
            $this->get_dashboard_capability(),
            self::MENU_SLUG,
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('功能設定', 'ur-ai-assistant'),
            __('功能設定', 'ur-ai-assistant'),
            $this->get_settings_capability(),
            'ur-ai-assistant-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('FAQ 知識庫', 'ur-ai-assistant'),
            __('知識庫管理', 'ur-ai-assistant'),
            $this->get_manage_faq_capability(),
            'ur-ai-assistant-faqs',
            array($this, 'render_faqs_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('問答紀錄', 'ur-ai-assistant'),
            __('問答數據分析', 'ur-ai-assistant'),
            $this->get_view_logs_capability(),
            'ur-ai-assistant-logs',
            array($this, 'render_logs_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('相關頁面推薦', 'ur-ai-assistant'),
            __('相關頁面推薦', 'ur-ai-assistant'),
            $this->get_manage_related_pages_capability(),
            'ur-ai-assistant-related-pages',
            array($this, 'render_related_pages_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('熱門問題', 'ur-ai-assistant'),
            __('熱門問題', 'ur-ai-assistant'),
            $this->get_manage_popular_questions_capability(),
            'ur-ai-assistant-popular-questions',
            array($this, 'render_popular_questions_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('回饋分析', 'ur-ai-assistant'),
            __('回饋分析', 'ur-ai-assistant'),
            $this->get_view_logs_capability(),
            'ur-ai-assistant-feedback',
            array($this, 'render_feedback_page')
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('內容缺口', 'ur-ai-assistant'),
            __('內容缺口', 'ur-ai-assistant'),
            $this->get_view_logs_capability(),
            'ur-ai-assistant-content-gap',
            array($this, 'render_content_gap_page')
        );

        /*
         * 熱門問題／相關頁面推薦／內容缺口／回饋分析四頁合併進「知識庫
         * 管理」「問答數據分析」選單項目底下，改用頁面內的分頁籤切換
         * （見 render_group_tabs()），不再各自佔用一個側邊選單項目。
         *
         * 重要：這裡刻意不使用 remove_submenu_page()。該函式會把項目從
         * WordPress 內部的 $submenu 陣列整個移除，而 WordPress 自己判斷
         * 「目前登入身分能否存取 admin.php?page=X」的
         * user_can_access_admin_page() 邏輯，正是靠查詢這個陣列來確認
         * 頁面已註冊與所需權限；一旦移除，即使是系統管理員直接用網址
         * 進入也會被 WordPress 判定為「頁面未登記」而擋下（顯示「沒有
         * 存取這個頁面的權限」），而不是真的權限不足。因此改用純 CSS
         * 只在畫面上隱藏側邊選單裡的這幾個項目，保留完整的選單註冊，
         * 讓網址、權限判斷與所有既有連結都正常運作。
         */
        add_action('admin_head', array($this, 'print_hidden_submenu_css'));
    }

    /**
     * 在側邊選單畫面上隱藏已合併進分頁籤的重複項目（純顯示層，不影響
     * WordPress 內部的選單註冊與權限判斷，見 register() 內的說明）。
     *
     * @return void
     */
    public function print_hidden_submenu_css() {
        $hidden_slugs = array(
            'ur-ai-assistant-popular-questions',
            'ur-ai-assistant-related-pages',
            'ur-ai-assistant-content-gap',
            'ur-ai-assistant-feedback',
            'ur-ai-assistant-calc-settings',
        );

        $selectors = array();

        foreach ($hidden_slugs as $slug) {
            $selectors[] = '#adminmenu a[href$="page=' . esc_attr($slug) . '"]';
        }

        echo '<style>' . implode(',', $selectors) . '{display:none;}</style>';
    }

    /**
     * 各選單分組的頁面清單，供 render_group_tabs() 畫出分頁籤列。
     *
     * @return array
     */
    private static function get_group_definitions() {
        return array(
            'knowledge' => array(
                array('slug' => 'ur-ai-assistant-faqs', 'label' => __('FAQ 知識庫', 'ur-ai-assistant')),
                array('slug' => 'ur-ai-assistant-popular-questions', 'label' => __('熱門問題', 'ur-ai-assistant')),
                array('slug' => 'ur-ai-assistant-related-pages', 'label' => __('相關頁面推薦', 'ur-ai-assistant')),
                array('slug' => 'ur-ai-assistant-content-gap', 'label' => __('內容缺口', 'ur-ai-assistant')),
            ),
            'analytics' => array(
                array('slug' => 'ur-ai-assistant-logs', 'label' => __('問答紀錄', 'ur-ai-assistant')),
                array('slug' => 'ur-ai-assistant-feedback', 'label' => __('回饋分析', 'ur-ai-assistant')),
            ),
            'calculator' => array(
                array('slug' => 'ur-ai-assistant-leads', 'label' => __('試算名單', 'ur-ai-assistant')),
                array('slug' => 'ur-ai-assistant-calc-settings', 'label' => __('試算器設定', 'ur-ai-assistant')),
            ),
        );
    }

    /**
     * 畫出分組頁面之間的分頁籤導覽列（真實整頁連結，非 JS 切換面板）。
     *
     * 對應「知識庫管理」「問答數據分析」「都更分回試算」三個合併後的選單
     * 項目：底下每個子頁面仍是各自獨立的網址與 render 邏輯，這裡只是在
     * 頁面最上方加一列導覽，讓使用者可以在同一組的子頁面之間快速切換。
     *
     * @param string $group 分組代號：knowledge｜analytics｜calculator。
     * @return void
     */
    public static function render_group_tabs($group) {
        $groups = self::get_group_definitions();

        if (empty($groups[$group])) {
            return;
        }

        $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        echo '<div class="ur-ai-admin-group-tabs">';

        foreach ($groups[$group] as $item) {
            $is_active = ($item['slug'] === $current_page);
            $url       = admin_url('admin.php?page=' . $item['slug']);

            printf(
                '<a href="%1$s" class="ur-ai-admin-group-tab%2$s">%3$s</a>',
                esc_url($url),
                $is_active ? ' is-active' : '',
                esc_html($item['label'])
            );
        }

        echo '</div>';
    }

    /**
     * 顯示總覽頁。
     *
     * @return void
     */
    public function render_dashboard_page() {
        $this->render_page('admin/pages/dashboard-page.php');
    }

    /**
     * 顯示功能設定頁。
     *
     * @return void
     */
    public function render_settings_page() {
        $this->render_page('admin/pages/settings-page.php');
    }

    /**
     * 顯示 FAQ 知識庫頁。
     *
     * @return void
     */
    public function render_faqs_page() {
        $this->render_page('admin/pages/faq-page.php');
    }

    /**
     * 顯示問答紀錄頁。
     *
     * @return void
     */
    public function render_logs_page() {
        $this->render_page('admin/pages/logs-page.php');
    }

    /**
     * 顯示相關頁面推薦頁。
     *
     * @return void
     */
    public function render_related_pages_page() {
        $this->render_page('admin/pages/related-pages-page.php');
    }

    /**
     * 顯示熱門問題頁。
     *
     * @return void
     */
    public function render_popular_questions_page() {
        $this->render_page('admin/pages/popular-questions-page.php');
    }

    /**
     * 顯示回饋分析頁。
     *
     * @return void
     */
    public function render_feedback_page() {
        $this->render_page('admin/pages/feedback-page.php');
    }

    /**
     * 顯示內容缺口總覽頁。
     *
     * @return void
     */
    public function render_content_gap_page() {
        $this->render_page('admin/pages/content-gap-page.php');
    }

    /**
     * 載入頁面檔案。
     *
     * @param string $relative_path 相對於外掛根目錄的頁面路徑。
     * @return void
     */
    private function render_page($relative_path) {
        $relative_path = $this->normalize_relative_path($relative_path);

        if ('' === $relative_path) {
            $this->render_missing_page_notice('');
            return;
        }

        $full_path = UR_AI_ASSISTANT_PLUGIN_DIR . $relative_path;

        if (!file_exists($full_path)) {
            $this->render_missing_page_notice($relative_path);
            return;
        }

        include $full_path;
    }

    /**
     * 顯示頁面缺漏提示。
     *
     * @param string $relative_path 缺漏頁面路徑。
     * @return void
     */
    private function render_missing_page_notice($relative_path) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $message = $relative_path
            ? sprintf(
                /* translators: %s: missing page path */
                __('UR AI Assistant 後台頁面檔案不存在：%s', 'ur-ai-assistant'),
                $relative_path
            )
            : __('UR AI Assistant 後台頁面路徑不正確。', 'ur-ai-assistant');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('UR AI Assistant', 'ur-ai-assistant') . '</h1>';
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        echo '</div>';
    }

    /**
     * 正規化相對路徑。
     *
     * @param string $relative_path 相對路徑。
     * @return string
     */
    private function normalize_relative_path($relative_path) {
        if (!is_string($relative_path)) {
            return '';
        }

        $relative_path = trim($relative_path);

        if ('' === $relative_path) {
            return '';
        }

        $relative_path = str_replace('\\', '/', $relative_path);
        $relative_path = ltrim($relative_path, '/');

        if (false !== strpos($relative_path, '../')) {
            return '';
        }

        if (false !== strpos($relative_path, '..\\')) {
            return '';
        }

        return $relative_path;
    }

    /**
     * 取得總覽頁權限。
     *
     * @return string
     */
    private function get_dashboard_capability() {
        if (class_exists('UR_AI_Permissions')) {
            return UR_AI_Permissions::CAP_VIEW_DASHBOARD;
        }

        return 'manage_options';
    }

    /**
     * 取得設定頁權限。
     *
     * @return string
     */
    private function get_settings_capability() {
        if (class_exists('UR_AI_Permissions')) {
            return UR_AI_Permissions::CAP_MANAGE_SETTINGS;
        }

        return 'manage_options';
    }

    /**
     * 取得 FAQ 管理權限。
     *
     * @return string
     */
    private function get_manage_faq_capability() {
        if (class_exists('UR_AI_Permissions')) {
            return UR_AI_Permissions::CAP_MANAGE_FAQS;
        }

        return 'manage_options';
    }

    /**
     * 取得問答紀錄檢視權限。
     *
     * @return string
     */
    private function get_view_logs_capability() {
        if (class_exists('UR_AI_Permissions')) {
            return UR_AI_Permissions::CAP_VIEW_LOGS;
        }

        return 'manage_options';
    }

    /**
     * 取得相關頁面管理權限。
     *
     * @return string
     */
    private function get_manage_related_pages_capability() {
        if (class_exists('UR_AI_Permissions')) {
            return UR_AI_Permissions::CAP_MANAGE_RELATED_PAGES;
        }

        return 'manage_options';
    }

    /**
     * 取得熱門問題管理權限。
     *
     * @return string
     */
    private function get_manage_popular_questions_capability() {
        if (class_exists('UR_AI_Permissions')) {
            return UR_AI_Permissions::CAP_MANAGE_POPULAR_QUESTIONS;
        }

        return 'manage_options';
    }
}