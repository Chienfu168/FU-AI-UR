<?php
/**
 * UR AI Assistant Popular Question Service
 *
 * 熱門問題服務層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Popular_Question_Service
 */
class UR_AI_Popular_Question_Service {

    /**
     * Popular Question Repository.
     *
     * @var UR_AI_Popular_Question_Repository|null
     */
    private $repository = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->repository = class_exists('UR_AI_Popular_Question_Repository')
            ? new UR_AI_Popular_Question_Repository()
            : null;
    }

    /**
     * 取得前台熱門問題。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_frontend_questions($limit = 6) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return array();
        }

        $items = $this->repository->get_frontend_questions($limit);

        return $this->format_many_for_frontend($items);
    }

    /**
     * 取得前台分類群組熱門問題。
     *
     * @param int $per_category 每分類筆數。
     * @return array
     */
    public function get_frontend_grouped_questions($per_category = 6) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return array();
        }

        $groups = $this->repository->get_frontend_grouped_questions($per_category);

        if (!is_array($groups) || empty($groups)) {
            return array();
        }

        $formatted = array();

        foreach ($groups as $category => $items) {
            $formatted[$category] = $this->format_many_for_frontend($items);
        }

        return $formatted;
    }

    /**
     * 增加點擊次數。
     *
     * @param int $id 熱門問題 ID。
     * @return bool
     */
    public function increase_click_count($id) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return false;
        }

        return $this->repository->increase_click_count($id);
    }

    /**
     * 新增熱門問題。
     *
     * @param array $data 熱門問題資料。
     * @return int
     */
    public function create($data) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return 0;
        }

        return $this->repository->create($data);
    }

    /**
     * 更新熱門問題。
     *
     * @param int   $id 熱門問題 ID。
     * @param array $data 熱門問題資料。
     * @return bool
     */
    public function update($id, $data) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return false;
        }

        return $this->repository->update($id, $data);
    }

    /**
     * 刪除熱門問題。
     *
     * @param int $id 熱門問題 ID。
     * @return bool
     */
    public function delete($id) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return false;
        }

        return $this->repository->delete($id);
    }

    /**
     * 批次刪除熱門問題。
     *
     * @param array $ids 熱門問題 ID 陣列。
     * @return int
     */
    public function bulk_delete($ids) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return 0;
        }

        return $this->repository->bulk_delete($ids);
    }

    /**
     * 查詢單筆熱門問題。
     *
     * @param int $id 熱門問題 ID。
     * @return object|null
     */
    public function find($id) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return null;
        }

        return $this->repository->find($id);
    }

    /**
     * 查詢熱門問題列表。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query($args = array()) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return array();
        }

        return $this->repository->query($args);
    }

    /**
     * 計算熱門問題數量。
     *
     * @param array $args 查詢參數。
     * @return int
     */
    public function count($args = array()) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
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
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return array();
        }

        return $this->repository->query_ids($args);
    }

    /**
     * 批次啟用。
     *
     * @param array $ids 熱門問題 ID 陣列。
     * @return int
     */
    public function bulk_activate($ids) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return 0;
        }

        return $this->repository->bulk_update_status($ids, 'active');
    }

    /**
     * 批次停用。
     *
     * @param array $ids 熱門問題 ID 陣列。
     * @return int
     */
    public function bulk_deactivate($ids) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return 0;
        }

        return $this->repository->bulk_update_status($ids, 'inactive');
    }

    /**
     * 更新對應 FAQ ID。
     *
     * @param int $id 熱門問題 ID。
     * @param int $faq_id FAQ ID。
     * @return bool
     */
    public function update_faq_id($id, $faq_id) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return false;
        }

        return $this->repository->update_faq_id($id, $faq_id);
    }

    /**
     * 取得摘要統計。
     *
     * @return array
     */
    public function get_summary() {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
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
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return array();
        }

        return $this->repository->get_category_stats();
    }

    /**
     * 取得高點擊但未對應 FAQ 的熱門問題。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_high_click_unlinked_questions($limit = 20) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return array();
        }

        return $this->repository->get_high_click_unlinked_questions($limit);
    }

    /**
     * 取得熱門排行。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_top_clicked_questions($limit = 10) {
        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return array();
        }

        return $this->repository->get_top_clicked_questions($limit);
    }

    /**
     * 從 FAQ 匯入熱門問題。
     *
     * @param int $faq_id FAQ ID。
     * @return int 熱門問題 ID。
     */
    public function import_from_faq($faq_id) {
        $faq_id = absint($faq_id);

        if ($faq_id <= 0) {
            return 0;
        }

        if (!$this->repository instanceof UR_AI_Popular_Question_Repository) {
            return 0;
        }

        if (!class_exists('UR_AI_FAQ_Repository')) {
            return 0;
        }

        $faq_repository = new UR_AI_FAQ_Repository();
        $faq            = $faq_repository->find($faq_id);

        if (!$faq) {
            return 0;
        }

        $existing = $this->repository->find_by_faq_id($faq_id);

        if ($existing) {
            return absint($this->get_value($existing, 'id', 0));
        }

        return $this->repository->create_from_faq($faq);
    }

    /**
     * 批次從 FAQ 匯入熱門問題。
     *
     * @param array $faq_ids FAQ ID 陣列。
     * @return array
     */
    public function bulk_import_from_faqs($faq_ids) {
        if (!is_array($faq_ids)) {
            return array(
                'success_count' => 0,
                'skipped_count' => 0,
                'failed_count'  => 0,
                'results'       => array(),
            );
        }

        $faq_ids = array_values(array_unique(array_filter(array_map('absint', $faq_ids))));

        if (empty($faq_ids)) {
            return array(
                'success_count' => 0,
                'skipped_count' => 0,
                'failed_count'  => 0,
                'results'       => array(),
            );
        }

        $success_count = 0;
        $skipped_count = 0;
        $failed_count  = 0;
        $results       = array();

        foreach ($faq_ids as $faq_id) {
            $existing_id = 0;

            if ($this->repository instanceof UR_AI_Popular_Question_Repository) {
                $existing = $this->repository->find_by_faq_id($faq_id);

                if ($existing) {
                    $existing_id = absint($this->get_value($existing, 'id', 0));
                }
            }

            if ($existing_id > 0) {
                $skipped_count++;

                $results[] = array(
                    'faq_id'              => $faq_id,
                    'popular_question_id' => $existing_id,
                    'success'             => false,
                    'code'                => 'already_exists',
                );

                continue;
            }

            $popular_question_id = $this->import_from_faq($faq_id);

            if ($popular_question_id > 0) {
                $success_count++;

                $results[] = array(
                    'faq_id'              => $faq_id,
                    'popular_question_id' => $popular_question_id,
                    'success'             => true,
                    'code'                => 'imported',
                );

                continue;
            }

            $failed_count++;

            $results[] = array(
                'faq_id'              => $faq_id,
                'popular_question_id' => 0,
                'success'             => false,
                'code'                => 'failed',
            );
        }

        return array(
            'success_count' => $success_count,
            'skipped_count' => $skipped_count,
            'failed_count'  => $failed_count,
            'results'       => $results,
        );
    }

    /**
     * 熱門問題轉 FAQ 草稿。
     *
     * @param int $popular_question_id 熱門問題 ID。
     * @return int FAQ ID。
     */
    public function convert_to_faq_draft($popular_question_id) {
        $popular_question_id = absint($popular_question_id);

        if ($popular_question_id <= 0) {
            return 0;
        }

        if (!class_exists('UR_AI_FAQ_Draft_Service')) {
            return 0;
        }

        $draft_service = new UR_AI_FAQ_Draft_Service();

        if (!method_exists($draft_service, 'create_from_popular_question')) {
            return 0;
        }

        return absint($draft_service->create_from_popular_question($popular_question_id));
    }

    /**
     * 格式化前台單筆熱門問題。
     *
     * @param object|array $item 熱門問題資料。
     * @return array
     */
    public function format_for_frontend($item) {
        $id              = absint($this->get_value($item, 'id', 0));
        $question        = (string) $this->get_value($item, 'question', '');
        $submit_question = (string) $this->get_value($item, 'submit_question', '');
        $category        = (string) $this->get_value($item, 'category', '');
        $description     = (string) $this->get_value($item, 'description', '');

        if ('' === trim($question)) {
            return array();
        }

        if ('' === trim($submit_question)) {
            $submit_question = $question;
        }

        return array(
            'id'              => $id,
            'category'        => $category,
            'question'        => $question,
            'submit_question' => $submit_question,
            'description'     => $description,
        );
    }

    /**
     * 格式化多筆前台熱門問題。
     *
     * @param array $items 熱門問題資料。
     * @return array
     */
    public function format_many_for_frontend($items) {
        if (!is_array($items)) {
            return array();
        }

        $formatted = array();

        foreach ($items as $item) {
            $row = $this->format_for_frontend($item);

            if (!empty($row)) {
                $formatted[] = $row;
            }
        }

        return $formatted;
    }

    /**
     * 格式化後台列表資料。
     *
     * @param object|array $item 熱門問題資料。
     * @return array
     */
    public function format_for_admin_list($item) {
        $click_count = absint($this->get_value($item, 'click_count', 0));
        $faq_id      = absint($this->get_value($item, 'faq_id', 0));

        $faq_exists = false;
        $faq_status = '';

        if ($faq_id > 0 && class_exists('UR_AI_FAQ_Repository')) {
            $faq_repository = new UR_AI_FAQ_Repository();
            $faq            = $faq_repository->find($faq_id);

            if ($faq) {
                $faq_exists = true;
                $faq_status = (string) $this->get_value($faq, 'status', '');
            }
        }

        $content_status = class_exists('UR_AI_Schema_Popular_Questions')
            ? UR_AI_Schema_Popular_Questions::get_content_status($click_count, $faq_id, $faq_status, $faq_exists)
            : ($faq_id > 0 ? 'linked' : 'unlinked');

        return array(
            'id'                  => absint($this->get_value($item, 'id', 0)),
            'category'            => (string) $this->get_value($item, 'category', ''),
            'question'            => (string) $this->get_value($item, 'question', ''),
            'question_excerpt'    => $this->excerpt((string) $this->get_value($item, 'question', ''), 70),
            'submit_question'     => (string) $this->get_value($item, 'submit_question', ''),
            'description'         => (string) $this->get_value($item, 'description', ''),
            'description_excerpt' => $this->excerpt((string) $this->get_value($item, 'description', ''), 80),
            'status'              => (string) $this->get_value($item, 'status', ''),
            'source'              => (string) $this->get_value($item, 'source', ''),
            'faq_id'              => $faq_id,
            'faq_exists'          => $faq_exists,
            'faq_status'          => $faq_status,
            'sort_order'          => absint($this->get_value($item, 'sort_order', 100)),
            'click_count'         => $click_count,
            'content_status'      => $content_status,
            'content_status_label'=> class_exists('UR_AI_Schema_Popular_Questions')
                ? UR_AI_Schema_Popular_Questions::get_content_status_label($content_status)
                : '',
            'suggestion'          => class_exists('UR_AI_Schema_Popular_Questions')
                ? UR_AI_Schema_Popular_Questions::get_content_status_suggestion($content_status)
                : '',
            'admin_note'          => (string) $this->get_value($item, 'admin_note', ''),
            'created_at'          => (string) $this->get_value($item, 'created_at', ''),
            'updated_at'          => (string) $this->get_value($item, 'updated_at', ''),
        );
    }

    /**
     * 格式化多筆後台列表資料。
     *
     * @param array $items 熱門問題資料。
     * @return array
     */
    public function format_many_for_admin_list($items) {
        if (!is_array($items)) {
            return array();
        }

        $formatted = array();

        foreach ($items as $item) {
            $formatted[] = $this->format_for_admin_list($item);
        }

        return $formatted;
    }

    /**
     * 準備 CSV 匯出資料列。
     *
     * @param array $items 熱門問題資料。
     * @return array
     */
    public function prepare_export_rows($items) {
        if (!is_array($items)) {
            return array();
        }

        $rows = array();

        foreach ($items as $item) {
            $formatted = $this->format_for_admin_list($item);

            $rows[] = array(
                'id'              => absint($this->get_value($item, 'id', 0)),
                'category'        => (string) $this->get_value($item, 'category', ''),
                'question'        => (string) $this->get_value($item, 'question', ''),
                'submit_question' => (string) $this->get_value($item, 'submit_question', ''),
                'description'     => (string) $this->get_value($item, 'description', ''),
                'status'          => (string) $this->get_value($item, 'status', ''),
                'source'          => (string) $this->get_value($item, 'source', ''),
                'faq_id'          => absint($this->get_value($item, 'faq_id', 0)),
                'sort_order'      => absint($this->get_value($item, 'sort_order', 100)),
                'click_count'     => absint($this->get_value($item, 'click_count', 0)),
                'content_status'  => isset($formatted['content_status_label']) ? $formatted['content_status_label'] : '',
                'admin_note'      => (string) $this->get_value($item, 'admin_note', ''),
                'created_at'      => (string) $this->get_value($item, 'created_at', ''),
                'updated_at'      => (string) $this->get_value($item, 'updated_at', ''),
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
        if (class_exists('UR_AI_Schema_Popular_Questions')) {
            return UR_AI_Schema_Popular_Questions::get_default_categories();
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
        );
    }

    /**
     * 取得狀態選項。
     *
     * @return array
     */
    public function get_statuses() {
        if (class_exists('UR_AI_Schema_Popular_Questions')) {
            return UR_AI_Schema_Popular_Questions::get_statuses();
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
        if (class_exists('UR_AI_Schema_Popular_Questions')) {
            return UR_AI_Schema_Popular_Questions::get_sources();
        }

        return array(
            'manual' => __('手動建立', 'ur-ai-assistant'),
            'faq'    => __('從 FAQ 匯入', 'ur-ai-assistant'),
            'import' => __('匯入建立', 'ur-ai-assistant'),
        );
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
            'from_faq'          => 0,
            'linked_faq'        => 0,
            'unlinked_faq'      => 0,
            'high_click'        => 0,
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
     * 文字摘要。
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