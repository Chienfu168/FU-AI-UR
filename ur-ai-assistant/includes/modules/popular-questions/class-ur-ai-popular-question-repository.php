<?php
/**
 * UR AI Assistant Popular Question Repository
 *
 * 熱門問題資料庫存取層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Popular_Question_Repository
 */
class UR_AI_Popular_Question_Repository {

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
        $this->table_name = class_exists('UR_AI_Schema_Popular_Questions')
            ? UR_AI_Schema_Popular_Questions::get_table_name()
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
     * 新增熱門問題。
     *
     * @param array $data 熱門問題資料。
     * @return int 新增 ID。
     */
    public function create($data) {
        global $wpdb;

        $data = $this->sanitize_data($data, 'create');

        if (empty($data['question'])) {
            return 0;
        }

        $now = current_time('mysql');

        $insert_data = array(
            'category'        => $data['category'],
            'question'        => $data['question'],
            'submit_question' => $data['submit_question'],
            'description'     => $data['description'],
            'status'          => $data['status'],
            'source'          => $data['source'],
            'faq_id'          => $data['faq_id'],
            'sort_order'      => $data['sort_order'],
            'click_count'     => 0,
            'admin_note'      => $data['admin_note'],
            'created_by'      => get_current_user_id(),
            'updated_by'      => get_current_user_id(),
            'created_at'      => $now,
            'updated_at'      => $now,
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
     * 更新熱門問題。
     *
     * @param int   $id 熱門問題 ID。
     * @param array $data 熱門問題資料。
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
            'category'        => $data['category'],
            'question'        => $data['question'],
            'submit_question' => $data['submit_question'],
            'description'     => $data['description'],
            'status'          => $data['status'],
            'faq_id'          => $data['faq_id'],
            'sort_order'      => $data['sort_order'],
            'admin_note'      => $data['admin_note'],
            'updated_by'      => get_current_user_id(),
            'updated_at'      => current_time('mysql'),
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
                '%d',
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
     * 刪除熱門問題。
     *
     * @param int $id 熱門問題 ID。
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
     * 批次刪除熱門問題。
     *
     * @param array $ids 熱門問題 ID 陣列。
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
     * 查詢單筆熱門問題。
     *
     * @param int $id 熱門問題 ID。
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
     * 查詢熱門問題列表。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query($args = array()) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            array(
                'status'    => '',
                'category'  => '',
                'source'    => '',
                'faq_id'    => null,
                'linked'    => null,
                'search'    => '',
                'orderby'   => 'sort_order',
                'order'     => 'ASC',
                'limit'     => 50,
                'offset'    => 0,
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
     * 查詢符合條件的全部 ID（不分頁），供「跨頁全選」批次操作使用。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query_ids($args = array()) {
        global $wpdb;

        list($where, $values) = $this->build_where($args);

        $sql = "SELECT id FROM {$this->table_name} WHERE " . implode(' AND ', $where);

        if (!empty($values)) {
            $ids = $wpdb->get_col($wpdb->prepare($sql, $values));
        } else {
            $ids = $wpdb->get_col($sql);
        }

        return array_map('absint', is_array($ids) ? $ids : array());
    }

    /**
     * 統計熱門問題數量。
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
     * 取得前台啟用熱門問題。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_frontend_questions($limit = 6) {
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
     * 取得前台分類群組熱門問題。
     *
     * @param int $per_category 每分類筆數。
     * @return array
     */
    public function get_frontend_grouped_questions($per_category = 6) {
        $questions = $this->query(
            array(
                'status'  => 'active',
                'orderby' => 'sort_order',
                'order'   => 'ASC',
                'limit'   => 300,
                'offset'  => 0,
            )
        );

        if (empty($questions)) {
            return array();
        }

        $per_category = absint($per_category);

        if ($per_category <= 0) {
            $per_category = 6;
        }

        $groups = array();

        foreach ($questions as $question) {
            $category = isset($question->category) && '' !== trim($question->category)
                ? $question->category
                : '其他';

            if (!isset($groups[$category])) {
                $groups[$category] = array();
            }

            if (count($groups[$category]) >= $per_category) {
                continue;
            }

            $groups[$category][] = $question;
        }

        return $groups;
    }

    /**
     * 增加點擊次數。
     *
     * @param int $id 熱門問題 ID。
     * @return bool
     */
    public function increase_click_count($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name}
                 SET click_count = click_count + 1,
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
     * @param array  $ids 熱門問題 ID 陣列。
     * @param string $status 狀態 active / inactive。
     * @return int 影響筆數。
     */
    public function bulk_update_status($ids, $status) {
        global $wpdb;

        $ids = class_exists('UR_AI_Security')
            ? UR_AI_Security::sanitize_ids($ids)
            : array_map('absint', (array) $ids);

        $ids    = array_values(array_unique(array_filter($ids)));
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
     * 更新對應 FAQ ID。
     *
     * @param int $id 熱門問題 ID。
     * @param int $faq_id FAQ ID。
     * @return bool
     */
    public function update_faq_id($id, $faq_id) {
        global $wpdb;

        $id     = absint($id);
        $faq_id = absint($faq_id);

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_name,
            array(
                'faq_id'     => $faq_id,
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql'),
            ),
            array(
                'id' => $id,
            ),
            array(
                '%d',
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
            'total'             => $this->count(),
            'active'            => $this->count(array('status' => 'active')),
            'inactive'          => $this->count(array('status' => 'inactive')),
            'manual'            => $this->count(array('source' => 'manual')),
            'from_faq'          => $this->count(array('source' => 'faq')),
            'linked_faq'        => $this->count(array('linked' => 1)),
            'unlinked_faq'      => $this->count(array('linked' => 0)),
            'high_click'        => $this->count_high_click(),
            'total_click_count' => $this->sum_click_count(),
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
                    SUM(click_count) AS click_count
             FROM {$this->table_name}
             GROUP BY category
             ORDER BY total DESC"
        );
    }

    /**
     * 取得高點擊但未對應 FAQ 的熱門問題。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_high_click_unlinked_questions($limit = 20) {
        global $wpdb;

        $limit = absint($limit);

        if ($limit <= 0) {
            $limit = 20;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$this->table_name}
                 WHERE faq_id = 0
                   AND click_count >= %d
                 ORDER BY click_count DESC, sort_order ASC
                 LIMIT %d",
                5,
                $limit
            )
        );
    }

    /**
     * 取得熱門排行。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_top_clicked_questions($limit = 10) {
        return $this->query(
            array(
                'orderby' => 'click_count',
                'order'   => 'DESC',
                'limit'   => absint($limit),
                'offset'  => 0,
            )
        );
    }

    /**
     * 從 FAQ 建立熱門問題。
     *
     * @param object|array $faq FAQ 資料。
     * @return int
     */
    public function create_from_faq($faq) {
        $faq_id   = absint($this->get_value($faq, 'id', 0));
        $question = (string) $this->get_value($faq, 'question', '');
        $category = (string) $this->get_value($faq, 'category', '');

        if ($faq_id <= 0 || '' === trim($question)) {
            return 0;
        }

        return $this->create(
            array(
                'category'        => $category ? $category : '待分類',
                'question'        => $question,
                'submit_question' => $question,
                'description'     => __('由 FAQ 知識庫匯入的熱門問題。', 'ur-ai-assistant'),
                'status'          => 'inactive',
                'source'          => 'faq',
                'faq_id'          => $faq_id,
                'sort_order'      => 100,
                'admin_note'      => __('由 FAQ 匯入，請確認是否適合放在前台熱門問題後再啟用。', 'ur-ai-assistant'),
            )
        );
    }

    /**
     * 取得指定 FAQ 是否已建立熱門問題。
     *
     * @param int $faq_id FAQ ID。
     * @return object|null
     */
    public function find_by_faq_id($faq_id) {
        global $wpdb;

        $faq_id = absint($faq_id);

        if ($faq_id <= 0) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE faq_id = %d LIMIT 1",
                $faq_id
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
                'status'   => '',
                'category' => '',
                'source'   => '',
                'faq_id'   => null,
                'linked'   => null,
                'search'   => '',
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

        if (null !== $args['faq_id'] && '' !== $args['faq_id']) {
            $where[]  = 'faq_id = %d';
            $values[] = absint($args['faq_id']);
        }

        if (null !== $args['linked'] && '' !== $args['linked']) {
            if (absint($args['linked']) === 1) {
                $where[] = 'faq_id > 0';
            } else {
                $where[] = 'faq_id = 0';
            }
        }

        if ('' !== $args['search']) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';

            $where[]  = '(question LIKE %s OR submit_question LIKE %s OR description LIKE %s OR category LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        return array($where, $values);
    }

    /**
     * 清理熱門問題資料。
     *
     * @param array  $data 原始資料。
     * @param string $context create/update.
     * @return array
     */
    private function sanitize_data($data, $context = 'create') {
        if (!is_array($data)) {
            $data = array();
        }

        $category        = isset($data['category']) ? $data['category'] : '待分類';
        $question        = isset($data['question']) ? $data['question'] : '';
        $submit_question = isset($data['submit_question']) ? $data['submit_question'] : '';
        $description     = isset($data['description']) ? $data['description'] : '';

        if ('' === trim((string) $submit_question)) {
            $submit_question = $question;
        }

        if (class_exists('UR_AI_Security')) {
            $category        = UR_AI_Security::sanitize_category($category);
            $question        = UR_AI_Security::sanitize_textarea($question);
            $submit_question = UR_AI_Security::sanitize_textarea($submit_question);
            $description     = UR_AI_Security::sanitize_textarea($description);
        } else {
            $category        = sanitize_text_field($category);
            $question        = sanitize_textarea_field($question);
            $submit_question = sanitize_textarea_field($submit_question);
            $description     = sanitize_textarea_field($description);
        }

        $status = isset($data['status']) ? sanitize_key($data['status']) : 'inactive';

        if (!in_array($status, array('active', 'inactive'), true)) {
            $status = 'inactive';
        }

        $source = isset($data['source']) ? sanitize_key($data['source']) : 'manual';

        if (!in_array($source, array('manual', 'faq', 'import'), true)) {
            $source = 'manual';
        }

        return array(
            'category'        => $category ? $category : '待分類',
            'question'        => $question,
            'submit_question' => $submit_question,
            'description'     => $description,
            'status'          => $status,
            'source'          => $source,
            'faq_id'          => isset($data['faq_id']) ? absint($data['faq_id']) : 0,
            'sort_order'      => isset($data['sort_order']) ? absint($data['sort_order']) : 100,
            'admin_note'      => isset($data['admin_note']) ? sanitize_textarea_field($data['admin_note']) : '',
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
            'faq_id',
            'sort_order',
            'click_count',
            'created_at',
            'updated_at',
        );

        return in_array($orderby, $allowed, true) ? $orderby : 'sort_order';
    }

    /**
     * 計算高點擊熱門問題。
     *
     * @return int
     */
    private function count_high_click() {
        global $wpdb;

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE click_count >= %d",
                    5
                )
            )
        );
    }

    /**
     * 統計總點擊數。
     *
     * @return int
     */
    private function sum_click_count() {
        global $wpdb;

        return absint($wpdb->get_var("SELECT SUM(click_count) FROM {$this->table_name}"));
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
     * fallback 資料表名稱。
     *
     * @return string
     */
    private function fallback_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_popular_questions';
    }
}