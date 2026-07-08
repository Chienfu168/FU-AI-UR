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
        $category    = (string) $this->get_value($popular, 'category', '');

        if ('' === trim($category)) {
            $category = $this->suggest_category($question, $description);
        }

        $keywords = $this->suggest_keywords($question, $description);

        $answer = $this->build_placeholder_answer($question, $description);

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
                'admin_note'    => sprintf(
                    /* translators: %d: popular question id */
                    __('由熱門問題 #%d 轉入 FAQ 草稿。請補上完整回答並人工審核後再啟用。', 'ur-ai-assistant'),
                    $popular_question_id
                ),
            )
        );

        if ($faq_id > 0 && method_exists($popular_service, 'update_faq_id')) {
            $popular_service->update_faq_id($popular_question_id, $faq_id);
        }

        return absint($faq_id);
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