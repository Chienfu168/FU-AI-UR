<?php
/**
 * UR AI Assistant FAQ Admin
 *
 * FAQ 知識庫後台管理控制器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_Admin
 */
class UR_AI_FAQ_Admin {

    /**
     * FAQ Service.
     *
     * @var UR_AI_FAQ_Service|null
     */
    private $service = null;

    /**
     * FAQ Draft Service.
     *
     * @var UR_AI_FAQ_Draft_Service|null
     */
    private $draft_service = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->service = class_exists('UR_AI_FAQ_Service')
            ? new UR_AI_FAQ_Service()
            : null;

        $this->draft_service = class_exists('UR_AI_FAQ_Draft_Service')
            ? new UR_AI_FAQ_Draft_Service()
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
            UR_AI_Permissions::require_manage_faqs();
        } elseif (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('您沒有權限執行此操作。', 'ur-ai-assistant'),
                esc_html__('權限不足', 'ur-ai-assistant'),
                array('response' => 403)
            );
        }

        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::verify_admin_form_nonce_or_die();
        } else {
            check_admin_referer('ur_ai_assistant_admin_action', 'ur_ai_nonce');
        }

        $action = sanitize_key(wp_unslash($_POST['ur_ai_action']));

        switch ($action) {
            case 'create_faq':
                $this->handle_create();
                break;

            case 'update_faq':
                $this->handle_update();
                break;

            case 'delete_faq':
                $this->handle_delete();
                break;

            case 'bulk_faqs':
                $this->handle_bulk();
                break;

            case 'convert_log_to_faq':
                $this->handle_convert_log_to_faq();
                break;

            case 'import_faqs':
                $this->handle_import();
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
        if (!$this->service instanceof UR_AI_FAQ_Service) {
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
     * 新增 FAQ。
     *
     * @return void
     */
    private function handle_create() {
        if (!$this->service instanceof UR_AI_FAQ_Service) {
            $this->redirect_with_message('faq_service_missing', 'error');
        }

        $id = $this->service->create($this->get_form_data());

        $this->redirect_with_message(
            $id > 0 ? 'faq_created' : 'faq_create_failed',
            $id > 0 ? 'updated' : 'error',
            array('faq_id' => $id)
        );
    }

    /**
     * 更新 FAQ。
     *
     * @return void
     */
    private function handle_update() {
        if (!$this->service instanceof UR_AI_FAQ_Service) {
            $this->redirect_with_message('faq_service_missing', 'error');
        }

        $id = isset($_POST['faq_id']) ? absint($_POST['faq_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_faq_id', 'error');
        }

        $updated = $this->service->update($id, $this->get_form_data());

        $this->redirect_with_message(
            $updated ? 'faq_updated' : 'faq_update_failed',
            $updated ? 'updated' : 'error',
            array('faq_id' => $id)
        );
    }

    /**
     * 刪除 FAQ。
     *
     * @return void
     */
    private function handle_delete() {
        if (!$this->service instanceof UR_AI_FAQ_Service) {
            $this->redirect_with_message('faq_service_missing', 'error');
        }

        $id = isset($_POST['faq_id']) ? absint($_POST['faq_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_faq_id', 'error');
        }

        $deleted = $this->service->delete($id);

        $this->redirect_with_message(
            $deleted ? 'faq_deleted' : 'faq_delete_failed',
            $deleted ? 'updated' : 'error'
        );
    }

    /**
     * 批次操作。
     *
     * @return void
     */
    private function handle_bulk() {
        if (!$this->service instanceof UR_AI_FAQ_Service) {
            $this->redirect_with_message('faq_service_missing', 'error');
        }

        $bulk_action = isset($_POST['bulk_action'])
            ? sanitize_key(wp_unslash($_POST['bulk_action']))
            : '';

        $ids = isset($_POST['faq_ids'])
            ? (array) wp_unslash($_POST['faq_ids'])
            : array();

        if (class_exists('UR_AI_Security')) {
            $ids = UR_AI_Security::sanitize_ids($ids);
        } else {
            $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        }

        /*
         * 「全選」在畫面上只會勾選當頁看得到的項目。若使用者在跨頁全選
         * 提示中確認要套用到「全部符合目前篩選條件」的資料，改用篩選
         * 條件重新查出全部 ID，而不是只用當頁勾選送出的 faq_ids。
         */
        if (!empty($_POST['select_all_matching'])) {
            $ids = $this->service->query_ids(
                array(
                    'status'   => isset($_POST['filter_status']) ? sanitize_key(wp_unslash($_POST['filter_status'])) : '',
                    'category' => isset($_POST['filter_category']) ? sanitize_text_field(wp_unslash($_POST['filter_category'])) : '',
                    'source'   => isset($_POST['filter_source']) ? sanitize_key(wp_unslash($_POST['filter_source'])) : '',
                    'search'   => isset($_POST['filter_search']) ? sanitize_text_field(wp_unslash($_POST['filter_search'])) : '',
                )
            );
        }

        if (empty($ids)) {
            $this->redirect_with_message('no_items_selected', 'error');
        }

        switch ($bulk_action) {
            case 'activate':
                $count = $this->service->bulk_activate($ids);
                $this->redirect_with_message(
                    $count > 0 ? 'faqs_activated' : 'faqs_bulk_failed',
                    $count > 0 ? 'updated' : 'error',
                    array('count' => $count)
                );
                break;

            case 'deactivate':
                $count = $this->service->bulk_deactivate($ids);
                $this->redirect_with_message(
                    $count > 0 ? 'faqs_deactivated' : 'faqs_bulk_failed',
                    $count > 0 ? 'updated' : 'error',
                    array('count' => $count)
                );
                break;

            case 'delete':
                $count = $this->service->bulk_delete($ids);
                $this->redirect_with_message(
                    $count > 0 ? 'faqs_deleted' : 'faqs_bulk_failed',
                    $count > 0 ? 'updated' : 'error',
                    array('count' => $count)
                );
                break;

            default:
                $this->redirect_with_message('invalid_bulk_action', 'error');
                break;
        }
    }

    /**
     * AI 問答紀錄轉 FAQ 草稿。
     *
     * @return void
     */
    private function handle_convert_log_to_faq() {
        if (!$this->draft_service instanceof UR_AI_FAQ_Draft_Service) {
            $this->redirect_with_message('faq_draft_service_missing', 'error');
        }

        $log_id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;

        if ($log_id <= 0) {
            $this->redirect_with_message('invalid_log_id', 'error');
        }

        $faq_id = $this->draft_service->create_from_log($log_id);

        $this->redirect_with_message(
            $faq_id > 0 ? 'faq_draft_created' : 'faq_draft_create_failed',
            $faq_id > 0 ? 'updated' : 'error',
            array('faq_id' => $faq_id)
        );
    }

    /**
     * 處理 FAQ CSV 匯入。
     *
     * 流程：驗證上傳檔 → 解析 CSV → 正規化各列 → 交由 service upsert →
     * 以查詢字串帶回統計結果顯示。
     *
     * @return void
     */
    private function handle_import() {
        if (!$this->service instanceof UR_AI_FAQ_Service) {
            $this->redirect_with_message('faq_service_missing', 'error');
        }

        // 上傳檔基本檢查。
        if (empty($_FILES['ur_ai_faq_csv']) || !isset($_FILES['ur_ai_faq_csv']['tmp_name'])) {
            $this->redirect_with_message('faq_import_no_file', 'error');
        }

        $file = $_FILES['ur_ai_faq_csv'];

        if (!empty($file['error']) && UPLOAD_ERR_OK !== (int) $file['error']) {
            $this->redirect_with_message('faq_import_upload_error', 'error');
        }

        $tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';

        if ('' === $tmp_name || !is_uploaded_file($tmp_name)) {
            $this->redirect_with_message('faq_import_no_file', 'error');
        }

        // 副檔名檢查（寬鬆，僅擋明顯非 CSV）。
        $filename = isset($file['name']) ? sanitize_file_name($file['name']) : '';
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ('csv' !== $ext && 'txt' !== $ext) {
            $this->redirect_with_message('faq_import_bad_type', 'error');
        }

        $rows = $this->parse_faq_csv($tmp_name);

        if (null === $rows) {
            $this->redirect_with_message('faq_import_parse_error', 'error');
        }

        if (empty($rows)) {
            $this->redirect_with_message('faq_import_empty', 'error');
        }

        $stats = $this->service->import_rows($rows);

        $this->redirect_with_message(
            'faq_imported',
            'updated',
            array(
                'imp_created' => absint($stats['created']),
                'imp_updated' => absint($stats['updated']),
                'imp_skipped' => absint($stats['skipped']),
            )
        );
    }

    /**
     * 解析 FAQ 匯入 CSV，回傳正規化後的資料列。
     *
     * 支援中文欄名（與匯出一致）與英文欄名，依表頭自動對應欄位。
     * 自動去除 UTF-8 BOM；狀態欄可用「啟用／停用」中文或 active／inactive。
     *
     * @param string $path 上傳暫存檔路徑。
     * @return array|null 成功回傳資料列陣列；讀取失敗回傳 null。
     */
    private function parse_faq_csv($path) {
        $handle = fopen($path, 'r');

        if (false === $handle) {
            return null;
        }

        $rows       = array();
        $header_map = null;
        $line_no    = 0;

        while (false !== ($cols = fgetcsv($handle, 0, ','))) {
            $line_no++;

            // 全空列略過。
            if (null === $cols || (1 === count($cols) && (null === $cols[0] || '' === trim((string) $cols[0])))) {
                continue;
            }

            // 首列為表頭。
            if (null === $header_map) {
                // 去除第一欄可能的 UTF-8 BOM。
                if (isset($cols[0])) {
                    $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cols[0]);
                }

                $header_map = $this->map_faq_csv_header($cols);

                // 若表頭無法對應到 question / answer，視為格式錯誤。
                if (!isset($header_map['question']) || !isset($header_map['answer'])) {
                    fclose($handle);
                    return null;
                }

                continue;
            }

            $row = array();

            foreach ($header_map as $field => $index) {
                $value = isset($cols[$index]) ? (string) $cols[$index] : '';
                $row[$field] = trim($value);
            }

            // 狀態中文標籤還原。
            if (isset($row['status'])) {
                $row['status'] = $this->normalize_import_status($row['status']);
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * 依表頭文字對應欄位索引。
     *
     * @param array $header 表頭列。
     * @return array 欄位 => 欄索引。
     */
    private function map_faq_csv_header($header) {
        $aliases = array(
            'category'   => array('分類', 'category'),
            'question'   => array('標準問題', '問題', 'question'),
            'answer'     => array('固定回答', '回答', 'answer'),
            'keywords'   => array('關鍵字', 'keywords'),
            'status'     => array('狀態', 'status'),
            'sort_order' => array('排序', 'sort_order', 'sort'),
        );

        $map = array();

        foreach ($header as $index => $label) {
            $label = trim((string) $label);

            if ('' === $label) {
                continue;
            }

            foreach ($aliases as $field => $names) {
                if (isset($map[$field])) {
                    continue;
                }

                foreach ($names as $name) {
                    if (0 === strcasecmp($label, $name)) {
                        $map[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * 匯入狀態值正規化。
     *
     * 接受「啟用／停用」中文或 active／inactive；無法辨識時回傳空字串，
     * 交由 repository 套用預設（inactive）。
     *
     * @param string $value 原始狀態字串。
     * @return string 'active'、'inactive' 或 ''。
     */
    private function normalize_import_status($value) {
        $value = trim((string) $value);

        if ('' === $value) {
            return '';
        }

        $active_labels   = array('active', '啟用', '啟用中', '1', 'yes', 'on');
        $inactive_labels = array('inactive', '停用', '草稿', '停用 / 草稿', '0', 'no', 'off');

        foreach ($active_labels as $label) {
            if (0 === strcasecmp($value, $label)) {
                return 'active';
            }
        }

        foreach ($inactive_labels as $label) {
            if (0 === strcasecmp($value, $label)) {
                return 'inactive';
            }
        }

        return '';
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

        if ('export_faqs_csv' !== $action) {
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

        if (!$this->service instanceof UR_AI_FAQ_Service) {
            wp_die(
                esc_html__('FAQ 服務尚未載入，無法匯出資料。', 'ur-ai-assistant'),
                esc_html__('匯出失敗', 'ur-ai-assistant'),
                array('response' => 500)
            );
        }

        $query_args = $this->sanitize_query_args($_GET);
        $query_args['limit']  = 5000;
        $query_args['offset'] = 0;

        $items = $this->service->query($query_args);
        $rows  = $this->service->prepare_export_rows($items);

        $headers = class_exists('UR_AI_Schema_FAQs')
            ? UR_AI_Schema_FAQs::get_export_columns()
            : $this->fallback_export_columns();

        UR_AI_Exporter::output_csv(
            UR_AI_Exporter::build_filename('ur-ai-faqs'),
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
            'category'      => isset($_POST['category']) ? wp_unslash($_POST['category']) : '待分類',
            'question'      => isset($_POST['question']) ? wp_unslash($_POST['question']) : '',
            'answer'        => isset($_POST['answer']) ? wp_unslash($_POST['answer']) : '',
            'keywords'      => isset($_POST['keywords']) ? wp_unslash($_POST['keywords']) : '',
            'status'        => isset($_POST['status']) ? wp_unslash($_POST['status']) : 'inactive',
            'source'        => isset($_POST['source']) ? wp_unslash($_POST['source']) : 'manual',
            'source_log_id' => isset($_POST['source_log_id']) ? absint($_POST['source_log_id']) : 0,
            'review_status' => isset($_POST['review_status']) ? wp_unslash($_POST['review_status']) : 'draft',
            'sort_order'    => isset($_POST['sort_order']) ? absint($_POST['sort_order']) : 100,
            'admin_note'    => isset($_POST['admin_note']) ? wp_unslash($_POST['admin_note']) : '',
        );
    }

    /**
     * 清理查詢參數。
     *
     * @param array $args 原始參數。
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

        $review_status = isset($args['review_status'])
            ? sanitize_key(wp_unslash($args['review_status']))
            : '';

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
            'status'        => $this->allowed_value($status, array('', 'active', 'inactive'), ''),
            'category'      => $category,
            'source'        => $this->allowed_value($source, array('', 'manual', 'ai_log', 'import'), ''),
            'review_status' => $this->allowed_value($review_status, array('', 'draft', 'pending', 'approved', 'rejected'), ''),
            'search'        => $search,
            'orderby'       => $orderby,
            'order'         => strtoupper($order) === 'DESC' ? 'DESC' : 'ASC',
            'limit'         => $per_page,
            'offset'        => ($paged - 1) * $per_page,
            'paged'         => $paged,
            'per_page'      => $per_page,
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
            'page'        => 'ur-ai-assistant-faqs',
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
     * 後台訊息文字。
     *
     * @param string $code 訊息代碼。
     * @return string
     */
    public function get_admin_message($code) {
        $code = sanitize_key($code);

        $messages = array(
            'faq_created'               => __('FAQ 已新增。', 'ur-ai-assistant'),
            'faq_create_failed'         => __('FAQ 新增失敗，請確認問題與回答內容。', 'ur-ai-assistant'),
            'faq_updated'               => __('FAQ 已更新。', 'ur-ai-assistant'),
            'faq_update_failed'         => __('FAQ 更新失敗。', 'ur-ai-assistant'),
            'faq_deleted'               => __('FAQ 已刪除。', 'ur-ai-assistant'),
            'faq_delete_failed'         => __('FAQ 刪除失敗。', 'ur-ai-assistant'),
            'faqs_activated'            => __('已批次啟用 FAQ。', 'ur-ai-assistant'),
            'faqs_deactivated'          => __('已批次停用 FAQ。', 'ur-ai-assistant'),
            'faqs_deleted'              => __('已批次刪除 FAQ。', 'ur-ai-assistant'),
            'faqs_bulk_failed'          => __('批次操作失敗，請稍後再試。', 'ur-ai-assistant'),
            'faq_draft_created'         => __('已將問答紀錄轉成 FAQ 草稿，請檢查內容後再啟用。', 'ur-ai-assistant'),
            'faq_draft_create_failed'   => __('FAQ 草稿建立失敗，可能該紀錄已轉入或內容不完整。', 'ur-ai-assistant'),
            'invalid_faq_id'            => __('FAQ ID 不正確。', 'ur-ai-assistant'),
            'invalid_log_id'            => __('問答紀錄 ID 不正確。', 'ur-ai-assistant'),
            'no_items_selected'         => __('請先選擇要操作的 FAQ。', 'ur-ai-assistant'),
            'invalid_bulk_action'       => __('批次操作不正確。', 'ur-ai-assistant'),
            'faq_service_missing'       => __('FAQ 服務尚未正確載入。', 'ur-ai-assistant'),
            'faq_draft_service_missing' => __('FAQ 草稿服務尚未正確載入。', 'ur-ai-assistant'),
            'faq_imported'              => __('CSV 匯入完成。', 'ur-ai-assistant'),
            'faq_import_no_file'        => __('未收到上傳檔案，請選擇 CSV 檔後再匯入。', 'ur-ai-assistant'),
            'faq_import_upload_error'   => __('檔案上傳失敗，請重新嘗試。', 'ur-ai-assistant'),
            'faq_import_bad_type'       => __('檔案格式不符，請上傳 .csv 檔。', 'ur-ai-assistant'),
            'faq_import_parse_error'    => __('CSV 無法解析，請確認檔案含「標準問題」與「固定回答」欄位，且為 UTF-8 編碼。', 'ur-ai-assistant'),
            'faq_import_empty'          => __('CSV 內沒有可匯入的資料列。', 'ur-ai-assistant'),
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
            'id'            => __('ID', 'ur-ai-assistant'),
            'category'      => __('分類', 'ur-ai-assistant'),
            'question'      => __('問題', 'ur-ai-assistant'),
            'answer'        => __('回答', 'ur-ai-assistant'),
            'keywords'      => __('關鍵字', 'ur-ai-assistant'),
            'status'        => __('狀態', 'ur-ai-assistant'),
            'source'        => __('來源', 'ur-ai-assistant'),
            'source_log_id' => __('來源紀錄 ID', 'ur-ai-assistant'),
            'review_status' => __('審核狀態', 'ur-ai-assistant'),
            'sort_order'    => __('排序', 'ur-ai-assistant'),
            'hit_count'     => __('命中次數', 'ur-ai-assistant'),
            'admin_note'    => __('管理備註', 'ur-ai-assistant'),
            'created_at'    => __('建立時間', 'ur-ai-assistant'),
            'updated_at'    => __('更新時間', 'ur-ai-assistant'),
        );
    }
}