<?php
/**
 * UR AI Assistant Market Price Remote Fetch Service
 *
 * 從內政部不動產交易實價查詢服務開放資料端點下載「本期」或指定季別的
 * zip 檔，解壓出雙北主檔後直接餵給既有的 CSV 匯入服務，取代「手動下載
 * →另存 CSV→上傳」的人工步驟（仍由後台管理者按鈕觸發，屬半自動）。
 *
 * 設計原則：
 * - 政府端點回傳的 zip 內含全台各縣市檔案，本外掛僅支援雙北，因此只
 *   解壓 a_lvr_land_a.csv（台北市）／f_lvr_land_a.csv（新北市）兩個檔案，
 *   避免不必要的記憶體與運算負擔。
 * - 解壓出的原始 CSV 直接交給 UR_AI_Market_Price_Import_Service::import_from_csv()，
 *   不另外用 PHP 重寫一套清洗邏輯：該服務本身已能正確跳過政府 CSV 第二列
 *   的英文欄名列，也已具備特殊關係排除、去重、型別轉換等邏輯。
 * - 是否已抓取過某季別只作「提示」用途，不做強制阻擋：政府資料在同一
 *   季別內會隨遲繳／更正登記持續增補，重新抓取同一季別仍有意義；
 *   實際的重複資料防護由 source_record_id 唯一索引在資料庫層把關。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Remote_Fetch_Service
 */
class UR_AI_Market_Price_Remote_Fetch_Service {

    /**
     * 內政部開放資料網域。
     *
     * @var string
     */
    const BASE_URL = 'https://plvr.land.moi.gov.tw';

    /**
     * 本期（每月 1/11/21 更新的最新一批靜態資料）下載路徑。
     *
     * @var string
     */
    const CURRENT_PATH = '/Download?type=zip&fileName=lvr_landcsv.zip';

    /**
     * 指定季別下載路徑樣板。
     *
     * @var string
     */
    const SEASON_PATH = '/DownloadSeason?season=%s&type=zip&fileName=lvr_landcsv.zip';

    /**
     * 抓取紀錄的 option key。
     *
     * @var string
     */
    const LOG_OPTION = 'ur_ai_market_price_fetch_log';

    /**
     * 下載請求使用的 User-Agent。
     *
     * @var string
     */
    const USER_AGENT = 'Mozilla/5.0 (compatible; UR-AI-Assistant/1.0; +https://www.ur-promoter.com/)';

    /**
     * 縣市 key 對應政府資料檔名代碼（{code}_lvr_land_a.csv）。
     *
     * @var array
     */
    const CITY_FILE_CODE = array(
        'taipei'     => 'a',
        'new_taipei' => 'f',
    );

    /**
     * 匯入服務。
     *
     * @var UR_AI_Market_Price_Import_Service|null
     */
    private $import_service;

    /**
     * 建構子。
     *
     * @param UR_AI_Market_Price_Import_Service|null $import_service 匯入服務。
     */
    public function __construct($import_service = null) {
        $this->import_service = $import_service instanceof UR_AI_Market_Price_Import_Service
            ? $import_service
            : (class_exists('UR_AI_Market_Price_Import_Service') ? new UR_AI_Market_Price_Import_Service() : null);
    }

    /**
     * 產生可供後台下拉選單選擇的季別清單（含「本期」）。
     *
     * @param int $count 往前回溯的季數（不含本期）。
     * @return array season_tag => label
     */
    public function get_available_seasons($count = 12) {
        $options = array(
            'current' => __('本期（政府每月 1/11/21 更新的最新一批資料）', 'ur-ai-assistant'),
        );

        $roc_year = (int) current_time('Y') - 1911;
        $quarter  = (int) ceil((int) current_time('n') / 3);

        for ($i = 0; $i < $count; $i++) {
            $q = $quarter - $i;
            $y = $roc_year;

            while ($q <= 0) {
                $q += 4;
                $y--;
            }

            if ($y <= 0) {
                break;
            }

            $tag = sprintf('%dS%d', $y, $q);

            $options[$tag] = sprintf(
                /* translators: 1: 民國年 2: 季別（1～4） 3: 季別代碼 */
                __('民國 %1$d 年第 %2$d 季（%3$s）', 'ur-ai-assistant'),
                $y,
                $q,
                $tag
            );
        }

        return $options;
    }

