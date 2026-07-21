<?php
/**
 * UR AI Assistant Joint Burden Estimator Settings
 *
 * 都市更新共同負擔估算設定層。
 *
 * 設計原則：
 * - 費率、級距、單價表皆為新北市公告之提列基準，不開放後台調整（避免
 *   管理者誤改成與公告不符的數字）；後台唯一能調整的是「是否啟用」與
 *   「免責聲明文字」。
 * - 使用獨立 option（ur_ai_joint_burden_settings），不汙染既有設定。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Joint_Burden_Settings
 */
class UR_AI_Joint_Burden_Settings {

    /**
     * Option name。
     *
     * @var string
     */
    const OPTION_NAME = 'ur_ai_joint_burden_settings';

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

        return !isset($profile['modules']['joint_burden']) || !empty($profile['modules']['joint_burden']);
    }

    /**
     * 預設免責聲明。
     *
     * @return string
     */
    public static function default_disclaimer() {
        return '本估算僅供事前概算參考，實際金額會因個案條件而有差異，最終一律以「新北市都市更新及爭議處理審議會」審查委員審議通過之數額為準。本試算依「新北市」都市更新事業及權利變換計畫作業須知之提列總表與各分項說明之公開公式與費率計算，涵蓋工程費用A、權利變換費用C、貸款利息D、稅捐E（印花稅／營業稅）與管理費用F，並可計算共同負擔比率，非主管機關核定金額。臺北市及其他縣市另有各自的提列基準，公式與費率不同，不適用本結果。營建單價表物價基準日為民國112年4月，實際報核時應依當月營造工程物價指數調整；營業稅依財政部109年令釋公式概算。個案認定項目（建築設計費、公共設施、拆遷補償、信託費及容積獎勵維護費B、都市計畫變更負擔G、容積移轉費H等）與各項費率之實際認列，均須經審議會審定。';
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
