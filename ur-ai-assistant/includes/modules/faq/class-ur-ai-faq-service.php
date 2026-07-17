<?php
/**
 * UR AI Assistant FAQ Service
 *
 * FAQ 知識庫服務層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_Service
 */
class UR_AI_FAQ_Service {

    /**
     * active FAQ 快取 key 前綴。
     *
     * 實際 key 會附上 limit，因為不同 limit 的查詢結果不同。
     *
     * @var string
     */
    const ACTIVE_FAQS_CACHE_PREFIX = 'ur_ai_active_faqs_';

    /**
     * active FAQ 分類清單快取 key（供知識庫瀏覽的分類篩選使用）。
     *
     * @var string
     */
    const ACTIVE_CATEGORIES_CACHE_KEY = 'ur_ai_active_faq_categories';

    /**
     * 預設快取存活秒數（TTL）。
     *
     * 可透過 filter 'ur_ai_active_faqs_cache_ttl' 調整。
     *
     * @var int
     */
    const ACTIVE_FAQS_CACHE_TTL = 1200;

    /**
     * FAQ Repository.
     *
     * @var UR_AI_FAQ_Repository|null
     */
    private $repository = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->repository = class_exists('UR_AI_FAQ_Repository')
            ? new UR_AI_FAQ_Repository()
            : null;
    }

    /**
     * 新增 FAQ。
     *
     * @param array $data FAQ 資料。
     * @return int
     */
    public function create($data) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return 0;
        }

        $result = $this->repository->create($data);

        if ($result) {
            $this->clear_active_faqs_cache();
        }

        return $result;
    }

    /**
     * 更新 FAQ。
     *
     * @param int   $id FAQ ID。
     * @param array $data FAQ 資料。
     * @return bool
     */
    public function update($id, $data) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return false;
        }

        $result = $this->repository->update($id, $data);

        if ($result) {
            $this->clear_active_faqs_cache();
        }

        return $result;
    }

    /**
     * 刪除 FAQ。
     *
     * @param int $id FAQ ID。
     * @return bool
     */
    public function delete($id) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return false;
        }

        $result = $this->repository->delete($id);

        if ($result) {
            $this->clear_active_faqs_cache();
        }

        return $result;
    }

    /**
     * 批次刪除 FAQ。
     *
     * @param array $ids FAQ ID 陣列。
     * @return int
     */
    public function bulk_delete($ids) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return 0;
        }

        $result = $this->repository->bulk_delete($ids);

        if ($result) {
            $this->clear_active_faqs_cache();
        }

        return $result;
    }

    /**
     * 查詢單筆 FAQ。
     *
     * @param int $id FAQ ID。
     * @return object|null
     */
    public function find($id) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return null;
        }

        return $this->repository->find($id);
    }

    /**
     * 依來源問答紀錄 ID 查詢 FAQ。
     *
     * @param int $log_id 問答紀錄 ID。
     * @return object|null
     */
    public function find_by_source_log_id($log_id) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return null;
        }

        return $this->repository->find_by_source_log_id($log_id);
    }

    /**
     * 依標準問題文字找出既有 FAQ（供匯入覆蓋判斷）。
     *
     * @param string $question 標準問題。
     * @return object|null
     */
    public function find_by_question($question) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return null;
        }

        return $this->repository->find_by_question($question);
    }

    /**
     * 批次匯入 FAQ（CSV）。
     *
     * upsert 規則：以「標準問題」文字完全相同判斷。
     *   - 已存在 → 更新該筆的分類、回答、關鍵字、狀態、排序（覆蓋）。
     *   - 不存在 → 新增，來源標記為 import。
     *
     * 必填：question、answer。缺任一即略過該列並計入 skipped。
     * 其餘欄位留空時，交由 repository 的 sanitize_data 套用預設
     * （分類→待分類、狀態→inactive、排序→100）。
     *
     * @param array $rows 已解析並正規化的資料列陣列，每列為關聯陣列。
     * @return array {
     *     @type int   $created  新增筆數。
     *     @type int   $updated  更新筆數。
     *     @type int   $skipped  略過筆數（缺必填或寫入失敗）。
     *     @type int   $total    有效處理的資料列總數。
     * }
     */
    public function import_rows($rows) {
        $result = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'total'   => 0,
        );

        if (!is_array($rows) || empty($rows)) {
            return $result;
        }

        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return $result;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $result['skipped']++;
                continue;
            }

            $question = isset($row['question']) ? trim((string) $row['question']) : '';
            $answer   = isset($row['answer']) ? trim((string) $row['answer']) : '';

            // 必填缺漏：略過。
            if ('' === $question || '' === $answer) {
                $result['skipped']++;
                continue;
            }

            $result['total']++;

            $data = array(
                'category'   => isset($row['category']) ? (string) $row['category'] : '',
                'question'   => $question,
                'answer'     => $answer,
                'keywords'   => isset($row['keywords']) ? (string) $row['keywords'] : '',
                'status'     => isset($row['status']) ? (string) $row['status'] : 'inactive',
                'sort_order' => isset($row['sort_order']) ? absint($row['sort_order']) : 100,
                'source'     => 'import',
            );

            $existing = $this->repository->find_by_question($question);

            if ($existing && isset($existing->id) && absint($existing->id) > 0) {
                $updated = $this->repository->update(absint($existing->id), $data);

                if ($updated) {
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                }
            } else {
                $id = $this->repository->create($data);

                if ($id > 0) {
                    $result['created']++;
                } else {
                    $result['skipped']++;
                }
            }
        }

        // 內容有變動才清快取。
        if ($result['created'] > 0 || $result['updated'] > 0) {
            $this->clear_active_faqs_cache();
        }

        return $result;
    }

    /**
     * 查詢 FAQ 列表。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query($args = array()) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return array();
        }

        return $this->repository->query($args);
    }

    /**
     * 依分類找出相關 FAQ（排除自己），供前台問答助理在回答下方推薦
     * 「你也許想知道」的其他相關問答。
     *
     * @param int    $faq_id 目前這則 FAQ 的 ID（會被排除）。
     * @param string $category 分類名稱。
     * @param int    $limit 最多回傳幾筆。
     * @return array
     */
    public function find_related($faq_id, $category, $limit = 3) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return array();
        }

        $faq_id   = absint($faq_id);
        $category = trim((string) $category);
        $limit    = max(1, absint($limit));

        if ('' === $category) {
            return array();
        }

        $rows = $this->repository->query(
            array(
                'category' => $category,
                'status'   => 'active',
                'orderby'  => 'hit_count',
                'order'    => 'DESC',
                'limit'    => $limit + 1,
            )
        );

        $related = array();

        foreach ($rows as $row) {
            if (absint($row->id) === $faq_id) {
                continue;
            }

            $related[] = $row;

            if (count($related) >= $limit) {
                break;
            }
        }

        return $related;
    }

    /**
     * 計算 FAQ 數量。
     *
     * @param array $args 查詢參數。
     * @return int
     */
    public function count($args = array()) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return 0;
        }

        return $this->repository->count($args);
    }

    /**
     * 依篩選條件查出全部符合條件的 FAQ ID（不分頁），供跨頁全選批次操作使用。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query_ids($args = array()) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return array();
        }

        return $this->repository->query_ids($args);
    }

    /**
     * 取得啟用中的 FAQ。
     *
     * M1 快取：以 Transient 快取查詢結果，降低每次提問的全表查詢。
     * 內容類寫入（新增/更新/刪除/批次狀態切換）會清除此快取；
     * increase_hit_count() 為高頻寫入但「不影響比對結果」，刻意不清快取，
     * 否則快取會被命中流量持續打穿，失去意義。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_active_faqs($limit = 1000) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return array();
        }

        // 注意：$limit 原封不動傳給 repository，保持與原始行為 100% 一致
        // （例如 limit=0 時，repository 內部仍會套用其預設值，不在此改變）。
        // 僅在組合快取 key 時做正規化，避免 key 出現非預期字元。
        $cache_key = self::ACTIVE_FAQS_CACHE_PREFIX . absint($limit);

        $cached = get_transient($cache_key);

        // 命中快取（快取空陣列也是有效結果，故以 false 區分「未快取」）。
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        $faqs = $this->repository->get_active_faqs($limit);

        // 僅在取得有效陣列時才快取；非陣列（理論上不會發生）直接回傳不污染快取。
        if (!is_array($faqs)) {
            return $faqs;
        }

        set_transient($cache_key, $faqs, $this->get_active_faqs_cache_ttl());

        // 記錄此 limit 的快取 key，供清除時逐一移除。
        $this->register_cache_key($cache_key);

        return $faqs;
    }

    /**
     * 取得目前有啟用中 FAQ 使用的分類清單（供前台知識庫瀏覽的分類篩選）。
     *
     * 快取方式與 get_active_faqs() 相同：內容類寫入會清除此快取。
     *
     * @return array
     */
    public function get_active_categories() {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return array();
        }

        $cache_key = self::ACTIVE_CATEGORIES_CACHE_KEY;
        $cached    = get_transient($cache_key);

        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        $categories = $this->repository->get_active_categories();

        if (!is_array($categories)) {
            return $categories;
        }

        set_transient($cache_key, $categories, $this->get_active_faqs_cache_ttl());
        $this->register_cache_key($cache_key);

        return $categories;
    }

    /**
     * 前台知識庫瀏覽查詢：僅限 active 狀態，支援關鍵字＋分類篩選與分頁。
     *
     * 直接回傳問答內容本身（不經過 FAQ 比對演算法、不呼叫 AI），
     * 供獨立的「瀏覽／搜尋知識庫」功能使用。刻意不快取查詢結果本身
     * （查詢條件組合多變，快取效益低），僅快取上面的分類清單。
     *
     * @param array $args {
     *     @type string $search   關鍵字（可留空）。
     *     @type string $category 分類篩選（可留空＝不篩選）。
     *     @type int    $paged    頁碼（從 1 起）。
     *     @type int    $per_page 每頁筆數。
     * }
     * @return array{ items: array, total: int, per_page: int, paged: int, total_pages: int }
     */
    public function browse($args = array()) {
        $empty_result = array(
            'items'       => array(),
            'total'       => 0,
            'per_page'    => 0,
            'paged'       => 1,
            'total_pages' => 0,
        );

        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return $empty_result;
        }

        $args = is_array($args) ? $args : array();

        $per_page = isset($args['per_page']) ? absint($args['per_page']) : 10;
        $per_page = UR_AI_Security::int_range($per_page, 1, 50, 10);

        $paged = isset($args['paged']) ? absint($args['paged']) : 1;
        $paged = $paged > 0 ? $paged : 1;

        $query_args = array(
            'status'   => 'active',
            'category' => isset($args['category']) ? (string) $args['category'] : '',
            'search'   => isset($args['search']) ? (string) $args['search'] : '',
            'orderby'  => 'sort_order',
            'order'    => 'ASC',
            'limit'    => $per_page,
            'offset'   => ($paged - 1) * $per_page,
        );

        $total = $this->repository->count($query_args);
        $items = $this->repository->query($query_args);

        return array(
            'items'       => $this->format_many_for_frontend($items),
            'total'       => absint($total),
            'per_page'    => $per_page,
            'paged'       => $paged,
            'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
        );
    }

    /**
     * 增加 FAQ 命中次數。
     *
     * @param int $id FAQ ID。
     * @return bool
     */
    public function increase_hit_count($id) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return false;
        }

        return $this->repository->increase_hit_count($id);
    }

    /**
     * 批次啟用 FAQ。
     *
     * @param array $ids FAQ ID 陣列。
     * @return int
     */
    public function bulk_activate($ids) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return 0;
        }

        $result = $this->repository->bulk_update_status($ids, 'active');

        if ($result) {
            $this->clear_active_faqs_cache();
        }

        return $result;
    }

    /**
     * 批次停用 FAQ。
     *
     * @param array $ids FAQ ID 陣列。
     * @return int
     */
    public function bulk_deactivate($ids) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return 0;
        }

        $result = $this->repository->bulk_update_status($ids, 'inactive');

        if ($result) {
            $this->clear_active_faqs_cache();
        }

        return $result;
    }

    /**
     * 取得 FAQ 摘要統計。
     *
     * @return array
     */
    public function get_summary() {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
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
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return array();
        }

        return $this->repository->get_category_stats();
    }

    /**
     * 取得高命中 FAQ。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_top_hit_faqs($limit = 10) {
        if (!$this->repository instanceof UR_AI_FAQ_Repository) {
            return array();
        }

        return $this->repository->get_top_hit_faqs($limit);
    }

    /**
     * 格式化前台 FAQ 資料。
     *
     * @param object|array $faq FAQ 資料。
     * @return array
     */
    public function format_for_frontend($faq) {
        $id       = absint($this->get_value($faq, 'id', 0));
        $question = (string) $this->get_value($faq, 'question', '');
        $answer   = (string) $this->get_value($faq, 'answer', '');
        $category = (string) $this->get_value($faq, 'category', '');

        if ($id <= 0 || '' === trim($question) || '' === trim($answer)) {
            return array();
        }

        return array(
            'id'       => $id,
            'category' => $category,
            'question' => $question,
            'answer'   => $answer,
            'keywords' => (string) $this->get_value($faq, 'keywords', ''),
        );
    }

    /**
     * 格式化多筆前台 FAQ 資料。
     *
     * @param array $faqs FAQ 資料。
     * @return array
     */
    public function format_many_for_frontend($faqs) {
        if (!is_array($faqs)) {
            return array();
        }

        $items = array();

        foreach ($faqs as $faq) {
            $item = $this->format_for_frontend($faq);

            if (!empty($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * 格式化後台列表單筆資料。
     *
     * @param object|array $faq FAQ 資料。
     * @return array
     */
    public function format_for_admin_list($faq) {
        $status        = (string) $this->get_value($faq, 'status', '');
        $review_status = (string) $this->get_value($faq, 'review_status', '');
        $source        = (string) $this->get_value($faq, 'source', '');

        return array(
            'id'                 => absint($this->get_value($faq, 'id', 0)),
            'category'           => (string) $this->get_value($faq, 'category', ''),
            'question'           => (string) $this->get_value($faq, 'question', ''),
            'question_excerpt'   => $this->excerpt((string) $this->get_value($faq, 'question', ''), 80),
            'answer'             => (string) $this->get_value($faq, 'answer', ''),
            'answer_excerpt'     => $this->excerpt((string) $this->get_value($faq, 'answer', ''), 120),
            'keywords'           => (string) $this->get_value($faq, 'keywords', ''),
            'status'             => $status,
            'status_label'       => $this->get_status_label($status),
            'review_status'      => $review_status,
            'review_status_label'=> $this->get_review_status_label($review_status),
            'source'             => $source,
            'source_label'       => $this->get_source_label($source),
            'source_log_id'      => absint($this->get_value($faq, 'source_log_id', 0)),
            'sort_order'         => absint($this->get_value($faq, 'sort_order', 100)),
            'hit_count'          => absint($this->get_value($faq, 'hit_count', 0)),
            'admin_note'         => (string) $this->get_value($faq, 'admin_note', ''),
            'created_at'         => (string) $this->get_value($faq, 'created_at', ''),
            'updated_at'         => (string) $this->get_value($faq, 'updated_at', ''),
        );
    }

    /**
     * 格式化多筆後台列表資料。
     *
     * @param array $faqs FAQ 資料。
     * @return array
     */
    public function format_many_for_admin_list($faqs) {
        if (!is_array($faqs)) {
            return array();
        }

        $items = array();

        foreach ($faqs as $faq) {
            $items[] = $this->format_for_admin_list($faq);
        }

        return $items;
    }

    /**
     * 準備 CSV 匯出資料列。
     *
     * @param array $faqs FAQ 資料。
     * @return array
     */
    public function prepare_export_rows($faqs) {
        if (!is_array($faqs)) {
            return array();
        }

        $rows = array();

        foreach ($faqs as $faq) {
            $rows[] = array(
                'id'            => absint($this->get_value($faq, 'id', 0)),
                'category'      => (string) $this->get_value($faq, 'category', ''),
                'question'      => (string) $this->get_value($faq, 'question', ''),
                'answer'        => (string) $this->get_value($faq, 'answer', ''),
                'keywords'      => (string) $this->get_value($faq, 'keywords', ''),
                'status'        => $this->get_status_label((string) $this->get_value($faq, 'status', '')),
                'source'        => $this->get_source_label((string) $this->get_value($faq, 'source', '')),
                'source_log_id' => absint($this->get_value($faq, 'source_log_id', 0)),
                'review_status' => $this->get_review_status_label((string) $this->get_value($faq, 'review_status', '')),
                'sort_order'    => absint($this->get_value($faq, 'sort_order', 100)),
                'hit_count'     => absint($this->get_value($faq, 'hit_count', 0)),
                'admin_note'    => (string) $this->get_value($faq, 'admin_note', ''),
                'created_at'    => (string) $this->get_value($faq, 'created_at', ''),
                'updated_at'    => (string) $this->get_value($faq, 'updated_at', ''),
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
        if (class_exists('UR_AI_Schema_FAQs')) {
            return UR_AI_Schema_FAQs::get_default_categories();
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
        if (class_exists('UR_AI_Schema_FAQs')) {
            return UR_AI_Schema_FAQs::get_statuses();
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
        if (class_exists('UR_AI_Schema_FAQs')) {
            return UR_AI_Schema_FAQs::get_sources();
        }

        return array(
            'manual' => __('手動建立', 'ur-ai-assistant'),
            'ai_log' => __('AI 問答轉入', 'ur-ai-assistant'),
            'import' => __('匯入建立', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得審核狀態選項。
     *
     * @return array
     */
    public function get_review_statuses() {
        if (class_exists('UR_AI_Schema_FAQs')) {
            return UR_AI_Schema_FAQs::get_review_statuses();
        }

        return array(
            'draft'    => __('草稿', 'ur-ai-assistant'),
            'pending'  => __('待審核', 'ur-ai-assistant'),
            'approved' => __('已確認', 'ur-ai-assistant'),
            'rejected' => __('不採用', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得狀態標籤。
     *
     * @param string $status 狀態。
     * @return string
     */
    public function get_status_label($status) {
        $statuses = $this->get_statuses();
        $status   = sanitize_key($status);

        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }

    /**
     * 取得來源標籤。
     *
     * @param string $source 來源。
     * @return string
     */
    public function get_source_label($source) {
        $sources = $this->get_sources();
        $source  = sanitize_key($source);

        return isset($sources[$source]) ? $sources[$source] : $source;
    }

    /**
     * 取得審核狀態標籤。
     *
     * @param string $review_status 審核狀態。
     * @return string
     */
    public function get_review_status_label($review_status) {
        $statuses      = $this->get_review_statuses();
        $review_status = sanitize_key($review_status);

        return isset($statuses[$review_status]) ? $statuses[$review_status] : $review_status;
    }

    /**
     * 空摘要。
     *
     * @return array
     */
    private function empty_summary() {
        return array(
            'total'      => 0,
            'active'     => 0,
            'inactive'   => 0,
            'manual'     => 0,
            'ai_drafts'  => 0,
            'draft'      => 0,
            'pending'    => 0,
            'approved'   => 0,
            'rejected'   => 0,
            'total_hits' => 0,
            'high_hit'   => 0,
        );
    }

    /**
     * 取得 active FAQ 快取 TTL（秒）。
     *
     * @return int
     */
    private function get_active_faqs_cache_ttl() {
        /**
         * Filter active FAQ 快取存活秒數。
         *
         * @param int $ttl 預設 TTL 秒數。
         */
        $ttl = absint(apply_filters('ur_ai_active_faqs_cache_ttl', self::ACTIVE_FAQS_CACHE_TTL));

        if ($ttl <= 0) {
            $ttl = self::ACTIVE_FAQS_CACHE_TTL;
        }

        return $ttl;
    }

    /**
     * 註冊一個快取 key 至索引（供日後清除）。
     *
     * 因為 get_active_faqs() 可能以不同 limit 被呼叫，產生多個快取 key，
     * 此處維護一份 key 索引，清除時才能逐一刪除所有變體。
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
            // autoload = no，避免進入 alloptions 快取。
            update_option($this->cache_index_option_name(), $index, false);
        }
    }

    /**
     * 清除所有 active FAQ 快取。
     *
     * 逐一刪除索引中記錄的每個 limit 變體 transient，再清空索引。
     *
     * @return void
     */
    public function clear_active_faqs_cache() {
        $index = get_option($this->cache_index_option_name(), array());

        if (is_array($index)) {
            foreach ($index as $cache_key) {
                if (is_string($cache_key) && '' !== $cache_key) {
                    delete_transient($cache_key);
                }
            }
        }

        // 保險：即使 index 因並發寫入而不完整，仍涵蓋實務上常見的 limit 變體。
        // Matcher 固定使用 limit=1000；其餘為防禦性涵蓋。
        $fallback_limits = array(1000, 500, 100, 50, 0);

        foreach ($fallback_limits as $limit) {
            delete_transient(self::ACTIVE_FAQS_CACHE_PREFIX . absint($limit));
        }

        // 重置索引。
        update_option($this->cache_index_option_name(), array(), false);

        /**
         * Action：active FAQ 快取已清除。
         *
         * 供外部（例如外部物件快取、CDN）掛載額外清除邏輯。
         */
        do_action('ur_ai_active_faqs_cache_cleared');
    }

    /**
     * 快取 key 索引的 option 名稱。
     *
     * @return string
     */
    private function cache_index_option_name() {
        return 'ur_ai_active_faqs_cache_index';
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