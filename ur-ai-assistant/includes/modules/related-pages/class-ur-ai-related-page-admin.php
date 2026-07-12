<?php
/**
 * UR AI Assistant Related Page Admin
 *
 * 相關頁面推薦後台管理控制器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Related_Page_Admin
 */
class UR_AI_Related_Page_Admin {

    /**
     * Related Page Service.
     *
     * @var UR_AI_Related_Page_Service|null
     */
    private $service = null;

    /**
     * Importer.
     *
     * @var UR_AI_Related_Page_Importer|null
     */
    private $importer = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->service = class_exists('UR_AI_Related_Page_Service')
            ? new UR_AI_Related_Page_Service()
            : null;

        $this->importer = class_exists('UR_AI_Related_Page_Importer')
            ? new UR_AI_Related_Page_Importer()
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
            UR_AI_Permissions::require_manage_related_pages();
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
            case 'create_related_page':
                $this->handle_create();
                break;

            case 'update_related_page':
                $this->handle_update();
                break;

            case 'delete_related_page':
                $this->handle_delete();
                break;

            case 'bulk_related_pages':
                $this->handle_bulk();
                break;

            case 'import_related_page_from_post':
                $this->handle_import_from_post();
                break;

            case 'bulk_import_related_pages':
                $this->handle_bulk_import();
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
        if (!$this->service instanceof UR_AI_Related_Page_Service) {
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
     * 取得可匯入文章。
     *
     * @param string $keyword 搜尋字。
     * @param int    $limit 筆數。
     * @return array
     */
    public function search_importable_posts($keyword = '', $limit = 20) {
        if (!$this->importer instanceof UR_AI_Related_Page_Importer) {
            return array();
        }

        return $this->importer->search_importable_posts($keyword, $limit);
    }

    /**
     * 處理新增推薦頁面。
     *
     * @return void
     */
    private function handle_create() {
        if (!$this->service instanceof UR_AI_Related_Page_Service) {
            $this->redirect_with_message('related_service_missing', 'error');
        }

        $data = $this->get_form_data();
        $id   = $this->service->create($data);

        $this->redirect_with_message(
            $id > 0 ? 'related_page_created' : 'related_page_create_failed',
            $id > 0 ? 'updated' : 'error',
            array(
                'related_page_id' => $id,
            )
        );
    }

    /**
     * 處理更新推薦頁面。
     *
     * @return void
     */
    private function handle_update() {
        if (!$this->service instanceof UR_AI_Related_Page_Service) {
            $this->redirect_with_message('related_service_missing', 'error');
        }

        $id = isset($_POST['related_page_id']) ? absint($_POST['related_page_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_related_page_id', 'error');
        }

        $updated = $this->service->update($id, $this->get_form_data());

        $this->redirect_with_message(
            $updated ? 'related_page_updated' : 'related_page_update_failed',
            $updated ? 'updated' : 'error',
            array(
                'related_page_id' => $id,
            )
        );
    }

    /**
     * 處理刪除推薦頁面。
     *
     * @return void
     */
    private function handle_delete() {
        if (!$this->service instanceof UR_AI_Related_Page_Service) {
            $this->redirect_with_message('related_service_missing', 'error');
        }

        $id = isset($_POST['related_page_id']) ? absint($_POST['related_page_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_related_page_id', 'error');
        }

        $deleted = $this->service->delete($id);

        $this->redirect_with_message(
            $deleted ? 'related_page_deleted' : 'related_page_delete_failed',
            $deleted ? 'updated' : 'error'
        );
    }

    /**
     * 處理批次操作。
     *
     * @return void
     */
    private function handle_bulk() {
        if (!$this->service instanceof UR_AI_Related_Page_Service) {
            $this->redirect_with_message('related_service_missing', 'error');
        }

        $bulk_action = isset($_POST['bulk_action'])
            ? sanitize_key(wp_unslash($_POST['bulk_action']))
            : '';

        $ids = isset($_POST['related_page_ids'])
            ? (array) wp_unslash($_POST['related_page_ids'])
            : array();

        if (class_exists('UR_AI_Security')) {
            $ids = UR_AI_Security::sanitize_ids($ids);
        } else {
            $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        }

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
                    $count > 0 ? 'related_pages_activated' : 'related_pages_bulk_failed',
                    $count > 0 ? 'updated' : 'error',
                    array('count' => $count)
                );
                break;

            case 'deactivate':
                $count = $this->service->bulk_deactivate($ids);
                $this->redirect_with_message(
                    $count > 0 ? 'related_pages_deactivated' : 'related_pages_bulk_failed',
                    $count > 0 ? 'updated' : 'error',
                    array('count' => $count)
                );
                break;

            case 'delete':
                $count = $this->service->bulk_delete($ids);
                $this->redirect_with_message(
                    $count > 0 ? 'related_pages_deleted' : 'related_pages_bulk_failed',
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
     * 處理從單篇文章匯入。
     *
     * @return void
     */
    private function handle_import_from_post() {
        if (!$this->importer instanceof UR_AI_Related_Page_Importer) {
            $this->redirect_with_message('related_importer_missing', 'error');
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if ($post_id <= 0) {
            $this->redirect_with_message('invalid_post_id', 'error');
        }

        $allow_duplicate = !empty($_POST['allow_duplicate']);
        $result          = $this->importer->import_from_post($post_id, $allow_duplicate);

        $this->redirect_with_message(
            !empty($result['success']) ? 'related_page_imported' : $result['code'],
            !empty($result['success']) ? 'updated' : 'error',
            array(
                'related_page_id' => isset($result['related_page_id']) ? absint($result['related_page_id']) : 0,
            )
        );
    }

    /**
     * 處理批次匯入。
     *
     * @return void
     */
    private function handle_bulk_import() {
        if (!$this->importer instanceof UR_AI_Related_Page_Importer) {
            $this->redirect_with_message('related_importer_missing', 'error');
        }

        $post_ids = isset($_POST['post_ids'])
            ? (array) wp_unslash($_POST['post_ids'])
            : array();

        $post_ids = array_values(array_unique(array_filter(array_map('absint', $post_ids))));

        if (empty($post_ids)) {
            $this->redirect_with_message('no_posts_selected', 'error');
        }

        $allow_duplicate = !empty($_POST['allow_duplicate']);
        $result          = $this->importer->bulk_import_from_posts($post_ids, $allow_duplicate);

        $this->redirect_with_message(
            'related_pages_bulk_imported',
            'updated',
            array(
                'success_count' => isset($result['success_count']) ? absint($result['success_count']) : 0,
                'failed_count'  => isset($result['failed_count']) ? absint($result['failed_count']) : 0,
                'skipped_count' => isset($result['skipped_count']) ? absint($result['skipped_count']) : 0,
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

        if ('export_related_pages_csv' !== $action) {
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

        if (!$this->service instanceof UR_AI_Related_Page_Service) {
            wp_die(
                esc_html__('推薦頁面服務尚未載入，無法匯出資料。', 'ur-ai-assistant'),
                esc_html__('匯出失敗', 'ur-ai-assistant'),
                array('response' => 500)
            );
        }

        $query_args = $this->sanitize_query_args($_GET);
        $query_args['limit']  = 5000;
        $query_args['offset'] = 0;

        $items = $this->service->query($query_args);
        $rows  = $this->service->prepare_export_rows($items);

        $headers = class_exists('UR_AI_Schema_Related_Pages')
            ? UR_AI_Schema_Related_Pages::get_export_columns()
            : $this->fallback_export_columns();

        UR_AI_Exporter::output_csv(
            UR_AI_Exporter::build_filename('ur-ai-related-pages'),
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
            'category'    => isset($_POST['category']) ? wp_unslash($_POST['category']) : '待分類',
            'title'       => isset($_POST['title']) ? wp_unslash($_POST['title']) : '',
            'url'         => isset($_POST['url']) ? wp_unslash($_POST['url']) : '',
            'description' => isset($_POST['description']) ? wp_unslash($_POST['description']) : '',
            'keywords'    => isset($_POST['keywords']) ? wp_unslash($_POST['keywords']) : '',
            'status'      => isset($_POST['status']) ? wp_unslash($_POST['status']) : 'inactive',
            'source'      => isset($_POST['source']) ? wp_unslash($_POST['source']) : 'manual',
            'sort_order'  => isset($_POST['sort_order']) ? absint($_POST['sort_order']) : 100,
            'admin_note'  => isset($_POST['admin_note']) ? wp_unslash($_POST['admin_note']) : '',
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
            'source'   => $this->allowed_value($source, array('', 'manual', 'post', 'page', 'import'), ''),
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
            'page'        => 'ur-ai-assistant-related-pages',
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
            'related_page_created'       => __('推薦頁面已新增。', 'ur-ai-assistant'),
            'related_page_create_failed' => __('推薦頁面新增失敗，請確認標題與網址。', 'ur-ai-assistant'),
            'related_page_updated'       => __('推薦頁面已更新。', 'ur-ai-assistant'),
            'related_page_update_failed' => __('推薦頁面更新失敗。', 'ur-ai-assistant'),
            'related_page_deleted'       => __('推薦頁面已刪除。', 'ur-ai-assistant'),
            'related_page_delete_failed' => __('推薦頁面刪除失敗。', 'ur-ai-assistant'),
            'related_pages_activated'    => __('已批次啟用推薦頁面。', 'ur-ai-assistant'),
            'related_pages_deactivated'  => __('已批次停用推薦頁面。', 'ur-ai-assistant'),
            'related_pages_deleted'      => __('已批次刪除推薦頁面。', 'ur-ai-assistant'),
            'related_pages_bulk_failed'  => __('批次操作失敗，請稍後再試。', 'ur-ai-assistant'),
            'related_page_imported'      => __('已成功匯入推薦頁面，預設為停用，請檢查後再啟用。', 'ur-ai-assistant'),
            'related_pages_bulk_imported'=> __('批次匯入完成，請檢查匯入結果與資料內容。', 'ur-ai-assistant'),
            'already_imported'           => __('此文章已匯入過推薦頁面。', 'ur-ai-assistant'),
            'url_exists'                 => __('此網址已存在於推薦頁面中。', 'ur-ai-assistant'),
            'post_not_found'             => __('找不到可匯入的已發布文章或頁面。', 'ur-ai-assistant'),
            'invalid_related_page_id'    => __('推薦頁面 ID 不正確。', 'ur-ai-assistant'),
            'invalid_post_id'            => __('文章 ID 不正確。', 'ur-ai-assistant'),
            'no_items_selected'          => __('請先選擇要操作的推薦頁面。', 'ur-ai-assistant'),
            'no_posts_selected'          => __('請先選擇要匯入的文章或頁面。', 'ur-ai-assistant'),
            'invalid_bulk_action'        => __('批次操作不正確。', 'ur-ai-assistant'),
            'related_service_missing'    => __('推薦頁面服務尚未正確載入。', 'ur-ai-assistant'),
            'related_importer_missing'   => __('推薦頁面匯入服務尚未正確載入。', 'ur-ai-assistant'),
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
            'id'          => __('ID', 'ur-ai-assistant'),
            'category'    => __('分類', 'ur-ai-assistant'),
            'title'       => __('推薦標題', 'ur-ai-assistant'),
            'url'         => __('推薦網址', 'ur-ai-assistant'),
            'description' => __('簡短說明', 'ur-ai-assistant'),
            'keywords'    => __('關鍵字', 'ur-ai-assistant'),
            'status'      => __('狀態', 'ur-ai-assistant'),
            'show_count'  => __('曝光次數', 'ur-ai-assistant'),
            'click_count' => __('點擊次數', 'ur-ai-assistant'),
        );
    }
}