    /**
     * 驗證季別代碼格式（例如 113S4），或字面值 'current'。
     *
     * @param string $season 季別代碼。
     * @return bool
     */
    public function is_valid_season($season) {
        return 'current' === $season || (bool) preg_match('/^\d{2,3}S[1-4]$/', (string) $season);
    }

    /**
     * 取得完整抓取紀錄（供後台顯示「是否已抓取過」）。
     *
     * @return array season_tag => array{ fetched_at: string, created: int, duplicate: int, skipped: int, total: int, cities: array }
     */
    public function get_fetch_log() {
        $log = get_option(self::LOG_OPTION, array());

        return is_array($log) ? $log : array();
    }

    /**
     * 取得單一季別的抓取紀錄。
     *
     * @param string $season 季別代碼。
     * @return array|null
     */
    public function get_log_entry($season) {
        $log = $this->get_fetch_log();

        return isset($log[$season]) ? $log[$season] : null;
    }

    /**
     * 下載＋解壓＋匯入指定季別（或本期）的雙北住宅買賣資料。
     *
     * @param string $season 季別代碼（如 113S4）或 'current'。
     * @return array{ success: bool, warnings: array, created: int, updated: int, duplicate: int, skipped: int, total: int, cities: array }
     */
    public function fetch_and_import($season) {
        $result = array(
            'success'   => false,
            'warnings'  => array(),
            'created'   => 0,
            'updated'   => 0,
            'duplicate' => 0,
            'skipped'   => 0,
            'total'     => 0,
            'cities'    => array(),
        );

        if (!$this->import_service instanceof UR_AI_Market_Price_Import_Service) {
            $result['warnings'][] = __('匯入服務尚未正確載入。', 'ur-ai-assistant');
            return $result;
        }

        if (!$this->is_valid_season($season)) {
            $result['warnings'][] = __('季別格式不正確。', 'ur-ai-assistant');
            return $result;
        }

        if (!class_exists('ZipArchive')) {
            $result['warnings'][] = __('伺服器 PHP 環境未啟用 zip 擴充功能（ZipArchive），無法自動解壓政府開放資料，請改用下方手動上傳 CSV。', 'ur-ai-assistant');
            return $result;
        }

        if (function_exists('set_time_limit')) {
            // 下載＋解壓＋匯入雙北資料可能超過預設 30 秒限制，盡量延長（部分主機環境會忽略此設定）。
            @set_time_limit(150);
        }

        $url = 'current' === $season
            ? self::BASE_URL . self::CURRENT_PATH
            : self::BASE_URL . sprintf(self::SEASON_PATH, rawurlencode($season));

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 120,
                'headers' => array('User-Agent' => self::USER_AGENT),
            )
        );

        if (is_wp_error($response)) {
            $result['warnings'][] = sprintf(
                /* translators: %s: 錯誤訊息 */
                __('下載失敗：%s', 'ur-ai-assistant'),
                $response->get_error_message()
            );
            return $result;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if (200 !== $code) {
            $result['warnings'][] = sprintf(
                /* translators: %d: HTTP 狀態碼 */
                __('下載失敗：內政部開放資料端點回應狀態碼 %d。', 'ur-ai-assistant'),
                $code
            );
            return $result;
        }

        $body = wp_remote_retrieve_body($response);

        if ('' === $body || 'PK' !== substr($body, 0, 2)) {
            $result['warnings'][] = __('下載內容不是有效的 zip 檔，該季別可能尚未開放下載，或政府端點格式已變動。', 'ur-ai-assistant');
            return $result;
        }

        $tmp_zip = wp_tempnam('ur-ai-mp-fetch');

        if (false === $tmp_zip || false === file_put_contents($tmp_zip, $body)) {
            $result['warnings'][] = __('無法寫入暫存檔案，請確認伺服器暫存目錄可寫入。', 'ur-ai-assistant');
            return $result;
        }

        $zip = new ZipArchive();

        if (true !== $zip->open($tmp_zip)) {
            @unlink($tmp_zip);
            $result['warnings'][] = __('無法開啟下載的 zip 檔案。', 'ur-ai-assistant');
            return $result;
        }

        $city_entries = $this->find_city_entries($zip);

        foreach (self::CITY_FILE_CODE as $city_key => $file_code) {
            if (!isset($city_entries[$file_code])) {
                $result['warnings'][] = sprintf(
                    /* translators: %s: 縣市名稱 */
                    __('zip 內找不到「%s」的主檔，已略過。', 'ur-ai-assistant'),
                    $this->city_label($city_key)
                );
                continue;
            }

            $csv_content = $zip->getFromName($city_entries[$file_code]);

            if (false === $csv_content) {
                $result['warnings'][] = sprintf(
                    /* translators: %s: 縣市名稱 */
                    __('無法讀取「%s」的主檔內容，已略過。', 'ur-ai-assistant'),
                    $this->city_label($city_key)
                );
                continue;
            }

            $tmp_csv = wp_tempnam('ur-ai-mp-csv');

            if (false === $tmp_csv || false === file_put_contents($tmp_csv, $csv_content)) {
                $result['warnings'][] = sprintf(
                    /* translators: %s: 縣市名稱 */
                    __('無法寫入「%s」的暫存 CSV，已略過。', 'ur-ai-assistant'),
                    $this->city_label($city_key)
                );
                continue;
            }

            $imported = $this->import_service->import_from_csv($tmp_csv, $city_key);

            @unlink($tmp_csv);

            $result['created']   += (int) $imported['created'];
            $result['updated']   += (int) $imported['updated'];
            $result['duplicate'] += (int) $imported['duplicate'];
            $result['skipped']   += (int) $imported['skipped'];
            $result['total']     += (int) $imported['total'];

            $result['cities'][$city_key] = array(
                'created'   => (int) $imported['created'],
                'updated'   => (int) $imported['updated'],
                'duplicate' => (int) $imported['duplicate'],
                'skipped'   => (int) $imported['skipped'],
                'total'     => (int) $imported['total'],
            );

            if (!empty($imported['warnings'])) {
                foreach ($imported['warnings'] as $warning) {
                    $result['warnings'][] = sprintf('%s：%s', $this->city_label($city_key), $warning);
                }
            }
        }

        $zip->close();
        @unlink($tmp_zip);

        $result['success'] = !empty($result['cities']);

        $this->record_fetch($season, $result);

        return $result;
    }

    /**
     * 在 zip 內尋找各縣市主檔（{code}_lvr_land_a.csv）的完整 entry 名稱。
     *
     * @param ZipArchive $zip Zip。
     * @return array file_code => entry_name
     */
    private function find_city_entries($zip) {
        $entries = array();
        $wanted   = array_flip(self::CITY_FILE_CODE);

        for ($i = 0, $count = $zip->numFiles; $i < $count; $i++) {
            $name = $zip->getNameIndex($i);

            if (false === $name) {
                continue;
            }

            $basename = basename($name);

            if (preg_match('/^([a-z])_lvr_land_a\.csv$/i', $basename, $matches)) {
                $code = strtolower($matches[1]);

                if (isset($wanted[$code])) {
                    $entries[$code] = $name;
                }
            }
        }

        return $entries;
    }

    /**
     * 記錄一次抓取結果，供後台顯示「是否已抓取過」。
     *
     * @param string $season 季別代碼。
     * @param array  $result 抓取結果。
     * @return void
     */
    private function record_fetch($season, $result) {
        $log = $this->get_fetch_log();

        $log[$season] = array(
            'fetched_at' => current_time('mysql'),
            'created'    => (int) $result['created'],
            'updated'    => (int) $result['updated'],
            'duplicate'  => (int) $result['duplicate'],
            'skipped'    => (int) $result['skipped'],
            'total'      => (int) $result['total'],
            'cities'     => $result['cities'],
        );

        update_option(self::LOG_OPTION, $log);
    }

    /**
     * 縣市顯示名稱。
     *
     * @param string $city_key 縣市 key。
     * @return string
     */
    private function city_label($city_key) {
        $cities = class_exists('UR_AI_Schema_Market_Prices')
            ? UR_AI_Schema_Market_Prices::get_supported_cities()
            : array();

        return isset($cities[$city_key]) ? $cities[$city_key] : $city_key;
    }
}
