<?php
/**
 * UR AI Assistant Admin Assets
 *
 * 後台 CSS / JS 載入管理器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Admin_Assets
 */
class UR_AI_Admin_Assets {

    /**
     * 後台 CSS handle。
     *
     * @var string
     */
    const STYLE_HANDLE = 'ur-ai-assistant-admin';

    /**
     * 後台 JS handle。
     *
     * @var string
     */
    const SCRIPT_HANDLE = 'ur-ai-assistant-admin';

    /**
     * 載入後台資源。
     *
     * @param string $hook_suffix 後台頁面 hook。
     * @return void
     */
    public function enqueue($hook_suffix) {
        if (!$this->is_plugin_admin_page($hook_suffix)) {
            return;
        }

        $this->enqueue_styles();
        $this->enqueue_scripts();
    }

    /**
     * 載入 CSS。
     *
     * @return void
     */
    private function enqueue_styles() {
        wp_enqueue_style(
            self::STYLE_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            UR_AI_ASSISTANT_VERSION
        );
    }

    /**
     * 載入 JS。
     *
     * @return void
     */
    private function enqueue_scripts() {
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'admin/assets/js/admin.js',
            array('jquery'),
            UR_AI_ASSISTANT_VERSION,
            true
        );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'UR_AI_ADMIN',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ur_ai_assistant_admin_nonce'),
                'i18n'     => array(
                    'confirm_delete'       => __('確定要刪除這筆資料嗎？此操作無法復原。', 'ur-ai-assistant'),
                    'confirm_bulk_delete'  => __('確定要批次刪除所選資料嗎？此操作無法復原。', 'ur-ai-assistant'),
                    'confirm_convert_faq'  => __('確定要轉成 FAQ 草稿嗎？轉換後仍需人工檢查後再啟用。', 'ur-ai-assistant'),
                    'confirm_import'       => __('確定要匯入所選資料嗎？匯入後預設停用，請檢查後再啟用。', 'ur-ai-assistant'),
                    'select_items'         => __('請先選擇要操作的項目。', 'ur-ai-assistant'),
                    'copy_success'         => __('已複製。', 'ur-ai-assistant'),
                    'copy_failed'          => __('複製失敗，請手動選取文字。', 'ur-ai-assistant'),
                    'processing'           => __('處理中...', 'ur-ai-assistant'),
                ),
            )
        );
    }

    /**
     * 判斷是否為外掛後台頁面。
     *
     * @param string $hook_suffix 後台頁面 hook。
     * @return bool
     */
    private function is_plugin_admin_page($hook_suffix) {
        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        if (0 === strpos($page, 'ur-ai-assistant')) {
            return true;
        }

        if (is_string($hook_suffix) && false !== strpos($hook_suffix, 'ur-ai-assistant')) {
            return true;
        }

        return false;
    }
}