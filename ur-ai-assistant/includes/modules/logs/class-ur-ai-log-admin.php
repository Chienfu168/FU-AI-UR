<?php
/**
 * UR AI Assistant Log Admin
 *
 * 問答紀錄後台管理控制器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Log_Admin
 */
class UR_AI_Log_Admin {

    /**
     * Log Service.
     *
     * @var UR_AI_Log_Service|null
     */
    private $service = null;

    /**
     * FAQ Draft Service.
     *
     * @var UR_AI_FAQ_Draft_Service|null
     */
    private $faq_draft_service = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->service = class_exists('UR_AI_Log_Service')
            ? new UR_AI_Log_Service()
            : null;

        $this->faq_draft_service = class_exists('UR_AI_FAQ_Draft_Service')
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
            UR_AI_Permissions::require_view_logs();
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
            case 'delete_log':
                $this->handle_delete();
                break;

            case 'bulk_logs':
                $this->handle_bulk();
                break;

            case 'convert_log_to_faq':
                $this->handle_convert_log_to_faq();
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
        if (!$this->service instanceof UR_AI_Log_Service) {
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
     * 刪除單筆問答紀錄。
     *
     * @return void
     */
    private function handle_delete() {
        if (!$this->service instanceof UR_AI_Log_Service) {
            $this->redirect_with_message('log_service_missing', 'error');
        }

        $id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_log_id', 'error');
        }

        $deleted = $this->service->delete($id);

        $this->redirect_with_message(
            $deleted ? 'log_deleted' : 'log_delete_failed',
            $deleted ? 'updated' : 'error'
        );
    }

