<?php
/**
 * UR AI Assistant Calculator Lead Repository
 *
 * 都更分回試算「名單」資料存取層。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Calculator_Lead_Repository
 */
class UR_AI_Calculator_Lead_Repository {

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    private function table() {
        return UR_AI_Schema_Calculator_Leads::get_table_name();
    }

    /**
     * 新增一筆名單。
     *
     * @param array $data 已清理的資料。
     * @return int|false 新名單 ID，失敗回 false。
     */
    public function insert(array $data) {
        global $wpdb;

        $now = current_time('mysql');

        $row = array(
            'name'           => isset($data['name']) ? (string) $data['name'] : '',
            'tel'            => isset($data['tel']) ? (string) $data['tel'] : '',
            'email'          => isset($data['email']) ? (string) $data['email'] : '',
            'message'        => isset($data['message']) ? (string) $data['message'] : '',
            'consent'        => !empty($data['consent']) ? 1 : 0,
            'city'           => isset($data['city']) ? (string) $data['city'] : '',
            'track'          => isset($data['track']) ? (string) $data['track'] : '',
            'result_summary' => isset($data['result_summary']) ? (string) $data['result_summary'] : '',
            'context_json'   => isset($data['context_json']) ? (string) $data['context_json'] : '',
            'source_url'     => isset($data['source_url']) ? (string) $data['source_url'] : '',
            'ip_hash'        => isset($data['ip_hash']) ? (string) $data['ip_hash'] : '',
            'cf7_form_id'    => isset($data['cf7_form_id']) ? absint($data['cf7_form_id']) : 0,
            'status'         => isset($data['status']) ? (string) $data['status'] : 'new',
            'admin_note'     => '',
            'created_at'     => $now,
            'updated_at'     => $now,
        );

        $formats = array(
            '%s', '%s', '%s', '%s', '%d',
            '%s', '%s', '%s', '%s',
            '%s', '%s', '%d',
            '%s', '%s', '%s', '%s',
        );

        $result = $wpdb->insert($this->table(), $row, $formats);

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * 依條件查詢名單（分頁）。
     *
     * @param array $args {
     *     @type int    $paged    頁碼（1 起算）。
     *     @type int    $per_page 每頁筆數。
     *     @type string $status   篩選狀態（空為全部）。
     *     @type string $city     篩選縣市（空為全部）。
     * }
     * @return array{ items: array, total: int, paged: int, per_page: int, total_pages: int }
     */
    public function query(array $args = array()) {
        global $wpdb;

        $paged    = max(1, isset($args['paged']) ? (int) $args['paged'] : 1);
        $per_page = max(1, min(100, isset($args['per_page']) ? (int) $args['per_page'] : 20));
        $offset   = ($paged - 1) * $per_page;

        $where  = 'WHERE 1=1';
        $params = array();

        if (!empty($args['status'])) {
            $where   .= ' AND status = %s';
            $params[] = (string) $args['status'];
        }

        if (!empty($args['city'])) {
            $where   .= ' AND city = %s';
            $params[] = (string) $args['city'];
        }

        $table = $this->table();

        // 總數。
        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // 內容。
        $list_params   = $params;
        $list_params[] = $per_page;
        $list_params[] = $offset;

        $list_sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $list_sql = $wpdb->prepare($list_sql, $list_params);

        $items = $wpdb->get_results($list_sql, ARRAY_A);

        if (!is_array($items)) {
            $items = array();
        }

        return array(
            'items'       => $items,
            'total'       => $total,
            'paged'       => $paged,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        );
    }

    /**
     * 取得單筆。
     *
     * @param int $id 名單 ID。
     * @return array|null
     */
    public function get($id) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * 更新狀態與備註。
     *
     * @param int    $id     名單 ID。
     * @param string $status 新狀態。
     * @param string $note   備註（null 表示不更動）。
     * @return bool
     */
    public function update_status($id, $status, $note = null) {
        global $wpdb;

        $id = absint($id);

        if ($id <= 0) {
            return false;
        }

        $valid_statuses = array_keys(UR_AI_Schema_Calculator_Leads::get_statuses());

        if (!in_array($status, $valid_statuses, true)) {
            return false;
        }

        $data    = array('status' => $status, 'updated_at' => current_time('mysql'));
        $formats = array('%s', '%s');

        if (null !== $note) {
            $data['admin_note'] = sanitize_textarea_field((string) $note);
            $formats[]          = '%s';
        }

        $result = $wpdb->update($this->table(), $data, array('id' => $id), $formats, array('%d'));

        return false !== $result;
    }

    /**
     * 統計各狀態數量（後台儀表用）。
     *
     * @return array status => count
     */
    public function count_by_status() {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS n FROM {$this->table()} GROUP BY status",
            ARRAY_A
        );

        $out = array();

        if (is_array($rows)) {
            foreach ($rows as $r) {
                $out[(string) $r['status']] = (int) $r['n'];
            }
        }

        return $out;
    }
}
