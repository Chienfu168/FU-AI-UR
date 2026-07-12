<?php
/**
 * UR AI Assistant Related Page Repository
 *
 * 相關頁面推薦資料庫存取層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Related_Page_Repository
 */
class UR_AI_Related_Page_Repository {

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
        $this->table_name = class_exists('UR_AI_Schema_Related_Pages')
            ? UR_AI_Schema_Related_Pages::get_table_name()
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
     * 新增推薦頁面。
     *
     * @param array $data 推薦頁面資料。
     * @return int 新增 ID。
     */
    public function create($data) {
        global $wpdb;

        $data = $this->sanitize_data($data, 'create');

        if (empty($data['title']) || empty($data['url'])) {
            return 0;
        }

        $now = current_time('mysql');

        $insert_data = array(
            'category'       => $data['category'],
            'title'          => $data['title'],
            'url'            => $data['url'],
            'description'    => $data['description'],
            'keywords'       => $data['keywords'],
            'status'         => $data['status'],
            'source'         => $data['source'],
            'source_post_id' => $data['source_post_id'],
            'sort_order'     => $data['sort_order'],
            'show_count'     => 0,
            'click_count'    => 0,
            'admin_note'     => $data['admin_note'],
            'created_by'     => get_current_user_id(),
            'updated_by'     => get_current_user_id(),
            'created_at'     => $now,
            'updated_at'     => $now,
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
                '%s',
                '%d',
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
     * 更新推薦頁面。
     *
     * @param int   $id 推薦頁面 ID。
     * @param array $data 推薦頁面資料。
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
            'category'    => $data['category'],
            'title'       => $data['title'],
            'url'         => $data['url'],
            'description' => $data['description'],
            'keywords'    => $data['keywords'],
            'status'      => $data['status'],
            'sort_order'  => $data['sort_order'],
            'admin_note'  => $data['admin_note'],
            'updated_by'  => get_current_user_id(),
            'updated_at'  => current_time('mysql'),
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
     * 刪除推薦頁面。
     *
     * @param int $id 推薦頁面 ID。
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
     * 批次刪除推薦頁面。
     *
     * @param array $ids 推薦頁面 ID 陣列。
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
     * 查詢單筆推薦頁面。
     *
     * @param int $id 推薦頁面 ID。
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
     * 依 URL 查詢推薦頁面。
     *
     * @param string $url URL。
     * @return object|null
     */
    public function find_by_url($url) {
        global $wpdb;

        $url = esc_url_raw($url);

        if ('' === $url) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE url = %s LIMIT 1",
                $url
            )
        );
    }

