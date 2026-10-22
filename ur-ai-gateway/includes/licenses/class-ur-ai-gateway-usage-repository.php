<?php
/**
 * UR AI Gateway Usage Repository
 *
 * 用量明細資料存取層。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Usage_Repository
 */
class UR_AI_Gateway_Usage_Repository {

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
        $this->table_name = class_exists('UR_AI_Gateway_Schema_Usage')
            ? UR_AI_Gateway_Schema_Usage::table_name()
            : $GLOBALS['wpdb']->prefix . 'ur_ai_gateway_usage';
    }

    /**
     * 記錄一次用量。
     *
     * @param array $data {license_id, model, prompt_tokens, completion_tokens, total_tokens, status_code}
     * @return int|false 新 id 或 false。
     */
    public function record(array $data) {
        global $wpdb;

        $row = array(
            'license_id'        => absint($data['license_id'] ?? 0),
            'model'             => substr((string) ($data['model'] ?? ''), 0, 100),
            'prompt_tokens'     => max(0, (int) ($data['prompt_tokens'] ?? 0)),
            'completion_tokens' => max(0, (int) ($data['completion_tokens'] ?? 0)),
            'total_tokens'      => max(0, (int) ($data['total_tokens'] ?? 0)),
            'status_code'       => max(0, (int) ($data['status_code'] ?? 0)),
            'created_at'        => current_time('mysql'),
        );

        $ok = $wpdb->insert($this->table_name, $row);

        return $ok ? (int) $wpdb->insert_id : false;
    }

    /**
     * 某授權碼在指定起始時間之後的 token 合計（供用量計費查詢）。
     *
     * @param int    $license_id 授權碼 ID。
     * @param string $since      起始時間（mysql datetime，空＝全部）。
     * @return int
     */
    public function sum_tokens_for_license($license_id, $since = '') {
        global $wpdb;

        $license_id = absint($license_id);
        if ($license_id <= 0) {
            return 0;
        }

        if ('' !== $since) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(total_tokens),0) FROM {$this->table_name} WHERE license_id = %d AND created_at >= %s",
                    $license_id,
                    $since
                )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(SUM(total_tokens),0) FROM {$this->table_name} WHERE license_id = %d", $license_id)
        );
    }

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public function get_table_name() {
        return $this->table_name;
    }
}
