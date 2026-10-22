<?php
/**
 * UR AI Gateway Schema: Usage
 *
 * 用量明細資料表：每一次成功轉發給 OpenAI 的請求，記錄其 token 用量與
 * 模型，供用量計費、毛利分析與出帳爭議時的依據。
 *
 * 只在「成功取得 OpenAI 回應且含 usage」時寫入一筆；驗證失敗、額度超限、
 * 上游錯誤等情況不寫入（那些不產生 OpenAI 計費）。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Schema_Usage
 */
class UR_AI_Gateway_Schema_Usage {

    /**
     * 資料表名稱（不含 prefix）。
     *
     * @var string
     */
    const TABLE_NAME = 'ur_ai_gateway_usage';

    /**
     * 取得完整資料表名稱。
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * 取得建表 SQL（dbDelta 格式）。
     *
     * @return string
     */
    public static function get_sql() {
        global $wpdb;

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            license_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            model VARCHAR(100) NOT NULL DEFAULT '',
            prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            status_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY license_id (license_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
    }
}
