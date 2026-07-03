<?php
/**
 * UR AI Assistant Calculator AJAX
 *
 * 都更分回試算 AJAX 端點。
 *
 * 流程（方案甲）：
 * 1. 前台送出試算 → 本端點計算結果。
 * 2. 同時將「完整試算情境」存入 transient（以 token 為鍵，TTL 2 小時）。
 * 3. 回傳結果 + token 給前台；前台於 CF7 送出時夾帶 token，
 *    由 CF7 hook 還原情境寫入 leads 表。情境不進通知信。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Calculator_Ajax
 */
class UR_AI_Calculator_Ajax {

    /**
     * AJAX action 名稱（計算）。
     *
     * @var string
     */
    const ACTION_COMPUTE = 'ur_ai_calc_compute';

    /**
     * 情境 transient 前綴。
     *
     * @var string
     */
    const CTX_PREFIX = 'ur_ai_calc_ctx_';

    /**
     * 情境保存秒數（2 小時）。
     *
     * @var int
     */
    const CTX_TTL = 7200;

    /**
     * 每 IP 每小時計算次數上限（防濫用）。
     *
     * @var int
     */
    const RATE_LIMIT_PER_HOUR = 60;

    /**
     * 計算服務。
     *
     * @var UR_AI_Calculator_Service
     */
    private $service;

    /**
     * 建構。
     *
     * @param UR_AI_Calculator_Service $service 計算服務。
     */
    public function __construct(UR_AI_Calculator_Service $service) {
        $this->service = $service;
    }

    /**
     * 註冊 AJAX 掛鉤（登入與未登入皆可）。
     *
     * @return void
     */
    public function register() {
        add_action('wp_ajax_' . self::ACTION_COMPUTE, array($this, 'handle_compute'));
        add_action('wp_ajax_nopriv_' . self::ACTION_COMPUTE, array($this, 'handle_compute'));
    }

    /**
     * 處理計算請求。
     *
     * @return void
     */
    public function handle_compute() {
        UR_AI_Security::ajax_verify_public_nonce_or_die();

        if (!UR_AI_Calculator_Settings::is_enabled()) {
            wp_send_json_error(array('message' => __('試算功能目前未啟用。', 'ur-ai-assistant')), 403);
        }

        if (!$this->check_rate_limit()) {
            wp_send_json_error(
                array('message' => __('操作過於頻繁，請稍後再試。', 'ur-ai-assistant')),
                429
            );
        }

        $track = isset($_POST['track']) ? sanitize_key(wp_unslash($_POST['track'])) : 'single';
        // 縣市欄位已於前台移除；保留相容，未傳則為空（參數組以預設解析）。
        $city = isset($_POST['city']) ? sanitize_key(wp_unslash($_POST['city'])) : '';

        // 進階＝三案擇優；其餘＝單一容積率＋獎勵驅動。
        if ('advanced' === $track) {
            $payload = $this->compute_advanced($city);
        } else {
            $payload = $this->compute_site($city);
        }

        if (is_wp_error($payload)) {
            wp_send_json_error(array('message' => $payload->get_error_message()), 400);
        }

        // 存情境，回 token。
        $token = $this->store_context($payload['context']);

        wp_send_json_success(
            array(
                'result'  => $payload['result'],
                'summary' => $payload['summary'],
                'token'   => $token,
                'notice'  => UR_AI_Calculator_Settings::get('public_ratio_notice', ''),
            )
        );
    }

    /**
     * 軌道 A：換坪比法（地主）。
     *
     * @param string $city 縣市 key。
     * @return array|WP_Error
     */
    private function compute_swap($city) {
        $city_data = UR_AI_Calculator_Settings::get_city($city);

        $current_pings = isset($_POST['current_pings']) ? (float) wp_unslash($_POST['current_pings']) : 0.0;

        if ($current_pings <= 0) {
            return new WP_Error('invalid_input', __('請輸入有效的現有權狀坪數。', 'ur-ai-assistant'));
        }

        $zone = isset($_POST['zone']) ? UR_AI_Security::sanitize_text(wp_unslash($_POST['zone'])) : '';

        $result = $this->service->calculate_swap(
            $current_pings,
            (float) $city_data['swap_multiplier_low'],
            (float) $city_data['swap_multiplier_high']
        );

        $summary = sprintf(
            '現有 %s 坪 → 約 %s ~ %s 坪',
            $this->fmt($result['current_pings']),
            $this->fmt($result['result_low']),
            $this->fmt($result['result_high'])
        );

        $context = array(
            'track'   => 'swap',
            'city'    => $city,
            'zone'    => $zone,
            'inputs'  => array('current_pings' => $current_pings),
            'result'  => $result,
            'summary' => $summary,
        );

        return array('result' => $result, 'summary' => $summary, 'context' => $context);
    }

