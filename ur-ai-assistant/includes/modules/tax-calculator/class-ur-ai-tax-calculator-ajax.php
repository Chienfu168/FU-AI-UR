<?php
/**
 * UR AI Assistant Tax Calculator Ajax
 *
 * 稅賦試算前台 AJAX 處理器（土地增值稅／契稅）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Tax_Calculator_Ajax
 */
class UR_AI_Tax_Calculator_Ajax {

    /**
     * AJAX action 名稱。
     *
     * @var string
     */
    const ACTION_COMPUTE = 'ur_ai_tax_calc_compute';

    /**
     * 每 IP 每小時計算次數上限（防濫用）。
     *
     * @var int
     */
    const RATE_LIMIT_PER_HOUR = 60;

    /**
     * 試算服務。
     *
     * @var UR_AI_Tax_Calculator_Service|null
     */
    private $service;

    /**
     * 建構子。
     *
     * @param UR_AI_Tax_Calculator_Service|null $service Service。
     */
    public function __construct($service = null) {
        $this->service = $service instanceof UR_AI_Tax_Calculator_Service
            ? $service
            : (class_exists('UR_AI_Tax_Calculator_Service') ? new UR_AI_Tax_Calculator_Service() : null);
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
     * 處理試算請求。
     *
     * @return void
     */
    public function handle_compute() {
        UR_AI_Security::ajax_verify_public_nonce_or_die();

        if (!class_exists('UR_AI_Tax_Calculator_Settings') || !UR_AI_Tax_Calculator_Settings::is_enabled()) {
            wp_send_json_error(array('message' => __('稅賦試算功能目前未啟用。', 'ur-ai-assistant')), 403);
        }

        if (!$this->service instanceof UR_AI_Tax_Calculator_Service) {
            wp_send_json_error(array('message' => __('稅賦試算服務尚未正確載入，請稍後再試。', 'ur-ai-assistant')), 500);
        }

        if (!$this->check_rate_limit()) {
            wp_send_json_error(array('message' => __('操作過於頻繁，請稍後再試。', 'ur-ai-assistant')), 429);
        }

        $calc_type = isset($_POST['calc_type']) ? sanitize_key(wp_unslash($_POST['calc_type'])) : 'land_value_tax';

        $result = ('deed_tax' === $calc_type) ? $this->compute_deed_tax() : $this->compute_land_value_tax();

        if (empty($result['success'])) {
            wp_send_json_error(
                array('message' => !empty($result['message']) ? $result['message'] : __('試算失敗，請確認輸入內容。', 'ur-ai-assistant')),
                400
            );
        }

        wp_send_json_success($result);
    }

    /**
     * 解析土地增值稅試算輸入並計算。
     *
     * @return array
     */
    private function compute_land_value_tax() {
        $args = array(
            'land_type'              => isset($_POST['land_type']) && 'non_urban' === sanitize_key(wp_unslash($_POST['land_type'])) ? 'non_urban' : 'urban',
            'self_use'               => !empty($_POST['self_use']),
            'area'                   => isset($_POST['area']) ? (float) wp_unslash($_POST['area']) : 0,
            'share_numerator'        => isset($_POST['share_numerator']) ? (float) wp_unslash($_POST['share_numerator']) : 1,
            'share_denominator'      => isset($_POST['share_denominator']) ? (float) wp_unslash($_POST['share_denominator']) : 1,
            'current_value'          => isset($_POST['current_value']) ? (float) wp_unslash($_POST['current_value']) : 0,
            'original_value'         => isset($_POST['original_value']) ? (float) wp_unslash($_POST['original_value']) : 0,
            'cpi_percent'            => isset($_POST['cpi_percent']) ? (float) wp_unslash($_POST['cpi_percent']) : 100,
            'holding_years'          => isset($_POST['holding_years']) ? (int) wp_unslash($_POST['holding_years']) : 0,
            'land_value_tax_credit'  => isset($_POST['land_value_tax_credit']) ? (float) wp_unslash($_POST['land_value_tax_credit']) : 0,
            'reduction_scenario'     => isset($_POST['reduction_scenario']) ? sanitize_key(wp_unslash($_POST['reduction_scenario'])) : 'none',
        );

        $result              = $this->service->calculate_land_value_increment_tax($args);
        $result['calc_type'] = 'land_value_tax';

        return $result;
    }

    /**
     * 解析契稅試算輸入並計算。
     *
     * @return array
     */
    private function compute_deed_tax() {
        $args = array(
            'declared_value'     => isset($_POST['declared_value']) ? (float) wp_unslash($_POST['declared_value']) : 0,
            'transfer_type'      => isset($_POST['transfer_type']) ? sanitize_key(wp_unslash($_POST['transfer_type'])) : 'buy',
            'reduction_scenario' => isset($_POST['reduction_scenario']) ? sanitize_key(wp_unslash($_POST['reduction_scenario'])) : 'none',
        );

        $result              = $this->service->calculate_deed_tax($args);
        $result['calc_type'] = 'deed_tax';

        return $result;
    }

    /**
     * 簡易 IP rate-limit。
     *
     * @return bool true 表示允許。
     */
    private function check_rate_limit() {
        $ip_hash = md5(UR_AI_Security::get_user_ip());
        $key     = 'ur_ai_tax_calc_rl_' . $ip_hash;

        $count = UR_AI_Helper::atomic_increment_transient($key, HOUR_IN_SECONDS);

        return $count <= self::RATE_LIMIT_PER_HOUR;
    }
}
