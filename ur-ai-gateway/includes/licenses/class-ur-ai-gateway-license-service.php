<?php
/**
 * UR AI Gateway License Service
 *
 * 授權碼業務邏輯層：產生授權碼、依 WooCommerce 訂閱狀態建立／更新
 * 授權、驗證授權是否有效、每日用量限制的檢查與累加。
 *
 * 設計原則：
 * - 授權碼一律由本外掛產生（隨機字串，不使用可猜測的遞增 ID），
 *   客戶端網站把它當成 Bearer 憑證使用，不會知道也不需要知道任何
 *   內部訂閱／訂單細節。
 * - 訂閱狀態與授權狀態的對應集中在 map_subscription_status_to_license_status()
 *   一個地方維護，未來 WooCommerce Subscriptions 狀態如有調整，只需要
 *   改這裡。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_License_Service
 */
class UR_AI_Gateway_License_Service {

    /**
     * Repository.
     *
     * @var UR_AI_Gateway_License_Repository|null
     */
    private $repository;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->repository = class_exists('UR_AI_Gateway_License_Repository') ? new UR_AI_Gateway_License_Repository() : null;
    }

    /**
     * 依 WooCommerce 訂閱建立（或若已存在則直接回傳既有）授權碼。
     *
     * @param int    $subscription_id 訂閱 ID。
     * @param int    $order_id 訂單 ID。
     * @param string $customer_email 客戶 Email。
     * @param string $plan 方案名稱。
     * @return object|null 授權碼資料列。
     */
    public function create_from_subscription($subscription_id, $order_id, $customer_email, $plan = '') {
        if (!$this->repository instanceof UR_AI_Gateway_License_Repository) {
            return null;
        }

        $subscription_id = absint($subscription_id);

        if ($subscription_id <= 0) {
            return null;
        }

        $existing = $this->repository->find_by_subscription_id($subscription_id);

        if ($existing) {
            return $existing;
        }

        $license_key = $this->generate_license_key();

        $daily_limit = class_exists('UR_AI_Gateway_Settings')
            ? UR_AI_Gateway_Settings::get_default_daily_limit()
            : 200;

        $id = $this->repository->insert(
            array(
                'license_key'     => $license_key,
                'customer_email'  => $customer_email,
                'plan'            => $plan,
                'order_id'        => $order_id,
                'subscription_id' => $subscription_id,
                'status'          => 'active',
                'daily_limit'     => $daily_limit,
            )
        );

        return $id > 0 ? $this->repository->find($id) : null;
    }

    /**
     * 依 WooCommerce 訂閱狀態變化，更新對應授權碼的狀態。
     *
     * 找不到對應授權碼時不做任何事——訂閱建立初期（尚未進入 active
     * 狀態前）本來就還沒有授權碼可以更新，不是錯誤情況。
     *
     * @param int    $subscription_id 訂閱 ID。
     * @param string $subscription_status WooCommerce 訂閱狀態（不含 wc- 前綴）。
     * @return void
     */
    public function sync_from_subscription_status($subscription_id, $subscription_status) {
        if (!$this->repository instanceof UR_AI_Gateway_License_Repository) {
            return;
        }

        $license = $this->repository->find_by_subscription_id(absint($subscription_id));

        if (!$license) {
            return;
        }

        $license_status = $this->map_subscription_status_to_license_status($subscription_status);

        if ($license_status === $license->status) {
            return;
        }

        $this->repository->update(absint($license->id), array('status' => $license_status));
    }

    /**
     * 手動建立一組授權碼（後台「手動發放」用，不綁定任何 WooCommerce 訂閱）。
     *
     * @param string $customer_email 客戶 Email。
     * @param string $plan 方案名稱／備註。
     * @param int    $daily_limit 每日呼叫上限。
     * @param string $admin_note 備註。
     * @return object|null
     */
    public function create_manual($customer_email, $plan = '', $daily_limit = 0, $admin_note = '') {
        if (!$this->repository instanceof UR_AI_Gateway_License_Repository) {
            return null;
        }

        $daily_limit = absint($daily_limit);

        if ($daily_limit <= 0) {
            $daily_limit = class_exists('UR_AI_Gateway_Settings') ? UR_AI_Gateway_Settings::get_default_daily_limit() : 200;
        }

        $id = $this->repository->insert(
            array(
                'license_key'    => $this->generate_license_key(),
                'customer_email' => $customer_email,
                'plan'           => $plan,
                'status'         => 'active',
                'daily_limit'    => $daily_limit,
                'admin_note'     => $admin_note,
            )
        );

        return $id > 0 ? $this->repository->find($id) : null;
    }

    /**
     * 依授權碼字串取得授權碼資料列。
     *
     * @param string $license_key 授權碼字串。
     * @return object|null
     */
    public function find_by_key($license_key) {
        if (!$this->repository instanceof UR_AI_Gateway_License_Repository) {
            return null;
        }

        return $this->repository->find_by_key($license_key);
    }

    /**
     * 判斷一組授權碼目前是否有效（狀態為 active，且未過期）。
     *
     * @param object|null $license 授權碼資料列。
     * @return bool
     */
    public function is_valid($license) {
        if (!$license) {
            return false;
        }

        if ('active' !== $license->status) {
            return false;
        }

        if (!empty($license->expires_at) && strtotime($license->expires_at) < current_time('timestamp')) {
            return false;
        }

        return true;
    }

    /**
     * 檢查並累加每日用量：換日時自動重設，超過每日上限時回傳 false。
     *
     * @param object $license 授權碼資料列。
     * @return bool 是否還在額度內（true=可以繼續呼叫並已扣一次額度）。
     */
    public function check_and_consume_daily_limit($license) {
        if (!$this->repository instanceof UR_AI_Gateway_License_Repository || !$license) {
            return false;
        }

        $id           = absint($license->id);
        $daily_count  = absint($license->daily_usage_count);
        $daily_limit  = absint($license->daily_limit);
        $last_reset   = !empty($license->daily_usage_reset_at) ? strtotime($license->daily_usage_reset_at) : 0;
        $today        = date('Y-m-d', current_time('timestamp'));
        $last_reset_d = $last_reset ? date('Y-m-d', $last_reset) : '';

        if ($last_reset_d !== $today) {
            $this->repository->reset_daily_usage($id);
            $daily_count = 0;
        }

        if ($daily_limit > 0 && $daily_count >= $daily_limit) {
            return false;
        }

        $this->repository->increment_usage($id);

        return true;
    }

    /**
     * 產生一組隨機授權碼。
     *
     * @return string
     */
    private function generate_license_key() {
        $random = function_exists('wp_generate_password')
            ? wp_generate_password(40, false, false)
            : bin2hex(random_bytes(20));

        return 'urg_' . $random;
    }

    /**
     * WooCommerce 訂閱狀態 → 授權狀態對應表。
     *
     * @param string $subscription_status WooCommerce 訂閱狀態。
     * @return string
     */
    private function map_subscription_status_to_license_status($subscription_status) {
        $subscription_status = str_replace('wc-', '', (string) $subscription_status);

        $map = array(
            'active'          => 'active',
            'on-hold'         => 'suspended',
            'pending'         => 'suspended',
            'pending-cancel'  => 'active', // 訂閱到期前仍可使用，到期後會另外收到 expired/cancelled 事件。
            'cancelled'       => 'revoked',
            'expired'         => 'expired',
            'trash'           => 'revoked',
        );

        return isset($map[$subscription_status]) ? $map[$subscription_status] : 'suspended';
    }
}
