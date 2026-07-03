<?php
/**
 * UR AI Assistant Feedback Service
 *
 * 使用者回饋服務層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Feedback_Service
 */
class UR_AI_Feedback_Service {

    /**
     * Log Service.
     *
     * @var UR_AI_Log_Service|null
     */
    private $log_service = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->log_service = class_exists('UR_AI_Log_Service')
            ? new UR_AI_Log_Service()
            : null;
    }

    /**
     * 送出使用者回饋。
     *
     * @param int    $log_id 問答紀錄 ID。
     * @param string $feedback 回饋類型 helpful / not_helpful。
     * @param string $reason 原因。
     * @param string $comment 補充說明。
     * @return bool
     */
    public function submit_feedback($log_id, $feedback, $reason = '', $comment = '') {
        $log_id   = absint($log_id);
        $feedback = sanitize_key($feedback);

        if ($log_id <= 0) {
            return false;
        }

        if (!in_array($feedback, array('helpful', 'not_helpful'), true)) {
            return false;
        }

        $reason  = is_string($reason) ? sanitize_text_field($reason) : '';
        $comment = is_string($comment) ? sanitize_textarea_field($comment) : '';

        if ('helpful' === $feedback) {
            $reason = '';
        }

        if (!$this->log_service instanceof UR_AI_Log_Service) {
            return false;
        }

        return $this->log_service->update_feedback($log_id, $feedback, $reason, $comment);
    }

    /**
     * 取得回饋摘要統計。
     *
     * @return array
     */
    public function get_summary() {
        if (!$this->log_service instanceof UR_AI_Log_Service) {
            return $this->empty_summary();
        }

        $summary = $this->log_service->get_summary();

        $total_feedback = absint($this->get_value($summary, 'helpful', 0))
            + absint($this->get_value($summary, 'not_helpful', 0));

        $helpful     = absint($this->get_value($summary, 'helpful', 0));
        $not_helpful = absint($this->get_value($summary, 'not_helpful', 0));

        return array(
            'total_logs'          => absint($this->get_value($summary, 'total', 0)),
            'total_feedback'      => $total_feedback,
            'helpful'             => $helpful,
            'not_helpful'         => $not_helpful,
            'feedback_rate'       => $this->percentage($total_feedback, absint($this->get_value($summary, 'total', 0))),
            'helpful_rate'        => $this->percentage($helpful, $total_feedback),
            'not_helpful_rate'    => $this->percentage($not_helpful, $total_feedback),
            'faq_answers'         => absint($this->get_value($summary, 'faq', 0)),
            'ai_answers'          => absint($this->get_value($summary, 'ai', 0)),
            'error_answers'       => absint($this->get_value($summary, 'error', 0)),
        );
    }

    /**
     * 取得沒幫助原因統計。
     *
     * @return array
     */
    public function get_reason_counts() {
        if (!$this->log_service instanceof UR_AI_Log_Service) {
            return array();
        }

        $rows = $this->log_service->get_feedback_reason_counts();

        if (!is_array($rows)) {
            return array();
        }

        $items = array();

        foreach ($rows as $row) {
            $reason = (string) $this->get_value($row, 'feedback_reason', '');
            $total  = absint($this->get_value($row, 'total', 0));

            if ('' === trim($reason)) {
                continue;
            }

            $items[] = array(
                'reason' => $reason,
                'total'  => $total,
            );
        }

        return $items;
    }

    /**
     * 取得需優先改善的問答紀錄。
     *
     * 條件：使用者標示沒幫助。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_not_helpful_logs($limit = 20) {
        if (!$this->log_service instanceof UR_AI_Log_Service) {
            return array();
        }

        $logs = $this->log_service->query(
            array(
                'feedback' => 'not_helpful',
                'orderby'  => 'created_at',
                'order'    => 'DESC',
                'limit'    => absint($limit),
                'offset'   => 0,
            )
        );

        return $this->log_service->format_many_for_admin_list($logs);
    }

    /**
     * 取得有幫助的問答紀錄。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_helpful_logs($limit = 20) {
        if (!$this->log_service instanceof UR_AI_Log_Service) {
            return array();
        }

        $logs = $this->log_service->query(
            array(
                'feedback' => 'helpful',
                'orderby'  => 'created_at',
                'order'    => 'DESC',
                'limit'    => absint($limit),
                'offset'   => 0,
            )
        );

        return $this->log_service->format_many_for_admin_list($logs);
    }

    /**
     * 取得回答來源回饋分析。
     *
     * @return array
     */
    public function get_source_feedback_summary() {
        $sources = array(
            'faq' => __('知識庫回答', 'ur-ai-assistant'),
            'ai'  => __('AI 回答', 'ur-ai-assistant'),
        );

        $items = array();

        foreach ($sources as $source => $label) {
            $items[] = array(
                'source'           => $source,
                'label'            => $label,
                'total'            => $this->count_logs(array('answer_source' => $source)),
                'helpful'          => $this->count_logs(array('answer_source' => $source, 'feedback' => 'helpful')),
                'not_helpful'      => $this->count_logs(array('answer_source' => $source, 'feedback' => 'not_helpful')),
                'helpful_rate'     => $this->calculate_source_helpful_rate($source),
            );
        }

        return $items;
    }

    /**
     * 計算指定來源的有幫助比例。
     *
     * @param string $source 回答來源。
     * @return float
     */
    private function calculate_source_helpful_rate($source) {
        $source = sanitize_key($source);

        $helpful     = $this->count_logs(array('answer_source' => $source, 'feedback' => 'helpful'));
        $not_helpful = $this->count_logs(array('answer_source' => $source, 'feedback' => 'not_helpful'));

        return $this->percentage($helpful, $helpful + $not_helpful);
    }

    /**
     * 統計問答紀錄。
     *
     * @param array $args 查詢參數。
     * @return int
     */
    private function count_logs($args = array()) {
        if (!$this->log_service instanceof UR_AI_Log_Service) {
            return 0;
        }

        return absint($this->log_service->count($args));
    }

    /**
     * 格式化回饋摘要，給後台頁面使用。
     *
     * @return array
     */
    public function get_admin_dashboard_data() {
        return array(
            'summary'          => $this->get_summary(),
            'reason_counts'    => $this->get_reason_counts(),
            'source_summary'   => $this->get_source_feedback_summary(),
            'not_helpful_logs' => $this->get_not_helpful_logs(10),
        );
    }

    /**
     * 回饋標籤。
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
     * 沒幫助原因標籤。
     *
     * @param string $reason 原因。
     * @return string
     */
    public function get_reason_label($reason) {
        $reason = is_string($reason) ? trim($reason) : '';

        if ('' === $reason) {
            return __('未填寫原因', 'ur-ai-assistant');
        }

        return $reason;
    }

    /**
     * 空摘要。
     *
     * @return array
     */
    private function empty_summary() {
        return array(
            'total_logs'       => 0,
            'total_feedback'   => 0,
            'helpful'          => 0,
            'not_helpful'      => 0,
            'feedback_rate'    => 0,
            'helpful_rate'     => 0,
            'not_helpful_rate' => 0,
            'faq_answers'      => 0,
            'ai_answers'       => 0,
            'error_answers'    => 0,
        );
    }

    /**
     * 計算百分比。
     *
     * @param int|float $numerator 分子。
     * @param int|float $denominator 分母。
     * @return float
     */
    private function percentage($numerator, $denominator) {
        if (class_exists('UR_AI_Helper')) {
            return UR_AI_Helper::percentage($numerator, $denominator, 1);
        }

        $numerator   = (float) $numerator;
        $denominator = (float) $denominator;

        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 1);
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