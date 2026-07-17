<?php
/**
 * UR AI Assistant Quiz Repository
 *
 * 知識大考驗題庫與作答紀錄資料庫存取層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Quiz_Repository
 */
class UR_AI_Quiz_Repository {

    /**
     * 題庫資料表名稱。
     *
     * @var string
     */
    private $questions_table;

    /**
     * 作答紀錄資料表名稱。
     *
     * @var string
     */
    private $attempts_table;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->questions_table = class_exists('UR_AI_Schema_Quiz_Questions')
            ? UR_AI_Schema_Quiz_Questions::get_table_name()
            : $this->fallback_table_name('ur_ai_quiz_questions');

        $this->attempts_table = class_exists('UR_AI_Schema_Quiz_Attempts')
            ? UR_AI_Schema_Quiz_Attempts::get_table_name()
            : $this->fallback_table_name('ur_ai_quiz_attempts');
    }

    /**
     * Fallback 資料表名稱（schema class 未載入時使用）。
     *
     * @param string $suffix 表名後綴。
     * @return string
     */
    private function fallback_table_name($suffix) {
        global $wpdb;

        return $wpdb->prefix . $suffix;
    }

    /* =====================================================================
     * 題庫（Questions）
     * ================================================================== */

    /**
     * 新增題目。
     *
     * @param array $data 題目資料。
     * @return int 新增 ID。
     */
    public function create_question($data) {
        global $wpdb;

        $data = $this->sanitize_question_data($data);

        if (empty($data['question']) || empty($data['option_a']) || empty($data['option_b'])) {
            return 0;
        }

        $now = current_time('mysql');

        $insert_data = array(
            'question'       => $data['question'],
            'option_a'       => $data['option_a'],
            'option_b'       => $data['option_b'],
            'option_c'       => $data['option_c'],
            'option_d'       => $data['option_d'],
            'correct_option' => $data['correct_option'],
            'explanation'    => $data['explanation'],
            'difficulty'     => $data['difficulty'],
            'category'       => $data['category'],
            'source_faq_id'  => $data['source_faq_id'],
            'status'         => $data['status'],
            'review_status'  => $data['review_status'],
            'source'         => $data['source'],
            'admin_note'     => $data['admin_note'],
            'created_by'     => get_current_user_id(),
            'updated_by'     => get_current_user_id(),
            'created_at'     => $now,
            'updated_at'     => $now,
        );

        $result = $wpdb->insert(
            $this->questions_table,
            $insert_data,
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%d', '%s', '%s', '%s', '%s',
                '%d', '%d', '%s', '%s',
            )
        );

        if (false === $result) {
            return 0;
        }

        return absint($wpdb->insert_id);
    }

    /**
     * 更新題目。
     *
     * @param int   $id 題目 ID。
     * @param array $data 題目資料。
     * @return bool
     */
    public function update_question($id, $data) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $data = $this->sanitize_question_data($data);

        $update_data = array(
            'question'       => $data['question'],
            'option_a'       => $data['option_a'],
            'option_b'       => $data['option_b'],
            'option_c'       => $data['option_c'],
            'option_d'       => $data['option_d'],
            'correct_option' => $data['correct_option'],
            'explanation'    => $data['explanation'],
            'difficulty'     => $data['difficulty'],
            'category'       => $data['category'],
            'status'         => $data['status'],
            'review_status'  => $data['review_status'],
            'admin_note'     => $data['admin_note'],
            'updated_by'     => get_current_user_id(),
            'updated_at'     => current_time('mysql'),
        );

        $result = $wpdb->update(
            $this->questions_table,
            $update_data,
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );

        return false !== $result;
    }

    /**
     * 刪除題目。
     *
     * @param int $id 題目 ID。
     * @return bool
     */
    public function delete_question($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->delete($this->questions_table, array('id' => $id), array('%d'));

        return false !== $result;
    }

    /**
     * 查詢單一題目。
     *
     * @param int $id 題目 ID。
     * @return object|null
     */
    public function find_question($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->questions_table} WHERE id = %d", $id)
        );
    }

    /**
     * 依 ID 陣列查詢多筆題目（保留輸入順序）。
     *
     * @param array $ids 題目 ID 陣列。
     * @return array
     */
    public function find_questions_by_ids($ids) {
        global $wpdb;

        $ids = class_exists('UR_AI_Security')
            ? UR_AI_Security::sanitize_ids($ids)
            : array_values(array_unique(array_filter(array_map('absint', (array) $ids))));

        if (empty($ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->questions_table} WHERE id IN ({$placeholders})",
                $ids
            )
        );

        if (!is_array($rows)) {
            return array();
        }

        $by_id = array();
        foreach ($rows as $row) {
            $by_id[absint($row->id)] = $row;
        }

        $ordered = array();
        foreach ($ids as $id) {
            if (isset($by_id[$id])) {
                $ordered[] = $by_id[$id];
            }
        }

        return $ordered;
    }

    /**
     * 查詢題目列表（後台管理用）。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query_questions($args = array()) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            array(
                'status'        => '',
                'review_status' => '',
                'category'      => '',
                'search'        => '',
                'orderby'       => 'id',
                'order'         => 'DESC',
                'limit'         => 50,
                'offset'        => 0,
            )
        );

        list($where, $values) = $this->build_question_where($args);

        $orderby = in_array($args['orderby'], array('id', 'created_at', 'difficulty', 'category'), true)
            ? $args['orderby']
            : 'id';
        $order  = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit  = absint($args['limit']);
        $offset = absint($args['offset']);

        if ($limit <= 0) {
            $limit = 50;
        }

        $sql = "SELECT * FROM {$this->questions_table} WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$orderby} {$order}";
        $sql .= ' LIMIT %d OFFSET %d';

        $values[] = $limit;
        $values[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * 計算題目數量。
     *
     * @param array $args 查詢參數。
     * @return int
     */
    public function count_questions($args = array()) {
        global $wpdb;

        list($where, $values) = $this->build_question_where($args);

        $sql = "SELECT COUNT(*) FROM {$this->questions_table} WHERE " . implode(' AND ', $where);

        if (!empty($values)) {
            return absint($wpdb->get_var($wpdb->prepare($sql, $values)));
        }

        return absint($wpdb->get_var($sql));
    }

    /**
     * 查詢符合條件的全部題目 ID（不分頁），供「跨頁全選」批次操作使用。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query_question_ids($args = array()) {
        global $wpdb;

        list($where, $values) = $this->build_question_where($args);

        $sql = "SELECT id FROM {$this->questions_table} WHERE " . implode(' AND ', $where);

        if (!empty($values)) {
            $ids = $wpdb->get_col($wpdb->prepare($sql, $values));
        } else {
            $ids = $wpdb->get_col($sql);
        }

        return array_map('absint', is_array($ids) ? $ids : array());
    }

    /**
     * 隨機抽取指定數量的「已啟用且已審核」題目。
     *
     * @param int $count 抽題數量。
     * @return array
     */
    public function get_random_active_questions($count) {
        global $wpdb;

        $count = absint($count);

        if ($count <= 0) {
            return array();
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->questions_table}
             WHERE status = %s AND review_status = %s
             ORDER BY RAND()
             LIMIT %d",
            'active',
            'approved',
            $count
        );

        $rows = $wpdb->get_results($sql);

        return is_array($rows) ? $rows : array();
    }

    /**
     * 計算已啟用且已審核的題目總數（供健檢用）。
     *
     * @return int
     */
    public function count_active_questions() {
        return $this->count_questions(
            array(
                'status'        => 'active',
                'review_status' => 'approved',
            )
        );
    }

    /**
     * 建立題目查詢 WHERE 條件。
     *
     * @param array $args 查詢參數。
     * @return array [where 陣列, values 陣列]
     */
    private function build_question_where($args) {
        $where  = array('1=1');
        $values = array();

        if (!empty($args['status'])) {
            $where[]  = 'status = %s';
            $values[] = sanitize_key($args['status']);
        }

        if (!empty($args['review_status'])) {
            $where[]  = 'review_status = %s';
            $values[] = sanitize_key($args['review_status']);
        }

        if (!empty($args['category'])) {
            $where[]  = 'category = %s';
            $values[] = sanitize_text_field($args['category']);
        }

        if (!empty($args['search'])) {
            $search   = '%' . $GLOBALS['wpdb']->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[]  = 'question LIKE %s';
            $values[] = $search;
        }

        return array($where, $values);
    }

    /**
     * 清理題目資料。
     *
     * @param array $data 原始資料。
     * @return array
     */
    private function sanitize_question_data($data) {
        if (!is_array($data)) {
            $data = array();
        }

        $sanitize_text = function ($value) {
            return class_exists('UR_AI_Security')
                ? UR_AI_Security::sanitize_textarea($value)
                : sanitize_textarea_field((string) $value);
        };

        $correct_option = isset($data['correct_option']) ? strtolower(sanitize_key($data['correct_option'])) : 'a';
        if (!in_array($correct_option, array('a', 'b', 'c', 'd'), true)) {
            $correct_option = 'a';
        }

        $difficulty = isset($data['difficulty']) ? sanitize_key($data['difficulty']) : 'medium';
        if (!in_array($difficulty, array('easy', 'medium', 'hard'), true)) {
            $difficulty = 'medium';
        }

        $status = isset($data['status']) ? sanitize_key($data['status']) : 'inactive';
        if (!in_array($status, array('active', 'inactive'), true)) {
            $status = 'inactive';
        }

        $review_status = isset($data['review_status']) ? sanitize_key($data['review_status']) : 'draft';
        if (!in_array($review_status, array('draft', 'approved', 'rejected'), true)) {
            $review_status = 'draft';
        }

        $source = isset($data['source']) ? sanitize_key($data['source']) : 'manual';
        if (!in_array($source, array('manual', 'ai_faq', 'ai_article'), true)) {
            $source = 'manual';
        }

        $category = isset($data['category']) ? sanitize_text_field($data['category']) : '';

        if (function_exists('mb_substr')) {
            $category = mb_substr($category, 0, 100, 'UTF-8');
        } else {
            $category = substr($category, 0, 100);
        }

        return array(
            'question'       => $sanitize_text(isset($data['question']) ? $data['question'] : ''),
            'option_a'       => $sanitize_text(isset($data['option_a']) ? $data['option_a'] : ''),
            'option_b'       => $sanitize_text(isset($data['option_b']) ? $data['option_b'] : ''),
            'option_c'       => $sanitize_text(isset($data['option_c']) ? $data['option_c'] : ''),
            'option_d'       => $sanitize_text(isset($data['option_d']) ? $data['option_d'] : ''),
            'correct_option' => $correct_option,
            'explanation'    => $sanitize_text(isset($data['explanation']) ? $data['explanation'] : ''),
            'difficulty'     => $difficulty,
            'category'       => $category,
            'source_faq_id'  => isset($data['source_faq_id']) ? absint($data['source_faq_id']) : 0,
            'status'         => $status,
            'review_status'  => $review_status,
            'source'         => $source,
            'admin_note'     => sanitize_textarea_field(isset($data['admin_note']) ? $data['admin_note'] : ''),
        );
    }

    /* =====================================================================
     * 作答紀錄／排行榜（Attempts）
     * ================================================================== */

    /**
     * 新增一筆作答紀錄。
     *
     * @param array $data 作答資料。
     * @return int 新增 ID。
     */
    public function create_attempt($data) {
        global $wpdb;

        if (!is_array($data)) {
            return 0;
        }

        $nickname     = class_exists('UR_AI_Security') ? UR_AI_Security::sanitize_text($data['nickname'] ?? '') : sanitize_text_field((string) ($data['nickname'] ?? ''));
        $nickname     = function_exists('mb_substr') ? mb_substr($nickname, 0, 60, 'UTF-8') : substr($nickname, 0, 60);
        $nickname_key = self::normalize_nickname_key($nickname);

        $insert_data = array(
            'nickname'         => $nickname,
            'nickname_key'     => $nickname_key,
            'score'            => absint($data['score'] ?? 0),
            'total_questions'  => absint($data['total_questions'] ?? 0),
            'correct_count'    => absint($data['correct_count'] ?? 0),
            'duration_seconds' => absint($data['duration_seconds'] ?? 0),
            'ip_hash'          => sanitize_text_field((string) ($data['ip_hash'] ?? '')),
            'created_at'       => current_time('mysql'),
        );

        $result = $wpdb->insert(
            $this->attempts_table,
            $insert_data,
            array('%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s')
        );

        if (false === $result) {
            return 0;
        }

        return absint($wpdb->insert_id);
    }

    /**
     * 更新一筆作答紀錄的分數（用於「同暱稱只留最高分」覆蓋）。
     *
     * @param int   $id 紀錄 ID。
     * @param array $data 作答資料。
     * @return bool
     */
    public function update_attempt_score($id, $data) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            $this->attempts_table,
            array(
                'score'            => absint($data['score'] ?? 0),
                'total_questions'  => absint($data['total_questions'] ?? 0),
                'correct_count'    => absint($data['correct_count'] ?? 0),
                'duration_seconds' => absint($data['duration_seconds'] ?? 0),
                'created_at'       => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', '%d', '%d', '%d', '%s'),
            array('%d')
        );

        return false !== $result;
    }

    /**
     * 依正規化暱稱鍵值查詢既有紀錄（非空暱稱才需要比對，用於「同暱稱取最高分」）。
     *
     * @param string $nickname_key 正規化暱稱鍵值。
     * @return object|null
     */
    public function find_attempt_by_nickname_key($nickname_key) {
        global $wpdb;

        $nickname_key = sanitize_text_field((string) $nickname_key);

        if ('' === $nickname_key) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->attempts_table} WHERE nickname_key = %s ORDER BY score DESC LIMIT 1",
                $nickname_key
            )
        );
    }

    /**
     * 計算指定 IP hash 在時間範圍內的作答次數（節流用）。
     *
     * @param string $ip_hash IP hash。
     * @param string $since   起算時間（mysql datetime 字串）。
     * @return int
     */
    public function count_attempts_since($ip_hash, $since) {
        global $wpdb;

        $ip_hash = sanitize_text_field((string) $ip_hash);
        $since   = sanitize_text_field((string) $since);

        if ('' === $ip_hash || '' === $since) {
            return 0;
        }

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->attempts_table} WHERE ip_hash = %s AND created_at >= %s",
                    $ip_hash,
                    $since
                )
            )
        );
    }

    /**
     * 取得排行榜（依分數由高到低）。
     *
     * @param int $limit 顯示筆數。
     * @return array
     */
    public function get_leaderboard($limit = 20) {
        global $wpdb;

        $limit = absint($limit);

        if ($limit <= 0) {
            $limit = 20;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->attempts_table} ORDER BY score DESC, duration_seconds ASC, created_at ASC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * 刪除一筆作答紀錄（供後台排行榜管理下架用）。
     *
     * @param int $id 紀錄 ID。
     * @return bool
     */
    public function delete_attempt($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->delete($this->attempts_table, array('id' => $id), array('%d'));

        return false !== $result;
    }

    /**
     * 查詢作答紀錄列表（後台管理用）。
     *
     * @param int $limit 筆數。
     * @param int $offset 位移。
     * @return array
     */
    public function query_attempts($limit = 50, $offset = 0) {
        global $wpdb;

        $limit  = absint($limit) > 0 ? absint($limit) : 50;
        $offset = absint($offset);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->attempts_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * 計算作答紀錄總數。
     *
     * @return int
     */
    public function count_attempts() {
        global $wpdb;

        return absint($wpdb->get_var("SELECT COUNT(*) FROM {$this->attempts_table}"));
    }

    /**
     * 正規化暱稱鍵值（trim、轉小寫、去除多餘空白），供「同一人」比對使用。
     *
     * 空字串（匿名）一律回傳空字串，呼叫端不應對空字串做「同暱稱覆蓋」比對，
     * 每筆匿名作答都應視為獨立參與者，不與其他匿名紀錄合併。
     *
     * @param string $nickname 原始暱稱。
     * @return string
     */
    public static function normalize_nickname_key($nickname) {
        $nickname = trim((string) $nickname);

        if ('' === $nickname) {
            return '';
        }

        $nickname = preg_replace('/\s+/u', ' ', $nickname);

        if (function_exists('mb_strtolower')) {
            $nickname = mb_strtolower($nickname, 'UTF-8');
        } else {
            $nickname = strtolower($nickname);
        }

        return $nickname;
    }
}
