<?php
/**
 * UR AI Assistant Calculator Settings
 *
 * 都更分回試算「參數設定層」。
 *
 * 設計原則（對應規格「浮動數據後台可調」）：
 * - 使用獨立 option（ur_ai_calculator_settings），不汙染既有 AI 助理設定。
 * - 台北市／新北市各自一組參數（容積率分區表、獎勵、實設係數、分回比例、換坪倍數）。
 * - 其他獎勵以選項清單呈現（無／防災／海砂屋／容積移轉），預設皆為 0。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Calculator_Settings
 */
class UR_AI_Calculator_Settings {

    /**
     * Option name。
     *
     * @var string
     */
    const OPTION_NAME = 'ur_ai_calculator_settings';

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

        // 第一層合併。
        $settings = array_merge(self::defaults(), $settings);

        // cities 為巢狀結構，需逐市補齊，避免後台只存了一市就遺失另一市預設。
        if (!isset($settings['cities']) || !is_array($settings['cities'])) {
            $settings['cities'] = array();
        }

        $default_cities = self::default_cities();

        foreach ($default_cities as $key => $defaults) {
            $existing = isset($settings['cities'][$key]) && is_array($settings['cities'][$key])
                ? $settings['cities'][$key]
                : array();

            $settings['cities'][$key] = array_merge($defaults, $existing);
        }

