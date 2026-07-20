<?php
/**
 * UR AI Assistant Joint Burden Estimator Ajax
 *
 * 都市更新共同負擔估算前台 AJAX 處理器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Joint_Burden_Ajax
 */
class UR_AI_Joint_Burden_Ajax {

    /**
     * AJAX action 名稱。
     *
     * @var string
     */
    const ACTION_COMPUTE = 'ur_ai_joint_burden_compute';

    /**
     * 每 IP 每小時計算次數上限（防濫用）。
     *
     * @var int
     */
    const RATE_LIMIT_PER_HOUR = 60;

    /**
     * 估算服務。
     *
     * @var UR_AI_Joint_Burden_Service|null
     */
    private $service;

    /**
     * 建構子。
     *
     * @param UR_AI_Joint_Burden_Service|null $service Service。
     */
    public function __construct($service = null) {
        $this->service = $service instanceof UR_AI_Joint_Burden_Service
            ? $service
            : (class_exists('UR_AI_Joint_Burden_Service') ? new UR_AI_Joint_Burden_Service() : null);
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
     * 處理估算請求。
     *
     * @return void
     */
    public function handle_compute() {
        UR_AI_Security::ajax_verify_public_nonce_or_die();

        if (!class_exists('UR_AI_Joint_Burden_Settings') || !UR_AI_Joint_Burden_Settings::is_enabled()) {
            wp_send_json_error(array('message' => __('共同負擔估算功能目前未啟用。', 'ur-ai-assistant')), 403);
        }

        if (!$this->service instanceof UR_AI_Joint_Burden_Service) {
            wp_send_json_error(array('message' => __('共同負擔估算服務尚未正確載入，請稍後再試。', 'ur-ai-assistant')), 500);
        }

        if (!$this->check_rate_limit()) {
            wp_send_json_error(array('message' => __('操作過於頻繁，請稍後再試。', 'ur-ai-assistant')), 429);
        }

        $args = $this->collect_args();

        $result = $this->service->calculate($args);

        if (empty($result['success'])) {
            wp_send_json_error(
                array('message' => !empty($result['message']) ? $result['message'] : __('估算失敗，請確認輸入內容。', 'ur-ai-assistant')),
                400
            );
        }

        wp_send_json_success($result);
    }

    /**
     * 從 POST 收集並清理輸入參數。
     *
     * @return array
     */
    private function collect_args() {
        $float_keys = array(
            'demolition_area', 'total_floor_area_ping', 'surcharge_rate',
            'price_index_base', 'price_index_current', 'unit_area_sqm',
            'land_current_value_total', 'base_site_area_sqm',
            'own_capital_ratio', 'postal_rate', 'bank_rate',
            'design_fee', 'construction_mgmt_fee', 'public_facility_fee', 'condo_fund',
            'demolition_compensation', 'relocation_fee', 'other_c_fee',
            'planning_extra_wan', 'trust_fee', 'g_cost', 'h_cost',
        );

        $int_keys = array(
            'floors_above', 'floors_below', 'household_count', 'rights_holders',
            'main_building_parcels_before', 'land_parcels', 'main_building_parcels_after',
            'boundary_survey_parcels', 'drilling_holes', 'door_count', 'owner_count',
        );

        $args = array();

        foreach ($float_keys as $k) {
            $args[$k] = isset($_POST[$k]) ? (float) wp_unslash($_POST[$k]) : 0;
        }

        foreach ($int_keys as $k) {
            $args[$k] = isset($_POST[$k]) ? (int) wp_unslash($_POST[$k]) : 0;
        }

        // surcharge_rate / own_capital_ratio 前台以百分比輸入，換算為比率。
        $args['surcharge_rate']    = isset($_POST['surcharge_rate']) ? ((float) wp_unslash($_POST['surcharge_rate'])) / 100.0 : 0;
        $args['own_capital_ratio'] = isset($_POST['own_capital_ratio']) ? ((float) wp_unslash($_POST['own_capital_ratio'])) / 100.0 : 0;

        $structure = isset($_POST['structure']) ? sanitize_key(wp_unslash($_POST['structure'])) : 'rc';
        $args['structure'] = in_array($structure, array('steel', 'src', 'rc'), true) ? $structure : 'rc';

        $demo_structure = isset($_POST['demolition_structure']) ? sanitize_key(wp_unslash($_POST['demolition_structure'])) : 'rc';
        $valid_demo = array('steel', 'src', 'rc', 'reinforced', 'brick', 'wood', 'stone', 'metal_shed');
        $args['demolition_structure'] = in_array($demo_structure, $valid_demo, true) ? $demo_structure : 'rc';

        $trust_type = isset($_POST['trust_fee_type']) ? sanitize_key(wp_unslash($_POST['trust_fee_type'])) : 'self';
        $args['trust_fee_type'] = ('developer' === $trust_type) ? 'developer' : 'self';

        $args['top_down_construction'] = !empty($_POST['top_down_construction']) ? 1 : 0;
        $args['include_selection_fee'] = isset($_POST['include_selection_fee']) ? !empty($_POST['include_selection_fee']) : 1;

        return $args;
    }

    /**
     * 簡易 IP rate-limit。
     *
     * @return bool true 表示允許。
     */
    private function check_rate_limit() {
        $ip_hash = md5(UR_AI_Security::get_user_ip());
        $key     = 'ur_ai_jb_rl_' . $ip_hash;

        $count = UR_AI_Helper::atomic_increment_transient($key, HOUR_IN_SECONDS);

        return $count <= self::RATE_LIMIT_PER_HOUR;
    }
}
