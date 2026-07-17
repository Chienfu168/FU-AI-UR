<?php
/**
 * UR AI Assistant Answer Service
 *
 * AI 問答核心服務層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Answer_Service
 */
class UR_AI_Answer_Service {

    /**
     * FAQ Matcher.
     *
     * @var UR_AI_FAQ_Matcher|null
     */
    private $faq_matcher = null;

    /**
     * API Client.
     *
     * @var UR_AI_OpenAI_Client|null
     */
    private $api_client = null;

    /**
     * Log Service.
     *
     * @var UR_AI_Log_Service|null
     */
    private $log_service = null;

    /**
     * Related Page Service.
     *
     * @var UR_AI_Related_Page_Service|null
     */
    private $related_service = null;

    /**
     * FAQ Service，用於在 FAQ 命中回答下方推薦其他相關 FAQ。
     *
     * @var UR_AI_FAQ_Service|null
     */
    private $faq_service = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->faq_matcher = class_exists('UR_AI_FAQ_Matcher')
            ? new UR_AI_FAQ_Matcher()
            : null;

        $this->api_client = class_exists('UR_AI_OpenAI_Client')
            ? new UR_AI_OpenAI_Client()
            : null;

        $this->log_service = class_exists('UR_AI_Log_Service')
            ? new UR_AI_Log_Service()
            : null;

        $this->related_service = class_exists('UR_AI_Related_Page_Service')
            ? new UR_AI_Related_Page_Service()
            : null;

