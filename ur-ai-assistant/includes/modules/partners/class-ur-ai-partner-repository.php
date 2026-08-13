<?php
/**
 * UR AI Assistant Partner Repository
 *
 * 相關產業名錄資料庫存取層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Partner_Repository
 */
class UR_AI_Partner_Repository {

    /**
     * 資料表名稱。
     *
     * @var string
     */
    private $table_name;

    /**
     * 可寫入欄位。
     *
     * @var array
     */
    private $fields = array(
        'name', 'category', 'region', 'tagline', 'description', 'contact_person',
        'phone', 'email', 'website_url', 'logo_url', 'tier', 'weight', 'status',
        'sort_order', 'source_type', 'source_url', 'verified_at', 'is_verified', 'featured_until',
    );

    /**
     * 建構子。
     */
    public function __construct() {
        global $wpdb;

        $this->table_name = class_exists('UR_AI_Schema_Partners')
            ? UR_AI_Schema_Partners::get_table_name()
            : $wpdb->prefix . 'ur_ai_partners';
    }

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /* ---------------------------------------------------------------------
     * 前台
     * ------------------------------------------------------------------- */

    /**
     * 取得所有啟用中的名錄（前台用），依：置頂→權重→已核對→每日輪替 排序。
     *
     * 每日輪替以「今天日期」為 RAND 種子，同一天內順序固定、跨日換位，
     * 讓同權重的名錄不會永遠固定順序。
     *
     * @return array
     */
    public function get_active_directory() {
        global $wpdb;

        $seed = (int) gmdate('Ymd');

        $sql = "SELECT * FROM {$this->table_name}
                WHERE status = 'active'
                ORDER BY (tier = 'featured' AND (featured_until IS NULL OR featured_until >= CURDATE())) DESC,
                         weight DESC, is_verified DESC, RAND(%d)";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $seed), ARRAY_A); // phpcs:ignore WordPress.DB -- table name is internal; values prepared.

        return is_array($rows) ? $rows : array();
    }