    /**
     * 解析「土地面積輸入方式」（雙軌，立即試算／進階試算共用）。
     *
     * pings 模式：site_area 直接視為個人持分坪數。
     * ratio 模式：以「基地總面積 + 持分分子/分母」推算個人持分，site_area 改視為基地總面積。
     *
     * @return array{ share_mode: string, site_area: float, own_share: float, site_total_area: float }|WP_Error
     */
    private function resolve_land_area_input() {
        $share_mode = isset($_POST['share_mode']) ? sanitize_key(wp_unslash($_POST['share_mode'])) : 'pings';
        if (!in_array($share_mode, array('pings', 'ratio'), true)) {
            $share_mode = 'pings';
        }

        $own_share       = 0.0;
        $site_area       = 0.0;
        $site_total_area = 0.0;

        if ('ratio' === $share_mode) {
            $site_total_area = isset($_POST['site_total_area']) ? (float) wp_unslash($_POST['site_total_area']) : 0.0;
            $numerator       = isset($_POST['share_numerator']) ? (float) wp_unslash($_POST['share_numerator']) : 0.0;
            $denominator     = isset($_POST['share_denominator']) ? (float) wp_unslash($_POST['share_denominator']) : 0.0;

            if ($site_total_area <= 0) {
                return new WP_Error('invalid_input', __('請輸入有效的基地總面積。', 'ur-ai-assistant'));
            }
            if ($numerator <= 0 || $denominator <= 0) {
                return new WP_Error('invalid_input', __('請輸入有效的土地持分分子／分母。', 'ur-ai-assistant'));
            }

            $share_ratio_input = $numerator / $denominator;
            if ($share_ratio_input > 1) {
                $share_ratio_input = 1.0;
            }

            $site_area = $site_total_area;
            $own_share = $site_total_area * $share_ratio_input;
        } else {
            $site_area = isset($_POST['site_area']) ? (float) wp_unslash($_POST['site_area']) : 0.0;
            if ($site_area <= 0) {
                return new WP_Error('invalid_input', __('請輸入有效的土地持分坪數。', 'ur-ai-assistant'));
            }

            // 相容舊版直接帶 own_share（個人持分坪數）的用法。
            $legacy_own_share = isset($_POST['own_share']) ? (float) wp_unslash($_POST['own_share']) : 0.0;
            if ($legacy_own_share > 0) {
                $own_share = $legacy_own_share;
            }
        }

        return array(
            'share_mode'      => $share_mode,
            'site_area'       => $site_area,
            'own_share'       => $own_share,
            'site_total_area' => $site_total_area,
        );
    }

    /**
     * 樓層／高度概估（僅 ratio 模式，且填了建蔽率時才計算）。
     *
     * @param string $share_mode      pings / ratio。
     * @param float  $site_total_area 基地總面積（坪）。
     * @param float  $sellable_floor  整棟可銷售樓地板（坪）。
     * @return array|null
     */
    private function resolve_massing($share_mode, $site_total_area, $sellable_floor) {
        if ('ratio' !== $share_mode) {
            return null;
        }

        $coverage_ratio = 0.0;
        if (isset($_POST['coverage_ratio']) && is_numeric($_POST['coverage_ratio'])) {
            $cr = (float) wp_unslash($_POST['coverage_ratio']);
            $coverage_ratio = $cr > 1 ? $cr / 100.0 : $cr;
        }

        if ($coverage_ratio <= 0) {
            return null;
        }

        $massing_defaults = UR_AI_Calculator_Settings::get_massing_params();

        $floor_height = $massing_defaults['floor_height'];
        if (isset($_POST['floor_height']) && is_numeric($_POST['floor_height'])) {
            $fh = (float) wp_unslash($_POST['floor_height']);
            if ($fh > 0) {
                $floor_height = $fh;
            }
        }

        return $this->service->estimate_massing(
            array(
                'site_area'      => $site_total_area,
                'coverage_ratio' => $coverage_ratio,
                'total_floor'    => $sellable_floor,
                'floor_height'   => $floor_height,
            )
        );
    }

