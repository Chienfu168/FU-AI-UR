<?php
/**
 * UR AI Assistant Market Price Settings
 *
 * 「行情參考」模組設定層。
 *
 * 設計原則：
 * - 使用獨立 option（ur_ai_market_price_settings），比照計算機模組，不汙染主設定。
 * - 屋齡門檻、最低樣本數皆後台可調，避免寫死在程式碼。
 * - 免責聲明獨立可編輯，法遵風險相對高，文字需要能隨時調整。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Settings
 */
class UR_AI_Market_Price_Settings {

    /**
     * Option name。
     *
     * @var string
     */
    const OPTION_NAME = 'ur_ai_market_price_settings';

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
     * 「老屋」屋齡門檻（年，以上視為老屋）。
     *
     * @return int
     */
    public static function get_old_age_threshold() {
        return absint(self::get('old_age_threshold', 30));
    }

    /**
     * 「新成屋」屋齡門檻（年，以下視為新成屋）。
     *
     * @return int
     */
    public static function get_new_age_threshold() {
        return absint(self::get('new_age_threshold', 5));
    }

    /**
     * 統計呈現的最低樣本數門檻。
     *
     * 低於此門檻不顯示統計數字，避免以過少樣本誤導使用者。
     *
     * @return int
     */
    public static function get_min_sample_size() {
        $min = absint(self::get('min_sample_size', 5));

        return $min > 0 ? $min : 5;
    }

    /**
     * 免責聲明。
     *
     * @return string
     */
    public static function get_disclaimer() {
        $disclaimer = (string) self::get('disclaimer', '');

        return '' !== trim($disclaimer) ? $disclaimer : self::default_disclaimer();
    }

    /**
     * 全部預設。
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'enabled'            => 0,
            'old_age_threshold'  => 30,
            'new_age_threshold'  => 5,
            'min_sample_size'    => 5,
            'disclaimer'         => self::default_disclaimer(),
        );
    }

    /**
     * 預設免責聲明。
     *
     * @return string
     */
    public static function default_disclaimer() {
        return '以上為內政部不動產交易實價查詢服務之歷史成交紀錄統計參考，已排除親友、員工、共有人等特殊關係交易，僅反映歷史登記資訊，不代表現在市場價格，亦不構成正式估價。實際案例請洽不動產估價師或地政士。';
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

        if (isset($values['old_age_threshold']) && is_numeric($values['old_age_threshold'])) {
            $clean['old_age_threshold'] = min(100, max(10, absint($values['old_age_threshold'])));
        }

        if (isset($values['new_age_threshold']) && is_numeric($values['new_age_threshold'])) {
            $clean['new_age_threshold'] = min(20, max(1, absint($values['new_age_threshold'])));
        }

        // 老屋門檻必須大於新成屋門檻，避免兩組屋齡區間重疊、同一筆交易被同時
        // 計入「老屋現況」與「新成屋」兩種矛盾的統計。
        if ($clean['new_age_threshold'] >= $clean['old_age_threshold']) {
            $clean['new_age_threshold'] = max(1, $clean['old_age_threshold'] - 1);
        }

        if (isset($values['min_sample_size']) && is_numeric($values['min_sample_size'])) {
            $clean['min_sample_size'] = min(50, max(1, absint($values['min_sample_size'])));
        }

        if (isset($values['disclaimer'])) {
            $clean['disclaimer'] = sanitize_textarea_field((string) $values['disclaimer']);
        }

        return $clean;
    }
}
