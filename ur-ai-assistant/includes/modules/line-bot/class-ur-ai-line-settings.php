<?php
/**
 * UR AI Assistant LINE Settings
 *
 * LINE 官方帳號客服模組設定（獨立於主外掛設定 option）。
 *
 * 安全：Channel secret 與 access token 優先讀取 wp-config.php 常數
 * （UR_AI_LINE_CHANNEL_SECRET／UR_AI_LINE_CHANNEL_ACCESS_TOKEN），
 * 未定義時才退回資料庫設定，降低金鑰隨資料庫備份外洩的風險。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Settings
 */
class UR_AI_Line_Settings {

    /**
     * Option name.
     *
     * @var string
     */
    const OPTION_NAME = 'ur_ai_line_settings';

    /**
     * 取得全部設定。
     *
     * @return array
     */
    public static function get_all() {
        $settings = get_option(self::OPTION_NAME, array());

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

        return update_option(self::OPTION_NAME, $settings);
    }

    /**
     * 預設值。
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'enabled'              => 0,
            'channel_secret'       => '',
            'channel_access_token' => '',
            'welcome_message'      => '您好，我是 AI 客服助理，可協助回答都更危老的常見問題。本對話由 AI 協助處理並保存紀錄；需要專人服務時，請輸入「我要找真人」。',
            'reply_signature'      => "—\n更多都更危老資訊：\nwww.ur-promoter.com\nwww.fudawang.com",
            'transfer_keywords'    => '真人,專人,客服人員,人工',
            'admin_notify_email'   => '',
            'admin_line_ids'       => '',
            'human_timeout_hours'  => 12,
            'context_messages'     => 10,
            'hourly_limit'         => 30,
            'max_message_chars'    => 1000,
            'retention_days'       => 180,
        );
    }

    /**
     * 是否啟用 LINE 客服。
     *
     * @return bool
     */
    public static function is_enabled() {
        return (int) self::get('enabled', 0) === 1;
    }

    /**
     * 取得 Channel secret（優先 wp-config 常數）。
     *
     * @return string
     */
    public static function get_channel_secret() {
        if (defined('UR_AI_LINE_CHANNEL_SECRET') && '' !== trim((string) UR_AI_LINE_CHANNEL_SECRET)) {
            return trim((string) UR_AI_LINE_CHANNEL_SECRET);
        }

        return trim((string) self::get('channel_secret', ''));
    }

    /**
     * 取得 Channel access token（優先 wp-config 常數）。
     *
     * @return string
     */
    public static function get_channel_access_token() {
        if (defined('UR_AI_LINE_CHANNEL_ACCESS_TOKEN') && '' !== trim((string) UR_AI_LINE_CHANNEL_ACCESS_TOKEN)) {
            return trim((string) UR_AI_LINE_CHANNEL_ACCESS_TOKEN);
        }

        return trim((string) self::get('channel_access_token', ''));
    }

    /**
     * 憑證是否來自 wp-config 常數（後台顯示來源用）。
     *
     * @return bool
     */
    public static function credentials_from_constant() {
        return (defined('UR_AI_LINE_CHANNEL_SECRET') && '' !== trim((string) UR_AI_LINE_CHANNEL_SECRET))
            || (defined('UR_AI_LINE_CHANNEL_ACCESS_TOKEN') && '' !== trim((string) UR_AI_LINE_CHANNEL_ACCESS_TOKEN));
    }

    /**
     * 轉真人關鍵字陣列。
     *
     * @return array
     */
    public static function get_transfer_keywords() {
        $raw   = (string) self::get('transfer_keywords', '');
        $items = array();

        foreach (preg_split('/[\s,，、]+/u', $raw) as $kw) {
            $kw = trim((string) $kw);

            if ('' !== $kw) {
                $items[] = $kw;
            }
        }

        return $items;
    }

    /**
     * 管理員 LINE userId 清單（可下「#恢復」指令者）。
     *
     * @return array
     */
    public static function get_admin_line_ids() {
        $raw   = (string) self::get('admin_line_ids', '');
        $items = array();

        foreach (preg_split('/[\s,，、]+/u', $raw) as $id) {
            $id = trim((string) $id);

            if ('' !== $id) {
                $items[] = $id;
            }
        }

        return $items;
    }

    /**
     * 專人模式自動恢復時數。
     *
     * @return int
     */
    public static function get_human_timeout_hours() {
        return max(1, absint(self::get('human_timeout_hours', 12)));
    }

    /**
     * 帶給 AI 的近期對話筆數。
     *
     * @return int
     */
    public static function get_context_messages() {
        return max(1, absint(self::get('context_messages', 10)));
    }

    /**
     * 每位使用者每小時 AI 回覆上限（0＝不限制）。
     *
     * @return int
     */
    public static function get_hourly_limit() {
        return absint(self::get('hourly_limit', 30));
    }

    /**
     * 單則訊息最大字元數。
     *
     * @return int
     */
    public static function get_max_message_chars() {
        return max(1, absint(self::get('max_message_chars', 1000)));
    }

    /**
     * 對話紀錄保留天數（0＝不自動清理）。
     *
     * @return int
     */
    public static function get_retention_days() {
        return absint(self::get('retention_days', 180));
    }

    /**
     * 設定清理規則。
     *
     * @param string $key 設定鍵。
     * @param mixed  $value 設定值。
     * @return mixed
     */
    private static function sanitize_value($key, $value) {
        switch ($key) {
            case 'enabled':
                return !empty($value) ? 1 : 0;

            case 'channel_secret':
            case 'channel_access_token':
                return sanitize_text_field((string) $value);

            case 'welcome_message':
            case 'reply_signature':
                return sanitize_textarea_field((string) $value);

            case 'transfer_keywords':
                return sanitize_text_field((string) $value);

            case 'admin_notify_email':
                return sanitize_text_field((string) $value);

            case 'admin_line_ids':
                return sanitize_text_field((string) $value);

            case 'human_timeout_hours':
                return min(max(1, absint($value)), 720);

            case 'context_messages':
                return min(max(1, absint($value)), 50);

            case 'hourly_limit':
                return min(absint($value), 100000);

            case 'max_message_chars':
                return min(max(1, absint($value)), 20000);

            case 'retention_days':
                return min(absint($value), 3650);

            default:
                return is_scalar($value) ? sanitize_text_field((string) $value) : '';
        }
    }
}