    /**
     * 軌道 C：基地總量回推法（整合公司進階／立即試算共用）。
     *
     * @param string $city 縣市 key。
     * @return array|WP_Error
     */
    private function compute_site($city) {
        $city_data = UR_AI_Calculator_Settings::get_city($city);

        $land = $this->resolve_land_area_input();
        if (is_wp_error($land)) {
            return $land;
        }

        $share_mode      = $land['share_mode'];
        $site_area       = $land['site_area'];
        $own_share       = $land['own_share'];
        $site_total_area = $land['site_total_area'];

        // 容積率：優先採用手動輸入（使用者自行查閱填入），分區表僅作後備相容。
        $zone = isset($_POST['zone']) ? UR_AI_Security::sanitize_text(wp_unslash($_POST['zone'])) : '';
        $far  = 0.0;

        if (isset($_POST['far']) && is_numeric($_POST['far'])) {
            $far_in = (float) wp_unslash($_POST['far']);
            // 容許輸入 225（百分比）或 2.25（小數）。
            $far = $far_in > 5 ? $far_in / 100.0 : $far_in;
        }

        // 後備：若未填容積率，嘗試用分區查表（保留相容，不強制）。
        if ($far <= 0 && '' !== $zone) {
            $zones = UR_AI_Calculator_Settings::get_zones($city);
            if (isset($zones[$zone])) {
                $far = (float) $zones[$zone];
            }
        }

        if ($far <= 0) {
            return new WP_Error('invalid_input', __('請輸入法定容積率（%）。', 'ur-ai-assistant'));
        }

        // 一般獎勵：預設取縣市值，可被前台輸入覆蓋（夾在 0 ~ 上限）。
        $general_bonus = (float) $city_data['general_bonus'];
        if (isset($_POST['general_bonus']) && is_numeric($_POST['general_bonus'])) {
            $gb_in = (float) wp_unslash($_POST['general_bonus']);
            $gb_in = $gb_in > 1 ? $gb_in / 100.0 : $gb_in; // 50 → 0.5
            $max   = isset($city_data['general_bonus_max']) ? (float) $city_data['general_bonus_max'] : 0.5;
            $general_bonus = max(0.0, min($gb_in, $max));
        }

        // 其他獎勵 1、2：以選項 key 解析，custom 類型讀自填值。
        $other_1 = $this->resolve_other_bonus_input('other_bonus_1');
        $other_2 = $this->resolve_other_bonus_input('other_bonus_2');

        $build_factor = (float) $city_data['build_factor'];

        $result = $this->service->calculate_site_total(
            array(
                'site_area'        => $site_area,
                'far'              => $far,
                'general_bonus'    => $general_bonus,
                'other_bonus_1'    => $other_1['value'],
                'other_bonus_2'    => $other_2['value'],
                'build_factor'     => $build_factor,
                'owner_ratio_low'  => (float) $city_data['owner_ratio_low'],
                'owner_ratio_high' => (float) $city_data['owner_ratio_high'],
                'own_share'        => $own_share,
            )
        );

        $massing = $this->resolve_massing($share_mode, $site_total_area, $result['sellable_floor']);
        if (null !== $massing) {
            $result['massing'] = $massing;
        }

        if (!empty($result['has_individual'])) {
            $summary = sprintf(
                '土地 %s 坪｜持分 %s 坪 → 可分回約 %s ~ %s 坪',
                $this->fmt($result['site_area']),
                $this->fmt($result['own_share']),
                $this->fmt($result['individual_low']),
                $this->fmt($result['individual_high'])
            );
        } else {
            $summary = sprintf(
                '土地 %s 坪 → 可分回約 %s ~ %s 坪',
                $this->fmt($result['site_area']),
                $this->fmt($result['owner_total_low']),
                $this->fmt($result['owner_total_high'])
            );
        }

        $context = array(
            'track'  => 'site',
            'city'   => $city,
            'zone'   => $zone,
            'inputs' => array(
                'share_mode'      => $share_mode,
                'site_area'       => $site_area,
                'site_total_area' => $site_total_area,
                'own_share'       => $own_share,
                'far'             => $far,
                'general_bonus'   => $general_bonus,
                'other_bonus_1'   => $other_1,
                'other_bonus_2'   => $other_2,
                'build_factor'    => $build_factor,
            ),
            'result'  => $result,
            'summary' => $summary,
        );

        return array('result' => $result, 'summary' => $summary, 'context' => $context);
    }

