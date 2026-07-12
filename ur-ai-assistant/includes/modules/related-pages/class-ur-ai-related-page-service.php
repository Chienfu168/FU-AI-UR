<?php
/**
 * UR AI Assistant Related Page Service
 *
 * 相關頁面推薦服務層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Related_Page_Service
 */
class UR_AI_Related_Page_Service {

    /**
     * active 推薦頁面快取 key 前綴。
     *
     * 實際 key 會附上 limit，因為不同 limit 的查詢結果不同。
     *
     * @var string
     */
    const ACTIVE_PAGES_CACHE_PREFIX = 'ur_ai_active_pages_';

    /**
     * 預設快取存活秒數（TTL）。
     *
     * @var int
     */
    const ACTIVE_PAGES_CACHE_TTL = 1200;

    /**
     * Related Page Repository.
     *
     * @var UR_AI_Related_Page_Repository|null
     */
    private $repository = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->repository = class_exists('UR_AI_Related_Page_Repository')
            ? new UR_AI_Related_Page_Repository()
            : null;
    }

    /**
     * 依問題找出相關頁面。
     *
     * @param string $question 使用者問題。
     * @param int    $limit 筆數。
     * @return array
     */
    public function find_related_pages($question, $limit = 3) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return array();
        }

        $active_pages = $this->get_active_pages(500);

        if (empty($active_pages)) {
            return array();
        }

        $pages = $this->repository->find_related_by_question($question, $limit, $active_pages);

        return $this->format_many_for_frontend($pages);
    }

    /**
     * 取得啟用中的推薦頁面。
     *
     * 以 Transient 快取查詢結果，避免每次提問都重新查詢並對最多 500 筆
     * 資料重新評分（比照 UR_AI_FAQ_Service::get_active_faqs() 的快取做法）。
     * 內容類寫入（新增/更新/刪除/批次狀態切換）會清除此快取。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_active_pages($limit = 500) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return array();
        }

        $cache_key = self::ACTIVE_PAGES_CACHE_PREFIX . absint($limit);

        $cached = get_transient($cache_key);

        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        $pages = $this->repository->get_active_pages($limit);

        if (!is_array($pages)) {
            return $pages;
        }

        set_transient($cache_key, $pages, self::ACTIVE_PAGES_CACHE_TTL);

        $this->register_cache_key($cache_key);

        return $pages;
    }

    /**
     * 增加多筆曝光次數。
     *
     * @param array $pages_or_ids 推薦頁面資料或 ID 陣列。
     * @return int
     */
    public function increase_show_counts($pages_or_ids) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return 0;
        }

        $ids = $this->extract_ids($pages_or_ids);

        if (empty($ids)) {
            return 0;
        }

        return $this->repository->increase_show_counts($ids);
    }

    /**
     * 增加單筆曝光次數。
     *
     * @param int $id 推薦頁面 ID。
     * @return bool
     */
    public function increase_show_count($id) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return false;
        }

        return $this->repository->increase_show_count($id);
    }

    /**
     * 增加點擊次數。
     *
     * @param int $id 推薦頁面 ID。
     * @return bool
     */
    public function increase_click_count($id) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return false;
        }

        return $this->repository->increase_click_count($id);
    }

    /**
     * 新增推薦頁面。
     *
     * @param array $data 推薦頁面資料。
     * @return int
     */
    public function create($data) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return 0;
        }

        $result = $this->repository->create($data);

        if ($result) {
            $this->clear_active_pages_cache();
        }

        return $result;
    }

    /**
     * 更新推薦頁面。
     *
     * @param int   $id 推薦頁面 ID。
     * @param array $data 推薦頁面資料。
     * @return bool
     */
    public function update($id, $data) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return false;
        }

        $result = $this->repository->update($id, $data);

        if ($result) {
            $this->clear_active_pages_cache();
        }

        return $result;
    }

    /**
     * 刪除推薦頁面。
     *
     * @param int $id 推薦頁面 ID。
     * @return bool
     */
    public function delete($id) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return false;
        }

        $result = $this->repository->delete($id);

        if ($result) {
            $this->clear_active_pages_cache();
        }

        return $result;
    }

    /**
     * 批次刪除推薦頁面。
     *
     * @param array $ids 推薦頁面 ID 陣列。
     * @return int
     */
    public function bulk_delete($ids) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return 0;
        }

        $result = $this->repository->bulk_delete($ids);

        if ($result) {
            $this->clear_active_pages_cache();
        }

        return $result;
    }

    /**
     * 查詢單筆推薦頁面。
     *
     * @param int $id 推薦頁面 ID。
     * @return object|null
     */
    public function find($id) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return null;
        }

        return $this->repository->find($id);
    }

    /**
     * 依 URL 查詢推薦頁面。
     *
     * @param string $url URL。
     * @return object|null
     */
    public function find_by_url($url) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return null;
        }

        return $this->repository->find_by_url($url);
    }

    /**
     * 依來源文章 ID 查詢推薦頁面。
     *
     * @param int $post_id 文章 ID。
     * @return object|null
     */
    public function find_by_source_post_id($post_id) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return null;
        }

        return $this->repository->find_by_source_post_id($post_id);
    }

    /**
     * 批次依來源文章 ID 查詢既有推薦頁面（一次查詢取代逐筆查詢）。
     *
     * @param array $post_ids 文章 ID 陣列。
     * @return array source_post_id => object
     */
    public function find_existing_by_source_post_ids($post_ids) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return array();
        }

        return $this->repository->find_existing_by_source_post_ids($post_ids);
    }

    /**
     * 批次依 URL 查詢既有推薦頁面（一次查詢取代逐筆查詢）。
     *
     * @param array $urls URL 陣列。
     * @return array url => object
     */
    public function find_existing_by_urls($urls) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return array();
        }

        return $this->repository->find_existing_by_urls($urls);
    }

    /**
     * 查詢推薦頁面列表。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query($args = array()) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return array();
        }

        return $this->repository->query($args);
    }

    /**
     * 計算推薦頁面數量。
     *
     * @param array $args 查詢參數。
     * @return int
     */
    public function count($args = array()) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return 0;
        }

        return $this->repository->count($args);
    }

    /**
     * 查詢符合條件的全部 ID（不分頁），供「跨頁全選」批次操作使用。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query_ids($args = array()) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return array();
        }

        return $this->repository->query_ids($args);
    }

    /**
     * 批次啟用。
     *
     * @param array $ids 推薦頁面 ID 陣列。
     * @return int
     */
    public function bulk_activate($ids) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return 0;
        }

        $result = $this->repository->bulk_update_status($ids, 'active');

        if ($result) {
            $this->clear_active_pages_cache();
        }

        return $result;
    }

    /**
     * 批次停用。
     *
     * @param array $ids 推薦頁面 ID 陣列。
     * @return int
     */
    public function bulk_deactivate($ids) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return 0;
        }

        $result = $this->repository->bulk_update_status($ids, 'inactive');

        if ($result) {
            $this->clear_active_pages_cache();
        }

        return $result;
    }

    /**
     * 取得摘要統計。
     *
     * @return array
     */
    public function get_summary() {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return $this->empty_summary();
        }

        return wp_parse_args(
            $this->repository->get_summary(),
            $this->empty_summary()
        );
    }

    /**
     * 取得分類統計。
     *
     * @return array
     */
    public function get_category_stats() {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return array();
        }

        return $this->repository->get_category_stats();
    }

    /**
     * 取得低 CTR 推薦頁面。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_low_ctr_pages($limit = 20) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return array();
        }

        return $this->repository->get_low_ctr_pages($limit);
    }

    /**
     * 取得有曝光無點擊的推薦頁面。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_shown_no_click_pages($limit = 20) {
        if (!$this->repository instanceof UR_AI_Related_Page_Repository) {
            return array();
        }

        $pages = $this->repository->query(
            array(
                'orderby' => 'show_count',
                'order'   => 'DESC',
                'limit'   => absint($limit),
                'offset'  => 0,
            )
        );

        $filtered = array();

        foreach ($pages as $page) {
            $show_count  = absint($this->get_value($page, 'show_count', 0));
            $click_count = absint($this->get_value($page, 'click_count', 0));

            if ($show_count > 0 && 0 === $click_count) {
                $filtered[] = $page;
            }
        }

        return $filtered;
    }

    /**
     * 格式化前台單筆推薦頁面。
     *
     * @param object|array $page 推薦頁面。
     * @return array
     */
    public function format_for_frontend($page) {
        $id          = absint($this->get_value($page, 'id', 0));
        $title       = (string) $this->get_value($page, 'title', '');
        $url         = (string) $this->get_value($page, 'url', '');
        $description = (string) $this->get_value($page, 'description', '');
        $category    = (string) $this->get_value($page, 'category', '');

        if ($id <= 0 || '' === trim($title) || '' === trim($url)) {
            return array();
        }

        return array(
            'id'              => $id,
            'title'           => $title,
            'url'             => esc_url_raw($url),
            'description'     => class_exists('UR_AI_Formatter')
                ? UR_AI_Formatter::related_page_description($description, 70)
                : wp_strip_all_tags($description),
            'category'        => $category,
            'match_score'     => absint($this->get_value($page, 'match_score', 0)),
            'matched_keywords'=> $this->get_value($page, 'matched_keywords', array()),
        );
    }

    /**
     * 格式化多筆前台推薦頁面。
     *
     * @param array $pages 推薦頁面資料。
     * @return array
     */
    public function format_many_for_frontend($pages) {
        if (!is_array($pages)) {
            return array();
        }

        $items = array();

        foreach ($pages as $page) {
            $item = $this->format_for_frontend($page);

            if (!empty($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * 格式化後台列表資料。
     *
     * @param object|array $page 推薦頁面。
     * @return array
     */
    public function format_for_admin_list($page) {
        $show_count  = absint($this->get_value($page, 'show_count', 0));
        $click_count = absint($this->get_value($page, 'click_count', 0));

        $performance_status = class_exists('UR_AI_Schema_Related_Pages')
            ? UR_AI_Schema_Related_Pages::get_performance_status($show_count, $click_count)
            : 'observing';

        return array(
            'id'                    => absint($this->get_value($page, 'id', 0)),
            'category'              => (string) $this->get_value($page, 'category', ''),
            'title'                 => (string) $this->get_value($page, 'title', ''),
            'title_excerpt'         => $this->excerpt((string) $this->get_value($page, 'title', ''), 60),
            'url'                   => (string) $this->get_value($page, 'url', ''),
            'url_label'             => class_exists('UR_AI_Formatter')
                ? UR_AI_Formatter::url_label((string) $this->get_value($page, 'url', ''), 55)
                : (string) $this->get_value($page, 'url', ''),
            'description'           => (string) $this->get_value($page, 'description', ''),
            'description_excerpt'   => $this->excerpt((string) $this->get_value($page, 'description', ''), 80),
            'keywords'              => (string) $this->get_value($page, 'keywords', ''),
            'status'                => (string) $this->get_value($page, 'status', ''),
            'source'                => (string) $this->get_value($page, 'source', ''),
            'source_post_id'        => absint($this->get_value($page, 'source_post_id', 0)),
            'sort_order'            => absint($this->get_value($page, 'sort_order', 100)),
            'show_count'            => $show_count,
            'click_count'           => $click_count,
            'ctr'                   => class_exists('UR_AI_Schema_Related_Pages')
                ? UR_AI_Schema_Related_Pages::calculate_ctr($show_count, $click_count)
                : $this->calculate_ctr($show_count, $click_count),
            'performance_status'    => $performance_status,
            'performance_label'     => class_exists('UR_AI_Schema_Related_Pages')
                ? UR_AI_Schema_Related_Pages::get_performance_label($performance_status)
                : '',
            'maintenance_suggestion'=> class_exists('UR_AI_Schema_Related_Pages')
                ? UR_AI_Schema_Related_Pages::get_maintenance_suggestion($show_count, $click_count)
                : '',
            'created_at'            => (string) $this->get_value($page, 'created_at', ''),
            'updated_at'            => (string) $this->get_value($page, 'updated_at', ''),
        );
    }

    /**
     * 格式化多筆後台列表資料。
     *
     * @param array $pages 推薦頁面資料。
     * @return array
     */
    public function format_many_for_admin_list($pages) {
        if (!is_array($pages)) {
            return array();
        }

        $items = array();

        foreach ($pages as $page) {
            $items[] = $this->format_for_admin_list($page);
        }

        return $items;
    }

    /**
     * 準備匯出資料列。
     *
     * @param array $pages 推薦頁面資料。
     * @return array
     */
    public function prepare_export_rows($pages) {
        if (!is_array($pages)) {
            return array();
        }

        $rows = array();

        foreach ($pages as $page) {
            $show_count  = absint($this->get_value($page, 'show_count', 0));
            $click_count = absint($this->get_value($page, 'click_count', 0));

            $rows[] = array(
                'id'             => absint($this->get_value($page, 'id', 0)),
                'category'       => (string) $this->get_value($page, 'category', ''),
                'title'          => (string) $this->get_value($page, 'title', ''),
                'url'            => (string) $this->get_value($page, 'url', ''),
                'description'    => (string) $this->get_value($page, 'description', ''),
                'keywords'       => (string) $this->get_value($page, 'keywords', ''),
                'status'         => (string) $this->get_value($page, 'status', ''),
                'source'         => (string) $this->get_value($page, 'source', ''),
                'source_post_id' => absint($this->get_value($page, 'source_post_id', 0)),
                'sort_order'     => absint($this->get_value($page, 'sort_order', 100)),
                'show_count'     => $show_count,
                'click_count'    => $click_count,
                'ctr'            => class_exists('UR_AI_Schema_Related_Pages')
                    ? UR_AI_Schema_Related_Pages::calculate_ctr($show_count, $click_count) . '%'
                    : $this->calculate_ctr($show_count, $click_count) . '%',
                'admin_note'     => (string) $this->get_value($page, 'admin_note', ''),
                'created_at'     => (string) $this->get_value($page, 'created_at', ''),
                'updated_at'     => (string) $this->get_value($page, 'updated_at', ''),
            );
        }

        return $rows;
    }

    /**
     * 取得分類選項。
     *
     * @return array
     */
    public function get_categories() {
        if (class_exists('UR_AI_Schema_Related_Pages')) {
            return UR_AI_Schema_Related_Pages::get_default_categories();
        }

        return array(
            '都市更新',
            '危老重建',
            '更新會',
            '自主更新',
            '權利變換',
            '協議合建',
            '行政救濟',
            '同意與程序',
            '信託與資金控管',
            '估價與分配',
            '共同負擔',
            '其他',
            '待分類',
        );
    }

    /**
     * 取得狀態選項。
     *
     * @return array
     */
    public function get_statuses() {
        if (class_exists('UR_AI_Schema_Related_Pages')) {
            return UR_AI_Schema_Related_Pages::get_statuses();
        }

        return array(
            'active'   => __('啟用', 'ur-ai-assistant'),
            'inactive' => __('停用', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得來源選項。
     *
     * @return array
     */
    public function get_sources() {
        if (class_exists('UR_AI_Schema_Related_Pages')) {
            return UR_AI_Schema_Related_Pages::get_sources();
        }

        return array(
            'manual' => __('手動建立', 'ur-ai-assistant'),
            'post'   => __('WordPress 文章匯入', 'ur-ai-assistant'),
            'page'   => __('WordPress 頁面匯入', 'ur-ai-assistant'),
            'import' => __('CSV 匯入', 'ur-ai-assistant'),
        );
    }

    /**
     * 從資料中抽出 ID 陣列。
     *
     * @param array $items 資料或 ID。
     * @return array
     */
    private function extract_ids($items) {
        if (!is_array($items)) {
            return array();
        }

        $ids = array();

        foreach ($items as $item) {
            if (is_numeric($item)) {
                $ids[] = absint($item);
                continue;
            }

            $id = absint($this->get_value($item, 'id', 0));

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * 空摘要。
     *
     * @return array
     */
    private function empty_summary() {
        return array(
            'total'             => 0,
            'active'            => 0,
            'inactive'          => 0,
            'manual'            => 0,
            'from_post'         => 0,
            'from_page'         => 0,
            'high_exposure'     => 0,
            'shown_no_click'    => 0,
            'low_ctr'           => 0,
            'total_show_count'  => 0,
            'total_click_count' => 0,
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

    /**
     * 計算 CTR。
     *
     * @param int $show_count 曝光。
     * @param int $click_count 點擊。
     * @return float
     */
    private function calculate_ctr($show_count, $click_count) {
        $show_count  = absint($show_count);
        $click_count = absint($click_count);

        if ($show_count <= 0) {
            return 0.0;
        }

        return round(($click_count / $show_count) * 100, 2);
    }

    /**
     * 註冊一個快取 key 至索引（供日後清除）。
     *
     * @param string $cache_key 快取 key。
     * @return void
     */
    private function register_cache_key($cache_key) {
        $index = get_option($this->cache_index_option_name(), array());

        if (!is_array($index)) {
            $index = array();
        }

        if (!in_array($cache_key, $index, true)) {
            $index[] = $cache_key;
            update_option($this->cache_index_option_name(), $index, false);
        }
    }

    /**
     * 清除所有 active 推薦頁面快取。
     *
     * @return void
     */
    public function clear_active_pages_cache() {
        $index = get_option($this->cache_index_option_name(), array());

        if (is_array($index)) {
            foreach ($index as $cache_key) {
                if (is_string($cache_key) && '' !== $cache_key) {
                    delete_transient($cache_key);
                }
            }
        }

        // 保險：即使 index 因並發寫入而不完整，仍涵蓋常見的 limit 變體。
        $fallback_limits = array(500, 100, 50, 0);

        foreach ($fallback_limits as $limit) {
            delete_transient(self::ACTIVE_PAGES_CACHE_PREFIX . absint($limit));
        }

        update_option($this->cache_index_option_name(), array(), false);
    }

    /**
     * 快取 key 索引的 option 名稱。
     *
     * @return string
     */
    private function cache_index_option_name() {
        return 'ur_ai_active_pages_cache_index';
    }
}