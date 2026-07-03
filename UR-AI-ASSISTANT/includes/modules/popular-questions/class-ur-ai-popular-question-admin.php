<?php
/**
 * UR AI Assistant Popular Question Admin
 *
 * 熱門問題後台管理控制器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Popular_Question_Admin
 */
class UR_AI_Popular_Question_Admin {

    /**
     * Popular Question Service.
     *
     * @var UR_AI_Popular_Question_Service|null
     */
    private $service = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->service = class_exists('UR_AI_Popular_Question_Service')
            ? new UR_AI_Popular_Question_Service()
            : null;
    }

    /**
     * 處理後台操作。
     *
     * @return void
     */
    public function handle_actions() {
        if (!is_admin()) {
            return;
        }

        $this->maybe_export_csv();

        if (empty($_POST['ur_ai_action'])) {
            return;
        }

        if (class_exists('UR_AI_Permissions')) {
            UR_AI_Permissions::require_manage_popular_questions();
        } elseif (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('您沒有權限執行此操作。', 'ur-ai-assistant'),
                esc_html__('權限不足', 'ur-ai-assistant'),
                array(
                    'response' => 403,
                )
            );
        }

        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::verify_admin_form_nonce_or_die();
        }

        $action = sanitize_key(wp_unslash($_POST['ur_ai_action']));

        switch ($action) {
            case 'create_popular_question':
                $this->handle_create();
                break;

            case 'update_popular_question':
                $this->handle_update();
                break;

            case 'delete_popular_question':
                $this->handle_delete();
                break;

            case 'bulk_popular_questions':
                $this->handle_bulk();
                break;

            case 'import_popular_questions_from_faq':
                $this->handle_import_from_faq();
                break;

            case 'convert_popular_question_to_faq':
                $this->handle_convert_to_faq();
                break;
        }
    }

    /**
     * 取得後台列表資料。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function get_list_data($args = array()) {
        if (!$this->service instanceof UR_AI_Popular_Question_Service) {
            return array(
                'items'      => array(),
                'formatted'  => array(),
                'total'      => 0,
                'summary'    => array(),
                'pagination' => array(),
                'query_args' => array(),
            );
        }

        $query_args = $this->sanitize_query_args($args);

        $items = $this->service->query($query_args);
        $total = $this->service->count($query_args);

        return array(
            'items'      => $items,
            'formatted'  => $this->service->format_many_for_admin_list($items),
            'total'      => $total,
            'summary'    => $this->service->get_summary(),
            'pagination' => $this->build_pagination_data($total, $query_args),
            'query_args' => $query_args,
        );
    }

    /**
     * 取得可匯入的 FAQ 列表。
     *
     * @param int $limit 筆數。
     * @return array
     */
    public function get_importable_faqs($limit = 50) {
        if (!class_exists('UR_AI_FAQ_Repository')) {
            return array();
        }

        $repository = new UR_AI_FAQ_Repository();

        $faqs = $repository->query(
            array(
                'status'  => 'active',
                'orderby' => 'hit_count',
                'order'   => 'DESC',
                'limit'   => absint($limit),
                'offset'  => 0,
            )
        );

        if (!is_array($faqs)) {
            return array();
        }

        return $faqs;
    }

    /**
     * 處理新增熱門問題。
     *
     * @return void
     */
    private function handle_create() {
        if (!$this->service instanceof UR_AI_Popular_Question_Service) {
            $this->redirect_with_message('popular_service_missing', 'error');
        }

        $id = $this->service->create($this->get_form_data());

        $this->redirect_with_message(
            $id > 0 ? 'popular_question_created' : 'popular_question_create_failed',
            $id > 0 ? 'updated' : 'error',
            array(
                'popular_question_id' => $id,
            )
        );
    }

    /**
     * 處理更新熱門問題。
     *
     * @return void
     */
    private function handle_update() {
        if (!$this->service instanceof UR_AI_Popular_Question_Service) {
            $this->redirect_with_message('popular_service_missing', 'error');
        }

        $id = isset($_POST['popular_question_id']) ? absint($_POST['popular_question_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_popular_question_id', 'error');
        }

        $updated = $this->service->update($id, $this->get_form_data());

        $this->redirect_with_message(
            $updated ? 'popular_question_updated' : 'popular_question_update_failed',
            $updated ? 'updated' : 'error',
            array(
                'popular_question_id' => $id,
            )
        );
    }

    /**
     * 處理刪除熱門問題。
     *
     * @return void
     */
    private function handle_delete() {
        if (!$this->service instanceof UR_AI_Popular_Question_Service) {
            $this->redirect_with_message('popular_service_missing', 'error');
        }

        $id = isset($_POST['popular_question_id']) ? absint($_POST['popular_question_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_popular_question_id', 'error');
        }

        $deleted = $this->service->delete($id);

        $this->redirect_with_message(
            $deleted ? 'popular_question_deleted' : 'popular_question_delete_failed',
            $deleted ? 'updated' : 'error'
        );
    }

    /**
     * 處理批次操作。
     *
     * @return void
     */
    private function handle_bulk() {
        if (!$this->service instanceof UR_AI_Popular_Question_Service) {
            $this->redirect_with_message('popular_service_missing', 'error');
        }

        $bulk_action = isset($_POST['bulk_action'])
            ? sanitize_key(wp_unslash($_POST['bulk_action']))
            : '';

        $ids = isset($_POST['popular_question_ids'])
            ? (array) wp_unslash($_POST['popular_question_ids'])
            : array();

        if (class_exists('UR_AI_Security')) {
            $ids = UR_AI_Security::sanitize_ids($ids);
        } else {
            $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        }

        if (empty($ids)) {
            $this->redirect_with_message('no_items_selected', 'error');
        }

        switch ($bulk_action) {
            case 'activate':
                $count = $this->service->bulk_activate($ids);
                $this->redirect_with_message(
                    $count > 0 ? 'popular_questions_activated' : 'popular_questions_bulk_failed',
                    $count > 0 ? 'updated' : 'error',
                    array('count' => $count)
                );
                break;

            case 'deactivate':
                $count = $this->service->bulk_deactivate($ids);
                $this->redirect_with_message(
                    $count > 0 ? 'popular_questions_deactivated' : 'popular_questions_bulk_failed',
                    $count > 0 ? 'updated' : 'error',
                    array('count' => $count)
                );
                break;

            case 'delete':
                $count = $this->service->bulk_delete($ids);
                $this->redirect_with_message(
                    $count > 0 ? 'popular_questions_deleted' : 'popular_questions_bulk_failed',
                    $count > 0 ? 'updated' : 'error',
                    array('count' => $count)
                );
                break;

            case 'convert_to_faq':
                $this->handle_bulk_convert_to_faq($ids);
                break;

            default:
                $this->redirect_with_message('invalid_bulk_action', 'error');
                break;
        }
    }

    /**
     * 從 FAQ 匯入熱門問題。
     *
     * @return void
     */
    private function handle_import_from_faq() {
        if (!$this->service instanceof UR_AI_Popular_Question_Service) {
            $this->redirect_with_message('popular_service_missing', 'error');
        }

        $faq_ids = isset($_POST['faq_ids'])
            ? (array) wp_unslash($_POST['faq_ids'])
            : array();

        $faq_ids = array_values(array_unique(array_filter(array_map('absint', $faq_ids))));

        if (empty($faq_ids)) {
            $this->redirect_with_message('no_faq_selected', 'error');
        }

        $result = $this->service->bulk_import_from_faqs($faq_ids);

        $this->redirect_with_message(
            'popular_questions_imported_from_faq',
            'updated',
            array(
                'success_count' => isset($result['success_count']) ? absint($result['success_count']) : 0,
                'skipped_count' => isset($result['skipped_count']) ? absint($result['skipped_count']) : 0,
                'failed_count'  => isset($result['failed_count']) ? absint($result['failed_count']) : 0,
            )
        );
    }

    /**
     * 處理單筆熱門問題轉 FAQ 草稿。
     *
     * @return void
     */
    private function handle_convert_to_faq() {
        if (!$this->service instanceof UR_AI_Popular_Question_Service) {
            $this->redirect_with_message('popular_service_missing', 'error');
        }

        $id = isset($_POST['popular_question_id']) ? absint($_POST['popular_question_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_popular_question_id', 'error');
        }

        $faq_id = $this->service->convert_to_faq_draft($id);

        $this->redirect_with_message(
            $faq_id > 0 ? 'popular_question_converted_to_faq' : 'popular_question_convert_failed',
            $faq_id > 0 ? 'updated' : 'error',
            array(
                'faq_id' => $faq_id,
            )
        );
    }

    /**
     * 批次熱門問題轉 FAQ 草稿。
     *
     * @param array $ids 熱門問題 ID 陣列。
     * @return void
     */
    private function handle_bulk_convert_to_faq($ids) {
        if (!$this->service instanceof UR_AI_Popular_Question_Service) {
            $this->redirect_with_message('popular_service_missing', 'error');
        }

        $success_count = 0;
        $failed_count  = 0;

        foreach ($ids as $id) {
            $faq_id = $this->service->convert_to_faq_draft($id);

            if ($faq_id > 0) {
                $success_count++;
            } else {
                $failed_count++;
            }
        }

        $this->redirect_with_message(
            'popular_questions_converted_to_faq',
            $success_count > 0 ? 'updated' : 'error',
            array(
                'success_count' => $success_count,
                'failed_count'  => $failed_count,
            )
        );
    }

    /**
     * 若為匯出請求，輸出 CSV。
     *
     * @return void
     */
    private function maybe_export_csv() {
        $action = isset($_GET['ur_action'])
            ? sanitize_key(wp_unslash($_GET['ur_action']))
            : '';

        if ('export_popular_questions_csv' !== $action) {
            return;
        }

        if (!class_exists('UR_AI_Exporter')) {
            wp_die(
                esc_html__('匯出工具尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant'),
                esc_html__('匯出失敗', 'ur-ai-assistant'),
                array('response' => 500)
            );
        }

        UR_AI_Exporter::verify_export_request_or_die();

        if (!$this->service instanceof UR_AI_Popular_Question_Service) {
            wp_die(
                esc_html__('熱門問題服務尚未載入，無法匯出資料。', 'ur-ai-assistant'),
                esc_html__('匯出失敗', 'ur-ai-assistant'),
                array('response' => 500)
            );
        }

        $query_args = $this->sanitize_query_args($_GET);
        $query_args['limit']  = 5000;
        $query_args['offset'] = 0;

        $items = $this->service->query($query_args);
        $rows  = $this->service->prepare_export_rows($items);

        $headers = class_exists('UR_AI_Schema_Popular_Questions')
            ? UR_AI_Schema_Popular_Questions::get_export_columns()
            : $this->fallback_export_columns();

        UR_AI_Exporter::output_csv(
            UR_AI_Exporter::build_filename('ur-ai-popular-questions'),
            $headers,
            $rows
        );
    }

    /**
     * 取得表單資料。
     *
     * @return array
     */
    private function get_form_data() {
        return array(
            'category'        => isset($_POST['category']) ? wp_unslash($_POST['category']) : '其他',
            'question'        => isset($_POST['question']) ? wp_unslash($_POST['question']) : '',
            'submit_question' => isset($_POST['submit_question']) ? wp_unslash($_POST['submit_question']) : '',
            'description'     => isset($_POST['description']) ? wp_unslash($_POST['description']) : '',
            'status'          => isset($_POST['status']) ? wp_unslash($_POST['status']) : 'inactive',
            'source'          => isset($_POST['source']) ? wp_unslash($_POST['source']) : 'manual',
            'faq_id'          => isset($_POST['faq_id']) ? absint($_POST['faq_id']) : 0,
            'sort_order'      => isset($_POST['sort_order']) ? absint($_POST['sort_order']) : 100,
            'admin_note'      => isset($_POST['admin_note']) ? wp_unslash($_POST['admin_note']) : '',
        );
    }

    /**
     * 清理查詢參數。
     *
     * @param array $args 原始查詢參數。
     * @return array
     */
    private function sanitize_query_args($args) {
        if (!is_array($args)) {
            $args = array();
        }

        $paged = isset($args['paged']) ? absint($args['paged']) : 1;

        if ($paged <= 0) {
            $paged = 1;
        }

        $per_page = isset($args['per_page']) ? absint($args['per_page']) : 20;

        if ($per_page <= 0 || $per_page > 100) {
            $per_page = 20;
        }

        $status = isset($args['status'])
            ? sanitize_key(wp_unslash($args['status']))
            : '';

        $category = isset($args['category'])
            ? sanitize_text_field(wp_unslash($args['category']))
            : '';

        $source = isset($args['source'])
            ? sanitize_key(wp_unslash($args['source']))
            : '';

        $linked = null;

        if (isset($args['linked']) && '' !== $args['linked']) {
            $linked = absint($args['linked']);
        }

        $search = isset($args['s'])
            ? sanitize_text_field(wp_unslash($args['s']))
            : '';

        $orderby = isset($args['orderby'])
            ? sanitize_key(wp_unslash($args['orderby']))
            : 'sort_order';

        $order = isset($args['order'])
            ? sanitize_key(wp_unslash($args['order']))
            : 'ASC';

        return array(
            'status'   => $this->allowed_value($status, array('', 'active', 'inactive'), ''),
            'category' => $category,
            'source'   => $this->allowed_value($source, array('', 'manual', 'faq', 'import'), ''),
            'linked'   => $linked,
            'search'   => $search,
            'orderby'  => $orderby,
            'order'    => strtoupper($order) === 'DESC' ? 'DESC' : 'ASC',
            'limit'    => $per_page,
            'offset'   => ($paged - 1) * $per_page,
            'paged'    => $paged,
            'per_page' => $per_page,
        );
    }

    /**
     * 建立分頁資料。
     *
     * @param int   $total 總筆數。
     * @param array $query_args 查詢參數。
     * @return array
     */
    private function build_pagination_data($total, $query_args) {
        $total    = absint($total);
        $per_page = isset($query_args['per_page']) ? absint($query_args['per_page']) : 20;
        $paged    = isset($query_args['paged']) ? absint($query_args['paged']) : 1;

        if ($per_page <= 0) {
            $per_page = 20;
        }

        return array(
            'total'       => $total,
            'per_page'    => $per_page,
            'current'     => $paged,
            'total_pages' => (int) ceil($total / $per_page),
        );
    }

    /**
     * 重導向並帶入訊息。
     *
     * @param string $message 訊息代碼。
     * @param string $type 類型 updated/error。
     * @param array  $extra_args 額外參數。
     * @return void
     */
    private function redirect_with_message($message, $type = 'updated', $extra_args = array()) {
        $args = array(
            'page'        => 'ur-ai-assistant-popular-questions',
            'ur_message'  => sanitize_key($message),
            'ur_msg_type' => sanitize_key($type),
        );

        if (is_array($extra_args) && !empty($extra_args)) {
            foreach ($extra_args as $key => $value) {
                $args[sanitize_key($key)] = sanitize_text_field((string) $value);
            }
        }

        $url = add_query_arg($args, admin_url('admin.php'));

        wp_safe_redirect($url);
        exit;
    }

    /**
     * 取得後台訊息文字。
     *
     * @param string $code 訊息代碼。
     * @return string
     */
    public function get_admin_message($code) {
        $code = sanitize_key($code);

        $messages = array(
            'popular_question_created'          => __('熱門問題已新增。', 'ur-ai-assistant'),
            'popular_question_create_failed'    => __('熱門問題新增失敗，請確認問題內容。', 'ur-ai-assistant'),
            'popular_question_updated'          => __('熱門問題已更新。', 'ur-ai-assistant'),
            'popular_question_update_failed'    => __('熱門問題更新失敗。', 'ur-ai-assistant'),
            'popular_question_deleted'          => __('熱門問題已刪除。', 'ur-ai-assistant'),
            'popular_question_delete_failed'    => __('熱門問題刪除失敗。', 'ur-ai-assistant'),
            'popular_questions_activated'       => __('已批次啟用熱門問題。', 'ur-ai-assistant'),
            'popular_questions_deactivated'     => __('已批次停用熱門問題。', 'ur-ai-assistant'),
            'popular_questions_deleted'         => __('已批次刪除熱門問題。', 'ur-ai-assistant'),
            'popular_questions_bulk_failed'     => __('批次操作失敗，請稍後再試。', 'ur-ai-assistant'),
            'popular_questions_imported_from_faq'=> __('已從 FAQ 匯入熱門問題，請檢查後再啟用。', 'ur-ai-assistant'),
            'popular_question_converted_to_faq' => __('已將熱門問題轉成 FAQ 草稿，請至 FAQ 知識庫補上完整回答並檢查後再啟用。', 'ur-ai-assistant'),
            'popular_question_convert_failed'   => __('熱門問題轉 FAQ 草稿失敗。', 'ur-ai-assistant'),
            'popular_questions_converted_to_faq'=> __('批次轉 FAQ 草稿完成。', 'ur-ai-assistant'),
            'invalid_popular_question_id'       => __('熱門問題 ID 不正確。', 'ur-ai-assistant'),
            'no_items_selected'                 => __('請先選擇要操作的熱門問題。', 'ur-ai-assistant'),
            'no_faq_selected'                   => __('請先選擇要匯入的 FAQ。', 'ur-ai-assistant'),
            'invalid_bulk_action'               => __('批次操作不正確。', 'ur-ai-assistant'),
            'popular_service_missing'           => __('熱門問題服務尚未正確載入。', 'ur-ai-assistant'),
        );

        return isset($messages[$code])
            ? $messages[$code]
            : __('操作已完成。', 'ur-ai-assistant');
    }

    /**
     * 檢查允許值。
     *
     * @param string $value 原始值。
     * @param array  $allowed 允許值。
     * @param string $default 預設值。
     * @return string
     */
    private function allowed_value($value, $allowed, $default = '') {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * fallback 匯出欄位。
     *
     * @return array
     */
    private function fallback_export_columns() {
        return array(
            'id'              => __('ID', 'ur-ai-assistant'),
            'category'        => __('分類', 'ur-ai-assistant'),
            'question'        => __('前台顯示問題', 'ur-ai-assistant'),
            'submit_question' => __('實際送出問題', 'ur-ai-assistant'),
            'description'     => __('簡短說明', 'ur-ai-assistant'),
            'status'          => __('狀態', 'ur-ai-assistant'),
            'source'          => __('來源', 'ur-ai-assistant'),
            'faq_id'          => __('對應 FAQ ID', 'ur-ai-assistant'),
            'sort_order'      => __('排序', 'ur-ai-assistant'),
            'click_count'     => __('點擊次數', 'ur-ai-assistant'),
        );
    }
}