    /**
     * 進階模式：三案擇優試算。
     *
     * @param string $city 縣市 key。
     * @return array|WP_Error
     */
    private function compute_advanced($city) {
        $city_data = UR_AI_Calculator_Settings::get_city($city);

        $land = $this->resolve_land_area_input();
        if (is_wp_error($land)) {
            return $land;
        }

        $share_mode      = $land['share_mode'];
        $site_area       = $land['site_area'];
        $own_share       = $land['own_share'];
        $site_total_area = $land['site_total_area'];

        // 法定容積率（手動輸入；容許 225 或 2.25）。
        $far_legal = 0.0;
        if (isset($_POST['far']) && is_numeric($_POST['far'])) {
            $f = (float) wp_unslash($_POST['far']);
            $far_legal = $f > 5 ? $f / 100.0 : $f;
        }
        if ($far_legal <= 0) {
            return new WP_Error('invalid_input', __('請輸入法定容積率（%）。', 'ur-ai-assistant'));
        }

        // 原建築容積率（手動輸入）。
        $far_origin = 0.0;
        if (isset($_POST['far_origin']) && is_numeric($_POST['far_origin'])) {
            $f = (float) wp_unslash($_POST['far_origin']);
            $far_origin = $f > 5 ? $f / 100.0 : $f;
        }
        if ($far_origin <= 0) {
            return new WP_Error('invalid_input', __('請輸入原建築容積率（%）。', 'ur-ai-assistant'));
        }

        $building_type = isset($_POST['building_type']) ? sanitize_key(wp_unslash($_POST['building_type'])) : 'normal';
        if (!in_array($building_type, array('normal', 'risk', 'seasand'), true)) {
            $building_type = 'normal';
        }

        $adv = UR_AI_Calculator_Settings::get_advanced_params();

        $service_args = array(
            'site_area'            => $site_area,
            'far_legal'            => $far_legal,
            'far_origin'           => $far_origin,
            'building_type'        => $building_type,
            'build_factor'         => (float) $city_data['build_factor'],
            'owner_ratio_low'      => (float) $city_data['owner_ratio_low'],
            'owner_ratio_high'     => (float) $city_data['owner_ratio_high'],
            'a_multiplier'         => $adv['a_multiplier'],
            'b_legal_ratio'        => $adv['b_legal_ratio'],
            'c_multiplier'         => $adv['c_multiplier'],
            'c_multiplier_special' => $adv['c_multiplier_special'],
            'cap_multiplier'       => $adv['cap_multiplier'],
        );

        $service_args['own_share'] = $own_share;

        $result = $this->service->calculate_best_incentive($service_args);

        $massing = $this->resolve_massing($share_mode, $site_total_area, $result['sellable_floor']);
        if (null !== $massing) {
            $result['massing'] = $massing;
        }

        $type_labels = array(
            'normal'  => __('一般建築', 'ur-ai-assistant'),
            'risk'    => __('危險建築', 'ur-ai-assistant'),
            'seasand' => __('海砂屋', 'ur-ai-assistant'),
        );

        if (!empty($result['has_individual'])) {
            $summary = sprintf(
                '進階擇優（採方案 %s）｜基地 %s 坪｜持分 %s 坪 → 可分回約 %s ~ %s 坪',
                $result['best_key'],
                $this->fmt($result['site_area']),
                $this->fmt($result['own_share']),
                $this->fmt($result['individual_low']),
                $this->fmt($result['individual_high'])
            );
        } else {
            $summary = sprintf(
                '進階擇優（採方案 %s）｜土地 %s 坪 → 可分回約 %s ~ %s 坪',
                $result['best_key'],
                $this->fmt($result['site_area']),
                $this->fmt($result['owner_total_low']),
                $this->fmt($result['owner_total_high'])
            );
        }

        $context = array(
            'track'  => 'advanced',
            'city'   => $city,
            'inputs' => array(
                'share_mode'      => $share_mode,
                'site_area'       => $site_area,
                'site_total_area' => $site_total_area,
                'own_share'       => $own_share,
                'far_legal'       => $far_legal,
                'far_origin'      => $far_origin,
                'building_type'   => $building_type,
                'building_label'  => isset($type_labels[$building_type]) ? $type_labels[$building_type] : $building_type,
            ),
            'result'  => $result,
            'summary' => $summary,
        );

        return array('result' => $result, 'summary' => $summary, 'context' => $context);
    }

