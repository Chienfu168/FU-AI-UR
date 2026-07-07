<?php
/**
 * UR AI Assistant Market Price Service
 *
 * 行情參考業務邏輯層。
 *
 * 這裡刻意維持一組穩定、有清楚文件註解的公開方法，方便未來其他模組
 * （例如計算機模組）需要參考行情資料時，直接 new 一個本類別呼叫，
 * 不需要額外設計跨模組的 hook 或 REST API。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Service
 */
class UR_AI_Market_Price_Service {

    /**
     * 查詢相關參考資料快取 key 前綴。
     *
     * @var string
     */
    const CACHE_PREFIX = 'ur_ai_market_price_';

    /**
     * 快取存活秒數。
     *
     * @var int
     */
    const CACHE_TTL = 1200;

    /**
     * 資料過舊提醒門檻（天）。
     *
     * @var int
     */
    const STALE_DAYS_THRESHOLD = 90;

    /**
     * Repository。
     *
     * @var UR_AI_Market_Price_Repository|null
     */
    private $repository;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->repository = class_exists('UR_AI_Market_Price_Repository')
            ? new UR_AI_Market_Price_Repository()
            : null;
    }

    /**
     * 取得支援的縣市清單（第一階段僅雙北）。
     *
     * @return array city key => label
     */
    public function get_supported_cities() {
        return class_exists('UR_AI_Schema_Market_Prices')
            ? UR_AI_Schema_Market_Prices::get_supported_cities()
            : array(
                'taipei'     => '台北市',
                'new_taipei' => '新北市',
            );
    }

    /**
     * 取得指定縣市的行政區清單。
     *
     * @param string $city 縣市 key。
     * @return array
     */
    public function get_districts($city) {
        if (!$this->repository instanceof UR_AI_Market_Price_Repository) {
            return array();
        }

        $city = sanitize_key($city);

        return $this->cached('districts_' . $city, function () use ($city) {
            return $this->repository->get_districts($city);
        });
    }

    /**
     * 取得指定縣市（可選行政區）的分區清單。
     *
     * @param string $city 縣市 key。
     * @param string $district 行政區（留空＝不限）。
     * @return array
     */
    public function get_zones($city, $district = '') {
        if (!$this->repository instanceof UR_AI_Market_Price_Repository) {
            return array();
        }

        $city     = sanitize_key($city);
        $district = sanitize_text_field($district);

        return $this->cached('zones_' . $city . '_' . $district, function () use ($city, $district) {
            return $this->repository->get_zones($city, $district);
        });
    }

    /**
     * 取得指定縣市（可選行政區）的建物型態清單。
     *
     * @param string $city 縣市 key。
     * @param string $district 行政區（留空＝不限）。
     * @return array
     */
    public function get_building_types($city, $district = '') {
        if (!$this->repository instanceof UR_AI_Market_Price_Repository) {
            return array();
        }

        $city     = sanitize_key($city);
        $district = sanitize_text_field($district);

        return $this->cached('types_' . $city . '_' . $district, function () use ($city, $district) {
            return $this->repository->get_building_types($city, $district);
        });
    }

    /**
     * 取得「老屋現況」與「新成屋」的行情比較。
     *
     * 供前台查詢與（未來）其他模組跨模組參考使用的主要方法。
     *
     * 樣本數低於後台設定的最低門檻時，該情境的價格統計會回傳 null，
     * 但仍會回傳實際樣本數，讓呼叫端可以清楚呈現「樣本不足」而非
     * 顯示一個不具統計意義的數字。
     *
     * @param array $args {
     *     @type string $city          縣市 key（必填）。
     *     @type string $district      行政區（必填）。
     *     @type string $zone          正規化分區（選填，留空＝不限）。
     *     @type string $building_type 建物型態（選填，留空＝不限）。
     * }
     * @return array{
     *     old: array{ count: int, median: float|null, average: float|null, min: float|null, max: float|null, avg_age: float, sufficient: bool },
     *     new: array{ count: int, median: float|null, average: float|null, min: float|null, max: float|null, avg_age: float, sufficient: bool },
     *     old_age_threshold: int,
     *     new_age_threshold: int,
     *     min_sample_size: int,
     * }
     */
    public function get_comparison($args = array()) {
        $old_threshold   = $this->get_old_age_threshold();
        $new_threshold   = $this->get_new_age_threshold();
        $min_sample_size = $this->get_min_sample_size();

        $base_args = array(
            'city'          => isset($args['city']) ? sanitize_key($args['city']) : '',
            'district'      => isset($args['district']) ? sanitize_text_field($args['district']) : '',
            'zone'          => isset($args['zone']) ? sanitize_text_field($args['zone']) : '',
            'building_type' => isset($args['building_type']) ? sanitize_text_field($args['building_type']) : '',
        );

        // 「老屋」與「新成屋」共用相同的縣市／行政區／分區／建物型態基本
        // 篩選，只差屋齡門檻，因此一次查詢取出符合基本條件的紀錄後在
        // Repository 端依屋齡分桶，避免對資料庫下兩次幾乎相同的查詢。
        $pair = $this->repository instanceof UR_AI_Market_Price_Repository
            ? $this->repository->get_price_stats_pair($base_args, $old_threshold, $new_threshold)
            : array('old' => $this->empty_raw_stats(), 'new' => $this->empty_raw_stats());

        return array(
            'old'               => $this->format_stats($pair['old'], $min_sample_size),
            'new'               => $this->format_stats($pair['new'], $min_sample_size),
            'old_age_threshold' => $old_threshold,
            'new_age_threshold' => $new_threshold,
            'min_sample_size'   => $min_sample_size,
        );
    }

    /**
     * 套用最低樣本數門檻判斷，將原始統計轉為前台可直接使用的格式。
     *
     * @param array $stats 原始統計（count／median／average／min／max／avg_age）。
     * @param int   $min_sample_size 最低樣本數門檻。
     * @return array
     */
    private function format_stats($stats, $min_sample_size) {
        $sufficient = $stats['count'] >= $min_sample_size;

        return array(
            'count'      => $stats['count'],
            'median'     => $sufficient ? $stats['median'] : null,
            'average'    => $sufficient ? $stats['average'] : null,
            'min'        => $sufficient ? $stats['min'] : null,
            'max'        => $sufficient ? $stats['max'] : null,
            'avg_age'    => $stats['avg_age'],
            'sufficient' => $sufficient,
        );
    }

    /**
     * 空的原始統計結果（服務不可用時的保底回傳值）。
     *
     * @return array
     */
    private function empty_raw_stats() {
        return array(
            'count'   => 0,
            'median'  => 0.0,
            'average' => 0.0,
            'min'     => 0.0,
            'max'     => 0.0,
            'avg_age' => 0.0,
        );
    }

    /**
     * 取得樣本數健檢資料（後台用）。
     *
     * @param string $city 縣市 key（留空＝全部）。
     * @return array
     */
    public function get_sample_health($city = '') {
        if (!$this->repository instanceof UR_AI_Market_Price_Repository) {
            return array();
        }

        return $this->repository->get_sample_health(
            sanitize_key($city),
            $this->get_old_age_threshold(),
            $this->get_new_age_threshold()
        );
    }

    /**
     * 取得最後匯入時間。
     *
     * @return string|null
     */
    public function get_last_imported_at() {
        if (!$this->repository instanceof UR_AI_Market_Price_Repository) {
            return null;
        }

        return $this->repository->get_last_imported_at();
    }

    /**
     * 距離最後一次匯入的天數。
     *
     * 集中在服務層計算，避免各後台頁面各自重複同一段日期換算邏輯，
     * 未來若要調整「過舊」門檻或計算方式，只需要改這裡一處。
     *
     * @return int|null 尚無任何匯入資料時回傳 null。
     */
    public function get_stale_days() {
        $last_imported = $this->get_last_imported_at();

        if (!$last_imported) {
            return null;
        }

        return (int) floor((current_time('timestamp') - strtotime($last_imported)) / DAY_IN_SECONDS);
    }

    /**
     * 資料是否已久未更新（超過 STALE_DAYS_THRESHOLD 天）。
     *
     * @return bool
     */
    public function is_stale() {
        $days = $this->get_stale_days();

        return null !== $days && $days >= self::STALE_DAYS_THRESHOLD;
    }

    /**
     * 取得資料表總筆數。
     *
     * @return int
     */
    public function count_all() {
        if (!$this->repository instanceof UR_AI_Market_Price_Repository) {
            return 0;
        }

        return $this->repository->count_all();
    }

    /**
     * 清除本模組所有查詢快取（匯入完成後呼叫）。
     *
     * 分區／行政區／建物型態清單快取 key 組合有限但無法窮舉，這裡採用
     * 「快取 key 前綴＋清除時全表掃描 wp_options」在資料量小的情境下
     * 不划算；改為直接刪除已知會用到的固定快取 key 組合（縣市數量固定
     * 只有雙北兩組，行政區清單快取數量也有限，逐一清除即可）。
     *
     * @return void
     */
    public function clear_cache() {
        $cities = array_keys($this->get_supported_cities());

        foreach ($cities as $city) {
            delete_transient(self::CACHE_PREFIX . 'districts_' . $city);

            $districts = $this->repository instanceof UR_AI_Market_Price_Repository
                ? $this->repository->get_districts($city)
                : array();

            delete_transient(self::CACHE_PREFIX . 'zones_' . $city . '_');
            delete_transient(self::CACHE_PREFIX . 'types_' . $city . '_');

            foreach ($districts as $district) {
                delete_transient(self::CACHE_PREFIX . 'zones_' . $city . '_' . $district);
                delete_transient(self::CACHE_PREFIX . 'types_' . $city . '_' . $district);
            }
        }
    }

    /**
     * 讀取／寫入 transient 快取的小工具。
     *
     * @param string   $key 快取 key（不含前綴）。
     * @param callable $callback 快取未命中時取得資料的方法。
     * @return mixed
     */
    private function cached($key, $callback) {
        $cache_key = self::CACHE_PREFIX . $key;
        $cached    = get_transient($cache_key);

        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        $value = call_user_func($callback);

        if (is_array($value)) {
            set_transient($cache_key, $value, self::CACHE_TTL);
        }

        return $value;
    }

    /**
     * 老屋屋齡門檻。
     *
     * @return int
     */
    private function get_old_age_threshold() {
        return class_exists('UR_AI_Market_Price_Settings')
            ? UR_AI_Market_Price_Settings::get_old_age_threshold()
            : 30;
    }

    /**
     * 新成屋屋齡門檻。
     *
     * @return int
     */
    private function get_new_age_threshold() {
        return class_exists('UR_AI_Market_Price_Settings')
            ? UR_AI_Market_Price_Settings::get_new_age_threshold()
            : 5;
    }

    /**
     * 最低樣本數門檻。
     *
     * @return int
     */
    private function get_min_sample_size() {
        return class_exists('UR_AI_Market_Price_Settings')
            ? UR_AI_Market_Price_Settings::get_min_sample_size()
            : 5;
    }
}
