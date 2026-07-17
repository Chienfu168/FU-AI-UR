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
     * @return string
     */
    public static function get_openai_api_key() {
        return trim((string) self::get('openai_api_key', ''));
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

            default:
                return is_scalar($value) ? sanitize_text_field((string) $value) : '';
        }
    }
}