    /**
     * 批次操作。
     *
     * @return void
     */
    private function handle_bulk() {
        if (!$this->service instanceof UR_AI_Log_Service) {
            $this->redirect_with_message('log_service_missing', 'error');
        }

        $bulk_action = isset($_POST['bulk_action'])
            ? sanitize_key(wp_unslash($_POST['bulk_action']))
            : '';

        $ids = isset($_POST['log_ids'])
            ? (array) wp_unslash($_POST['log_ids'])
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
            case 'delete':
                $count = $this->service->bulk_delete($ids);
                $this->redirect_with_message(
                    $count > 0 ? 'logs_deleted' : 'logs_bulk_failed',
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
     * 問答紀錄轉 FAQ 草稿。
     *
     * @return void
     */
    private function handle_convert_log_to_faq() {
        if (!$this->faq_draft_service instanceof UR_AI_FAQ_Draft_Service) {
            $this->redirect_with_message('faq_draft_service_missing', 'error');
        }

        $log_id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;

        if ($log_id <= 0) {
            $this->redirect_with_message('invalid_log_id', 'error');
        }

        $faq_id = $this->faq_draft_service->create_from_log($log_id);

        $this->redirect_with_message(
            $faq_id > 0 ? 'log_converted_to_faq' : 'log_convert_to_faq_failed',
            $faq_id > 0 ? 'updated' : 'error',
            array(
                'faq_id' => $faq_id,
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

        if ('export_logs_csv' !== $action) {
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

        if (!$this->service instanceof UR_AI_Log_Service) {
            wp_die(
                esc_html__('問答紀錄服務尚未載入，無法匯出資料。', 'ur-ai-assistant'),
                esc_html__('匯出失敗', 'ur-ai-assistant'),
                array('response' => 500)
            );
        }

        $query_args = $this->sanitize_query_args($_GET);
        $query_args['limit']  = 5000;
        $query_args['offset'] = 0;

        $items = $this->service->query($query_args);
        $rows  = $this->service->prepare_export_rows($items);

        $headers = class_exists('UR_AI_Schema_Logs')
            ? UR_AI_Schema_Logs::get_export_columns()
            : $this->fallback_export_columns();

        UR_AI_Exporter::output_csv(
            UR_AI_Exporter::build_filename('ur-ai-logs'),
            $headers,
            $rows
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

        $answer_source = isset($args['answer_source'])
            ? sanitize_key(wp_unslash($args['answer_source']))
            : '';

        $status = isset($args['status'])
            ? sanitize_key(wp_unslash($args['status']))
            : '';

        $feedback = isset($args['feedback'])
            ? sanitize_key(wp_unslash($args['feedback']))
            : '';

        $has_related_pages = null;

        if (isset($args['has_related_pages']) && '' !== $args['has_related_pages']) {
            $has_related_pages = absint($args['has_related_pages']);
        }

        $converted = null;

        if (isset($args['converted']) && '' !== $args['converted']) {
            $converted = absint($args['converted']);
        }

        $faq_id = null;

        if (isset($args['faq_id']) && '' !== $args['faq_id']) {
            $faq_id = absint($args['faq_id']);
        }

        $user_id = null;

        if (isset($args['user_id']) && '' !== $args['user_id']) {
            $user_id = absint($args['user_id']);
        }

        $date_from = isset($args['date_from'])
            ? sanitize_text_field(wp_unslash($args['date_from']))
            : '';

        $date_to = isset($args['date_to'])
            ? sanitize_text_field(wp_unslash($args['date_to']))
            : '';

        $search = isset($args['s'])
            ? sanitize_text_field(wp_unslash($args['s']))
            : '';

        $orderby = isset($args['orderby'])
            ? sanitize_key(wp_unslash($args['orderby']))
            : 'created_at';

        $order = isset($args['order'])
            ? sanitize_key(wp_unslash($args['order']))
            : 'DESC';

        return array(
            'answer_source'     => $this->allowed_value($answer_source, array('', 'faq', 'ai', 'error'), ''),
            'status'            => $this->allowed_value($status, array('', 'success', 'error'), ''),
            'feedback'          => $this->allowed_value($feedback, array('', 'helpful', 'not_helpful'), ''),
            'has_related_pages' => $has_related_pages,
            'converted'         => $converted,
            'faq_id'            => $faq_id,
            'user_id'           => $user_id,
            'date_from'         => $this->sanitize_date($date_from),
            'date_to'           => $this->sanitize_date($date_to),
            'search'            => $search,
            'orderby'           => $orderby,
            'order'             => strtoupper($order) === 'ASC' ? 'ASC' : 'DESC',
            'limit'             => $per_page,
            'offset'            => ($paged - 1) * $per_page,
            'paged'             => $paged,
            'per_page'          => $per_page,
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
            'page'        => 'ur-ai-assistant-logs',
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
            'log_deleted'               => __('問答紀錄已刪除。', 'ur-ai-assistant'),
            'log_delete_failed'         => __('問答紀錄刪除失敗。', 'ur-ai-assistant'),
            'logs_deleted'              => __('已批次刪除問答紀錄。', 'ur-ai-assistant'),
            'logs_bulk_failed'          => __('批次操作失敗，請稍後再試。', 'ur-ai-assistant'),
            'log_converted_to_faq'      => __('已將問答紀錄轉成 FAQ 草稿，請至 FAQ 知識庫檢查後再啟用。', 'ur-ai-assistant'),
            'log_convert_to_faq_failed' => __('問答紀錄轉 FAQ 草稿失敗，可能已轉入、內容不完整或服務未載入。', 'ur-ai-assistant'),
            'invalid_log_id'            => __('問答紀錄 ID 不正確。', 'ur-ai-assistant'),
            'no_items_selected'         => __('請先選擇要操作的問答紀錄。', 'ur-ai-assistant'),
            'invalid_bulk_action'       => __('批次操作不正確。', 'ur-ai-assistant'),
            'log_service_missing'       => __('問答紀錄服務尚未正確載入。', 'ur-ai-assistant'),
            'faq_draft_service_missing' => __('FAQ 草稿服務尚未正確載入。', 'ur-ai-assistant'),
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
     * 清理日期。
     *
     * @param string $date 日期。
     * @return string
     */
    private function sanitize_date($date) {
        $date = sanitize_text_field((string) $date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return '';
    }

    /**
     * fallback 匯出欄位。
     *
     * @return array
     */
    private function fallback_export_columns() {
        return array(
            'id'                   => __('ID', 'ur-ai-assistant'),
            'created_at'           => __('建立時間', 'ur-ai-assistant'),
            'user_id'              => __('使用者 ID', 'ur-ai-assistant'),
            'ip_address'           => __('IP', 'ur-ai-assistant'),
            'question'             => __('問題', 'ur-ai-assistant'),
            'answer'               => __('回答', 'ur-ai-assistant'),
            'answer_source'        => __('回答來源', 'ur-ai-assistant'),
            'model'                => __('模型', 'ur-ai-assistant'),
            'tokens_used'          => __('Token 使用量', 'ur-ai-assistant'),
            'faq_id'               => __('FAQ ID', 'ur-ai-assistant'),
            'faq_match_score'      => __('FAQ 命中分數', 'ur-ai-assistant'),
            'faq_matched_keywords' => __('FAQ 命中關鍵字', 'ur-ai-assistant'),
            'has_related_pages'    => __('是否有推薦頁面', 'ur-ai-assistant'),
            'related_page_ids'     => __('推薦頁面 ID', 'ur-ai-assistant'),
            'converted_faq_id'     => __('轉入 FAQ ID', 'ur-ai-assistant'),
            'feedback'             => __('回饋', 'ur-ai-assistant'),
            'feedback_reason'      => __('回饋原因', 'ur-ai-assistant'),
            'feedback_comment'     => __('回饋補充', 'ur-ai-assistant'),
            'status'               => __('狀態', 'ur-ai-assistant'),
            'error_code'           => __('錯誤代碼', 'ur-ai-assistant'),
            'error_message'        => __('錯誤訊息', 'ur-ai-assistant'),
        );
    }
}