        $this->faq_service = class_exists('UR_AI_FAQ_Service')
            ? new UR_AI_FAQ_Service()
            : null;
    }

    /**
     * 回答問題。
     *
     * @param string $question 使用者問題。
     * @return array
     */
    public function answer($question) {
        $question = $this->sanitize_question($question);

        if ('' === trim($question)) {
            return $this->error_result(
                __('請先輸入想詢問的問題。', 'ur-ai-assistant'),
                'empty_question',
                400
            );
        }

        $related_pages = $this->find_related_pages($question);

        if ($this->is_faq_enabled()) {
            $faq_result = $this->try_faq_answer($question, $related_pages);

            if (!empty($faq_result['success'])) {
                return $faq_result;
            }
        }

        return $this->try_ai_answer($question, $related_pages);
    }

    /**
     * 嘗試使用 FAQ 回答。
     *
     * @param string $question 使用者問題。
     * @param array  $related_pages 相關頁面。
     * @return array
     */
    private function try_faq_answer($question, $related_pages = array()) {
        if (!$this->faq_matcher instanceof UR_AI_FAQ_Matcher) {
            return array(
                'success' => false,
            );
        }

        $match = $this->faq_matcher->match($question);

        if (empty($match) || empty($match['faq'])) {
            return array(
                'success' => false,
            );
        }

        $faq   = $match['faq'];
        $score = isset($match['score']) ? absint($match['score']) : 0;

        $answer = (string) $this->get_value($faq, 'answer', '');

        if ('' === trim($answer)) {
            return array(
                'success' => false,
            );
        }

        $faq_id = absint($this->get_value($faq, 'id', 0));

        if ($faq_id > 0 && method_exists($this->faq_matcher, 'increase_hit_count')) {
            $this->faq_matcher->increase_hit_count($faq_id);
        }

        $related_page_ids = $this->extract_related_page_ids($related_pages);
        $this->increase_related_show_counts($related_page_ids);

        $log_id = $this->create_log(
            array(
                'question'             => $question,
                'answer'               => $answer,
                'answer_source'        => 'faq',
                'model'                => '',
                'tokens_used'          => 0,
                'faq_id'               => $faq_id,
                'faq_match_score'      => $score,
                'faq_matched_keywords' => isset($match['matched_keywords']) ? $this->keywords_to_string($match['matched_keywords']) : '',
                'has_related_pages'    => !empty($related_page_ids) ? 1 : 0,
                'related_page_ids'     => implode(',', $related_page_ids),
                'status'               => 'success',
            )
        );

        return array(
            'success'             => true,
            'answer'              => $answer,
            'answer_html'         => $this->format_answer_html($answer),
            'answer_source'       => 'faq',
            'answer_source_label' => $this->answer_source_label('faq'),
            'faq_id'              => $faq_id,
            'faq_match_score'     => $score,
            'related_pages'       => $related_pages,
            'related_faqs'        => $this->find_related_faqs($faq_id, (string) $this->get_value($faq, 'category', '')),
            'log_id'              => $log_id,
            'message'             => '',
            'status_code'         => 200,
        );
    }

    /**
     * 依目前命中的 FAQ 分類，推薦其他相關 FAQ（供回答下方「你也許
     * 還想知道」使用），依命中次數由高到低排序。
     *
     * @param int    $faq_id 目前命中的 FAQ ID（會被排除）。
     * @param string $category 分類名稱。
     * @return array
     */
    private function find_related_faqs($faq_id, $category) {
        if (!$this->faq_service instanceof UR_AI_FAQ_Service) {
            return array();
        }

        $rows = $this->faq_service->find_related($faq_id, $category, 3);

        $related_faqs = array();

        foreach ($rows as $row) {
            $related_faqs[] = array(
                'id'       => absint($this->get_value($row, 'id', 0)),
                'question' => (string) $this->get_value($row, 'question', ''),
                'category' => (string) $this->get_value($row, 'category', ''),
            );
        }

        return $related_faqs;
    }

    /**
     * 嘗試使用 AI 回答。
     *
     * @param string $question 使用者問題。
     * @param array  $related_pages 相關頁面。
     * @return array
     */
    private function try_ai_answer($question, $related_pages = array()) {
        if (!$this->api_client instanceof UR_AI_OpenAI_Client) {
            $message = __('AI API 服務尚未正確載入。', 'ur-ai-assistant');

            $log_id = $this->create_error_log($question, $message, 'api_client_missing', $related_pages);

            return $this->error_result($message, 'api_client_missing', 500, $log_id);
        }

        if (!$this->has_api_key()) {
            $message = __('尚未設定 OpenAI API Key，且 FAQ 未命中，無法產生 AI 回答。', 'ur-ai-assistant');

            $log_id = $this->create_error_log($question, $message, 'api_key_missing', $related_pages);

            return $this->error_result($message, 'api_key_missing', 500, $log_id);
        }

        $api_result = $this->api_client->chat($question);

        if (!is_array($api_result) || empty($api_result['success'])) {
            $message = isset($api_result['message'])
                ? (string) $api_result['message']
                : __('AI 回答產生失敗，請稍後再試。', 'ur-ai-assistant');

            $error_code = isset($api_result['error_code'])
                ? sanitize_key($api_result['error_code'])
                : 'api_failed';

            $log_id = $this->create_error_log($question, $message, $error_code, $related_pages);

            return $this->error_result($message, $error_code, 500, $log_id);
        }

        $answer = isset($api_result['answer']) ? (string) $api_result['answer'] : '';

        if ('' === trim($answer)) {
            $message = __('AI 回答內容為空，請稍後再試。', 'ur-ai-assistant');

            $log_id = $this->create_error_log($question, $message, 'empty_ai_answer', $related_pages);

            return $this->error_result($message, 'empty_ai_answer', 500, $log_id);
        }

        $related_page_ids = $this->extract_related_page_ids($related_pages);
        $this->increase_related_show_counts($related_page_ids);

        $log_id = $this->create_log(
            array(
                'question'             => $question,
                'answer'               => $answer,
                'answer_source'        => 'ai',
                'model'                => isset($api_result['model']) ? sanitize_text_field($api_result['model']) : $this->get_model(),
                'tokens_used'          => isset($api_result['tokens_used']) ? absint($api_result['tokens_used']) : 0,
                'faq_id'               => 0,
                'faq_match_score'      => 0,
                'faq_matched_keywords' => '',
                'has_related_pages'    => !empty($related_page_ids) ? 1 : 0,
                'related_page_ids'     => implode(',', $related_page_ids),
                'status'               => 'success',
            )
        );

        return array(
            'success'             => true,
            'answer'              => $answer,
            'answer_html'         => $this->format_answer_html($answer),
            'answer_source'       => 'ai',
            'answer_source_label' => $this->answer_source_label('ai'),
            'faq_id'              => 0,
            'faq_match_score'     => 0,
            'related_pages'       => $related_pages,
            'log_id'              => $log_id,
            'message'             => '',
            'status_code'         => 200,
        );
    }

    /**
     * 取得相關頁面。
     *
     * @param string $question 使用者問題。
     * @return array
     */
    private function find_related_pages($question) {
        if (!$this->is_related_enabled()) {
            return array();
        }

        if (!$this->related_service instanceof UR_AI_Related_Page_Service) {
            return array();
        }

        return $this->related_service->find_related_pages($question, 3);
    }

    /**
     * 增加推薦頁面曝光次數。
     *
     * @param array $ids 推薦頁面 ID 陣列。
     * @return void
     */
    private function increase_related_show_counts($ids) {
        if (empty($ids) || !$this->related_service instanceof UR_AI_Related_Page_Service) {
            return;
        }

        $this->related_service->increase_show_counts($ids);
    }

    /**
     * 建立問答紀錄。
     *
     * @param array $data 紀錄資料。
     * @return int
     */
    private function create_log($data) {
        if (!$this->is_logging_enabled()) {
            return 0;
        }

        if (!$this->log_service instanceof UR_AI_Log_Service) {
            return 0;
        }

        $defaults = array(
            'user_id'              => get_current_user_id(),
            'ip_address'           => $this->get_user_ip(),
            'user_agent'           => isset($_SERVER['HTTP_USER_AGENT'])
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
                : '',
            'question'             => '',
            'answer'               => '',
            'answer_source'        => '',
            'model'                => '',
            'tokens_used'          => 0,
            'faq_id'               => 0,
            'faq_match_score'      => 0,
            'faq_matched_keywords' => '',
            'has_related_pages'    => 0,
            'related_page_ids'     => '',
            'feedback'             => '',
            'feedback_reason'      => '',
            'feedback_comment'     => '',
            'status'               => 'success',
            'error_code'           => '',
            'error_message'        => '',
        );

        return $this->log_service->create(wp_parse_args($data, $defaults));
    }

    /**
     * 建立錯誤紀錄。
     *
     * @param string $question 問題。
     * @param string $message 錯誤訊息。
     * @param string $code 錯誤代碼。
     * @param array  $related_pages 相關頁面。
     * @return int
     */
    private function create_error_log($question, $message, $code, $related_pages = array()) {
        $related_page_ids = $this->extract_related_page_ids($related_pages);

        return $this->create_log(
            array(
                'question'          => $question,
                'answer'            => '',
                'answer_source'     => 'error',
                'model'             => $this->get_model(),
                'tokens_used'       => 0,
                'has_related_pages' => !empty($related_page_ids) ? 1 : 0,
                'related_page_ids'  => implode(',', $related_page_ids),
                'status'            => 'error',
                'error_code'        => sanitize_key($code),
                'error_message'     => sanitize_textarea_field($message),
            )
        );
    }

    /**
     * 錯誤結果。
     *
     * @param string $message 錯誤訊息。
     * @param string $code 錯誤代碼。
     * @param int    $status_code HTTP 狀態碼。
     * @param int    $log_id 紀錄 ID。
     * @return array
     */
    private function error_result($message, $code = 'error', $status_code = 500, $log_id = 0) {
        return array(
            'success'       => false,
            'answer'        => '',
            'answer_source' => 'error',
            'message'       => $message,
            'error_code'    => sanitize_key($code),
            'status_code'   => absint($status_code),
            'log_id'        => absint($log_id),
        );
    }

    /**
     * 清理問題。
     *
     * @param mixed $question 原始問題。
     * @return string
     */
    private function sanitize_question($question) {
        if (class_exists('UR_AI_Security')) {
            return UR_AI_Security::sanitize_question($question);
        }

        $question = is_scalar($question) ? (string) $question : '';
        $question = wp_strip_all_tags($question);
        $question = sanitize_textarea_field($question);
        $question = preg_replace('/\s+/u', ' ', $question);

        return trim($question);
    }

    /**
     * 回答 HTML。
     *
     * @param string $answer 回答。
     * @return string
     */
    private function format_answer_html($answer) {
        if (class_exists('UR_AI_Formatter')) {
            return UR_AI_Formatter::answer_html($answer);
        }

        return wp_kses_post(wpautop($answer));
    }

    /**
     * 回答來源標籤。
     *
     * @param string $source 來源。
     * @return string
     */
    private function answer_source_label($source) {
        if (class_exists('UR_AI_Helper')) {
            return UR_AI_Helper::answer_source_label($source);
        }

        $labels = array(
            'faq'   => __('FAQ 知識庫回答', 'ur-ai-assistant'),
            'ai'    => __('AI 回答', 'ur-ai-assistant'),
            'error' => __('錯誤', 'ur-ai-assistant'),
        );

        return isset($labels[$source]) ? $labels[$source] : $source;
    }

    /**
     * 是否啟用 FAQ。
     *
     * @return bool
     */
    private function is_faq_enabled() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::is_faq_enabled();
        }

        return true;
    }

    /**
     * 是否啟用相關頁面推薦。
     *
     * @return bool
     */
    private function is_related_enabled() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::is_related_enabled();
        }

        return true;
    }

    /**
     * 是否啟用問答紀錄。
     *
     * @return bool
     */
    private function is_logging_enabled() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::is_logging_enabled();
        }

        return true;
    }

    /**
     * 是否已設定 API Key。
     *
     * @return bool
     */
    private function has_api_key() {
        if (!class_exists('UR_AI_Settings')) {
            return false;
        }

        return '' !== trim((string) UR_AI_Settings::get_api_key());
    }

    /**
     * 取得模型。
     *
     * @return string
     */
    private function get_model() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::get_model();
        }

        return '';
    }

    /**
     * 取得使用者 IP。
     *
     * @return string
     */
    private function get_user_ip() {
        if (class_exists('UR_AI_Security')) {
            return UR_AI_Security::get_user_ip();
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return '';
    }

    /**
     * 抽出推薦頁面 ID。
     *
     * @param array $related_pages 推薦頁面。
     * @return array
     */
    private function extract_related_page_ids($related_pages) {
        if (!is_array($related_pages)) {
            return array();
        }

        $ids = array();

        foreach ($related_pages as $page) {
            $id = absint($this->get_value($page, 'id', 0));

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 關鍵字陣列轉字串。
     *
     * @param mixed $keywords 關鍵字。
     * @return string
     */
    private function keywords_to_string($keywords) {
        if (is_string($keywords)) {
            return sanitize_text_field($keywords);
        }

        if (!is_array($keywords)) {
            return '';
        }

        $items = array();

        foreach ($keywords as $keyword) {
            $keyword = sanitize_text_field((string) $keyword);

            if ('' !== trim($keyword)) {
                $items[] = $keyword;
            }
        }

        return implode(', ', array_values(array_unique($items)));
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