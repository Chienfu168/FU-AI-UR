<?php
/**
 * UR AI Gateway WooCommerce Integration
 *
 * 掛勾 WooCommerce Subscriptions 的訂閱狀態變化事件，自動建立／更新
 * 對應的授權碼——訂閱從無到 active 時建立授權碼並寄送給客戶；訂閱
 * 進入其他狀態（到期、取消、逾期未繳等）時同步更新授權狀態。
 *
 * 這個外掛完全不負責「收費」本身：金流、扣款、訂閱排程都交給
 * WooCommerce ＋ WooCommerce Subscriptions ＋ 金流外掛（例如綠界）
 * 處理，這裡只在訂閱狀態變化時做「授權碼要不要開通／暫停」的判斷。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_WC_Integration
 */
class UR_AI_Gateway_WC_Integration {

    /**
     * License Service.
     *
     * @var UR_AI_Gateway_License_Service|null
     */
    private $license_service;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->license_service = class_exists('UR_AI_Gateway_License_Service') ? new UR_AI_Gateway_License_Service() : null;
    }

    /**
     * 註冊 WooCommerce Subscriptions 事件掛鉤。
     *
     * 只在偵測到 WooCommerce Subscriptions 已啟用時才掛鉤，避免只裝了
     * WooCommerce（沒裝訂閱外掛）時出現無意義的通知或錯誤。
     *
     * @return void
     */
    public function register() {
        if (!$this->license_service instanceof UR_AI_Gateway_License_Service) {
            return;
        }

        if (!class_exists('WC_Subscriptions')) {
            return;
        }

        add_action('woocommerce_subscription_status_active', array($this, 'handle_subscription_active'));
        add_action('woocommerce_subscription_status_updated', array($this, 'handle_subscription_status_updated'), 10, 3);
    }

    /**
     * 訂閱進入 active 狀態：建立授權碼（若已存在則不重複建立），並
     * 觸發一封通知信給客戶。
     *
     * @param WC_Subscription $subscription 訂閱物件。
     * @return void
     */
    public function handle_subscription_active($subscription) {
        if (!is_object($subscription) || !method_exists($subscription, 'get_id')) {
            return;
        }

        $subscription_id = absint($subscription->get_id());
        $order_id        = method_exists($subscription, 'get_parent_id') ? absint($subscription->get_parent_id()) : 0;
        $customer_email  = method_exists($subscription, 'get_billing_email') ? (string) $subscription->get_billing_email() : '';
        $plan            = $this->get_plan_label($subscription);

        $license = $this->license_service->create_from_subscription($subscription_id, $order_id, $customer_email, $plan);

        if (!$license) {
            return;
        }

        // 訂閱重新恢復為 active（例如補繳過期款項）時，也要確保授權狀態同步回 active。
        $this->license_service->sync_from_subscription_status($subscription_id, 'active');

        $this->maybe_send_license_email($license, $subscription);
    }

    /**
     * 訂閱狀態變化時，同步更新對應授權碼的狀態（active 以外的狀態）。
     *
     * @param WC_Subscription $subscription 訂閱物件。
     * @param string           $new_status 新狀態。
     * @param string           $old_status 舊狀態。
     * @return void
     */
    public function handle_subscription_status_updated($subscription, $new_status, $old_status) {
        if (!is_object($subscription) || !method_exists($subscription, 'get_id')) {
            return;
        }

        if ('active' === $new_status) {
            // active 狀態已經由 handle_subscription_active() 處理，避免重複動作。
            return;
        }

        $this->license_service->sync_from_subscription_status(absint($subscription->get_id()), $new_status);
    }

    /**
     * 首次建立授權碼時，寄送一封包含授權碼的通知信給客戶。
     *
     * 只在「這是新建立的授權碼」時寄送一次，避免訂閱狀態每次變動都
     * 重複寄信打擾客戶；判斷方式：授權碼的建立時間與更新時間相同。
     *
     * @param object $license 授權碼資料列。
     * @param object $subscription 訂閱物件。
     * @return void
     */
    private function maybe_send_license_email($license, $subscription) {
        if (empty($license->customer_email)) {
            return;
        }

        if ($license->created_at !== $license->updated_at) {
            return;
        }

        $subject = __('您的 AI 代管服務授權碼', 'ur-ai-gateway');

        $body = sprintf(
            /* translators: %s: 授權碼 */
            __("感謝您的訂閱，以下是您的 AI 代管服務授權碼：\n\n%s\n\n請將這組授權碼填入 UR AI Assistant 外掛「功能設定」頁的「代管服務授權碼」欄位，並將「AI 服務來源」切換為「使用代管服務」即可開始使用，不需要自行申請 OpenAI API Key。", 'ur-ai-gateway'),
            $license->license_key
        );

        wp_mail($license->customer_email, $subject, $body);
    }

    /**
     * 取得訂閱方案名稱（供授權碼記錄用，純顯示用途）。
     *
     * @param object $subscription 訂閱物件。
     * @return string
     */
    private function get_plan_label($subscription) {
        if (!method_exists($subscription, 'get_items')) {
            return '';
        }

        foreach ($subscription->get_items() as $item) {
            if (method_exists($item, 'get_name')) {
                return sanitize_text_field($item->get_name());
            }
        }

        return '';
    }
}
