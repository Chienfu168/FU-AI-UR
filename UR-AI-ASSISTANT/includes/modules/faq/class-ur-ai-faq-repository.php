<?php
/**
 * UR AI Assistant FAQ Repository
 *
 * FAQ 知識庫資料庫存取層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_Repository
 */
class UR_AI_FAQ_Repository {

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
        $this->table_name = class_exists('UR_AI_Schema_FAQs')
            ? UR_AI_Schema_FAQs::get_table_name()
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
     * 新增 FAQ。
     *
     * @param array $data FAQ 資料。
     * @return int 新增 ID。
     */
    public function create($data) {
        global $wpdb;

        $data = $this->sanitize_data($data, 'create');

        if (empty($data['question']) || empty($data['answer'])) {
            return 0;
        }

        $now = current_time('mysql');

        $insert_data = array(
            'category'      => $data['category'],
            'question'      => $data['question'],
            'answer'        => $data['answer'],
            'keywords'      => $data['keywords'],
            'status'        => $data['status'],
            'source'        => $data['source'],
            'source_log_id' => $data['source_log_id'],
            'review_status' => $data['review_status'],
            'sort_order'    => $data['sort_order'],
            'hit_count'     => 0,
            'admin_note'    => $data['admin_note'],
            'created_by'    => get_current_user_id(),
            'updated_by'    => get_current_user_id(),
            'created_at'    => $now,
            'updated_at'    => $now,
        );

        $result = $wpdb->insert(
            $this->table_name,
            $insert_data,
            array(
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
                '%d',
                '%s',
                '%d',
                '%d',
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
     * 更新 FAQ。
     *
     * @param int   $id FAQ ID。
     * @param array $data FAQ 資料。
     * @return bool
     */
    public function update($id, $data) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $data = $this->sanitize_data($data, 'update');

        $update_data = array(
            'category'      => $data['category'],
            'question'      => $data['question'],
            'answer'        => $data['answer'],
            'keywords'      => $data['keywords'],
            'status'        => $data['status'],
            'review_status' => $data['review_status'],
            'sort_order'    => $data['sort_order'],
            'admin_note'    => $data['admin_note'],
            'updated_by'    => get_current_user_id(),
            'updated_at'    => current_time('mysql'),
        );

        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array(
                'id' => $id,
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
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
     * 刪除 FAQ。
     *
     * @param int $id FAQ ID。
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
     * 批次刪除 FAQ。
     *
     * @param array $ids FAQ ID 陣列。
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
     * 查詢單筆 FAQ。
     *
     * @param int $id FAQ ID。
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
     * 依來源問答紀錄 ID 查詢 FAQ。
     *
     * @param int $log_id 問答紀錄 ID。
     * @return object|null
     */
    public function find_by_source_log_id($log_id) {
        global $wpdb;

        $log_id = absint($log_id);

        if ($log_id <= 0) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE source_log_id = %d LIMIT 1",
                $log_id
            )
        );
    }

    /**
     * 依標準問題文字精準比對，找出既有 FAQ。
     *
     * 供 CSV 匯入判斷「相同題目 → 覆蓋更新」使用。
     * 以完整字串比對（區分前後空白已於呼叫端 trim）。
     *
     * @param string $question 標準問題文字。
     * @return object|null 找到則回傳資料列，否則 null。
     */
    public function find_by_question($question) {
        global $wpdb;

        $question = is_string($question) ? trim($question) : '';

        if ('' === $question) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE question = %s ORDER BY id ASC LIMIT 1",
                $question
            )
        );
    }

