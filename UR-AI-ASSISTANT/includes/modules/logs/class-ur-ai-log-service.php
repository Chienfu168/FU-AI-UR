<?php
/**
 * UR AI Assistant Log Service
 *
 * 問答紀錄服務層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Log_Service
 */
class UR_AI_Log_Service {

    /**
     * Log Repository.
     *
     * @var UR_AI_Log_Repository|null
     */
    private $repository = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->repository = class_exists('UR_AI_Log_Repository')
            ? new UR_AI_Log_Repository()
            : null;
    }

    /**
     * 建立問答紀錄。
     *
     * @param array $data 紀錄資料。
     * @return int
     */
    public function create($data) {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return 0;
        }

        return $this->repository->create($data);
    }

    /**
     * 查詢單筆問答紀錄。
     *
     * @param int $id 紀錄 ID。
     * @return object|null
     */
    public function find($id) {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return null;
        }

        return $this->repository->find($id);
    }

    /**
     * 查詢問答紀錄列表。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query($args = array()) {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return array();
        }

        return $this->repository->query($args);
    }

    /**
     * 計算問答紀錄數量。
     *
     * @param array $args 查詢參數。
     * @return int
     */
    public function count($args = array()) {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return 0;
        }

        return $this->repository->count($args);
    }

    /**
     * 刪除問答紀錄。
     *
     * @param int $id 紀錄 ID。
     * @return bool
     */
    public function delete($id) {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return false;
        }

        return $this->repository->delete($id);
    }

    /**
     * 批次刪除問答紀錄。
     *
     * @param array $ids 紀錄 ID 陣列。
     * @return int
     */
    public function bulk_delete($ids) {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return 0;
        }

        return $this->repository->bulk_delete($ids);
    }

    /**
     * 更新使用者回饋。
     *
     * @param int    $id 紀錄 ID。
     * @param string $feedback 回饋 helpful / not_helpful。
     * @param string $reason 原因。
     * @param string $comment 補充說明。
     * @return bool
     */
    public function update_feedback($id, $feedback, $reason = '', $comment = '') {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return false;
        }

        return $this->repository->update_feedback($id, $feedback, $reason, $comment);
    }

    /**
     * 標記已轉 FAQ。
     *
     * @param int $id 紀錄 ID。
     * @param int $faq_id FAQ ID。
     * @return bool
     */
    public function mark_converted_to_faq($id, $faq_id) {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return false;
        }

        return $this->repository->mark_converted_to_faq($id, $faq_id);
    }

    /**
     * 取得摘要統計。
     *
     * @return array
     */
    public function get_summary() {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return $this->empty_summary();
        }

        return wp_parse_args(
            $this->repository->get_summary(),
            $this->empty_summary()
        );
    }

    /**
     * 取得沒幫助原因統計。
     *
     * @return array
     */
    public function get_feedback_reason_counts() {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return array();
        }

        return $this->repository->get_feedback_reason_counts();
    }

    /**
     * 取得回答來源統計。
     *
     * @return array
     */
    public function get_answer_source_counts() {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return array();
        }

        return $this->repository->get_answer_source_counts();
    }

    /**
     * 取得每日問答統計。
     *
     * @param int $days 天數。
     * @return array
     */
    public function get_daily_counts($days = 14) {
        if (!$this->repository instanceof UR_AI_Log_Repository) {
            return array();
        }

        return $this->repository->get_daily_counts($days);
    }

    /**
     * 格式化後台列表單筆資料。
     *
     * @param object|array $log 問答紀錄。
     * @return array
     */
    public function format_for_admin_list($log) {
        $answer_source = (string) $this->get_value($log, 'answer_source', '');
        $feedback      = (string) $this->get_value($log, 'feedback', '');
        $status        = (string) $this->get_value($log, 'status', '');

        return array(
            'id'                    => absint($this->get_value($log, 'id', 0)),
            'user_id'               => absint($this->get_value($log, 'user_id', 0)),
            'ip_address'            => (string) $this->get_value($log, 'ip_address', ''),
            'question'              => (string) $this->get_value($log, 'question', ''),
            'question_excerpt'      => $this->excerpt((string) $this->get_value($log, 'question', ''), 90),
            'answer'                => (string) $this->get_value($log, 'answer', ''),
            'answer_excerpt'        => $this->excerpt((string) $this->get_value($log, 'answer', ''), 120),
            'answer_source'         => $answer_source,
            'answer_source_label'   => $this->get_answer_source_label($answer_source),
            'model'                 => (string) $this->get_value($log, 'model', ''),
            'tokens_used'           => absint($this->get_value($log, 'tokens_used', 0)),
            'faq_id'                => absint($this->get_value($log, 'faq_id', 0)),
            'faq_match_score'       => absint($this->get_value($log, 'faq_match_score', 0)),
            'faq_matched_keywords'  => (string) $this->get_value($log, 'faq_matched_keywords', ''),
            'has_related_pages'     => !empty($this->get_value($log, 'has_related_pages', 0)) ? 1 : 0,
            'related_page_ids'      => (string) $this->get_value($log, 'related_page_ids', ''),
            'converted_faq_id'      => absint($this->get_value($log, 'converted_faq_id', 0)),
            'feedback'              => $feedback,
            'feedback_label'        => $this->get_feedback_label($feedback),
            'feedback_reason'       => (string) $this->get_value($log, 'feedback_reason', ''),
            'feedback_comment'      => (string) $this->get_value($log, 'feedback_comment', ''),
            'status'                => $status,
            'status_label'          => $this->get_status_label($status),
            'error_code'            => (string) $this->get_value($log, 'error_code', ''),
            'error_message'         => (string) $this->get_value($log, 'error_message', ''),
            'created_at'            => (string) $this->get_value($log, 'created_at', ''),
            'created_at_label'      => $this->format_datetime((string) $this->get_value($log, 'created_at', '')),
            'updated_at'            => (string) $this->get_value($log, 'updated_at', ''),
        );
    }

    /**
     * 格式化多筆後台列表資料。
     *
     * @param array $logs 問答紀錄。
     * @return array
     */
    public function format_many_for_admin_list($logs) {
        if (!is_array($logs)) {
            return array();
        }

        $items = array();

        foreach ($logs as $log) {
            $items[] = $this->format_for_admin_list($log);
        }

        return $items;
    }

    /**
     * 準備 CSV 匯出資料列。
     *
     * @param array $logs 問答紀錄。
     * @return array
     */
    public function prepare_export_rows($logs) {
        if (!is_array($logs)) {
            return array();
        }

        $rows = array();

        foreach ($logs as $log) {
            $rows[] = array(
                'id'                    => absint($this->get_value($log, 'id', 0)),
                'created_at'            => (string) $this->get_value($log, 'created_at', ''),
                'user_id'               => absint($this->get_value($log, 'user_id', 0)),
                'ip_address'            => (string) $this->get_value($log, 'ip_address', ''),
                'question'              => (string) $this->get_value($log, 'question', ''),
                'answer'                => (string) $this->get_value($log, 'answer', ''),
                'answer_source'         => $this->get_answer_source_label((string) $this->get_value($log, 'answer_source', '')),
                'model'                 => (string) $this->get_value($log, 'model', ''),
                'tokens_used'           => absint($this->get_value($log, 'tokens_used', 0)),
                'faq_id'                => absint($this->get_value($log, 'faq_id', 0)),
                'faq_match_score'       => absint($this->get_value($log, 'faq_match_score', 0)),
                'faq_matched_keywords'  => (string) $this->get_value($log, 'faq_matched_keywords', ''),
                'has_related_pages'     => !empty($this->get_value($log, 'has_related_pages', 0)) ? __('有', 'ur-ai-assistant') : __('無', 'ur-ai-assistant'),
                'related_page_ids'      => (string) $this->get_value($log, 'related_page_ids', ''),
                'converted_faq_id'      => absint($this->get_value($log, 'converted_faq_id', 0)),
                'feedback'              => $this->get_feedback_label((string) $this->get_value($log, 'feedback', '')),
                'feedback_reason'       => (string) $this->get_value($log, 'feedback_reason', ''),
                'feedback_comment'      => (string) $this->get_value($log, 'feedback_comment', ''),
                'status'                => $this->get_status_label((string) $this->get_value($log, 'status', '')),
                'error_code'            => (string) $this->get_value($log, 'error_code', ''),
                'error_message'         => (string) $this->get_value($log, 'error_message', ''),
            );
        }

        return $rows;
    }

    /**
     * 取得回答來源標籤。
     *
     * @param string $source 回答來源。
     * @return string
     */
    public function get_answer_source_label($source) {
        if (class_exists('UR_AI_Helper')) {
            return UR_AI_Helper::answer_source_label($source);
        }

        $source = sanitize_key($source);

        $labels = array(
            'faq'   => __('FAQ 知識庫回答', 'ur-ai-assistant'),
            'ai'    => __('AI 回答', 'ur-ai-assistant'),
            'error' => __('錯誤', 'ur-ai-assistant'),
        );

        return isset($labels[$source]) ? $labels[$source] : $source;
    }

    /**
     * 取得回饋標籤。
     *
     * @param string $feedback 回饋。
     * @return string
     */
    public function get_feedback_label($feedback) {
        if (class_exists('UR_AI_Helper')) {
            return UR_AI_Helper::feedback_label($feedback);
        }

        $feedback = sanitize_key($feedback);

        $labels = array(
            'helpful'     => __('有幫助', 'ur-ai-assistant'),
            'not_helpful' => __('沒幫助', 'ur-ai-assistant'),
            ''            => __('未回饋', 'ur-ai-assistant'),
        );

        return isset($labels[$feedback]) ? $labels[$feedback] : __('未回饋', 'ur-ai-assistant');
    }

    /**
     * 取得狀態標籤。
     *
     * @param string $status 狀態。
     * @return string
     */
    public function get_status_label($status) {
        $status = sanitize_key($status);

        $labels = array(
            'success' => __('成功', 'ur-ai-assistant'),
            'error'   => __('錯誤', 'ur-ai-assistant'),
        );

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * 格式化日期時間。
     *
     * @param string $datetime 日期時間。
     * @return string
     */
    private function format_datetime($datetime) {
        if ('' === trim($datetime)) {
            return '';
        }

        $timestamp = strtotime($datetime);

        if (!$timestamp) {
            return $datetime;
        }

        return date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
    }

    /**
     * 空摘要。
     *
     * @return array
     */
    private function empty_summary() {
        return array(
            'total'           => 0,
            'success'         => 0,
            'error'           => 0,
            'faq'             => 0,
            'ai'              => 0,
            'helpful'         => 0,
            'not_helpful'     => 0,
            'with_related'    => 0,
            'without_related' => 0,
            'converted'       => 0,
            'not_converted'   => 0,
            'tokens_used'     => 0,
        );
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

    /**
     * 摘要文字。
     *
     * @param string $text 文字。
     * @param int    $length 長度。
     * @return string
     */
    private function excerpt($text, $length = 80) {
        if (class_exists('UR_AI_Formatter')) {
            return UR_AI_Formatter::admin_excerpt($text, $length);
        }

        $text = wp_strip_all_tags((string) $text);

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, absint($length), 'UTF-8');
        }

        return substr($text, 0, absint($length));
    }
}