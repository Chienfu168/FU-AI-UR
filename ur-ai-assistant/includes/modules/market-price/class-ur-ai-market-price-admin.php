<?php
/**
 * UR AI Assistant Market Price Admin
 *
 * 行情參考後台：CSV 匯入處理與設定儲存。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Admin
 */
class UR_AI_Market_Price_Admin {

    /**
     * Service。
     *
     * @var UR_AI_Market_Price_Service|null
     */
    private $service;

    /**
     * Import Service。
     *
     * @var UR_AI_Market_Price_Import_Service|null
     */
    private $import_service;

    /**
     * 建構子。
     *
     * @param UR_AI_Market_Price_Service|null $service Service。
     */
    public function __construct($service = null) {
        $this->service        = $service instanceof UR_AI_Market_Price_Service
            ? $service
            : (class_exists('UR_AI_Market_Price_Service') ? new UR_AI_Market_Price_Service() : null);
        $this->import_service = class_exists('UR_AI_Market_Price_Import_Service')
            ? new UR_AI_Market_Price_Import_Service()
            : null;
    }

    /**
     * 處理行情資料 CSV 匯入。
     *
     * @return void
     */
    public function handle_import() {
        $this->require_admin_capability();
        check_admin_referer(UR_AI_Market_Price_Module::IMPORT_ACTION);

        $redirect_base = admin_url('admin.php?page=' . UR_AI_Market_Price_Module::ADMIN_MENU_SLUG);

        if (!$this->import_service instanceof UR_AI_Market_Price_Import_Service) {
            $this->redirect_with_message($redirect_base, 'import_service_missing', 'error');
        }

        $city = isset($_POST['city']) ? sanitize_key(wp_unslash($_POST['city'])) : '';

        if (!isset($_FILES['ur_ai_market_price_csv']) || !is_array($_FILES['ur_ai_market_price_csv'])) {
            $this->redirect_with_message($redirect_base, 'import_no_file', 'error');
        }

        $file          = $_FILES['ur_ai_market_price_csv'];
        $upload_error  = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        // UPLOAD_ERR_NO_FILE：欄位存在但使用者沒有選擇檔案，需與其他上傳失敗
        // 原因分開判斷，否則永遠只會顯示「上傳失敗」而非「請選擇檔案」。
        if (UPLOAD_ERR_NO_FILE === $upload_error) {
            $this->redirect_with_message($redirect_base, 'import_no_file', 'error');
        }

        if (UPLOAD_ERR_OK !== $upload_error) {
            $this->redirect_with_message($redirect_base, 'import_upload_error', 'error');
        }

        $tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';

        if ('' === $tmp_name || !is_uploaded_file($tmp_name)) {
            $this->redirect_with_message($redirect_base, 'import_no_file', 'error');
        }

        $filename = isset($file['name']) ? sanitize_file_name($file['name']) : '';
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ('csv' !== $ext && 'txt' !== $ext) {
            $this->redirect_with_message($redirect_base, 'import_bad_type', 'error');
        }

        $result = $this->import_service->import_from_csv($tmp_name, $city);

        if (!empty($result['city_mismatch'])) {
            $this->redirect_with_message(
                $redirect_base,
                'import_city_mismatch',
                'error',
                array(
                    'imp_detected' => isset($result['detected_city']) ? sanitize_key($result['detected_city']) : '',
                )
            );
        }

        if (class_exists('UR_AI_Market_Price_Service') && $this->service instanceof UR_AI_Market_Price_Service) {
            $this->service->clear_cache();
        }

        $this->redirect_with_message(
            $redirect_base,
            'imported',
            'updated',
            array(
                'imp_created'   => absint($result['created']),
                'imp_duplicate' => absint($result['duplicate']),
                'imp_skipped'   => absint($result['skipped']),
                'imp_total'     => absint($result['total']),
                'imp_warning'   => !empty($result['warnings']) ? 1 : 0,
                'imp_city'      => isset($result['detected_city']) ? sanitize_key($result['detected_city']) : '',
            )
        );
    }

    /**
     * 處理設定儲存。
     *
     * @return void
     */
    public function handle_settings_save() {
        $this->require_admin_capability();
        check_admin_referer(UR_AI_Market_Price_Module::SETTINGS_SAVE_ACTION);

        if (class_exists('UR_AI_Market_Price_Settings')) {
            UR_AI_Market_Price_Settings::update(
                array(
                    'enabled'           => !empty($_POST['enabled']) ? 1 : 0,
                    'old_age_threshold' => isset($_POST['old_age_threshold']) ? wp_unslash($_POST['old_age_threshold']) : '',
                    'new_age_threshold' => isset($_POST['new_age_threshold']) ? wp_unslash($_POST['new_age_threshold']) : '',
                    'min_sample_size'   => isset($_POST['min_sample_size']) ? wp_unslash($_POST['min_sample_size']) : '',
                    'disclaimer'        => isset($_POST['disclaimer']) ? wp_unslash($_POST['disclaimer']) : '',
                )
            );
        }

        wp_safe_redirect(
            admin_url('admin.php?page=' . UR_AI_Market_Price_Module::ADMIN_MENU_SLUG . '&ur_message=settings_saved&ur_msg_type=updated')
        );
        exit;
    }

    /**
     * 後台權限守門。
     *
     * @return void
     */
    private function require_admin_capability() {
        $capability = 'manage_options';

        if (class_exists('UR_AI_Permissions') && method_exists('UR_AI_Permissions', 'get_capability')) {
            $maybe = UR_AI_Permissions::get_capability('dashboard');
            if (is_string($maybe) && '' !== $maybe) {
                $capability = $maybe;
            }
        }

        if (class_exists('UR_AI_Permissions') && method_exists('UR_AI_Permissions', 'require_capability')) {
            UR_AI_Permissions::require_capability($capability);
            return;
        }

        if (!current_user_can($capability)) {
            wp_die(esc_html__('權限不足。', 'ur-ai-assistant'));
        }
    }

    /**
     * 帶訊息重導。
     *
     * @param string $redirect_base 基礎網址。
     * @param string $message 訊息代碼。
     * @param string $type 訊息類型（updated / error）。
     * @param array  $extra_args 額外查詢字串參數。
     * @return void
     */
    private function redirect_with_message($redirect_base, $message, $type = 'updated', $extra_args = array()) {
        $args = array_merge(
            array(
                'ur_message'  => sanitize_key($message),
                'ur_msg_type' => sanitize_key($type),
            ),
            $extra_args
        );

        wp_safe_redirect(add_query_arg($args, $redirect_base));
        exit;
    }
}
