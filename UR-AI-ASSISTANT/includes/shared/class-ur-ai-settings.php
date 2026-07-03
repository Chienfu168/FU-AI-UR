<?php
/**
 * UR AI Assistant Settings
 *
 * 外掛共用設定管理類別。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Settings
 */
class UR_AI_Settings {

    /**
     * Option name.
     *
     * @var string
     */
    const OPTION_NAME = 'ur_ai_assistant_settings';

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

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return $default;
    }

    /**
     * 更新單一設定。
     *
     * @param string $key 設定鍵。
     * @param mixed  $value 設定值。
     * @return bool
     */
    public static function update($key, $value) {
        $settings       = self::get_all();
        $settings[$key] = self::sanitize_value($key, $value);

        return update_option(self::OPTION_NAME, $settings);
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
     * 重設為預設值。
     *
     * @return bool
     */
    public static function reset() {
        return update_option(self::OPTION_NAME, self::defaults());
    }

    /**
     * 初始化預設設定。
     *
     * @return void
     */
    public static function maybe_install_defaults() {
        $existing = get_option(self::OPTION_NAME, null);

        if (null === $existing || false === $existing) {
            add_option(self::OPTION_NAME, self::defaults());
        }
    }

    /**
     * 取得 API Key。
     *
     * @return string
     */
    public static function get_api_key() {
        return trim((string) self::get('api_key', ''));
    }

    /**
     * 取得模型。
     *
     * @return string
     */
    public static function get_model() {
        $model = trim((string) self::get('model', 'gpt-4o-mini'));

        return '' !== $model ? $model : 'gpt-4o-mini';
    }

    /**
     * 取得 temperature.
     *
     * @return float
     */
    public static function get_temperature() {
        $temperature = (float) self::get('temperature', 0.3);

        if ($temperature < 0) {
            $temperature = 0;
        }

        if ($temperature > 2) {
            $temperature = 2;
        }

        return $temperature;
    }

    /**
     * 取得最大回答 token。
     *
     * @return int
     */
    public static function get_max_answer_tokens() {
        $tokens = absint(self::get('max_answer_tokens', 1200));

        if ($tokens <= 0) {
            $tokens = 1200;
        }

        if ($tokens > 4000) {
            $tokens = 4000;
        }

        return $tokens;
    }

    /**
     * 取得 system prompt.
     *
     * @return string
     */
    public static function get_system_prompt() {
        $prompt = (string) self::get('system_prompt', '');

        if ('' === trim($prompt)) {
            return self::default_system_prompt();
        }

        return $prompt;
    }

    /**
     * 取得最大問題字數。
     *
     * @return int
     */
    public static function get_max_question_length() {
        $length = absint(self::get('max_question_length', 500));

        if ($length <= 0) {
            $length = 500;
        }

        if ($length > 2000) {
            $length = 2000;
        }

        return $length;
    }

    /**
     * 取得每日限制。
     *
     * @param string $role guest/member/admin.
     * @return int
     */
    public static function get_daily_limit($role = 'guest') {
        $role = sanitize_key($role);

        $map = array(
            'guest'  => 'guest_daily_limit',
            'member' => 'member_daily_limit',
            'admin'  => 'admin_daily_limit',
        );

        $key = isset($map[$role]) ? $map[$role] : 'guest_daily_limit';

        return absint(self::get($key, 0));
    }

    /**
     * 前台是否啟用。
     *
     * @return bool
     */
    public static function is_frontend_enabled() {
        return self::truthy(self::get('frontend_enabled', 1));
    }

    /**
     * FAQ 是否啟用。
     *
     * @return bool
     */
    public static function is_faq_enabled() {
        return self::truthy(self::get('faq_enabled', 1));
    }

    /**
     * 相關頁面推薦是否啟用。
     *
     * @return bool
     */
    public static function is_related_enabled() {
        return self::truthy(self::get('related_enabled', 1));
    }

    /**
     * 熱門問題是否啟用。
     *
     * @return bool
     */
    public static function is_popular_enabled() {
        return self::truthy(self::get('popular_enabled', 1));
    }

    /**
     * 問答紀錄是否啟用。
     *
     * @return bool
     */
    public static function is_logging_enabled() {
        return self::truthy(self::get('logging_enabled', 1));
    }

    /**
     * 預設設定。
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'api_key'             => '',
            'model'               => 'gpt-4o-mini',
            'temperature'         => 0.3,
            'max_answer_tokens'   => 1200,
            'system_prompt'       => self::default_system_prompt(),

            'frontend_enabled'    => 1,
            'frontend_title'      => '都更危老 AI 助理',
            'frontend_subtitle'   => '用白話方式，快速了解都市更新、危老重建、更新會、自主更新、權利變換與協議合建等基礎問題。',
            'disclaimer'          => '本工具提供一般資訊參考，不構成法律、估價、建築、稅務或個案決策建議。若涉及個案權利、契約、訴訟、登記或稅務問題，建議洽詢相關專業人士。',

            'max_question_length' => 500,
            'guest_daily_limit'   => 20,
            'member_daily_limit'  => 50,
            'admin_daily_limit'   => 0,

            'faq_enabled'         => 1,
            'related_enabled'     => 1,
            'popular_enabled'     => 1,
            'logging_enabled'     => 1,
        );
    }

    /**
     * 預設 system prompt.
     *
     * @return string
     */
    public static function default_system_prompt() {
        return implode(
            "\n",
            array(
                '你是「都更危老 AI 助理」，專門協助台灣民眾理解台灣都市更新、危老重建、更新會、自主更新、權利變換、協議合建、都市更新程序與相關基礎知識。',
                '請使用繁體中文回答，語氣應客觀、中立、清楚、白話，適合一般民眾閱讀。',
                '回答應以一般性說明、概念整理、流程介紹與風險提醒為主。',
                '不可假裝已審閱使用者的個案文件、契約、權利變換計畫、估價報告、建築圖說、土地建物謄本或會議紀錄。',
                '不可直接替使用者作成法律、估價、建築、稅務、登記、權利分配、訴訟勝敗或個案是否合理之判斷。',
                '若問題涉及個案權益、契約內容、財產分配、訴訟或專業判斷，請提醒使用者應洽詢律師、建築師、估價師、地政士或都市更新專業人士。',
                '若問題明顯超出都市更新、危老重建與不動產重建基礎知識範圍，請禮貌說明本工具主要回答都更危老相關問題。',
            )
        );
    }

    /**
     * 清理設定值。
     *
     * @param string $key 設定鍵。
     * @param mixed  $value 設定值。
     * @return mixed
     */
    private static function sanitize_value($key, $value) {
        $key = sanitize_key($key);

        switch ($key) {
            case 'api_key':
                return sanitize_text_field((string) $value);

            case 'model':
                return sanitize_text_field((string) $value);

            case 'temperature':
                $temperature = (float) $value;

                if ($temperature < 0) {
                    $temperature = 0;
                }

                if ($temperature > 2) {
                    $temperature = 2;
                }

                return $temperature;

            case 'max_answer_tokens':
                $tokens = absint($value);

                if ($tokens < 100) {
                    $tokens = 100;
                }

                if ($tokens > 4000) {
                    $tokens = 4000;
                }

                return $tokens;

            case 'system_prompt':
            case 'frontend_subtitle':
            case 'disclaimer':
                return sanitize_textarea_field((string) $value);

            case 'frontend_title':
                return sanitize_text_field((string) $value);

            case 'frontend_enabled':
            case 'faq_enabled':
            case 'related_enabled':
            case 'popular_enabled':
            case 'logging_enabled':
                return !empty($value) ? 1 : 0;

            case 'max_question_length':
                $length = absint($value);

                if ($length < 20) {
                    $length = 20;
                }

                if ($length > 2000) {
                    $length = 2000;
                }

                return $length;

            case 'guest_daily_limit':
            case 'member_daily_limit':
            case 'admin_daily_limit':
                $limit = absint($value);

                if ($limit > 9999) {
                    $limit = 9999;
                }

                return $limit;

            default:
                if (is_scalar($value)) {
                    return sanitize_text_field((string) $value);
                }

                return '';
        }
    }

    /**
     * 判斷布林設定。
     *
     * @param mixed $value 原始值。
     * @return bool
     */
    private static function truthy($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, array('1', 'true', 'yes', 'on'), true);
    }
}