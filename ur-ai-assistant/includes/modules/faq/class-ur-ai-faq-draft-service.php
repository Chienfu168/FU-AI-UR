<?php
/**
 * UR AI Assistant FAQ Draft Service
 *
 * FAQ 草稿建立服務。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_Draft_Service
 */
class UR_AI_FAQ_Draft_Service {

    /**
     * FAQ Service.
     *
     * @var UR_AI_FAQ_Service|null
     */
    private $faq_service = null;

    /**
     * Log Service.
     *
     * @var UR_AI_Log_Service|null
     */
    private $log_service = null;

    /**
     * Category Helper.
     *
     * @var UR_AI_FAQ_Category_Helper|null
     */
    private $category_helper = null;

    /**
     * OpenAI Client（用於熱門問題轉 FAQ 草稿時，嘗試先產生 AI 草擬回答）。
     *
     * @var UR_AI_OpenAI_Client|null
     */
    private $openai_client = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->faq_service = class_exists('UR_AI_FAQ_Service')
            ? new UR_AI_FAQ_Service()
            : null;

        $this->log_service = class_exists('UR_AI_Log_Service')
            ? new UR_AI_Log_Service()
            : null;

        $this->category_helper = class_exists('UR_AI_FAQ_Category_Helper')
            ? new UR_AI_FAQ_Category_Helper()
            : null;

