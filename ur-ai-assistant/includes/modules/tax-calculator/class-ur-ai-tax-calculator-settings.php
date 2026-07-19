<?php
/**
 * UR AI Assistant Tax Calculator Settings
 *
 * 稅賦試算（土地增值稅／契稅）設定層。
 *
 * 設計原則：
 * - 稅率、級距、都更減免規則皆為法定數字，不開放後台調整（避免管理者
 *   誤改成與法規不符的數字）；後台唯一能調整的是「是否啟用」與
 *   「免責聲明文字」。
 * - 使用獨立 option（ur_ai_tax_calculator_settings），不汙染既有設定。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Tax_Calculator_Settings
 */
class UR_AI_Tax_Calculator_Settings {

    /**
     * Option name。
     *
     * @var string
     */
    const OPTION_NAME = 'ur_ai_tax_calculator_settings';

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
     * @param mixed  $default 預設。
     * @return mixed
     */
    public static function get($key, $default = null) {
        $settings = self::get_all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * 是否啟用。
     *
     * @return bool
     */
    public static function is_enabled() {
        return (int) self::get('enabled', 1) === 1;
    }

    /**
     * 更新設定。
     *
     * @param array $values 原始輸入。
     * @return bool
     */
    public static function update($values) {
        if (!is_array($values)) {
            return false;
        }

        $clean = self::sanitize($values);

        return update_option(self::OPTION_NAME, $clean);
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
     * 全部預設。
     *
     * @return array
     */
    public static function defaults() {
        return array(
            // 依目前啟用中的產業別決定預設啟用狀態，見 UR_AI_Industry_Profiles。
            'enabled'    => self::is_core_tool_for_active_industry() ? 1 : 0,
            'disclaimer' => self::default_disclaimer(),
        );
    }

    /**
     * 判斷是否為目前啟用中產業別的核心工具（決定預設啟用狀態）。
     *
     * @return bool
     */
    private static function is_core_tool_for_active_industry() {
        if (!class_exists('UR_AI_Industry_Profiles')) {
            return true;
        }

        $profile = UR_AI_Industry_Profiles::get_active();

        return !isset($profile['modules']['tax_calculator']) || !empty($profile['modules']['tax_calculator']);
    }

    /**
     * 預設免責聲明。
     *
     * @return string
     */
    public static function default_disclaimer() {
        return '本試算依土地稅法第33條、契稅條例第3條及都市更新條例第67條相關規定之公開公式概算，僅供參考，非正式稅捐核定金額，實際應納稅額仍以稅捐稽徵機關核定之發單資料為準。都市更新相關減免部分條款訂有實施期限，實際是否適用請洽詢主管機關確認最新公告。';
    }

    /**
     * 清理設定。
     *
     * @param array $values 原始輸入。
     * @return array
     */
    private static function sanitize($values) {
        $clean = self::get_all();

        if (isset($values['enabled'])) {
            $clean['enabled'] = !empty($values['enabled']) ? 1 : 0;
        }

        if (isset($values['disclaimer'])) {
            $clean['disclaimer'] = sanitize_textarea_field((string) $values['disclaimer']);
        }

        return $clean;
    }
}
