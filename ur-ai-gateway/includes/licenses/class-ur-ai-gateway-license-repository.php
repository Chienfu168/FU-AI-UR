<?php
/**
 * UR AI Gateway License Repository
 *
 * 授權碼資料表的直接存取層。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_License_Repository
 */
class UR_AI_Gateway_License_Repository {

    /**
     * 資料表名稱。
     *
     * @var string
     */
    private $table_name;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->table_name = UR_AI_Gateway_Schema_Licenses::table_name();
    }

    /**
     * 新增一筆授權碼。
     *
     * @param array $data 授權碼資料。
     * @return int 新增成功回傳 id，失敗回傳 0。
     */
    public function insert($data) {
        global $wpdb;

        $now = current_time('mysql');

        $row = array(
            'license_key'          => (string) $data['license_key'],
            'customer_email'       => isset($data['customer_email']) ? sanitize_email($data['customer_email']) : '',
            'site_url'             => isset($data['site_url']) ? esc_url_raw($data['site_url']) : '',
            'plan'                 => isset($data['plan']) ? sanitize_text_field($data['plan']) : '',
            'order_id'             => isset($data['order_id']) ? absint($data['order_id']) : 0,
            'subscription_id'      => isset($data['subscription_id']) ? absint($data['subscription_id']) : 0,
            'status'               => isset($data['status']) ? sanitize_key($data['status']) : 'active',
            'daily_limit'          => isset($data['daily_limit']) ? absint($data['daily_limit']) : 200,
            'daily_usage_count'    => 0,
            'daily_usage_reset_at' => $now,
            'total_usage_count'    => 0,
            'admin_note'           => isset($data['admin_note']) ? sanitize_textarea_field($data['admin_note']) : '',
            'expires_at'           => !empty($data['expires_at']) ? $data['expires_at'] : null,
            'created_at'           => $now,
            'updated_at'           => $now,
        );

        $formats = array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s');

        $inserted = $wpdb->insert($this->table_name, $row, $formats);

        return $inserted ? absint($wpdb->insert_id) : 0;
    }

    /**
     * 依 ID 更新授權碼。
     *
     * @param int   $id 授權碼 ID。
     * @param array $data 更新資料。
     * @return bool
     */
    public function update($id, $data) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0 || empty($data)) {
            return false;
        }

        $data['updated_at'] = current_time('mysql');

        $updated = $wpdb->update($this->table_name, $data, array('id' => $id));

        return false !== $updated;
    }

    /**
     * 依 ID 找出授權碼。
     *
     * @param int $id 授權碼 ID。
     * @return object|null
     */
    public function find($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id)
        );
    }

    /**
     * 依授權碼字串找出授權碼列。
     *
     * @param string $license_key 授權碼字串。
     * @return object|null
     */
    public function find_by_key($license_key) {
        global $wpdb;

        $license_key = trim((string) $license_key);

        if ('' === $license_key) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE license_key = %s", $license_key)
        );
    }

    /**
     * 依 WooCommerce 訂閱 ID 找出授權碼列。
     *
     * @param int $subscription_id 訂閱 ID。
     * @return object|null
     */
    public function find_by_subscription_id($subscription_id) {
        global $wpdb;

        $subscription_id = absint($subscription_id);

        if ($subscription_id <= 0) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE subscription_id = %d", $subscription_id)
        );
    }

    /**
     * 查詢授權碼列表（供後台管理頁使用）。
     *
     * @param array $args 查詢參數：status／search／limit／offset。
     * @return array
     */
    public function query($args = array()) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            array(
                'status' => '',
                'search' => '',
                'limit'  => 50,
                'offset' => 0,
            )
        );

        $where  = array('1=1');
        $values = array();

        if ('' !== $args['status']) {
            $where[]  = 'status = %s';
            $values[] = sanitize_key($args['status']);
        }

        if ('' !== $args['search']) {
            $like     = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[]  = '(license_key LIKE %s OR customer_email LIKE %s OR site_url LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $limit  = absint($args['limit']);
        $offset = absint($args['offset']);

        if ($limit <= 0) {
            $limit = 50;
        }

        $sql  = "SELECT * FROM {$this->table_name} WHERE " . implode(' AND ', $where);
        $sql .= ' ORDER BY id DESC LIMIT %d OFFSET %d';

        $values[] = $limit;
        $values[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * 計算符合條件的授權碼筆數。
     *
     * @param array $args 查詢參數。
     * @return int
     */
    public function count($args = array()) {
        global $wpdb;

        $args = wp_parse_args($args, array('status' => '', 'search' => ''));

        $where  = array('1=1');
        $values = array();

        if ('' !== $args['status']) {
            $where[]  = 'status = %s';
            $values[] = sanitize_key($args['status']);
        }

        if ('' !== $args['search']) {
            $like     = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[]  = '(license_key LIKE %s OR customer_email LIKE %s OR site_url LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE " . implode(' AND ', $where);

        if (!empty($values)) {
            return absint($wpdb->get_var($wpdb->prepare($sql, $values)));
        }

        return absint($wpdb->get_var($sql));
    }

    /**
     * 累加使用次數（每日與總計），回傳更新後的每日使用次數。
     *
     * @param int $id 授權碼 ID。
     * @return int
     */
    public function increment_usage($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return 0;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name}
                 SET daily_usage_count = daily_usage_count + 1,
                     total_usage_count = total_usage_count + 1,
                     updated_at = %s
                 WHERE id = %d",
                current_time('mysql'),
                $id
            )
        );

        return absint($wpdb->get_var($wpdb->prepare("SELECT daily_usage_count FROM {$this->table_name} WHERE id = %d", $id)));
    }

    /**
     * 原子性地「檢查並扣用一次每日額度」。
     *
     * 以單一條件式 UPDATE 完成「換日重設 ＋ 額度檢查 ＋ 遞增」，避免原本
     * 「讀→比較→寫」三步分離在併發下各自讀到舊值、衝破額度的問題。
     *
     * 一句 SQL 同時處理：
     * - 換日：若 daily_usage_reset_at 不是今天（或為 NULL），視為新的一天，
     *   daily_usage_count 重設為 1、reset_at 設為今天。
     * - 未換日：daily_usage_count + 1。
     * WHERE 條件保證「只有在額度內（或無上限、或換日）時」才會更新到該列，
     * 因此以受影響列數判斷是否成功扣用：0＝已達上限。
     *
     * @param int $id 授權碼 ID。
     * @return bool true＝成功扣用一次（可繼續呼叫）；false＝已達上限或無效。
     */
    public function try_consume_daily($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $today = current_time('Y-m-d');
        $now   = current_time('mysql');

        $sql = "UPDATE {$this->table_name}
                SET total_usage_count = total_usage_count + 1,
                    daily_usage_count = CASE WHEN DATE(daily_usage_reset_at) = %s THEN daily_usage_count + 1 ELSE 1 END,
                    daily_usage_reset_at = CASE WHEN DATE(daily_usage_reset_at) = %s THEN daily_usage_reset_at ELSE %s END,
                    updated_at = %s
                WHERE id = %d
                  AND (
                        daily_limit = 0
                        OR daily_usage_reset_at IS NULL
                        OR DATE(daily_usage_reset_at) <> %s
                        OR daily_usage_count < daily_limit
                  )";

        $affected = $wpdb->query(
            $wpdb->prepare($sql, $today, $today, $now, $now, $id, $today)
        );

        return (int) $affected > 0;
    }

    /**
     * 累加 token 用量（成功呼叫 OpenAI 後記錄，供用量計費與毛利分析）。
     *
     * @param int $id           授權碼 ID。
     * @param int $total_tokens  本次請求總 token 數。
     * @return void
     */
    public function add_tokens($id, $total_tokens) {
        global $wpdb;

        $id           = absint($id);
        $total_tokens = max(0, (int) $total_tokens);

        if ($id <= 0 || 0 === $total_tokens) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name}
                 SET total_tokens = total_tokens + %d, updated_at = %s
                 WHERE id = %d",
                $total_tokens,
                current_time('mysql'),
                $id
            )
        );
    }

    /**
     * 重設每日使用次數（換日時呼叫）。
     *
     * @param int $id 授權碼 ID。
     * @return void
     */
    public function reset_daily_usage($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return;
        }

        $wpdb->update(
            $this->table_name,
            array(
                'daily_usage_count'    => 0,
                'daily_usage_reset_at' => current_time('mysql'),
                'updated_at'           => current_time('mysql'),
            ),
            array('id' => $id)
        );
    }
}
