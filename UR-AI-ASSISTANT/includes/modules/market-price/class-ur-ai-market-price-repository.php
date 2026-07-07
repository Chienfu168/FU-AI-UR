<?php
/**
 * UR AI Assistant Market Price Repository
 *
 * 行情參考資料庫存取層。
 *
 * 中位數等統計計算故意在 PHP 端進行（而非依賴 MySQL 8 的 PERCENTILE_CONT
 * 等新版窗口函數），確保在較舊版 MySQL/MariaDB 的一般 WordPress 主機上
 * 也能正常運作。單一查詢組合的樣本數在雙北的規模下不大，PHP 端排序計算
 * 效能無虞。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Repository
 */
class UR_AI_Market_Price_Repository {

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
        $this->table_name = class_exists('UR_AI_Schema_Market_Prices')
            ? UR_AI_Schema_Market_Prices::get_table_name()
            : $this->fallback_table_name();
    }

    /**
     * 取得資料表名稱。
     *
     * @return string
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * 新增一筆行情紀錄（以 source_record_id 防重複）。
     *
     * 大量匯入時可傳入 $known_ids（由 get_existing_source_record_ids()
     * 預先查出的集合，呼叫端逐筆插入時同步更新），避免每一筆都對資料庫
     * 額外下一次 SELECT 查重複——唯一索引本身仍是最終防線。
     *
     * @param array      $data 資料。
     * @param array|null $known_ids 已知 source_record_id 集合（以值為 key），
     *                              會在成功新增後就地更新；留空則退回逐筆查詢。
     * @return string 'inserted' | 'duplicate' | 'failed'
     */
    public function insert($data, array &$known_ids = null) {
        global $wpdb;

        $source_record_id = isset($data['source_record_id']) ? (string) $data['source_record_id'] : '';

        if ('' === $source_record_id) {
            return 'failed';
        }

        $is_duplicate = null !== $known_ids
            ? isset($known_ids[$source_record_id])
            : $this->find_by_source_record_id($source_record_id);

        if ($is_duplicate) {
            return 'duplicate';
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'city'                    => isset($data['city']) ? (string) $data['city'] : '',
                'district'                => isset($data['district']) ? (string) $data['district'] : '',
                'zone'                    => isset($data['zone']) ? (string) $data['zone'] : '',
                'zone_raw'                => isset($data['zone_raw']) ? (string) $data['zone_raw'] : '',
                'building_type'           => isset($data['building_type']) ? (string) $data['building_type'] : '',
                'address_raw'             => isset($data['address_raw']) ? (string) $data['address_raw'] : '',
                'transaction_date'        => isset($data['transaction_date']) ? (string) $data['transaction_date'] : null,
                'built_year'              => isset($data['built_year']) ? absint($data['built_year']) : 0,
                'building_age_years'      => isset($data['building_age_years']) ? absint($data['building_age_years']) : 0,
                'building_area_sqm'       => isset($data['building_area_sqm']) ? (float) $data['building_area_sqm'] : 0,
                'total_price'             => isset($data['total_price']) ? absint($data['total_price']) : 0,
                'parking_price'           => isset($data['parking_price']) ? absint($data['parking_price']) : 0,
                'unit_price_per_ping'     => isset($data['unit_price_per_ping']) ? (float) $data['unit_price_per_ping'] : 0,
                'is_special_relationship' => !empty($data['is_special_relationship']) ? 1 : 0,
                'source_record_id'        => $source_record_id,
                'import_batch'            => isset($data['import_batch']) ? (string) $data['import_batch'] : '',
                'created_at'              => current_time('mysql'),
            ),
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d',
                '%f', '%d', '%d', '%f', '%d', '%s', '%s', '%s',
            )
        );

        if (false === $result) {
            return 'failed';
        }

        if (null !== $known_ids) {
            $known_ids[$source_record_id] = true;
        }

        return 'inserted';
    }

    /**
     * 依來源編號查詢是否已存在。
     *
     * @param string $source_record_id 政府資料的唯一編號。
     * @return bool
     */
    public function find_by_source_record_id($source_record_id) {
        global $wpdb;

        $source_record_id = (string) $source_record_id;

        if ('' === $source_record_id) {
            return false;
        }

        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE source_record_id = %s LIMIT 1",
                $source_record_id
            )
        );

        return null !== $id && '' !== $id;
    }

    /**
     * 預先取出指定縣市已存在的 source_record_id 集合（以值為 key），
     * 供大量匯入時逐筆比對，避免每一列都對資料庫下一次查重複 SELECT。
     *
     * @param string $city 縣市 key。
     * @return array
     */
    public function get_existing_source_record_ids($city) {
        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT source_record_id FROM {$this->table_name} WHERE city = %s",
                (string) $city
            )
        );

        return is_array($ids) ? array_fill_keys($ids, true) : array();
    }

    /**
     * 取得行情統計摘要（均價、中位數、樣本數等）。
     *
     * 一律排除特殊關係交易；中位數／均價僅計入 unit_price_per_ping > 0 的紀錄。
     *
     * @param array $args {
     *     @type string $city 縣市 key。
     *     @type string $district 行政區。
     *     @type string $zone 正規化分區。
     *     @type string $building_type 建物型態。
     *     @type int    $age_min 屋齡下限（含）。
     *     @type int    $age_max 屋齡上限（含）。
     * }
     * @return array{ count: int, median: float, average: float, min: float, max: float, avg_age: float }
     */
    public function get_price_stats($args = array()) {
        global $wpdb;

        list($where, $values) = $this->build_where($args);

        $sql = "SELECT unit_price_per_ping, building_age_years
                FROM {$this->table_name}
                WHERE " . implode(' AND ', $where) . '
                  AND unit_price_per_ping > 0';

        $rows = empty($values)
            ? $wpdb->get_results($sql)
            : $wpdb->get_results($wpdb->prepare($sql, $values));

        return $this->summarize_rows($rows);
    }

    /**
     * 一次查詢取得「老屋現況」與「新成屋」兩組統計摘要。
     *
     * $args 只帶縣市／行政區／分區／建物型態等基本篩選（不含 age_min／
     * age_max），一次撈出所有符合基本條件的紀錄後，於 PHP 端依屋齡門檻
     * 分成兩組再各自統計，避免同一批條件對資料庫下兩次幾乎相同的查詢。
     *
     * @param array $args 基本篩選條件（同 get_price_stats()，但不含 age_min／age_max）。
     * @param int   $old_threshold 老屋門檻（年，含）。
     * @param int   $new_threshold 新成屋門檻（年，含）。
     * @return array{ old: array, new: array }
     */
    public function get_price_stats_pair($args, $old_threshold, $new_threshold) {
        global $wpdb;

        list($where, $values) = $this->build_where($args);

        $sql = "SELECT unit_price_per_ping, building_age_years
                FROM {$this->table_name}
                WHERE " . implode(' AND ', $where) . '
                  AND unit_price_per_ping > 0';

        $rows = empty($values)
            ? $wpdb->get_results($sql)
            : $wpdb->get_results($wpdb->prepare($sql, $values));

        $old_threshold = absint($old_threshold);
        $new_threshold = absint($new_threshold);

        $old_rows = array();
        $new_rows = array();

        foreach ((array) $rows as $row) {
            $age = (int) $row->building_age_years;

            if ($age >= $old_threshold) {
                $old_rows[] = $row;
            }

            if ($age <= $new_threshold) {
                $new_rows[] = $row;
            }
        }

        return array(
            'old' => $this->summarize_rows($old_rows),
            'new' => $this->summarize_rows($new_rows),
        );
    }

    /**
     * 從一組紀錄計算統計摘要。
     *
     * @param array $rows $wpdb->get_results() 回傳的紀錄陣列。
     * @return array
     */
    private function summarize_rows($rows) {
        if (!is_array($rows) || empty($rows)) {
            return array(
                'count'      => 0,
                'median'     => 0.0,
                'average'    => 0.0,
                'min'        => 0.0,
                'max'        => 0.0,
                'range_low'  => 0.0,
                'range_high' => 0.0,
                'avg_age'    => 0.0,
            );
        }

        $prices = array();
        $ages   = array();

        foreach ($rows as $row) {
            $prices[] = (float) $row->unit_price_per_ping;
            $ages[]   = (int) $row->building_age_years;
        }

        sort($prices);
        $count = count($prices);

        return array(
            'count'      => $count,
            'median'     => $this->percentile($prices, 0.5),
            'average'    => round(array_sum($prices) / $count, 2),
            'min'        => $prices[0],
            'max'        => $prices[$count - 1],
            /*
             * 「區間」對外顯示用四分位距（25%～75%），而非 min／max。
             * min／max 對極端值（例如個別瑕疵屋或特殊裝潢戶）非常敏感，
             * 樣本一多，區間動輒橫跨好幾倍，容易讓使用者誤以為資料失真；
             * 四分位距代表「扣除最極端的前後各 25% 之後，中間一半案例
             * 落在的範圍」，更貼近一般認知的「行情區間」。
             */
            'range_low'  => $this->percentile($prices, 0.25),
            'range_high' => $this->percentile($prices, 0.75),
            'avg_age'    => $count > 0 ? round(array_sum($ages) / $count, 1) : 0.0,
        );
    }

    /**
     * 計算指定百分位數（陣列須已排序），採線性內插法（與常見統計軟體
     * 的預設方法一致）。
     *
     * @param array $sorted_values 已由小到大排序的數值陣列。
     * @param float $p 百分位（0～1 之間，例如 0.5 為中位數、0.25 為第一四分位）。
     * @return float
     */
    private function percentile($sorted_values, $p) {
        $count = count($sorted_values);

        if (0 === $count) {
            return 0.0;
        }

        if (1 === $count) {
            return round($sorted_values[0], 2);
        }

        $index = $p * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($upper >= $count) {
            return round($sorted_values[$count - 1], 2);
        }

        if ($lower === $upper) {
            return round($sorted_values[$lower], 2);
        }

        $weight = $index - $lower;
        $value  = $sorted_values[$lower] + $weight * ($sorted_values[$upper] - $sorted_values[$lower]);

        return round($value, 2);
    }

    /**
     * 取得指定縣市的行政區清單（去重，依名稱排序）。
     *
     * @param string $city 縣市 key。
     * @return array
     */
    public function get_districts($city) {
        global $wpdb;

        $city = (string) $city;

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT district FROM {$this->table_name}
                 WHERE city = %s AND district != ''
                 ORDER BY district ASC",
                $city
            )
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * 取得指定縣市（可選行政區）的分區清單。
     *
     * @param string $city 縣市 key。
     * @param string $district 行政區（留空＝不限）。
     * @return array
     */
    public function get_zones($city, $district = '') {
        return $this->get_distinct_column('zone', $city, $district);
    }

    /**
     * 取得指定縣市（可選行政區）的建物型態清單。
     *
     * @param string $city 縣市 key。
     * @param string $district 行政區（留空＝不限）。
     * @return array
     */
    public function get_building_types($city, $district = '') {
        return $this->get_distinct_column('building_type', $city, $district);
    }

    /**
     * 取得指定縣市（可選行政區）某欄位的去重清單，供 get_zones()／
     * get_building_types() 共用（兩者除欄位名稱外查詢邏輯完全相同）。
     *
     * @param string $column 欄位名稱（僅限本類別內部已知的安全欄位名）。
     * @param string $city 縣市 key。
     * @param string $district 行政區（留空＝不限）。
     * @return array
     */
    private function get_distinct_column($column, $city, $district = '') {
        global $wpdb;

        $where  = array('city = %s', "{$column} != ''");
        $values = array((string) $city);

        if ('' !== $district) {
            $where[]  = 'district = %s';
            $values[] = (string) $district;
        }

        $sql = "SELECT DISTINCT {$column} FROM {$this->table_name} WHERE " . implode(' AND ', $where) . " ORDER BY {$column} ASC";

        $rows = $wpdb->get_col($wpdb->prepare($sql, $values));

        return is_array($rows) ? $rows : array();
    }

    /**
     * 取得樣本數健檢資料（依縣市＋行政區＋屋齡桶分組計數）。
     *
     * 供後台一覽哪些組合樣本不足。
     *
     * @param string $city 縣市 key（留空＝全部）。
     * @param int    $old_threshold 老屋門檻（年）。
     * @param int    $new_threshold 新成屋門檻（年）。
     * @return array
     */
    public function get_sample_health($city, $old_threshold, $new_threshold) {
        global $wpdb;

        $old_threshold = absint($old_threshold);
        $new_threshold = absint($new_threshold);

        $where  = $this->exclusion_where();
        $values = array();

        if ('' !== (string) $city) {
            $where[]  = 'city = %s';
            $values[] = (string) $city;
        }

        $sql = "SELECT
                    district,
                    SUM(CASE WHEN building_age_years >= %d THEN 1 ELSE 0 END) AS old_count,
                    SUM(CASE WHEN building_age_years <= %d THEN 1 ELSE 0 END) AS new_count,
                    COUNT(*) AS total_count
                FROM {$this->table_name}
                WHERE " . implode(' AND ', $where) . '
                GROUP BY district
                ORDER BY district ASC';

        $prepare_values = array_merge(array($old_threshold, $new_threshold), $values);

        $rows = $wpdb->get_results($wpdb->prepare($sql, $prepare_values));

        return is_array($rows) ? $rows : array();
    }

    /**
     * 取得最後匯入時間。
     *
     * @return string|null MySQL datetime 字串，無資料時回傳 null。
     */
    public function get_last_imported_at() {
        global $wpdb;

        $value = $wpdb->get_var("SELECT MAX(created_at) FROM {$this->table_name}");

        return $value ? $value : null;
    }

    /**
     * 取得總筆數。
     *
     * @return int
     */
    public function count_all() {
        global $wpdb;

        return absint($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"));
    }

    /**
     * 特殊關係交易排除條件。
     *
     * 統計相關查詢（build_where() 與 get_sample_health()）都必須套用這條
     * 排除條件，集中在同一處避免兩處各自維護一份、日後規則異動時漏改。
     *
     * @return array
     */
    private function exclusion_where() {
        return array('is_special_relationship = 0');
    }

    /**
     * 建立查詢 WHERE 條件。
     *
     * @param array $args 查詢參數。
     * @return array{0: array, 1: array} array($where_clauses, $values)
     */
    private function build_where($args) {
        $where  = $this->exclusion_where();
        $values = array();

        if (!empty($args['city'])) {
            $where[]  = 'city = %s';
            $values[] = (string) $args['city'];
        }

        if (!empty($args['district'])) {
            $where[]  = 'district = %s';
            $values[] = (string) $args['district'];
        }

        if (!empty($args['zone'])) {
            $where[]  = 'zone = %s';
            $values[] = (string) $args['zone'];
        }

        if (!empty($args['building_type'])) {
            $where[]  = 'building_type = %s';
            $values[] = (string) $args['building_type'];
        }

        if (isset($args['age_min']) && is_numeric($args['age_min'])) {
            $where[]  = 'building_age_years >= %d';
            $values[] = absint($args['age_min']);
        }

        if (isset($args['age_max']) && is_numeric($args['age_max'])) {
            $where[]  = 'building_age_years <= %d';
            $values[] = absint($args['age_max']);
        }

        return array($where, $values);
    }

    /**
     * fallback 資料表名稱（schema class 不存在時使用）。
     *
     * @return string
     */
    private function fallback_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'ur_ai_market_prices';
    }
}