    /**
     * 依來源文章 ID 查詢推薦頁面。
     *
     * @param int $post_id 文章 ID。
     * @return object|null
     */
    public function find_by_source_post_id($post_id) {
        global $wpdb;

        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE source_post_id = %d LIMIT 1",
                $post_id
            )
        );
    }

    /**
     * 批次依來源文章 ID 查詢既有推薦頁面（一次查詢取代逐筆查詢）。
     *
     * 供匯入流程（掃描／批次匯入多篇文章）使用，避免每篇文章各自查詢一次。
     *
     * @param array $post_ids 文章 ID 陣列。
     * @return array source_post_id => object
     */
    public function find_existing_by_source_post_ids($post_ids) {
        global $wpdb;

        $post_ids = array_values(array_unique(array_filter(array_map('absint', (array) $post_ids))));

        if (empty($post_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE source_post_id IN ({$placeholders})",
                $post_ids
            )
        );

        $map = array();

        foreach ((array) $rows as $row) {
            if (isset($row->source_post_id)) {
                $map[(int) $row->source_post_id] = $row;
            }
        }

        return $map;
    }

    /**
     * 批次依 URL 查詢既有推薦頁面（一次查詢取代逐筆查詢）。
     *
     * @param array $urls URL 陣列。
     * @return array url => object
     */
    public function find_existing_by_urls($urls) {
        global $wpdb;

        $urls = array_values(array_unique(array_filter(array_map('esc_url_raw', (array) $urls))));

        if (empty($urls)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($urls), '%s'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE url IN ({$placeholders})",
                $urls
            )
        );

        $map = array();

        foreach ((array) $rows as $row) {
            if (isset($row->url)) {
                $map[$row->url] = $row;
            }
        }

        return $map;
    }

    /**
     * 查詢推薦頁面列表。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query($args = array()) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            array(
                'status'   => '',
                'category' => '',
                'source'   => '',
                'search'   => '',
                'orderby'  => 'sort_order',
                'order'    => 'ASC',
                'limit'    => 50,
                'offset'   => 0,
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
     * 計算推薦頁面數量。
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
     * 取得啟用中的推薦頁面。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_active_pages($limit = 500) {
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
     * 搜尋與問題相關的推薦頁面。
     *
     * @param string     $question 使用者問題。
     * @param int        $limit 筆數。
     * @param array|null $pages 預先取得的啟用中頁面列表；未提供時內部查詢
     *                           （呼叫端若已有快取結果，可傳入以避免重複查詢）。
     * @return array
     */
    public function find_related_by_question($question, $limit = 3, $pages = null) {
        $question = is_string($question) ? trim($question) : '';

        if ('' === $question) {
            return array();
        }

        if (null === $pages) {
            $pages = $this->get_active_pages(500);
        }

        if (empty($pages)) {
            return array();
        }

        $scored = array();

        foreach ($pages as $page) {
            $score_data = $this->score_page($question, $page);

            if ($score_data['score'] <= 0) {
                continue;
            }

            $scored[] = array(
                'page'             => $page,
                'score'            => $score_data['score'],
                'matched_keywords' => $score_data['matched_keywords'],
            );
        }

        if (empty($scored)) {
            return array();
        }

        usort(
            $scored,
            function ($a, $b) {
                if ($a['score'] === $b['score']) {
                    $a_order = isset($a['page']->sort_order) ? absint($a['page']->sort_order) : 100;
                    $b_order = isset($b['page']->sort_order) ? absint($b['page']->sort_order) : 100;

                    return $a_order <=> $b_order;
                }

                return $b['score'] <=> $a['score'];
            }
        );

        $limit  = absint($limit);
        $limit  = $limit > 0 ? $limit : 3;
        $result = array();

        foreach (array_slice($scored, 0, $limit) as $item) {
            $page = $item['page'];

            $page->match_score      = $item['score'];
            $page->matched_keywords = $item['matched_keywords'];

            $result[] = $page;
        }

        return $result;
    }

    /**
     * 增加曝光次數。
     *
     * @param int $id 推薦頁面 ID。
     * @return bool
     */
    public function increase_show_count($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name}
                 SET show_count = show_count + 1,
                     updated_at = %s
                 WHERE id = %d",
                current_time('mysql'),
                $id
            )
        );

        return false !== $result;
    }

    /**
     * 批次增加曝光次數。
     *
     * @param array $ids 推薦頁面 ID 陣列。
     * @return int 影響筆數。
     */
    public function increase_show_counts($ids) {
        global $wpdb;

        $ids = class_exists('UR_AI_Security')
            ? UR_AI_Security::sanitize_ids($ids)
            : array_map('absint', (array) $ids);

        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $values = array_merge(
            array(
                current_time('mysql'),
            ),
            $ids
        );

        $sql = "UPDATE {$this->table_name}
                SET show_count = show_count + 1,
                    updated_at = %s
                WHERE id IN ({$placeholders})";

        $result = $wpdb->query(
            $wpdb->prepare($sql, $values)
        );

        return false === $result ? 0 : absint($result);
    }

    /**
     * 增加點擊次數。
     *
     * @param int $id 推薦頁面 ID。
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
     * @param array  $ids 推薦頁面 ID 陣列。
     * @param string $status 狀態 active / inactive。
     * @return int 影響筆數。
     */
    public function bulk_update_status($ids, $status) {
        global $wpdb;

        $ids = class_exists('UR_AI_Security')
            ? UR_AI_Security::sanitize_ids($ids)
            : array_map('absint', (array) $ids);

        $ids = array_values(array_unique(array_filter($ids)));
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
            'total'              => $this->count(),
            'active'             => $this->count(array('status' => 'active')),
            'inactive'           => $this->count(array('status' => 'inactive')),
            'manual'             => $this->count(array('source' => 'manual')),
            'from_post'          => $this->count(array('source' => 'post')),
            'from_page'          => $this->count(array('source' => 'page')),
            'high_exposure'      => $this->count_high_exposure(),
            'shown_no_click'     => $this->count_shown_no_click(),
            'low_ctr'            => $this->count_low_ctr(),
            'total_show_count'   => $this->sum_column('show_count'),
            'total_click_count'  => $this->sum_column('click_count'),
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
                    SUM(show_count) AS show_count,
                    SUM(click_count) AS click_count
             FROM {$this->table_name}
             GROUP BY category
             ORDER BY total DESC"
        );
    }

    /**
     * 取得低 CTR 推薦頁面。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_low_ctr_pages($limit = 20) {
        global $wpdb;

        $limit = absint($limit);

        if ($limit <= 0) {
            $limit = 20;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$this->table_name}
                 WHERE show_count >= %d
                   AND click_count > 0
                   AND (click_count / show_count) < %f
                 ORDER BY show_count DESC
                 LIMIT %d",
                20,
                0.03,
                $limit
            )
        );
    }

    /**
     * 取得有曝光無點擊的推薦頁面。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_shown_no_click_pages($limit = 20) {
        return $this->query(
            array(
                'orderby' => 'show_count',
                'order'   => 'DESC',
                'limit'   => absint($limit),
                'offset'  => 0,
            )
        );
    }

    /**
     * 計算高曝光推薦頁面數。
     *
     * @return int
     */
    private function count_high_exposure() {
        global $wpdb;

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE show_count >= %d",
                    20
                )
            )
        );
    }

    /**
     * 計算有曝光無點擊數。
     *
     * @return int
     */
    private function count_shown_no_click() {
        global $wpdb;

        return absint(
            $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE show_count > 0 AND click_count = 0"
            )
        );
    }

    /**
     * 計算低 CTR 數。
     *
     * @return int
     */
    private function count_low_ctr() {
        global $wpdb;

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name}
                     WHERE show_count >= %d
                       AND click_count > 0
                       AND (click_count / show_count) < %f",
                    20,
                    0.03
                )
            )
        );
    }

    /**
     * 統計欄位總和。
     *
     * @param string $column 欄位名稱。
     * @return int
     */
    private function sum_column($column) {
        global $wpdb;

        $column = sanitize_key($column);

        if (!in_array($column, array('show_count', 'click_count'), true)) {
            return 0;
        }

        return absint($wpdb->get_var("SELECT SUM({$column}) FROM {$this->table_name}"));
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

        if ('' !== $args['search']) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';

            $where[]  = '(title LIKE %s OR description LIKE %s OR keywords LIKE %s OR category LIKE %s OR url LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        return array($where, $values);
    }

    /**
     * 計算單筆推薦頁面與問題的相關分數。
     *
     * @param string $question 問題。
     * @param object $page 推薦頁面。
     * @return array
     */
    private function score_page($question, $page) {
        $question = $this->normalize_text($question);
        $score = 0;
        $matched_keywords = array();

        $keywords = isset($page->keywords) ? $page->keywords : '';

        if (class_exists('UR_AI_Helper')) {
            $keyword_items = UR_AI_Helper::keywords_to_array($keywords);
        } else {
            $keyword_items = explode(',', (string) $keywords);
        }

        foreach ($keyword_items as $keyword) {
            $keyword = trim((string) $keyword);

            if ('' === $keyword) {
                continue;
            }

            if (false !== mb_stripos($question, $keyword, 0, 'UTF-8')) {
                $matched_keywords[] = $keyword;

                $length = function_exists('mb_strlen') ? mb_strlen($keyword, 'UTF-8') : strlen($keyword);

                if ($length >= 5) {
                    $score += 18;
                } elseif ($length >= 3) {
                    $score += 12;
                } else {
                    $score += 6;
                }
            }
        }

        $category = isset($page->category) ? $this->normalize_text($page->category) : '';

        if ('' !== $category && false !== mb_stripos($question, $category, 0, 'UTF-8')) {
            $score += 8;
        }

        $title = isset($page->title) ? $this->normalize_text($page->title) : '';

        if ('' !== $title && false !== mb_stripos($title, $question, 0, 'UTF-8')) {
            $score += 20;
        }

        return array(
            'score'            => min(absint($score), 100),
            'matched_keywords' => array_values(array_unique($matched_keywords)),
        );
    }

    /**
     * 清理推薦頁面資料。
     *
     * @param array  $data 原始資料。
     * @param string $context create/update.
     * @return array
     */
    private function sanitize_data($data, $context = 'create') {
        if (!is_array($data)) {
            $data = array();
        }

        $category    = isset($data['category']) ? $data['category'] : '待分類';
        $title       = isset($data['title']) ? $data['title'] : '';
        $url         = isset($data['url']) ? $data['url'] : '';
        $description = isset($data['description']) ? $data['description'] : '';
        $keywords    = isset($data['keywords']) ? $data['keywords'] : '';

        if (class_exists('UR_AI_Security')) {
            $category    = UR_AI_Security::sanitize_category($category);
            $title       = UR_AI_Security::sanitize_text($title);
            $url         = UR_AI_Security::sanitize_url($url);
            $description = UR_AI_Security::sanitize_textarea($description);
            $keywords    = UR_AI_Security::sanitize_keywords($keywords);
        } else {
            $category    = sanitize_text_field($category);
            $title       = sanitize_text_field($title);
            $url         = esc_url_raw($url);
            $description = sanitize_textarea_field($description);
            $keywords    = sanitize_text_field($keywords);
        }

        $status = isset($data['status']) ? sanitize_key($data['status']) : 'inactive';

        if (!in_array($status, array('active', 'inactive'), true)) {
            $status = 'inactive';
        }

        $source = isset($data['source']) ? sanitize_key($data['source']) : 'manual';

        if (!in_array($source, array('manual', 'post', 'page', 'import'), true)) {
            $source = 'manual';
        }

        return array(
            'category'       => $category ? $category : '待分類',
            'title'          => $title,
            'url'            => $url,
            'description'    => $description,
            'keywords'       => $keywords,
            'status'         => $status,
            'source'         => $source,
            'source_post_id' => isset($data['source_post_id']) ? absint($data['source_post_id']) : 0,
            'sort_order'     => isset($data['sort_order']) ? absint($data['sort_order']) : 100,
            'admin_note'     => isset($data['admin_note']) ? sanitize_textarea_field($data['admin_note']) : '',
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
            'source_post_id',
            'sort_order',
            'show_count',
            'click_count',
            'created_at',
            'updated_at',
        );

        return in_array($orderby, $allowed, true) ? $orderby : 'sort_order';
    }

    /**
     * 正規化文字。
     *
     * @param mixed $text 原始文字。
     * @return string
     */
    private function normalize_text($text) {
        if (!is_scalar($text)) {
            return '';
        }

        $text = wp_strip_all_tags((string) $text);
        $text = str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * fallback 資料表名稱。
     *
     * @return string
     */
    private function fallback_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_related_pages';
    }
}