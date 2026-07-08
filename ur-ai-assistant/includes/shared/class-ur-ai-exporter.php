<?php
/**
 * UR AI Assistant Exporter
 *
 * 外掛共用 CSV 匯出工具類別。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Exporter
 */
class UR_AI_Exporter {

    /**
     * 匯出 nonce action.
     *
     * @var string
     */
    const EXPORT_NONCE_ACTION = 'ur_ai_export_nonce';

    /**
     * 驗證匯出請求。
     *
     * @return void
     */
    public static function verify_export_request_or_die() {
        if (class_exists('UR_AI_Permissions')) {
            if (!UR_AI_Permissions::can_access_admin()) {
                wp_die(
                    esc_html__('您沒有權限匯出資料。', 'ur-ai-assistant'),
                    esc_html__('權限不足', 'ur-ai-assistant'),
                    array('response' => 403)
                );
            }
        } elseif (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('您沒有權限匯出資料。', 'ur-ai-assistant'),
                esc_html__('權限不足', 'ur-ai-assistant'),
                array('response' => 403)
            );
        }

        $nonce = isset($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash($_GET['_wpnonce']))
            : '';

        if (!wp_verify_nonce($nonce, self::EXPORT_NONCE_ACTION)) {
            wp_die(
                esc_html__('匯出安全驗證失敗，請返回後台重新操作。', 'ur-ai-assistant'),
                esc_html__('安全驗證失敗', 'ur-ai-assistant'),
                array('response' => 403)
            );
        }
    }

    /**
     * 建立匯出檔名。
     *
     * @param string $prefix 檔名前綴。
     * @param string $extension 副檔名。
     * @return string
     */
    public static function build_filename($prefix, $extension = 'csv') {
        $prefix    = sanitize_file_name((string) $prefix);
        $extension = sanitize_key((string) $extension);

        if ('' === $prefix) {
            $prefix = 'ur-ai-export';
        }

        if ('' === $extension) {
            $extension = 'csv';
        }

        return sprintf(
            '%1$s-%2$s.%3$s',
            $prefix,
            current_time('Ymd-His'),
            $extension
        );
    }

    /**
     * 輸出 CSV。
     *
     * @param string $filename 檔名。
     * @param array  $headers 標題欄位，格式 key => label。
     * @param array  $rows 資料列，格式 array(array('key' => 'value'))。
     * @return void
     */
    public static function output_csv($filename, $headers, $rows) {
        if (!is_array($headers) || empty($headers)) {
            wp_die(
                esc_html__('CSV 欄位設定不完整。', 'ur-ai-assistant'),
                esc_html__('匯出失敗', 'ur-ai-assistant'),
                array('response' => 500)
            );
        }

        if (!is_array($rows)) {
            $rows = array();
        }

        $filename = sanitize_file_name($filename);

        if ('' === $filename) {
            $filename = self::build_filename('ur-ai-export');
        }

        nocache_headers();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        if (false === $output) {
            wp_die(
                esc_html__('無法建立 CSV 輸出。', 'ur-ai-assistant'),
                esc_html__('匯出失敗', 'ur-ai-assistant'),
                array('response' => 500)
            );
        }

        /**
         * UTF-8 BOM，避免 Excel 開啟中文亂碼。
         */
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, array_values($headers));

        foreach ($rows as $row) {
            $line = array();

            foreach (array_keys($headers) as $key) {
                $value = is_array($row) && array_key_exists($key, $row)
                    ? $row[$key]
                    : '';

                $line[] = self::format_cell($value);
            }

            fputcsv($output, $line);
        }

        fclose($output);
        exit;
    }

    /**
     * 格式化 CSV 儲存格。
     *
     * @param mixed $value 原始值。
     * @return string
     */
    private static function format_cell($value) {
        if (class_exists('UR_AI_Formatter')) {
            return UR_AI_Formatter::csv_cell($value);
        }

        if (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $value = (string) $value;
        $value = str_replace(array("\r\n", "\r", "\n"), ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * 建立匯出 URL。
     *
     * @param string $page 後台頁面 slug。
     * @param string $action 匯出 action。
     * @param array  $args 額外參數。
     * @return string
     */
    public static function export_url($page, $action, $args = array()) {
        $args = is_array($args) ? $args : array();

        $args['page']      = sanitize_key($page);
        $args['ur_action'] = sanitize_key($action);
        $args['_wpnonce']  = wp_create_nonce(self::EXPORT_NONCE_ACTION);

        return add_query_arg($args, admin_url('admin.php'));
    }
}