    /**
     * 各分類啟用中筆數。
     *
     * @return array category => count
     */
    public function category_counts() {
        global $wpdb;

        $rows = $wpdb->get_results("SELECT category, COUNT(*) AS n FROM {$this->table_name} WHERE status = 'active' GROUP BY category", ARRAY_A); // phpcs:ignore WordPress.DB

        $out = array();
        foreach ((array) $rows as $r) {
            $out[$r['category']] = (int) $r['n'];
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * 後台
     * ------------------------------------------------------------------- */

    /**
     * 後台列表（可篩選分類／狀態／關鍵字，分頁）。
     *
     * @param array $args {category, status, search, per_page, page}
     * @return array {items, total}
     */
    public function find_all(array $args = array()) {
        global $wpdb;

        $where  = array('1=1');
        $params = array();

        if (!empty($args['category'])) {
            $where[]  = 'category = %s';
            $params[] = $args['category'];
        }
        if (!empty($args['status'])) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if (!empty($args['search'])) {
            $like     = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[]  = '(name LIKE %s OR tagline LIKE %s OR region LIKE %s OR phone LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}";
        $total     = (int) ( $params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql) ); // phpcs:ignore WordPress.DB

        $per_page = max(1, (int) ($args['per_page'] ?? 20));
        $page     = max(1, (int) ($args['page'] ?? 1));
        $offset   = ($page - 1) * $per_page;

        $list_sql   = "SELECT * FROM {$this->table_name} WHERE {$where_sql}
                       ORDER BY tier = 'featured' DESC, sort_order ASC, weight DESC, id DESC
                       LIMIT %d OFFSET %d";
        $list_params = array_merge($params, array($per_page, $offset));
        $items       = $wpdb->get_results($wpdb->prepare($list_sql, $list_params), ARRAY_A); // phpcs:ignore WordPress.DB

        return array('items' => is_array($items) ? $items : array(), 'total' => $total);
    }

    /**
     * 取得單筆。
     *
     * @param int $id id。
     * @return array|null
     */
    public function get($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", (int) $id), // phpcs:ignore WordPress.DB
            ARRAY_A
        );

        return $row ? $row : null;
    }

    /**
     * 新增一筆。
     *
     * @param array $data 資料（已由呼叫端清理）。
     * @return int|false 新 id 或 false。
     */
    public function insert(array $data) {
        global $wpdb;

        $row = $this->prepare_row($data);
        $row['created_at'] = current_time('mysql');
        $row['updated_at'] = current_time('mysql');

        $ok = $wpdb->insert($this->table_name, $row);

        return $ok ? (int) $wpdb->insert_id : false;
    }

    /**
     * 更新一筆。
     *
     * @param int   $id   id。
     * @param array $data 資料。
     * @return bool
     */
    public function update($id, array $data) {
        global $wpdb;

        $row = $this->prepare_row($data);
        $row['updated_at'] = current_time('mysql');

        $ok = $wpdb->update($this->table_name, $row, array('id' => (int) $id));

        return false !== $ok;
    }

    /**
     * 刪除一筆。
     *
     * @param int $id id。
     * @return bool
     */
    public function delete($id) {
        global $wpdb;

        return false !== $wpdb->delete($this->table_name, array('id' => (int) $id), array('%d'));
    }

    /**
     * 批次操作。
     *
     * @param array  $ids    id 陣列。
     * @param string $action enable|disable|verify|unverify|delete|set_category|set_weight。
     * @param mixed  $value  set_category / set_weight 用值。
     * @return int 影響筆數。
     */
    public function batch(array $ids, $action, $value = null) {
        global $wpdb;

        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $now          = current_time('mysql');
        $affected     = 0;

        switch ($action) {
            case 'enable':
                $affected = $wpdb->query($wpdb->prepare("UPDATE {$this->table_name} SET status='active', updated_at=%s WHERE id IN ($placeholders)", array_merge(array($now), $ids))); // phpcs:ignore WordPress.DB
                break;
            case 'disable':
                $affected = $wpdb->query($wpdb->prepare("UPDATE {$this->table_name} SET status='inactive', updated_at=%s WHERE id IN ($placeholders)", array_merge(array($now), $ids))); // phpcs:ignore WordPress.DB
                break;
            case 'verify':
                $affected = $wpdb->query($wpdb->prepare("UPDATE {$this->table_name} SET is_verified=1, verified_at=%s, updated_at=%s WHERE id IN ($placeholders)", array_merge(array(gmdate('Y-m-d'), $now), $ids))); // phpcs:ignore WordPress.DB
                break;
            case 'unverify':
                $affected = $wpdb->query($wpdb->prepare("UPDATE {$this->table_name} SET is_verified=0, updated_at=%s WHERE id IN ($placeholders)", array_merge(array($now), $ids))); // phpcs:ignore WordPress.DB
                break;
            case 'set_category':
                $cat      = class_exists('UR_AI_Partner_Service') ? UR_AI_Partner_Service::normalize_category((string) $value) : (string) $value;
                $affected = $wpdb->query($wpdb->prepare("UPDATE {$this->table_name} SET category=%s, updated_at=%s WHERE id IN ($placeholders)", array_merge(array($cat, $now), $ids))); // phpcs:ignore WordPress.DB
                break;
            case 'set_weight':
                $affected = $wpdb->query($wpdb->prepare("UPDATE {$this->table_name} SET weight=%d, updated_at=%s WHERE id IN ($placeholders)", array_merge(array(max(1, (int) $value), $now), $ids))); // phpcs:ignore WordPress.DB
                break;
            case 'delete':
                $affected = $wpdb->query($wpdb->prepare("DELETE FROM {$this->table_name} WHERE id IN ($placeholders)", $ids)); // phpcs:ignore WordPress.DB
                break;
        }

        return (int) $affected;
    }

    /**
     * 匯入多筆（CSV）。呼叫端已把每列轉為欄位陣列。
     *
     * @param array $rows 每筆為欄位 => 值的陣列（已清理）。
     * @return array {inserted, failed}
     */
    public function import_rows(array $rows) {
        $inserted = 0;
        $failed   = 0;

        foreach ($rows as $data) {
            if (empty($data['name'])) {
                $failed++;
                continue;
            }
            if ($this->insert($data)) {
                $inserted++;
            } else {
                $failed++;
            }
        }

        return array('inserted' => $inserted, 'failed' => $failed);
    }

    /**
     * 總筆數。
     *
     * @return int
     */
    public function count_all() {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"); // phpcs:ignore WordPress.DB
    }

    /**
     * 只保留可寫入欄位並套用適當型別。
     *
     * @param array $data 原始資料。
     * @return array
     */
    private function prepare_row(array $data) {
        $row = array();
        foreach ($this->fields as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $v = $data[$f];
            if (in_array($f, array('weight', 'sort_order', 'is_verified'), true)) {
                $row[$f] = (int) $v;
            } elseif (in_array($f, array('verified_at', 'featured_until'), true)) {
                $row[$f] = ('' === trim((string) $v)) ? null : trim((string) $v);
            } else {
                $row[$f] = (string) $v;
            }
        }
        return $row;
    }
}
