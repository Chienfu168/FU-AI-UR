<?php
/**
 * UR AI Assistant Market Price Ajax
 *
 * 行情參考前台 AJAX 處理器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Ajax
 */
class UR_AI_Market_Price_Ajax {

    /**
     * AJAX action 名稱。
     *
     * @var string
     */
    const ACTION_QUERY = 'ur_ai_market_price_query';

    /**
     * Service。
     *
     * @var UR_AI_Market_Price_Service|null
     */
    private $service;

    /**
     * 建構子。
     *
     * @param UR_AI_Market_Price_Service|null $service Service。
     */
    public function __construct($service = null) {
        $this->service = $service instanceof UR_AI_Market_Price_Service
            ? $service
            : (class_exists('UR_AI_Market_Price_Service') ? new UR_AI_Market_Price_Service() : null);
    }

    /**
     * 註冊 AJAX 掛鉤（登入與未登入皆可）。
     *
     * @return void
     */
    public function register() {
        add_action('wp_ajax_' . self::ACTION_QUERY, array($this, 'handle_query'));
        add_action('wp_ajax_nopriv_' . self::ACTION_QUERY, array($this, 'handle_query'));
    }

    /**
     * 處理行情查詢請求。
     *
     * @return void
     */
    public function handle_query() {
        UR_AI_Security::ajax_verify_public_nonce_or_die();

        if (!$this->is_enabled()) {
            wp_send_json_error(
                array('message' => __('行情參考功能目前未啟用。', 'ur-ai-assistant')),
                403
            );
        }

        if (!$this->service instanceof UR_AI_Market_Price_Service) {
            wp_send_json_error(
                array('message' => __('行情參考服務尚未正確載入，請稍後再試。', 'ur-ai-assistant')),
                500
            );
        }

        $city = isset($_POST['city']) ? sanitize_key(wp_unslash($_POST['city'])) : '';

        if (!array_key_exists($city, $this->service->get_supported_cities())) {
            wp_send_json_error(
                array('message' => __('目前僅支援台北市、新北市。', 'ur-ai-assistant')),
                400
            );
        }

        $district      = isset($_POST['district']) ? sanitize_text_field(wp_unslash($_POST['district'])) : '';
        $zone          = isset($_POST['zone']) ? sanitize_text_field(wp_unslash($_POST['zone'])) : '';
        $building_type = isset($_POST['building_type']) ? sanitize_text_field(wp_unslash($_POST['building_type'])) : '';

        if ('' === $district) {
            wp_send_json_error(
                array('message' => __('請選擇行政區。', 'ur-ai-assistant')),
                400
            );
        }

        $comparison = $this->service->get_comparison(
            array(
                'city'          => $city,
                'district'      => $district,
                'zone'          => $zone,
                'building_type' => $building_type,
            )
        );

        wp_send_json_success(
            array(
                'old'               => $comparison['old'],
                'new'               => $comparison['new'],
                'old_age_threshold' => absint($comparison['old_age_threshold']),
                'new_age_threshold' => absint($comparison['new_age_threshold']),
                'min_sample_size'   => absint($comparison['min_sample_size']),
                'uplift_percent'    => $comparison['uplift_percent'],
                'last_imported_at'  => (string) $this->service->get_last_imported_at(),
                'disclaimer'        => $this->get_disclaimer(),
            )
        );
    }

    /**
     * 判斷模組是否啟用。
     *
     * @return bool
     */
    private function is_enabled() {
        return class_exists('UR_AI_Market_Price_Settings') && UR_AI_Market_Price_Settings::is_enabled();
    }

    /**
     * 取得免責聲明。
     *
     * @return string
     */
    private function get_disclaimer() {
        return class_exists('UR_AI_Market_Price_Settings')
            ? UR_AI_Market_Price_Settings::get_disclaimer()
            : '';
    }
}