        return $settings;
    }

    /**
     * 取得單一頂層設定。
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
     * 取得指定縣市的參數組。
     *
     * @param string $city taipei / new_taipei。
     * @return array
     */
    public static function get_city($city) {
        $city     = self::sanitize_city_key($city);
        $settings = self::get_all();

        if (isset($settings['cities'][$city])) {
            return $settings['cities'][$city];
        }

        $defaults = self::default_cities();

        return isset($defaults[$city]) ? $defaults[$city] : reset($defaults);
    }

    /**
     * 取得縣市清單（key => label）。
     *
     * @return array
     */
    public static function get_city_choices() {
        $settings = self::get_all();
        $choices  = array();

        foreach ($settings['cities'] as $key => $city) {
            $choices[$key] = isset($city['label']) ? (string) $city['label'] : $key;
        }

        return $choices;
    }

    /**
     * 取得某縣市的「分區 => 容積率(小數)」對照。
     *
     * @param string $city 縣市 key。
     * @return array
     */
    public static function get_zones($city) {
        $city_data = self::get_city($city);

        if (!isset($city_data['zones']) || !is_array($city_data['zones'])) {
            return array();
        }

        return $city_data['zones'];
    }

    /**
     * 取得其他獎勵下拉選項。
     *
     * @return array 每項 array{ key, label, value(float), custom(bool) }
     */
    public static function get_other_bonus_options() {
        $settings = self::get_all();

        if (!isset($settings['other_bonus_options']) || !is_array($settings['other_bonus_options'])) {
            return self::default_other_bonus_options();
        }

        return $settings['other_bonus_options'];
    }

    /**
     * 取得 CF7 表單 ID。
     *
     * @return int
     */
    public static function get_cf7_form_id() {
        return absint(self::get('cf7_form_id', 0));
    }

    /**
     * 取得 CF7 欄位名稱對應（name/tel/email/message/consent → 表單實際欄位名）。
     *
     * 後台可調，避免站方重建 CF7 表單改了欄位名時，名單擷取悄悄失效。
     *
     * @return array{ name: string, tel: string, email: string, message: string, consent: string }
     */
    public static function get_cf7_field_map() {
        $map = self::get('cf7_field_map', array());

        return array_merge(self::default_cf7_field_map(), is_array($map) ? $map : array());
    }

    /**
     * CF7 欄位名稱對應預設值（沿用試算器上線時使用的表單欄位名）。
     *
     * @return array
     */
    public static function default_cf7_field_map() {
        return array(
            'name'    => 'your-name',
            'tel'     => 'tel',
            'email'   => 'your-email',
            'message' => 'your-message',
            'consent' => 'consent',
        );
    }

    /**
     * 計算機是否啟用。
     *
     * @return bool
     */
    public static function is_enabled() {
        return (int) self::get('enabled', 1) === 1;
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
            // 是否為目前產業別的核心工具，見 UR_AI_Industry_Profiles
            // （分回試算目前僅都更危老產業別預設開啟）。
            'enabled'             => self::is_core_tool_for_active_industry() ? 1 : 0,
            'cf7_form_id'         => 1157393,
            'cf7_field_map'       => self::default_cf7_field_map(),
            'cities'              => self::default_cities(),
            'other_bonus_options' => self::default_other_bonus_options(),
            'disclaimer'          => self::default_disclaimer(),
            'public_ratio_notice' => self::default_public_ratio_notice(),
            'lead_hook_title'     => '想實際推進，需要專業協助？',
            'lead_hook_subtitle'  => '線上試算只能給你一個概念。你這塊基地實際能談到的條件，要納入成本、房價與整合狀況的專業判斷，無法用工具一鍵算出。若你正考慮認真推進、需要都更顧問協助，留下聯絡方式，我們會與你聯繫、先了解你的狀況。',
            // 進階評估：三案擇優係數（皆後台可調，對應都更容積獎勵法規）。
            'adv_a_multiplier'      => 1.5,  // A 案：法定容積 × 1.5
            'adv_b_legal_ratio'     => 0.3,  // B 案：原容積 + 法定容積 × 0.3
            'adv_c_multiplier'      => 1.2,  // C 案：原容積 × 1.2（一般）
            'adv_c_multiplier_special' => 1.3, // C 案：原容積 × 1.3（危險建築／海砂屋）
            'adv_cap_multiplier'    => 2.0,  // 更新後容積上限＝基準容積 × 2
            // 樓層／高度概估（附屬於進階評估）。
            'massing_floor_height'   => 3.2, // 單層樓高預設值（米）。
            'massing_coverage_hint'  => '不確定可向地政或都發局查詢；住三常見約 45%，各分區規定不同。',
        );
    }

    /**
     * 取得進階評估係數（合併預設）。
     *
     * @return array
     */
    public static function get_advanced_params() {
        return array(
            'a_multiplier'         => (float) self::get('adv_a_multiplier', 1.5),
            'b_legal_ratio'        => (float) self::get('adv_b_legal_ratio', 0.3),
            'c_multiplier'         => (float) self::get('adv_c_multiplier', 1.2),
            'c_multiplier_special' => (float) self::get('adv_c_multiplier_special', 1.3),
            'cap_multiplier'       => (float) self::get('adv_cap_multiplier', 2.0),
        );
    }

    /**
     * 取得樓層／高度概估參數（合併預設）。
     *
     * @return array{ floor_height: float, coverage_hint: string }
     */
    public static function get_massing_params() {
        return array(
            'floor_height'  => (float) self::get('massing_floor_height', 3.2),
            'coverage_hint' => (string) self::get('massing_coverage_hint', ''),
        );
    }

    /**
     * 判斷分回試算是否為目前啟用中產業別的核心工具（決定預設啟用狀態）。
     *
     * @return bool
     */
    private static function is_core_tool_for_active_industry() {
        if (!class_exists('UR_AI_Industry_Profiles')) {
            return true;
        }

        $profile = UR_AI_Industry_Profiles::get_active();

        return !isset($profile['modules']['calculator']) || !empty($profile['modules']['calculator']);
    }

    /**
     * 台北／新北預設參數組。
     *
     * @return array
     */
    public static function default_cities() {
        return array(
            'taipei' => array(
                'label'                => '台北市',
                'general_bonus'        => 0.50, // 一般都更獎勵，預設 50%（上限 50%）。
                'general_bonus_max'    => 0.50,
                'build_factor'         => 1.5,  // 實設係數，後台可調。
                'owner_ratio_low'      => 0.50,
                'owner_ratio_high'     => 0.55,
                'swap_multiplier_low'  => 0.9,
                'swap_multiplier_high' => 1.1,
                // 分區 => 容積率（小數）。台北全市統一，種子可信。
                'zones'                => array(
                    '住一'     => 0.60,
                    '住二'     => 1.20,
                    '住二之一' => 1.60,
                    '住二之二' => 2.25,
                    '住三'     => 2.25,
                    '住三之一' => 3.00,
                    '住三之二' => 4.00,
                    '住四'     => 3.00,
                    '住四之一' => 4.00,
                    '商一'     => 3.60,
                    '商二'     => 6.30,
                    '商三'     => 5.60,
                    '商四'     => 8.00,
                ),
            ),
            'new_taipei' => array(
                'label'                => '新北市',
                'general_bonus'        => 0.50,
                'general_bonus_max'    => 0.50,
                'build_factor'         => 1.5,
                'owner_ratio_low'      => 0.50,
                'owner_ratio_high'     => 0.55,
                'swap_multiplier_low'  => 0.9,
                'swap_multiplier_high' => 1.1,
                // 新北因各計畫區差異極大，種子僅常見值，務必後台逐計畫區校正。
                'zones'                => array(
                    '住三'     => 3.00,
                    '住四'     => 3.00,
                    '商業區'   => 4.40,
                ),
            ),
        );
    }

    /**
     * 其他獎勵下拉預設選項。
     *
     * @return array
     */
    public static function default_other_bonus_options() {
        return array(
            array('key' => 'none',     'label' => '無',                'value' => 0.00, 'custom' => false),
            array('key' => 'disaster', 'label' => '防災都更（+30%）',  'value' => 0.30, 'custom' => false),
            array('key' => 'seasand',  'label' => '海砂屋（+30%）',    'value' => 0.30, 'custom' => false),
            array('key' => 'transfer', 'label' => '容積移轉（自填）',  'value' => 0.00, 'custom' => true),
        );
    }

    /**
     * 依 key 取得其他獎勵選項的數值（小數）。custom 類型回傳 0，由前台自填值另行處理。
     *
     * @param string $key 選項 key。
     * @return array{ value: float, custom: bool }
     */
    public static function resolve_other_bonus($key) {
        $key = sanitize_key((string) $key);

        foreach (self::get_other_bonus_options() as $opt) {
            if (isset($opt['key']) && $opt['key'] === $key) {
                return array(
                    'value'  => (float) ($opt['value'] ?? 0),
                    'custom' => !empty($opt['custom']),
                );
            }
        }

        return array('value' => 0.0, 'custom' => false);
    }

    /**
     * 預設免責聲明。
     *
     * @return string
     */
    public static function default_disclaimer() {
        return '本試算為初步估算，非正式權利變換評估。實際分回坪數受基地條件、使用分區、屋齡、樓層、共同負擔（營建與費用）、地段房價及都更審議結果影響，需以正式估價與審議為準。';
    }

    /**
     * 預設公設比警語（規格共6：明講室內實坪會變小）。
     *
     * @return string
     */
    public static function default_public_ratio_notice() {
        return '提醒：分回坪數為「含公設權狀坪」。老公寓公設比通常低於 10%，新大樓約 30~35%，因此即使坪數相近，重建後室內實際使用坪數仍可能變小。實際室內坪數請以建築設計為準。';
    }

    /**
     * 清理整包設定。
     *
     * @param array $values 原始輸入。
     * @return array
     */
    private static function sanitize($values) {
        $clean = self::get_all(); // 以現值為基礎，逐項覆蓋。

        if (isset($values['enabled'])) {
            $clean['enabled'] = !empty($values['enabled']) ? 1 : 0;
        }

        if (isset($values['cf7_form_id'])) {
            $clean['cf7_form_id'] = absint($values['cf7_form_id']);
        }

        if (isset($values['cf7_field_map']) && is_array($values['cf7_field_map'])) {
            $clean_map = self::default_cf7_field_map();

            foreach (array_keys($clean_map) as $map_key) {
                if (isset($values['cf7_field_map'][$map_key])) {
                    $field_name = sanitize_key((string) $values['cf7_field_map'][$map_key]);

                    if ('' !== $field_name) {
                        $clean_map[$map_key] = $field_name;
                    }
                }
            }

            $clean['cf7_field_map'] = $clean_map;
        }

        foreach (array('disclaimer', 'public_ratio_notice', 'lead_hook_title', 'lead_hook_subtitle', 'massing_coverage_hint') as $text_key) {
            if (isset($values[$text_key])) {
                $clean[$text_key] = sanitize_textarea_field((string) $values[$text_key]);
            }
        }

        // 單層樓高預設值（米，合理範圍 2.4 ~ 5.0，避免誤填離譜數字）。
        if (isset($values['massing_floor_height']) && is_numeric($values['massing_floor_height'])) {
            $v = (float) $values['massing_floor_height'];
            $v = max(2.4, min($v, 5.0));
            $clean['massing_floor_height'] = $v;
        }

        // 進階評估係數（正數，合理上限夾住避免誤填）。
        $adv_limits = array(
            'adv_a_multiplier'         => 3.0,
            'adv_b_legal_ratio'        => 2.0,
            'adv_c_multiplier'         => 3.0,
            'adv_c_multiplier_special' => 3.0,
            'adv_cap_multiplier'       => 5.0,
        );
        foreach ($adv_limits as $adv_key => $adv_max) {
            if (isset($values[$adv_key]) && is_numeric($values[$adv_key])) {
                $v = (float) $values[$adv_key];
                if ($v < 0) {
                    $v = 0.0;
                }
                $clean[$adv_key] = min($v, $adv_max);
            }
        }

        // 縣市參數組。
        if (isset($values['cities']) && is_array($values['cities'])) {
            foreach ($values['cities'] as $city_key => $city_values) {
                $city_key = self::sanitize_city_key($city_key);

                if (!isset($clean['cities'][$city_key])) {
                    continue;
                }

                $clean['cities'][$city_key] = self::sanitize_city(
                    $clean['cities'][$city_key],
                    is_array($city_values) ? $city_values : array()
                );
            }
        }

        return $clean;
    }

    /**
     * 清理單一縣市參數組。
     *
     * @param array $current 現有值。
     * @param array $input   新輸入。
     * @return array
     */
    private static function sanitize_city($current, $input) {
        $float_fields = array(
            'general_bonus',
            'build_factor',
            'owner_ratio_low',
            'owner_ratio_high',
            'swap_multiplier_low',
            'swap_multiplier_high',
        );

        foreach ($float_fields as $field) {
            if (isset($input[$field]) && is_numeric($input[$field])) {
                $val = (float) $input[$field];

                if ($val < 0) {
                    $val = 0.0;
                }

                // 比例類欄位上限保守夾在 1.0；倍數類夾在 5.0；實設係數夾在 3.0。
                if (in_array($field, array('general_bonus', 'owner_ratio_low', 'owner_ratio_high'), true)) {
                    $val = min($val, 1.0);
                } elseif ('build_factor' === $field) {
                    $val = min($val, 3.0);
                } else {
                    $val = min($val, 5.0);
                }

                $current[$field] = $val;
            }
        }

        // 分區表：接受「分區=容積率%」或巢狀陣列。
        if (isset($input['zones'])) {
            $current['zones'] = self::sanitize_zones($input['zones']);
        }

        return $current;
    }

    /**
     * 清理分區表。
     *
     * 支援兩種輸入：
     * 1. 多行文字，每行「分區名稱=容積率%」（例：住三=225）。
     * 2. 陣列 name => percent。
     *
     * 內部一律以「小數」儲存（225% => 2.25）。
     *
     * @param mixed $zones 輸入。
     * @return array
     */
    private static function sanitize_zones($zones) {
        $result = array();

        if (is_string($zones)) {
            $lines = preg_split("/\r\n|\r|\n/", $zones);

            foreach ($lines as $line) {
                $line = trim($line);

                if ('' === $line || false === strpos($line, '=')) {
                    continue;
                }

                list($name, $percent) = array_map('trim', explode('=', $line, 2));

                $name = sanitize_text_field($name);

                if ('' === $name || !is_numeric($percent)) {
                    continue;
                }

                $result[$name] = (float) $percent / 100.0;
            }

            return $result;
        }

        if (is_array($zones)) {
            foreach ($zones as $name => $percent) {
                $name = sanitize_text_field((string) $name);

                if ('' === $name || !is_numeric($percent)) {
                    continue;
                }

                // 若值 > 5 視為百分比輸入（225），否則視為小數（2.25）。
                $val = (float) $percent;
                $result[$name] = $val > 5 ? $val / 100.0 : $val;
            }
        }

        return $result;
    }

    /**
     * 清理縣市 key。
     *
     * @param string $city 縣市。
     * @return string
     */
    private static function sanitize_city_key($city) {
        $city = sanitize_key((string) $city);

        $allowed = array('taipei', 'new_taipei');

        return in_array($city, $allowed, true) ? $city : 'taipei';
    }
}
