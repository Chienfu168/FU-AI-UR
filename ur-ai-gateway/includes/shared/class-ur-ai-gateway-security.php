<?php
/**
 * UR AI Gateway Security
 *
 * 後台表單 nonce 與共用清理工具，沿用 UR AI Assistant 外掛同樣的
 * 命名與使用方式，降低跨外掛協作時的認知負擔。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Security
 */
class UR_AI_Gateway_Security {

    /**
     * 後台表單 nonce action。
     *
     * @var string
     */
    const ADMIN_NONCE_ACTION = 'ur_ai_gateway_admin_action';

    /**
     * 後台表單 nonce 欄位名稱。
     *
     * @var string
     */
    const ADMIN_NONCE_NAME = 'ur_ai_gateway_nonce';

    /**
     * 輸出後台表單 nonce 欄位。
     *
     * @return void
     */
    public static function admin_form_nonce_field() {
        wp_nonce_field(self::ADMIN_NONCE_ACTION, self::ADMIN_NONCE_NAME);
    }

    /**
     * 驗證後台表單 nonce，失敗則中止並提示。
     *
     * @return void
     */
    public static function verify_admin_form_nonce_or_die() {
        check_admin_referer(self::ADMIN_NONCE_ACTION, self::ADMIN_NONCE_NAME);
    }

    /**
     * 清理整數 ID 陣列（去除非正整數、重複值）。
     *
     * @param array $ids ID 陣列。
     * @return array
     */
    public static function sanitize_ids($ids) {
        return array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
    }
}
