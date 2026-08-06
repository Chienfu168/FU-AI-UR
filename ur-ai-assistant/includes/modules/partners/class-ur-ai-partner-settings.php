<?php
/**
 * UR AI Assistant Partners Settings
 *
 * 相關產業名錄設定層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Partner_Settings
 */
class UR_AI_Partner_Settings {

    const OPTION_NAME = 'ur_ai_partners_settings';

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
     * @param string $key     鍵。
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
     * 全部預設。
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'enabled'    => self::is_core_tool_for_active_industry() ? 1 : 0,
            'title'      => __('都市更新專業服務與相關組織名錄', 'ur-ai-assistant'),
            'intro'      => __('推動都市更新涉及整合規劃、建築設計、不動產估價、產權登記、法律、金融、營造及實施管理等不同專業領域。以下名錄僅供資訊查詢與自行洽詢參考。', 'ur-ai-assistant'),
            'disclaimer' => self::default_disclaimer(),
        );
    }

    /**
     * 判斷是否為目前產業別的核心工具（決定預設啟用狀態）。
     *
     * @return bool
     */
    private static function is_core_tool_for_active_industry() {
        if (!class_exists('UR_AI_Industry_Profiles')) {
            return true;
        }

        $profile = UR_AI_Industry_Profiles::get_active();

        return !isset($profile['modules']['partners']) || !empty($profile['modules']['partners']);
    }

    /**
     * 預設免責聲明。
     *
     * @return string
     */
    public static function default_disclaimer() {
        return '本名錄僅供資訊查詢與自行洽詢參考，資料以政府公開名冊、專業公會或單位官方網站整理為主，並標示資料來源與最後核對日期。名錄之列出不代表本平台之推薦、認證或背書，實際服務內容、資格與收費請自行向各單位查證確認。若發現資料有誤，歡迎與我們聯繫更正。';
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
        if (isset($values['title'])) {
            $clean['title'] = sanitize_text_field((string) $values['title']);
        }
        if (isset($values['intro'])) {
            $clean['intro'] = sanitize_textarea_field((string) $values['intro']);
        }
        if (isset($values['disclaimer'])) {
            $clean['disclaimer'] = sanitize_textarea_field((string) $values['disclaimer']);
        }

        return $clean;
    }
}