        $this->openai_client = class_exists('UR_AI_OpenAI_Client')
            ? new UR_AI_OpenAI_Client()
            : null;
    }

    /**
     * 從問答紀錄建立 FAQ 草稿。
     *
     * @param int $log_id 問答紀錄 ID。
     * @return int FAQ ID。
     */
    public function create_from_log($log_id) {
        $log_id = absint($log_id);

        if ($log_id <= 0) {
            return 0;
        }

        if (!$this->faq_service instanceof UR_AI_FAQ_Service || !$this->log_service instanceof UR_AI_Log_Service) {
            return 0;
        }

        $existing = $this->faq_service->find_by_source_log_id($log_id);

        if ($existing) {
            return absint($this->get_value($existing, 'id', 0));
        }

        $log = $this->log_service->find($log_id);

        if (!$log) {
            return 0;
        }

        $question = (string) $this->get_value($log, 'question', '');
        $answer   = (string) $this->get_value($log, 'answer', '');

        if ('' === trim($question) || '' === trim($answer)) {
            return 0;
        }

        $answer_source = sanitize_key((string) $this->get_value($log, 'answer_source', ''));

        if ('ai' !== $answer_source && 'faq' !== $answer_source) {
            return 0;
        }

        $category = $this->suggest_category($question, $answer);
        $keywords = $this->suggest_keywords($question, $answer);

        $faq_id = $this->faq_service->create(
            array(
                'category'      => $category,
                'question'      => $question,
                'answer'        => $answer,
                'keywords'      => $keywords,
                'status'        => 'inactive',
                'source'        => 'ai_log',
                'source_log_id' => $log_id,
                'review_status' => 'draft',
                'sort_order'    => 100,
                'admin_note'    => sprintf(
                    /* translators: %d: log id */
                    __('由問答紀錄 #%d 轉入。請人工檢查內容是否適合長期固定回答後，再改為啟用。', 'ur-ai-assistant'),
                    $log_id
                ),
            )
        );

        if ($faq_id > 0 && method_exists($this->log_service, 'mark_converted_to_faq')) {
            $this->log_service->mark_converted_to_faq($log_id, $faq_id);
        }

        return absint($faq_id);
    }

    /**
     * 從熱門問題建立 FAQ 草稿。
     *
     * @param int $popular_question_id 熱門問題 ID。
     * @return int FAQ ID。
     */
    public function create_from_popular_question($popular_question_id) {
        $popular_question_id = absint($popular_question_id);

        if ($popular_question_id <= 0) {
            return 0;
        }

        if (!$this->faq_service instanceof UR_AI_FAQ_Service) {
            return 0;
        }

        if (!class_exists('UR_AI_Popular_Question_Service')) {
            return 0;
        }

        $popular_service = new UR_AI_Popular_Question_Service();
        $popular         = $popular_service->find($popular_question_id);

        if (!$popular) {
            return 0;
        }

        $existing_faq_id = absint($this->get_value($popular, 'faq_id', 0));

        if ($existing_faq_id > 0) {
            $existing = $this->faq_service->find($existing_faq_id);

            if ($existing) {
                return $existing_faq_id;
            }
        }

        $question = (string) $this->get_value($popular, 'submit_question', '');

        if ('' === trim($question)) {
            $question = (string) $this->get_value($popular, 'question', '');
        }

        if ('' === trim($question)) {
            return 0;
        }

        $description = (string) $this->get_value($popular, 'description', '');

        $ai_draft = $this->draft_answer_via_ai($question);
        $answer   = null !== $ai_draft ? $ai_draft : $this->build_placeholder_answer($question, $description);

        // 分類／關鍵字建議：AI 已產生草擬回答時，用回答內容判斷會比只有
        // 熱門問題的簡短說明（可能為空）準確，優先採用。
        $category_source = null !== $ai_draft ? $ai_draft : $description;

        $category = (string) $this->get_value($popular, 'category', '');

        if ('' === trim($category)) {
            $category = $this->suggest_category($question, $category_source);
        }

        $keywords = $this->suggest_keywords($question, $category_source);

        $admin_note = null !== $ai_draft
            ? sprintf(
                /* translators: %d: popular question id */
                __('由熱門問題 #%d 轉入 FAQ 草稿，回答內容為 AI 草擬。上線前請務必核對事實正確性（AI 可能產生看似合理但不準確的內容），確認無誤後再啟用。', 'ur-ai-assistant'),
                $popular_question_id
            )
            : sprintf(
                /* translators: %d: popular question id */
                __('由熱門問題 #%d 轉入 FAQ 草稿。請補上完整回答並人工審核後再啟用。', 'ur-ai-assistant'),
                $popular_question_id
            );

        $faq_id = $this->faq_service->create(
            array(
                'category'      => $category,
                'question'      => $question,
                'answer'        => $answer,
                'keywords'      => $keywords,
                'status'        => 'inactive',
                'source'        => 'manual',
                'source_log_id' => 0,
                'review_status' => 'draft',
                'sort_order'    => 100,
                'admin_note'    => $admin_note,
            )
        );

        if ($faq_id > 0 && method_exists($popular_service, 'update_faq_id')) {
            $popular_service->update_faq_id($popular_question_id, $faq_id);
        }

        return absint($faq_id);
    }

    /**
     * 從後台「AI 對話」功能整理出的草稿建立 FAQ 草稿。
     *
     * 與 create_from_log()／create_from_popular_question() 不同：這裡的
     * 問題與回答已經是管理者在後台「AI 對話」頁確認（可能已手動編輯）
     * 過的內容，不是直接沿用某一筆既有紀錄，因此沒有「是否已存在對應
     * FAQ」的重複檢查。
     *
     * @param string $question 標準問題。
     * @param string $answer 固定回答。
     * @param string $category 分類（留空則自動建議）。
     * @param string $keywords 關鍵字（留空則自動建議）。
     * @return int FAQ ID，建立失敗時為 0。
     */
    public function create_from_admin_chat($question, $answer, $category = '', $keywords = '') {
        if (!$this->faq_service instanceof UR_AI_FAQ_Service) {
            return 0;
        }

        $question = is_scalar($question) ? trim((string) $question) : '';
        $answer   = is_scalar($answer) ? trim((string) $answer) : '';

        if ('' === $question || '' === $answer) {
            return 0;
        }

        $category = is_scalar($category) ? trim((string) $category) : '';

        if ('' === $category) {
            $category = $this->suggest_category($question, $answer);
        }

        $keywords = is_scalar($keywords) ? trim((string) $keywords) : '';

        if ('' === $keywords) {
            $keywords = $this->suggest_keywords($question, $answer);
        }

        $faq_id = $this->faq_service->create(
            array(
                'category'      => $category,
                'question'      => $question,
                'answer'        => $answer,
                'keywords'      => $keywords,
                'status'        => 'inactive',
                'source'        => 'ai_chat',
                'source_log_id' => 0,
                'review_status' => 'draft',
                'sort_order'    => 100,
                'admin_note'    => __('由後台「AI 對話」功能整理產生。上線前請務必核對事實正確性（AI 可能產生看似合理但不準確的內容），確認無誤後再啟用。', 'ur-ai-assistant'),
            )
        );

        return absint($faq_id);
    }

    /**
     * 嘗試呼叫 OpenAI 產生草擬回答。
     *
     * 沿用既有的 UR_AI_OpenAI_Client::chat()——跟前台 FAQ 未命中時的補位
     * 回答完全相同的 system prompt（目前啟用中產業別的人設）與費率／字數
     * 控管設定，不另外寫一套 prompt。失敗（未設定 API Key、API 呼叫錯誤、
     * 回傳格式錯誤等）時一律回傳 null，由呼叫端退回原本的純文字佔位草稿，
     * 確保沒有設定 AI 功能的站台行為與這個功能上線前完全一致。
     *
     * @param string $question 問題文字。
     * @return string|null 成功時為 AI 產生的回答文字；失敗時為 null。
     */
    private function draft_answer_via_ai($question) {
        if (!$this->openai_client instanceof UR_AI_OpenAI_Client) {
            return null;
        }

        $result = $this->openai_client->chat($question);

        if (empty($result['success']) || '' === trim((string) ($result['answer'] ?? ''))) {
            return null;
        }

        return sanitize_textarea_field((string) $result['answer']);
    }

    /**
     * 建立熱門問題轉 FAQ 的預設回答。
     *
     * @param string $question 問題。
     * @param string $description 簡述。
     * @return string
     */
    private function build_placeholder_answer($question, $description = '') {
        $answer = __('這是一筆由熱門問題轉入的 FAQ 草稿，請管理者補上完整回答。', 'ur-ai-assistant');

        if ('' !== trim($description)) {
            $answer .= "\n\n" . __('原熱門問題說明：', 'ur-ai-assistant') . "\n" . $description;
        }

        $answer .= "\n\n" . __('建議回答方向：請以客觀、中立、白話方式說明，並避免替個案作法律、估價、建築、稅務或權利分配判斷。', 'ur-ai-assistant');

        return $answer;
    }

    /**
     * 建議分類。
     *
     * @param string $question 問題。
     * @param string $answer 回答。
     * @return string
     */
    private function suggest_category($question, $answer = '') {
        if ($this->category_helper instanceof UR_AI_FAQ_Category_Helper) {
            return $this->category_helper->suggest_category($question, $answer);
        }

        return '待分類';
    }

    /**
     * 建議關鍵字。
     *
     * @param string $question 問題。
     * @param string $answer 回答。
     * @return string
     */
    private function suggest_keywords($question, $answer = '') {
        if ($this->category_helper instanceof UR_AI_FAQ_Category_Helper) {
            return $this->category_helper->suggest_keywords($question, $answer);
        }

        $text = $question . ' ' . $answer;

        $candidates = array(
            '都市更新',
            '危老重建',
            '更新會',
            '自主更新',
            '權利變換',
            '協議合建',
            '共同負擔',
            '估價',
            '分配',
            '同意書',
            '信託',
            '實施者',
            '地主',
            '所有權人',
        );

        $matched = array();

        foreach ($candidates as $candidate) {
            if (false !== mb_strpos($text, $candidate, 0, 'UTF-8')) {
                $matched[] = $candidate;
            }
        }

        return implode(', ', array_values(array_unique($matched)));
    }

    /**
     * 安全取得資料值。
     *
     * @param mixed  $data 資料。
     * @param string $key 鍵名。
     * @param mixed  $default 預設值。
     * @return mixed
     */
    private function get_value($data, $key, $default = null) {
        if (class_exists('UR_AI_Helper')) {
            return UR_AI_Helper::data_get($data, $key, $default);
        }

        if (is_array($data) && array_key_exists($key, $data)) {
            return $data[$key];
        }

        if (is_object($data) && isset($data->{$key})) {
            return $data->{$key};
        }

        return $default;
    }
}