    /**
     * 查詢 FAQ 列表。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query($args = array()) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            array(
                'status'        => '',
                'category'      => '',
                'source'        => '',
                'review_status' => '',
                'search'        => '',
                'orderby'       => 'sort_order',
                'order'         => 'ASC',
                'limit'         => 50,
                'offset'        => 0,
            )
        );

        list($where, $values) = $this->build_where($args);

        $orderby = $this->sanitize_orderby($args['orderby']);
        $order   = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
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
     * 計算 FAQ 數量。
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
     * 取得啟用中的 FAQ。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_active_faqs($limit = 1000) {
        return $this->query(
            array(
                'status'  => 'active',
                'orderby' => 'sort_order',
                'order'   => 'ASC',
                'limit'   => absint($limit),
                'offset'  => 0,
            )
        );
    }

    /**
     * 增加 FAQ 命中次數。
     *
     * @param int $id FAQ ID。
     * @return bool
     */
    public function increase_hit_count($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name}
                 SET hit_count = hit_count + 1,
                     updated_at = %s
                 WHERE id = %d",
                current_time('mysql'),
                $id
            )
        );

        return false !== $result;
    }

    /**
     * 批次更新狀態。
     *
     * @param array  $ids FAQ ID 陣列。
     * @param string $status 狀態 active / inactive。
     * @return int 影響筆數。
     */
    public function bulk_update_status($ids, $status) {
        global $wpdb;

        $ids = class_exists('UR_AI_Security')
            ? UR_AI_Security::sanitize_ids($ids)
            : array_map('absint', (array) $ids);

        $ids   = array_values(array_unique(array_filter($ids)));
        $status = sanitize_key($status);

        if (empty($ids) || !in_array($status, array('active', 'inactive'), true)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $values = array_merge(
            array(
                $status,
                get_current_user_id(),
                current_time('mysql'),
            ),
            $ids
        );

        $sql = "UPDATE {$this->table_name}
                SET status = %s,
                    updated_by = %d,
                    updated_at = %s
                WHERE id IN ({$placeholders})";

        $result = $wpdb->query(
            $wpdb->prepare($sql, $values)
        );

        return false === $result ? 0 : absint($result);
    }

    /**
     * 取得摘要統計。
     *
     * @return array
     */
    public function get_summary() {
        return array(
            'total'        => $this->count(),
            'active'       => $this->count(array('status' => 'active')),
            'inactive'     => $this->count(array('status' => 'inactive')),
            'manual'       => $this->count(array('source' => 'manual')),
            'ai_drafts'    => $this->count(array('source' => 'ai_log')),
            'draft'        => $this->count(array('review_status' => 'draft')),
            'pending'      => $this->count(array('review_status' => 'pending')),
            'approved'     => $this->count(array('review_status' => 'approved')),
            'rejected'     => $this->count(array('review_status' => 'rejected')),
            'total_hits'   => $this->sum_hit_count(),
            'high_hit'     => $this->count_high_hit(),
        );
    }

    /**
     * 取得分類統計。
     *
     * @return array
     */
    public function get_category_stats() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT category,
                    COUNT(*) AS total,
                    SUM(hit_count) AS hit_count
             FROM {$this->table_name}
             GROUP BY category
             ORDER BY total DESC"
        );
    }

    /**
     * 取得高命中 FAQ。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_top_hit_faqs($limit = 10) {
        return $this->query(
            array(
                'orderby' => 'hit_count',
                'order'   => 'DESC',
                'limit'   => absint($limit),
                'offset'  => 0,
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
                'status'        => '',
                'category'      => '',
                'source'        => '',
                'review_status' => '',
                'search'        => '',
            )
        );

        $where  = array('1=1');
        $values = array();

        if ('' !== $args['status']) {
            $where[]  = 'status = %s';
            $values[] = sanitize_key($args['status']);
        }

        if ('' !== $args['category']) {
            $where[]  = 'category = %s';
            $values[] = sanitize_text_field($args['category']);
        }

        if ('' !== $args['source']) {
            $where[]  = 'source = %s';
            $values[] = sanitize_key($args['source']);
        }

        if ('' !== $args['review_status']) {
            $where[]  = 'review_status = %s';
            $values[] = sanitize_key($args['review_status']);
        }

        if ('' !== $args['search']) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';

            $where[]  = '(question LIKE %s OR answer LIKE %s OR keywords LIKE %s OR category LIKE %s OR admin_note LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        return array($where, $values);
    }

    /**
     * 清理 FAQ 資料。
     *
     * @param array  $data 原始資料。
     * @param string $context create/update.
     * @return array
     */
    private function sanitize_data($data, $context = 'create') {
        if (!is_array($data)) {
            $data = array();
        }

        $category = isset($data['category']) ? $data['category'] : '待分類';
        $question = isset($data['question']) ? $data['question'] : '';
        $answer   = isset($data['answer']) ? $data['answer'] : '';
        $keywords = isset($data['keywords']) ? $data['keywords'] : '';

        if (class_exists('UR_AI_Security')) {
            $category = UR_AI_Security::sanitize_category($category);
            $question = UR_AI_Security::sanitize_textarea($question);
            $answer   = UR_AI_Security::sanitize_textarea($answer);
            $keywords = UR_AI_Security::sanitize_keywords($keywords);
        } else {
            $category = sanitize_text_field($category);
            $question = sanitize_textarea_field($question);
            $answer   = sanitize_textarea_field($answer);
            $keywords = sanitize_text_field($keywords);
        }

        $status = isset($data['status']) ? sanitize_key($data['status']) : 'inactive';

        if (!in_array($status, array('active', 'inactive'), true)) {
            $status = 'inactive';
        }

        $source = isset($data['source']) ? sanitize_key($data['source']) : 'manual';

        if (!in_array($source, array('manual', 'ai_log', 'import'), true)) {
            $source = 'manual';
        }

        $review_status = isset($data['review_status']) ? sanitize_key($data['review_status']) : 'draft';

        if (!in_array($review_status, array('draft', 'pending', 'approved', 'rejected'), true)) {
            $review_status = 'draft';
        }

        return array(
            'category'      => $category ? $category : '待分類',
            'question'      => $question,
            'answer'        => $answer,
            'keywords'      => $keywords,
            'status'        => $status,
            'source'        => $source,
            'source_log_id' => isset($data['source_log_id']) ? absint($data['source_log_id']) : 0,
            'review_status' => $review_status,
            'sort_order'    => isset($data['sort_order']) ? absint($data['sort_order']) : 100,
            'admin_note'    => isset($data['admin_note']) ? sanitize_textarea_field($data['admin_note']) : '',
        );
    }

    /**
     * 清理 orderby。
     *
     * @param string $orderby orderby.
     * @return string
     */
    private function sanitize_orderby($orderby) {
        $orderby = is_string($orderby) ? sanitize_key($orderby) : 'sort_order';

        $allowed = array(
            'id',
            'category',
            'status',
            'source',
            'review_status',
            'sort_order',
            'hit_count',
            'created_at',
            'updated_at',
        );

        return in_array($orderby, $allowed, true) ? $orderby : 'sort_order';
    }

    /**
     * 統計命中次數總和。
     *
     * @return int
     */
    private function sum_hit_count() {
        global $wpdb;

        return absint($wpdb->get_var("SELECT SUM(hit_count) FROM {$this->table_name}"));
    }

    /**
     * 統計高命中 FAQ 數量。
     *
     * @return int
     */
    private function count_high_hit() {
        global $wpdb;

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE hit_count >= %d",
                    5
                )
            )
        );
    }

    /**
     * fallback 資料表名稱。
     *
     * @return string
     */
    private function fallback_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_faqs';
    }
}