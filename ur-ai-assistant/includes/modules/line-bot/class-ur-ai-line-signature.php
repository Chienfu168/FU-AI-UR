<?php
/**
 * UR AI Assistant LINE Signature
 *
 * 驗證 LINE 平台送來的 webhook 請求簽章（X-Line-Signature）：以 Channel
 * secret 對「原始請求本體」做 HMAC-SHA256、Base64 編碼後，與標頭值做
 * 定時比較（hash_equals，避免時序攻擊）。驗不過一律拒絕。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Signature
 */
class UR_AI_Line_Signature {

    /**
     * 驗證簽章。
     *
     * @param string $raw_body  原始請求本體（未解析的 JSON 字串）。
     * @param string $signature X-Line-Signature 標頭值。
     * @param string $secret    Channel secret。
     * @return bool
     */
    public static function is_valid($raw_body, $signature, $secret) {
        $raw_body  = (string) $raw_body;
        $signature = trim((string) $signature);
        $secret    = (string) $secret;

        if ('' === $signature || '' === $secret) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $raw_body, $secret, true));

        return hash_equals($expected, $signature);
    }
}
