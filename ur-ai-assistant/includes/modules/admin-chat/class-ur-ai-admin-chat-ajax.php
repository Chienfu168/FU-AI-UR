<?php
/**
 * UR AI Assistant Admin Chat Ajax
 *
 * 後台「AI 對話」AJAX 處理器：傳送訊息、整理總結草稿、儲存草稿為 FAQ。
 *
 * 僅限已登入且具備權限的後台使用者呼叫（不註冊 nopriv 版本），與前台
 * 訪客提問的 AJAX 完全分開，各自的每日用量／費率控管、nonce 也不共用。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Admin_Chat_Ajax
 */
class UR_AI_Admin_Chat_Ajax {

    /**
     * 傳送訊息 action。
     *
     * @var string
     */
    const ACTION_SEND = 'ur_ai_admin_chat_send';

    /**
     * 整理總結草稿 action。
     *
     * @var string
     */
    const ACTION_SUMMARIZE = 'ur_ai_admin_chat_summarize';

    /**
     * 儲存草稿為 FAQ action。
     *
     * @var string
     */
    const ACTION_SAVE_DRAFT = 'ur_ai_admin_chat_save_draft';

    /**
     * OpenAI Client。
     *
     * @var UR_AI_OpenAI_Client|null
     */
    private $openai_client;

    /**
     * FAQ 分類／關鍵字建議工具。
     *
     * @var UR_AI_FAQ_Category_Helper|null
     */
    private $category_helper;

    /**
     * FAQ 草稿建立服務。
     *
     * @var UR_AI_FAQ_Draft_Service|null
     */
    private $faq_draft_service;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->openai_client     = class_exists('UR_AI_OpenAI_Client') ? new UR_AI_OpenAI_Client() : null;
        $this->category_helper   = class_exists('UR_AI_FAQ_Category_Helper') ? new UR_AI_FAQ_Category_Helper() : null;
        $this->faq_draft_service = class_exists('UR_AI_FAQ_Draft_Service') ? new UR_AI_FAQ_Draft_Service() : null;
    }

    /**
     * 註冊 AJAX 掛鉤。
     *
     * @return void
     */
    public function register() {
        add_action('wp_ajax_' . self::ACTION_SEND, array($this, 'handle_send'));
        add_action('wp_ajax_' . self::ACTION_SUMMARIZE, array($this, 'handle_summarize'));
        add_action('wp_ajax_' . self::ACTION_SAVE_DRAFT, array($this, 'handle_save_draft'));
    }

    /**
     * 處理傳送訊息：把目前累積的對話紀錄送給 OpenAI，取得下一則 AI 回覆。
     *
     * @return void
     */
    public function handle_send() {
        $this->require_access();

        if (!$this->openai_client instanceof UR_AI_OpenAI_Client) {
            wp_send_json_error(array('message' => __('AI 服務尚未正確載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')));
        }

        $messages = $this->decode_messages();
        $result   = $this->openai_client->chat_conversation($messages);

        if (empty($result['success'])) {
            wp_send_json_error(
                array('message' => !empty($result['message']) ? $result['message'] : __('AI 回覆失敗，請稍後再試。', 'ur-ai-assistant'))
            );
        }

        wp_send_json_success(array('answer' => $result['answer']));
    }

    /**
     * 處理「產生總結草稿」：請 AI 讀取整段對話，整理成 FAQ 草稿建議
     * （含分類／關鍵字建議），交由前端顯示供管理者逐則確認是否儲存。
     *
     * @return void
     */
    public function handle_summarize() {
        $this->require_access();

        if (!$this->openai_client instanceof UR_AI_OpenAI_Client) {
            wp_send_json_error(array('message' => __('AI 服務尚未正確載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')));
        }

        $messages = $this->decode_messages();
        $result   = $this->openai_client->summarize_conversation_to_faq_drafts($messages);

        if (empty($result['success'])) {
            wp_send_json_error(
                array('message' => !empty($result['message']) ? $result['message'] : __('整理草稿失敗，請稍後再試。', 'ur-ai-assistant'))
            );
        }

        $drafts = array();

        foreach ($result['drafts'] as $draft) {
            $question = isset($draft['question']) ? (string) $draft['question'] : '';
            $answer   = isset($draft['answer']) ? (string) $draft['answer'] : '';

            $drafts[] = array(
                'question' => $question,
                'answer'   => $answer,
                'category' => $this->suggest_category($question, $answer),
                'keywords' => $this->suggest_keywords($question, $answer),
            );
        }

        if (empty($drafts)) {
            wp_send_json_error(
                array('message' => __('這段對話內容還不足以整理出明確的 FAQ 草稿，請再多討論一些細節後再試一次。', 'ur-ai-assistant'))
            );
        }

        wp_send_json_success(array('drafts' => $drafts));
    }

    /**
     * 處理「加入知識庫（存成草稿）」：把管理者確認（可能已編輯）後的
     * 單則問答，建立為 FAQ 草稿（停用／待審核，需人工審核後再啟用）。
     *
     * @return void
     */
    public function handle_save_draft() {
        $this->require_access();

        if (!$this->faq_draft_service instanceof UR_AI_FAQ_Draft_Service) {
            wp_send_json_error(array('message' => __('FAQ 服務尚未正確載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')));
        }

        $question = isset($_POST['question']) ? sanitize_textarea_field(wp_unslash($_POST['question'])) : '';
        $answer   = isset($_POST['answer']) ? sanitize_textarea_field(wp_unslash($_POST['answer'])) : '';
        $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
        $keywords = isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '';

        $faq_id = $this->faq_draft_service->create_from_admin_chat($question, $answer, $category, $keywords);

        if ($faq_id <= 0) {
            wp_send_json_error(array('message' => __('儲存 FAQ 草稿失敗，請確認問題與回答內容不可空白。', 'ur-ai-assistant')));
        }

        wp_send_json_success(
            array(
                'faq_id'  => $faq_id,
                'message' => __('已儲存為 FAQ 草稿，請至「FAQ 知識庫」頁審核後再啟用。', 'ur-ai-assistant'),
            )
        );
    }

    /**
     * 建議分類（無法載入分類工具時退回「待分類」，不中斷整個流程）。
     *
     * @param string $question 問題。
     * @param string $answer 回答。
     * @return string
     */
    private function suggest_category($question, $answer) {
        if ($this->category_helper instanceof UR_AI_FAQ_Category_Helper) {
            return $this->category_helper->suggest_category($question, $answer);
        }

        return '待分類';
    }

    /**
     * 建議關鍵字（無法載入分類工具時回傳空字串，不中斷整個流程）。
     *
     * @param string $question 問題。
     * @param string $answer 回答。
     * @return string
     */
    private function suggest_keywords($question, $answer) {
        if ($this->category_helper instanceof UR_AI_FAQ_Category_Helper) {
            return $this->category_helper->suggest_keywords($question, $answer);
        }

        return '';
    }

    /**
     * 解析前端送來的對話紀錄（JSON 字串，格式為
     * [{"role":"user"|"assistant","content":"..."}]）。
     *
     * @return array
     */
    private function decode_messages() {
        $raw     = isset($_POST['messages']) ? wp_unslash($_POST['messages']) : '[]';
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * 權限與 nonce 驗證，失敗則直接回傳 JSON 錯誤並中止。
     *
     * @return void
     */
    private function require_access() {
        if (class_exists('UR_AI_Permissions')) {
            UR_AI_Permissions::ajax_require('admin_chat');
        } elseif (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('您沒有權限執行此操作。', 'ur-ai-assistant')), 403);
        }

        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::ajax_verify_admin_nonce_or_die();
        }
    }
}
