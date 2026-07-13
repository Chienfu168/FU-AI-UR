<?php
/**
 * UR AI Assistant Log Repository
 *
 * 問答紀錄資料庫存取層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Log_Repository
 */
class UR_AI_Log_Repository {

    /**
     * 資料表名稱。
     *
     * @var string
     */
    private $table_name;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->table_name = class_exists('UR_AI_Schema_Logs')
            ? UR_AI_Schema_Logs::get_table_name()
            : $this->fallback_table_name();
    }

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * 新增問答紀錄。
     *
     * @param array $data 紀錄資料。
     * @return int 新增 ID。
     */
    public function create($data) {
        global $wpdb;

        $data = $this->sanitize_data($data);

        if (empty($data['question'])) {
            return 0;
        }

        $now = current_time('mysql');

        $insert_data = array(
            'user_id'              => $data['user_id'],
            'ip_address'           => $data['ip_address'],
            'user_agent'           => $data['user_agent'],
            'question'             => $data['question'],
            'answer'               => $data['answer'],
            'answer_source'        => $data['answer_source'],
            'model'                => $data['model'],
            'tokens_used'          => $data['tokens_used'],
            'faq_id'               => $data['faq_id'],
            'faq_match_score'      => $data['faq_match_score'],
            'faq_matched_keywords' => $data['faq_matched_keywords'],
            'has_related_pages'    => $data['has_related_pages'],
            'related_page_ids'     => $data['related_page_ids'],
            'converted_faq_id'     => 0,
            'feedback'             => $data['feedback'],
            'feedback_reason'      => $data['feedback_reason'],
            'feedback_comment'     => $data['feedback_comment'],
            'status'               => $data['status'],
            'error_code'           => $data['error_code'],
            'error_message'        => $data['error_message'],
            'created_at'           => $now,
            'updated_at'           => $now,
        );

        $result = $wpdb->insert(
            $this->table_name,
            $insert_data,
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%d',
                '%s',
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        if (false === $result) {
            return 0;
        }

        return absint($wpdb->insert_id);
    }

    /**
     * 查詢單筆問答紀錄。
     *
     * @param int $id 紀錄 ID。
     * @return object|null
     */
    public function find($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * 查詢問答紀錄列表。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query($args = array()) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            array(
                'answer_source'     => '',
                'status'            => '',
                'feedback'          => '',
                'has_related_pages' => null,
                'converted'         => null,
                'faq_id'            => null,
                'user_id'           => null,
                'date_from'         => '',
                'date_to'           => '',
                'search'            => '',
                'orderby'           => 'created_at',
                'order'             => 'DESC',
                'limit'             => 50,
                'offset'            => 0,
            )
        );

        list($where, $values) = $this->build_where($args);

        $orderby = $this->sanitize_orderby($args['orderby']);
        $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit   = absint($args['limit']);
        $offset  = absint($args['offset']);

        if ($limit <= 0) {
            $limit = 50;
        }

        $sql = "SELECT * FROM {$this->table_name} WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$orderby} {$order}, id DESC";
        $sql .= " LIMIT %d OFFSET %d";

        $values[] = $limit;
        $values[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare($sql, $values)
        );
    }

    /**
     * 計算問答紀錄數量。
     *
     * @param array $args 查詢參數。
     * @return int
     */
    public function count($args = array()) {
        global $wpdb;

        list($where, $values) = $this->build_where($args);

        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE " . implode(' AND ', $where);

        if (!empty($values)) {
            return absint($wpdb->get_var($wpdb->prepare($sql, $values)));
        }

        return absint($wpdb->get_var($sql));
    }

    /**
     * 刪除單筆問答紀錄。
     *
     * @param int $id 紀錄 ID。
     * @return bool
     */
    public function delete($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            $this->table_name,
            array(
                'id' => $id,
            ),
            array(
                '%d',
            )
        );

        return false !== $result;
    }

    /**
     * 批次刪除問答紀錄。
     *
     * @param array $ids 紀錄 ID 陣列。
     * @return int 影響筆數。
     */
    public function bulk_delete($ids) {
        global $wpdb;

        $ids = class_exists('UR_AI_Security')
            ? UR_AI_Security::sanitize_ids($ids)
            : array_map('absint', (array) $ids);

        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})";

        $result = $wpdb->query(
            $wpdb->prepare($sql, $ids)
        );

        return false === $result ? 0 : absint($result);
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
        global $wpdb;

        $id       = absint($id);
        $feedback = sanitize_key($feedback);

        if ($id <= 0 || !in_array($feedback, array('helpful', 'not_helpful'), true)) {
            return false;
        }

        $reason  = sanitize_text_field((string) $reason);
        $comment = sanitize_textarea_field((string) $comment);

        if ('helpful' === $feedback) {
            $reason = '';
        }

        $result = $wpdb->update(
            $this->table_name,
            array(
                'feedback'         => $feedback,
                'feedback_reason'  => $reason,
                'feedback_comment' => $comment,
                'updated_at'       => current_time('mysql'),
            ),
            array(
                'id' => $id,
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
            ),
            array(
                '%d',
            )
        );

        return false !== $result;
    }

    /**
     * 標記問答紀錄已轉成 FAQ。
     *
     * @param int $id 紀錄 ID。
     * @param int $faq_id FAQ ID。
     * @return bool
     */
    public function mark_converted_to_faq($id, $faq_id) {
        global $wpdb;

        $id     = absint($id);
        $faq_id = absint($faq_id);

        if ($id <= 0 || $faq_id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_name,
            array(
                'converted_faq_id' => $faq_id,
                'updated_at'       => current_time('mysql'),
            ),
            array(
                'id' => $id,
            ),
            array(
                '%d',
                '%s',
            ),
            array(
                '%d',
            )
        );

        return false !== $result;
    }

    /**
     * 取得摘要統計。
     *
     * @return array
     */
    public function get_summary() {
        return array(
            'total'           => $this->count(),
            'success'         => $this->count(array('status' => 'success')),
            'error'           => $this->count(array('status' => 'error')),
            'faq'             => $this->count(array('answer_source' => 'faq')),
            'ai'              => $this->count(array('answer_source' => 'ai')),
            'helpful'         => $this->count(array('feedback' => 'helpful')),
            'not_helpful'     => $this->count(array('feedback' => 'not_helpful')),
            'with_related'    => $this->count(array('has_related_pages' => 1)),
            'without_related' => $this->count(array('has_related_pages' => 0)),
            'converted'       => $this->count(array('converted' => 1)),
            'not_converted'   => $this->count(array('converted' => 0, 'answer_source' => 'ai', 'status' => 'success')),
            'tokens_used'     => $this->sum_tokens_used(),
        );
    }

    /**
     * 取得沒幫助原因統計。
     *
     * @return array
     */
    public function get_feedback_reason_counts() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT feedback_reason, COUNT(*) AS total
             FROM {$this->table_name}
             WHERE feedback = 'not_helpful'
               AND feedback_reason <> ''
             GROUP BY feedback_reason
             ORDER BY total DESC"
        );
    }

    /**
     * 取得回答來源統計。
     *
     * @return array
     */
    public function get_answer_source_counts() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT answer_source, COUNT(*) AS total
             FROM {$this->table_name}
             GROUP BY answer_source
             ORDER BY total DESC"
        );
    }

    /**
     * 取得每日問答數。
     *
     * @param int $days 天數。
     * @return array
     */
    public function get_daily_counts($days = 14) {
        global $wpdb;

        $days = absint($days);

        if ($days <= 0 || $days > 365) {
            $days = 14;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) AS date_label,
                        COUNT(*) AS total,
                        SUM(CASE WHEN answer_source = 'faq' THEN 1 ELSE 0 END) AS faq_total,
                        SUM(CASE WHEN answer_source = 'ai' THEN 1 ELSE 0 END) AS ai_total,
                        SUM(tokens_used) AS tokens_used
                 FROM {$this->table_name}
                 WHERE created_at >= DATE_SUB(%s, INTERVAL %d DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date_label ASC",
                current_time('mysql'),
                $days
            )
        );
    }

    /**
     * 依模型統計 AI 回答的 token 用量（供後台估算花費使用）。
     *
     * @param int $days 統計天數，0 表示不限天數（全部歷史資料）。
     * @return array
     */
    public function get_token_usage_by_model($days = 0) {
        global $wpdb;

        $days = absint($days);

        if ($days > 0) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT model,
                            COUNT(*) AS requests,
                            SUM(tokens_used) AS tokens
                     FROM {$this->table_name}
                     WHERE answer_source = 'ai'
                       AND created_at >= DATE_SUB(%s, INTERVAL %d DAY)
                     GROUP BY model
                     ORDER BY tokens DESC",
                    current_time('mysql'),
                    $days
                )
            );
        }

        return $wpdb->get_results(
            "SELECT model,
                    COUNT(*) AS requests,
                    SUM(tokens_used) AS tokens
             FROM {$this->table_name}
             WHERE answer_source = 'ai'
             GROUP BY model
             ORDER BY tokens DESC"
        );
    }

    /**
     * 取得重複被問、卻一直落到 AI 回答（沒有對應 FAQ）的問題清單。
     *
     * 依「問題文字完全相同」分組計數，屬於粗略比對（不同措辭的相同問題
     * 不會被合併），但足以找出「明顯該建 FAQ 卻還沒建」的候選題目。
     * 已轉過 FAQ 草稿的問題（converted_faq_id > 0）不會再列入，避免重複提醒。
     *
     * @param int $min_count 至少被問幾次才列入，預設 2。
     * @param int $limit 筆數上限。
     * @return array
     */
    public function get_frequent_ai_questions($min_count = 2, $limit = 20) {
        global $wpdb;

        $min_count = absint($min_count);

        if ($min_count <= 0) {
            $min_count = 2;
        }

        $limit = absint($limit);

        if ($limit <= 0) {
            $limit = 20;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question,
                        COUNT(*) AS total,
                        MAX(created_at) AS last_asked_at,
                        MAX(id) AS sample_log_id
                 FROM {$this->table_name}
                 WHERE answer_source = 'ai'
                   AND status = 'success'
                   AND converted_faq_id = 0
                 GROUP BY question
                 HAVING COUNT(*) >= %d
                 ORDER BY total DESC, last_asked_at DESC
                 LIMIT %d",
                $min_count,
                $limit
            )
        );
    }

    /**
     * 依 FAQ 分組統計「沒幫助」回饋次數，找出最需要改寫的 FAQ。
     *
     * @param int $limit 筆數上限。
     * @return array
     */
    public function get_not_helpful_faq_summary($limit = 20) {
        global $wpdb;

        $limit = absint($limit);

        if ($limit <= 0) {
            $limit = 20;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT faq_id,
                        COUNT(*) AS not_helpful_count
                 FROM {$this->table_name}
                 WHERE answer_source = 'faq'
                   AND feedback = 'not_helpful'
                   AND faq_id > 0
                 GROUP BY faq_id
                 ORDER BY not_helpful_count DESC
                 LIMIT %d",
                $limit
            )
        );
    }

    /**
     * 建立 where 條件。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    private function build_where($args) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            array(
                'answer_source'     => '',
                'status'            => '',
                'feedback'          => '',
                'has_related_pages' => null,
                'converted'         => null,
                'faq_id'            => null,
                'user_id'           => null,
                'date_from'         => '',
                'date_to'           => '',
                'search'            => '',
            )
        );

        $where  = array('1=1');
        $values = array();

        if ('' !== $args['answer_source']) {
            $where[]  = 'answer_source = %s';
            $values[] = sanitize_key($args['answer_source']);
        }

        if ('' !== $args['status']) {
            $where[]  = 'status = %s';
            $values[] = sanitize_key($args['status']);
        }

        if ('' !== $args['feedback']) {
            $where[]  = 'feedback = %s';
            $values[] = sanitize_key($args['feedback']);
        }

        if (null !== $args['has_related_pages'] && '' !== $args['has_related_pages']) {
            $where[]  = 'has_related_pages = %d';
            $values[] = absint($args['has_related_pages']);
        }

        if (null !== $args['converted'] && '' !== $args['converted']) {
            if (absint($args['converted']) === 1) {
                $where[] = 'converted_faq_id > 0';
            } else {
                $where[] = 'converted_faq_id = 0';
            }
        }

        if (null !== $args['faq_id'] && '' !== $args['faq_id']) {
            $where[]  = 'faq_id = %d';
            $values[] = absint($args['faq_id']);
        }

        if (null !== $args['user_id'] && '' !== $args['user_id']) {
            $where[]  = 'user_id = %d';
            $values[] = absint($args['user_id']);
        }

        if ('' !== $args['date_from']) {
            $where[]  = 'created_at >= %s';
            $values[] = sanitize_text_field($args['date_from']) . ' 00:00:00';
        }

        if ('' !== $args['date_to']) {
            $where[]  = 'created_at <= %s';
            $values[] = sanitize_text_field($args['date_to']) . ' 23:59:59';
        }

        if ('' !== $args['search']) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';

            $where[]  = '(question LIKE %s OR answer LIKE %s OR error_message LIKE %s OR faq_matched_keywords LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        return array($where, $values);
    }

    /**
     * 清理紀錄資料。
     *
     * @param array $data 原始資料。
     * @return array
     */
    private function sanitize_data($data) {
        if (!is_array($data)) {
            $data = array();
        }

        $answer_source = isset($data['answer_source']) ? sanitize_key($data['answer_source']) : 'ai';

        if (!in_array($answer_source, array('faq', 'ai', 'error'), true)) {
            $answer_source = 'ai';
        }

        $status = isset($data['status']) ? sanitize_key($data['status']) : 'success';

        if (!in_array($status, array('success', 'error'), true)) {
            $status = 'success';
        }

        $feedback = isset($data['feedback']) ? sanitize_key($data['feedback']) : '';

        if (!in_array($feedback, array('', 'helpful', 'not_helpful'), true)) {
            $feedback = '';
        }

        return array(
            'user_id'              => isset($data['user_id']) ? absint($data['user_id']) : get_current_user_id(),
            'ip_address'           => isset($data['ip_address']) ? sanitize_text_field($data['ip_address']) : '',
            'user_agent'           => isset($data['user_agent']) ? sanitize_text_field($data['user_agent']) : '',
            'question'             => isset($data['question']) ? sanitize_textarea_field($data['question']) : '',
            'answer'               => isset($data['answer']) ? sanitize_textarea_field($data['answer']) : '',
            'answer_source'        => $answer_source,
            'model'                => isset($data['model']) ? sanitize_text_field($data['model']) : '',
            'tokens_used'          => isset($data['tokens_used']) ? absint($data['tokens_used']) : 0,
            'faq_id'               => isset($data['faq_id']) ? absint($data['faq_id']) : 0,
            'faq_match_score'      => isset($data['faq_match_score']) ? absint($data['faq_match_score']) : 0,
            'faq_matched_keywords' => isset($data['faq_matched_keywords']) ? sanitize_text_field($data['faq_matched_keywords']) : '',
            'has_related_pages'    => !empty($data['has_related_pages']) ? 1 : 0,
            'related_page_ids'     => isset($data['related_page_ids']) ? sanitize_text_field($data['related_page_ids']) : '',
            'feedback'             => $feedback,
            'feedback_reason'      => isset($data['feedback_reason']) ? sanitize_text_field($data['feedback_reason']) : '',
            'feedback_comment'     => isset($data['feedback_comment']) ? sanitize_textarea_field($data['feedback_comment']) : '',
            'status'               => $status,
            'error_code'           => isset($data['error_code']) ? sanitize_key($data['error_code']) : '',
            'error_message'        => isset($data['error_message']) ? sanitize_textarea_field($data['error_message']) : '',
        );
    }

    /**
     * 清理 orderby。
     *
     * @param string $orderby orderby.
     * @return string
     */
    private function sanitize_orderby($orderby) {
        $orderby = is_string($orderby) ? sanitize_key($orderby) : 'created_at';

        $allowed = array(
            'id',
            'user_id',
            'answer_source',
            'status',
            'feedback',
            'tokens_used',
            'faq_match_score',
            'created_at',
            'updated_at',
        );

        return in_array($orderby, $allowed, true) ? $orderby : 'created_at';
    }

    /**
     * Token 使用量加總。
     *
     * @return int
     */
    private function sum_tokens_used() {
        global $wpdb;

        return absint($wpdb->get_var("SELECT SUM(tokens_used) FROM {$this->table_name}"));
    }

    /**
     * fallback 資料表名稱。
     *
     * @return string
     */
    private function fallback_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_logs';
    }
}