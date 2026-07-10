<?php
/**
 * UR AI Assistant Quiz Settings
 *
 * 「知識大考驗」模組設定層。
 *
 * 設計原則：
 * - 使用獨立 option（ur_ai_quiz_settings），比照行情參考模組，不汙染主設定。
 * - 題數、節流次數皆後台可調，避免寫死在程式碼。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Quiz_Settings
 */
class UR_AI_Quiz_Settings {

    /**
     * Option name。
     *
     * @var string
     */
    const OPTION_NAME = 'ur_ai_quiz_settings';

    /**
     * 取得全部設定（合併預設）。
     *
     * @return array
     */
    public static function get_all() {
        $settings = get_option(self::OPTION_NAME, array());

        if (!is_array($settings)) {
            $settings = array();
        }

        return array_merge(self::defaults(), $settings);
    }

    /**
     * 取得單一設定。
     *
     * @param string $key 鍵。
     * @param mixed  $default 預設值。
     * @return mixed
     */
    public static function get($key, $default = null) {
        $settings = self::get_all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * 更新設定（批次，會清理）。
     *
     * @param array $values 原始輸入。
     * @return bool
     */
    public static function update($values) {
        if (!is_array($values)) {
            return false;
        }

        return update_option(self::OPTION_NAME, self::sanitize($values));
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
     * 模組是否啟用。
     *
     * 預設關閉：避免既有網站升級後未經確認就多出新的前台區塊。
     *
     * @return bool
     */
    public static function is_enabled() {
        return (int) self::get('enabled', 0) === 1;
    }

    /**
     * 每次挑戰抽題數量。
     *
     * @return int
     */
    public static function get_question_count() {
        $count = absint(self::get('question_count', 10));

        return $count > 0 ? $count : 10;
    }

    /**
     * 同一 IP／裝置每小時可作答次數上限（節流）。
     *
     * @return int
     */
    public static function get_rate_limit_per_hour() {
        $limit = absint(self::get('rate_limit_per_hour', 3));

        return $limit > 0 ? $limit : 3;
    }

    /**
     * 標題文字。
     *
     * @return string
     */
    public static function get_title() {
        $title = (string) self::get('title', '');

        return '' !== trim($title) ? $title : __('都更危老知識大考驗', 'ur-ai-assistant');
    }

    /**
     * 全部預設。
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'enabled'              => 0,
            'question_count'       => 10,
            'rate_limit_per_hour'  => 3,
            'title'                => __('都更危老知識大考驗', 'ur-ai-assistant'),
        );
    }

    /**
     * 清理設定值。
     *
     * @param array $values 原始輸入。
     * @return array
     */
    private static function sanitize($values) {
        $clean = self::get_all();

        if (isset($values['enabled'])) {
            $clean['enabled'] = !empty($values['enabled']) ? 1 : 0;
        }

        if (isset($values['question_count']) && is_numeric($values['question_count'])) {
            $clean['question_count'] = min(30, max(5, absint($values['question_count'])));
        }

        if (isset($values['rate_limit_per_hour']) && is_numeric($values['rate_limit_per_hour'])) {
            $clean['rate_limit_per_hour'] = min(20, max(1, absint($values['rate_limit_per_hour'])));
        }

        if (isset($values['title'])) {
            $clean['title'] = sanitize_text_field((string) $values['title']);
        }

        return $clean;
    }
}