    /**
     * 解析其他獎勵輸入（key + 自填值）。
     *
     * @param string $field 欄位名（other_bonus_1 / other_bonus_2）。
     * @return array{ key: string, value: float, label: string }
     */
    private function resolve_other_bonus_input($field) {
        $key = isset($_POST[$field]) ? sanitize_key(wp_unslash($_POST[$field])) : 'none';

        $resolved = UR_AI_Calculator_Settings::resolve_other_bonus($key);

        $value = $resolved['value'];

        if ($resolved['custom']) {
            $custom_field = $field . '_custom';
            if (isset($_POST[$custom_field]) && is_numeric($_POST[$custom_field])) {
                $in    = (float) wp_unslash($_POST[$custom_field]);
                $in    = $in > 1 ? $in / 100.0 : $in;
                $value = max(0.0, min($in, 1.0));
            }
        }

        return array('key' => $key, 'value' => $value, 'label' => $this->bonus_label($key));
    }

    /**
     * 取得其他獎勵選項的顯示標籤。
     *
     * @param string $key 選項 key。
     * @return string
     */
    private function bonus_label($key) {
        foreach (UR_AI_Calculator_Settings::get_other_bonus_options() as $opt) {
            if (isset($opt['key']) && $opt['key'] === $key) {
                return isset($opt['label']) ? (string) $opt['label'] : $key;
            }
        }

        return $key;
    }

    /**
     * 存情境到 transient，回傳 token。
     *
     * @param array $context 情境。
     * @return string token。
     */
    private function store_context(array $context) {
        $token = wp_generate_password(32, false, false);

        $context['source_url'] = isset($_SERVER['HTTP_REFERER'])
            ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']))
            : '';

        $context['ip_hash']    = md5(UR_AI_Security::get_user_ip());
        $context['created_at'] = current_time('mysql');

        set_transient(self::CTX_PREFIX . $token, $context, self::CTX_TTL);

        return $token;
    }

    /**
     * 依 token 取出情境（供 CF7 hook 使用）。
     *
     * @param string $token token。
     * @return array|null
     */
    public static function pull_context($token) {
        $token = preg_replace('/[^A-Za-z0-9]/', '', (string) $token);

        if ('' === $token) {
            return null;
        }

        $ctx = get_transient(self::CTX_PREFIX . $token);

        if (!is_array($ctx)) {
            return null;
        }

        // 一次性使用，取出即刪。
        delete_transient(self::CTX_PREFIX . $token);

        return $ctx;
    }

    /**
     * 簡易 IP rate-limit。
     *
     * @return bool true 表示允許。
     */
    private function check_rate_limit() {
        $ip_hash = md5(UR_AI_Security::get_user_ip());
        $key     = 'ur_ai_calc_rl_' . $ip_hash;

        // 原本的 get_transient()→set_transient() 為非原子操作，高並發下
        // 可能多個請求讀到相同舊值、各自 +1，讓限制被繞過。改用原子遞增，
        // 每次呼叫先計數再判斷是否超限。
        $count = UR_AI_Helper::atomic_increment_transient($key, HOUR_IN_SECONDS);

        return $count <= self::RATE_LIMIT_PER_HOUR;
    }

    /**
     * 數字格式化（去除無意義小數）。
     *
     * @param float $n 數字。
     * @return string
     */
    private function fmt($n) {
        $n = (float) $n;

        return rtrim(rtrim(number_format($n, 1), '0'), '.');
    }
}
