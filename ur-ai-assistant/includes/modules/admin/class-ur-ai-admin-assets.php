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
                'ajax_url'          => admin_url('admin-ajax.php'),
                'nonce'             => wp_create_nonce('ur_ai_assistant_admin_nonce'),
                'industry_profiles' => $this->get_industry_profiles_for_js(),
                'i18n'     => array(
                    'confirm_delete'       => __('確定要刪除這筆資料嗎？此操作無法復原。', 'ur-ai-assistant'),
                    'confirm_bulk_delete'  => __('確定要批次刪除所選資料嗎？此操作無法復原。', 'ur-ai-assistant'),
                    'confirm_convert_faq'  => __('確定要轉成 FAQ 草稿嗎？轉換後仍需人工檢查後再啟用。', 'ur-ai-assistant'),
                    'confirm_import'       => __('確定要匯入所選資料嗎？匯入後預設停用，請檢查後再啟用。', 'ur-ai-assistant'),
                    'select_items'         => __('請先選擇要操作的項目。', 'ur-ai-assistant'),
                    'copy_success'         => __('已複製。', 'ur-ai-assistant'),
                    'copy_failed'          => __('複製失敗，請手動選取文字。', 'ur-ai-assistant'),
                    'processing'           => __('處理中...', 'ur-ai-assistant'),
                    /* translators: 1: 本頁筆數 2: 符合條件的全部筆數 */
                    'select_all_prompt'    => __('已選取本頁 %1$s 筆，是否改為選取符合目前篩選條件的全部 %2$s 筆？', 'ur-ai-assistant'),
                    /* translators: %1$s: 符合條件的全部筆數 */
                    'select_all_confirmed' => __('已選取全部 %1$s 筆，套用批次操作時會套用到全部符合條件的資料。', 'ur-ai-assistant'),
                    'select_all_confirm_button' => __('選取全部', 'ur-ai-assistant'),
                    'select_all_cancel_button'  => __('僅本頁', 'ur-ai-assistant'),
                    'confirm_apply_industry'    => __('確定要套用所選產業別的預設文案嗎？這會覆蓋下方系統提示詞／前台標題／副標題目前填寫的內容（尚未按「儲存設定」前都可以再修改）。', 'ur-ai-assistant'),
                    'confirm_generate_article'  => __('確定要請 AI 依這則 FAQ 產生一篇文章草稿嗎？會呼叫 AI API（產生費用需自行負擔），文章將以草稿狀態建立，不會自動發布。', 'ur-ai-assistant'),
                    'generating_article'        => __('產生中…', 'ur-ai-assistant'),
                    'article_generated'         => __('已產生文章草稿，將為您開啟編輯畫面，請核對內容後再發布。', 'ur-ai-assistant'),
                    'generate_article_error'    => __('產生文章草稿失敗，請稍後再試。', 'ur-ai-assistant'),
                ),
            )
        );
    }

    /**
     * 取得各產業別的 AI 助理預設文案，供設定頁「套用此產業別的預設文案」
     * 按鈕使用（純前端預覽填入，不會自動儲存）。
     *
     * @return array
     */
    private function get_industry_profiles_for_js() {
        if (!class_exists('UR_AI_Industry_Profiles')) {
            return array();
        }

        $map = array();

        foreach (UR_AI_Industry_Profiles::get_all() as $key => $label) {
            $profile = UR_AI_Industry_Profiles::get($key);

            if (is_array($profile) && !empty($profile['assistant'])) {
                $map[$key] = $profile['assistant'];
            }
        }

        return $map;
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