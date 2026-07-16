<?php
/**
 * UR AI Assistant Admin Chat Module
 *
 * 後台「AI 對話」模組：讓管理者能與 AI 助理多輪對話，腦力激盪知識庫
 * 內容方向，對話結束後可請 AI 整理成 FAQ 草稿，審核後再啟用。
 *
 * 設計原則：
 * - 對話內容本身不寫入資料庫，只存在瀏覽器記憶體中（重新整理頁面
 *   會清空），避免新增資料表；真正需要長期保存的是「轉出的 FAQ
 *   草稿」，這部分沿用既有的 FAQ 資料表與審核機制。
 * - 每次請求都是把目前累積的對話紀錄整批送給 OpenAI（Chat Completions
 *   API 本身無狀態），伺服器端不保存對話狀態。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Admin_Chat_Module
 */
class UR_AI_Admin_Chat_Module {

    const STYLE_HANDLE    = 'ur-ai-admin-chat';
    const SCRIPT_HANDLE   = 'ur-ai-admin-chat';
    const ADMIN_MENU_SLUG = 'ur-ai-assistant-ai-chat';
    const PARENT_SLUG     = 'ur-ai-assistant';

    /**
     * AJAX 處理器。
     *
     * @var UR_AI_Admin_Chat_Ajax|null
     */
    private $ajax;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->ajax = class_exists('UR_AI_Admin_Chat_Ajax') ? new UR_AI_Admin_Chat_Ajax() : null;
    }

    /**
     * 註冊掛鉤。
     *
     * @return void
     */
    public function register() {
        if ($this->ajax instanceof UR_AI_Admin_Chat_Ajax) {
            $this->ajax->register();
        }

        // 優先權 20，確保父選單（由 UR_AI_Admin_Menu 建立）已存在。
        add_action('admin_menu', array($this, 'register_admin_page'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * 啟動。目前無需額外初始化。
     *
     * @return void
     */
    public function boot() {
    }

    /**
     * 註冊後台「AI 對話」選單頁。
     *
     * @return void
     */
    public function register_admin_page() {
        $capability = 'manage_options';

        if (class_exists('UR_AI_Permissions') && method_exists('UR_AI_Permissions', 'get_capability')) {
            $maybe = UR_AI_Permissions::get_capability('admin_chat');

            if (is_string($maybe) && '' !== $maybe) {
                $capability = $maybe;
            }
        }

        add_submenu_page(
            self::PARENT_SLUG,
            __('AI 對話', 'ur-ai-assistant'),
            __('AI 對話', 'ur-ai-assistant'),
            $capability,
            self::ADMIN_MENU_SLUG,
            array($this, 'render_admin_page')
        );
    }

    /**
     * 渲染後台「AI 對話」頁。
     *
     * @return void
     */
    public function render_admin_page() {
        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'admin/pages/ai-chat-page.php';

        if (file_exists($view)) {
            include $view;
        }
    }

    /**
     * 只在本頁載入專屬 CSS／JS，避免拖累其他後台頁面的載入速度。
     *
     * @param string $hook_suffix 後台頁面 hook。
     * @return void
     */
    public function enqueue_assets($hook_suffix) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if (self::ADMIN_MENU_SLUG !== $page) {
            return;
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'admin/assets/css/ai-chat.css',
            array('ur-ai-assistant-admin'),
            UR_AI_ASSISTANT_VERSION
        );

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'admin/assets/js/ai-chat.js',
            array('jquery', 'ur-ai-assistant-admin'),
            UR_AI_ASSISTANT_VERSION,
            true
        );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'UR_AI_ADMIN_CHAT',
            array(
                'action_send'      => 'ur_ai_admin_chat_send',
                'action_summarize' => 'ur_ai_admin_chat_summarize',
                'action_save_draft' => 'ur_ai_admin_chat_save_draft',
                'i18n'             => array(
                    'send_button'        => __('傳送', 'ur-ai-assistant'),
                    'sending'            => __('傳送中…', 'ur-ai-assistant'),
                    'placeholder'        => __('輸入想討論的知識庫內容方向……', 'ur-ai-assistant'),
                    'empty_message'      => __('請先輸入訊息內容。', 'ur-ai-assistant'),
                    'send_error'         => __('AI 回覆失敗，請稍後再試。', 'ur-ai-assistant'),
                    'summarize_button'   => __('產生總結草稿', 'ur-ai-assistant'),
                    'summarizing'        => __('整理中…', 'ur-ai-assistant'),
                    'no_conversation'    => __('請先與 AI 對話幾輪，再產生總結草稿。', 'ur-ai-assistant'),
                    'summarize_error'    => __('整理草稿失敗，請稍後再試。', 'ur-ai-assistant'),
                    'drafts_title'       => __('AI 整理出的 FAQ 草稿建議', 'ur-ai-assistant'),
                    'question_label'     => __('標準問題', 'ur-ai-assistant'),
                    'answer_label'       => __('固定回答', 'ur-ai-assistant'),
                    'category_label'     => __('分類', 'ur-ai-assistant'),
                    'keywords_label'     => __('關鍵字', 'ur-ai-assistant'),
                    'save_draft_button'  => __('加入知識庫（存成草稿）', 'ur-ai-assistant'),
                    'saving_draft'       => __('儲存中…', 'ur-ai-assistant'),
                    'save_draft_error'   => __('儲存失敗，請稍後再試。', 'ur-ai-assistant'),
                    'draft_saved'        => __('已儲存為草稿', 'ur-ai-assistant'),
                    'confirm_save_draft' => __('確定要把這則內容加入知識庫嗎？會以「停用／待審核」草稿狀態存入，仍需人工審核後再啟用。', 'ur-ai-assistant'),
                ),
            )
        );
    }
}
