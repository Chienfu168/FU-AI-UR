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
     * 計算授權碼的查詢雜湊（完整金鑰的 SHA-256 十六進位）。
     *
     * 授權碼本身是 40 字元的高亂度隨機字串，直接以 SHA-256 存查詢雜湊
     * 即可——不可反推、不可暴力列舉，因此刻意不加 pepper，避免「更換
     * pepper 會讓所有既有客戶金鑰同時失效」的風險。與 MySQL 的
     * SHA2(x, 256) 產生相同的小寫十六進位值，遷移時可直接在 SQL 計算。
     *
     * @param string $key 完整授權碼。
     * @return string 64 字元十六進位雜湊，空金鑰回空字串。
     */
    public static function hash_key($key) {
        $key = trim((string) $key);

        return '' === $key ? '' : hash('sha256', $key);
    }

    /**
     * 將完整授權碼轉成「遮罩顯示值」（如 urg_abcdef...wxyz），存進
     * license_key 欄位供後台清單顯示。保留前 10 碼與後 4 碼，中間遮掉，
     * 對 44 字元的金鑰仍遮去 30 碼以上，無法據此還原或盜用。
     *
     * @param string $key 完整授權碼。
     * @return string
     */
    public static function mask_key($key) {
        $key = (string) $key;

        // 太短（理論上不會發生）就原樣返回，避免切出比原字串還長的結果。
        if (strlen($key) <= 16) {
            return $key;
        }

        return substr($key, 0, 10) . '...' . substr($key, -4);
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

        // 傳入的 license_key 是「完整金鑰」；存檔時只存遮罩值＋雜湊，
        // 不以明文落地資料庫。
        $full_key = (string) $data['license_key'];

        $row = array(
            'license_key'          => self::mask_key($full_key),
            'key_hash'             => self::hash_key($full_key),
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

        $formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s');

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

        // 主要路徑：以完整金鑰的雜湊查詢。
        $hash = self::hash_key($license_key);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE key_hash = %s", $hash)
        );

        if ($row) {
            return $row;
        }

        // 向下相容：資料遷移尚未完成（key_hash 仍為空）時，暫時允許以明文
        // 比對命中，命中後立即補上雜湊並把 license_key 轉為遮罩值。正常
        // 情況下遷移在升級時就已完成，這條路徑只在升級過渡期生效。
        $legacy = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE license_key = %s AND (key_hash IS NULL OR key_hash = '')",
                $license_key
            )
        );

        if ($legacy) {
            $this->backfill_key_hash(absint($legacy->id), $license_key);

            return $this->find(absint($legacy->id));
        }

        return null;
    }

    /**
     * 為單一列補上雜湊並遮罩明文（find_by_key 的過渡期回填用）。
     *
     * @param int    $id       授權碼 ID。
     * @param string $full_key 完整金鑰。
     * @return void
     */
    private function backfill_key_hash($id, $full_key) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0 || '' === trim((string) $full_key)) {
            return;
        }

        $wpdb->update(
            $this->table_name,
            array(
                'key_hash'    => self::hash_key($full_key),
                'license_key' => self::mask_key($full_key),
            ),
            array('id' => $id)
        );
    }

    /**
     * 一次性資料遷移：把既有明文授權碼改成「雜湊＋遮罩」儲存。
     *
     * 由 Schema_Manager 於升級時呼叫。流程：
     * 1. 移除 license_key 的舊 UNIQUE 索引（遮罩值可能重複，不能再唯一）。
     * 2. 以明文算出 key_hash（只處理尚未遷移、仍為完整金鑰長度的列）。
     * 3. 把 license_key 覆寫成遮罩顯示值。
     *
     * 每一步都有條件保護（key_hash 是否已填、license_key 長度），因此
     * 可重複執行而不會重複遮罩或污染雜湊（具冪等性）。
     *
     * @return void
     */
    public function migrate_to_hashed_keys() {
        global $wpdb;

        $table = $this->table_name;

        // 1) 移除舊的 UNIQUE 索引（若存在）。新版 get_sql 已不再宣告它，
        //    dbDelta 不會自行移除既有索引，故在此明確處理一次。
        $existing_index = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'license_key'");

        if (!empty($existing_index)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- 表名為內部常數，無使用者輸入。
            $wpdb->query("ALTER TABLE {$table} DROP INDEX license_key");
        }

        // 2) 以明文計算雜湊（SHA2(x,256) 等同 PHP hash('sha256', x)）。
        //    只處理「尚未遷移」且「仍是完整金鑰長度」的列，避免對已遮罩值再次雜湊。
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- 無使用者輸入，條件為固定字面值。
        $wpdb->query(
            "UPDATE {$table}
             SET key_hash = SHA2(license_key, 256)
             WHERE (key_hash IS NULL OR key_hash = '')
               AND license_key LIKE 'urg\\_%'
               AND CHAR_LENGTH(license_key) > 20"
        );

        // 3) 把明文覆寫為遮罩顯示值（雜湊已填、且仍為完整金鑰長度的列）。
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- 無使用者輸入，條件為固定字面值。
        $wpdb->query(
            "UPDATE {$table}
             SET license_key = CONCAT(SUBSTRING(license_key, 1, 10), '...', SUBSTRING(license_key, -4))
             WHERE key_hash IS NOT NULL AND key_hash <> ''
               AND license_key LIKE 'urg\\_%'
               AND CHAR_LENGTH(license_key) > 20"
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
            // 允許操作者直接貼上客戶的「完整金鑰」反查（以雜湊比對），
            // 一般文字則照常以遮罩值／Email／網址模糊比對。
            $where[]  = '(license_key LIKE %s OR customer_email LIKE %s OR site_url LIKE %s OR key_hash = %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = self::hash_key($args['search']);
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
            $where[]  = '(license_key LIKE %s OR customer_email LIKE %s OR site_url LIKE %s OR key_hash = %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = self::hash_key($args['search']);
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
