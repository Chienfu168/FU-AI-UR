<?php
/**
 * UR AI Gateway Settings
 *
 * 外掛設定管理類別：這裡存的是「服務提供者自己的」OpenAI API Key
 * （代管服務實際呼叫 OpenAI 用的憑證），與客戶端網站各自的設定完全
 * 分開、互不影響。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Settings
 */
class UR_AI_Gateway_Settings {

    /**
     * 取得全部設定。
     *
     * @return array
     */
    public static function get_all() {
        $settings = get_option(UR_AI_GATEWAY_OPTION_SETTINGS, array());

        if (!is_array($settings)) {
            $settings = array();
        }

        return wp_parse_args($settings, self::defaults());
    }

    /**
     * 取得單一設定。
     *
     * @param string $key 設定鍵。
     * @param mixed  $default 預設值。
     * @return mixed
     */
    public static function get($key, $default = null) {
        $settings = self::get_all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * 批次更新設定。
     *
     * @param array $values 設定值。
     * @return bool
     */
    public static function update_many($values) {
        if (!is_array($values)) {
            return false;
        }

        $settings = self::get_all();

        foreach ($values as $key => $value) {
            $key = sanitize_key($key);

            if ('' === $key) {
                continue;
            }

            $settings[$key] = self::sanitize_value($key, $value);
        }

        $settings = wp_parse_args($settings, self::defaults());

        return update_option(UR_AI_GATEWAY_OPTION_SETTINGS, $settings);
    }

    /**
     * 取得代管服務實際用來呼叫 OpenAI 的 API Key。
     *
     * 優先讀取 wp-config.php 的 UR_AI_GATEWAY_OPENAI_KEY 常數（金鑰不落在
     * 資料庫，降低資料庫備份或其他外掛漏洞外洩金鑰的風險）；未定義時才
     * 退回資料庫設定。
     *
     * @return string
     */
    public static function get_openai_api_key() {
        if (defined('UR_AI_GATEWAY_OPENAI_KEY') && '' !== trim((string) UR_AI_GATEWAY_OPENAI_KEY)) {
            return trim((string) UR_AI_GATEWAY_OPENAI_KEY);
        }

        return trim((string) self::get('openai_api_key', ''));
    }

    /**
     * API Key 是否來自 wp-config.php 常數（供後台設定頁顯示來源）。
     *
     * @return bool
     */
    public static function is_api_key_from_constant() {
        return defined('UR_AI_GATEWAY_OPENAI_KEY') && '' !== trim((string) UR_AI_GATEWAY_OPENAI_KEY);
    }

    /**
     * 取得轉發請求的成本防護上限（供 UR_AI_Gateway_Request_Validator 使用）。
     *
     * @return array
     */
    public static function get_request_limits() {
        $models_raw = (string) self::get('allowed_models', '');
        $models     = array();
        foreach (preg_split('/[\s,，]+/', $models_raw) as $m) {
            $m = trim((string) $m);
            if ('' !== $m) {
                $models[] = $m;
            }
        }

        return array(
            'allowed_models'    => $models, // 空＝不限制。
            'max_output_tokens' => absint(self::get('max_output_tokens', 16384)),
            'max_messages'      => max(1, absint(self::get('max_messages', 100))),
            'max_input_chars'   => max(1, absint(self::get('max_input_chars', 200000))),
        );
    }

    /**
     * 取得端點層每 IP 每分鐘請求上限（0＝停用節流）。
     *
     * @return int
     */
    public static function get_rate_limit_per_min() {
        return absint(self::get('rate_limit_per_min', 120));
    }

    /**
     * 取得預設每日呼叫上限（新授權碼建立時的預設值，個別授權碼仍可
     * 各自覆蓋）。
     *
     * @return int
     */
    public static function get_default_daily_limit() {
        $limit = absint(self::get('default_daily_limit', 200));

        return $limit > 0 ? $limit : 200;
    }

    /**
     * 預設設定。
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'openai_api_key'       => '',
            'default_daily_limit'  => 200,
            // 轉發請求的成本防護上限（見 UR_AI_Gateway_Request_Validator）。
            'allowed_models'       => '', // 逗號分隔；空＝不限制 model。
            'max_output_tokens'    => 16384,
            'max_messages'         => 100,
            'max_input_chars'      => 200000,
            // 端點層每 IP 每分鐘請求上限（見 UR_AI_Gateway_Rate_Limiter）；0＝停用。
            'rate_limit_per_min'   => 120,
        );
    }

    /**
     * 設定值清理規則。
     *
     * @param string $key 設定鍵。
     * @param mixed  $value 設定值。
     * @return mixed
     */
    private static function sanitize_value($key, $value) {
        switch ($key) {
            case 'openai_api_key':
                return sanitize_text_field((string) $value);

            case 'default_daily_limit':
                $limit = absint($value);

                if ($limit < 1) {
                    $limit = 1;
                }

                if ($limit > 100000) {
                    $limit = 100000;
                }

                return $limit;

            case 'allowed_models':
                return sanitize_text_field((string) $value);

            case 'max_output_tokens':
                $v = absint($value);
                return $v > 0 ? min($v, 128000) : 16384;

            case 'max_messages':
                $v = absint($value);
                return $v > 0 ? min($v, 1000) : 100;

            case 'max_input_chars':
                $v = absint($value);
                return $v > 0 ? min($v, 5000000) : 200000;

            case 'rate_limit_per_min':
                // 0 合法（＝停用）；上限設個合理天花板避免誤填天文數字。
                return min(absint($value), 1000000);

            default:
                return is_scalar($value) ? sanitize_text_field((string) $value) : '';
        }
    }
}
