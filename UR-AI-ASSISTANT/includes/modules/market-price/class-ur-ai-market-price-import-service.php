<?php
/**
 * UR AI Assistant Market Price Import Service
 *
 * 解析內政部不動產交易實價查詢服務開放資料的 CSV 匯出檔，清洗後匯入
 * 行情參考資料表。
 *
 * 設計原則：
 * - 只接受 CSV（UTF-8）。政府原始檔為舊版二進位 .xls，PHP 沒有內建解析
 *   能力；要求管理者先用 Excel／Numbers／Google 試算表另存成 CSV 再上傳，
 *   避免外掛需要額外綁入大型的 XLS 解析函式庫。
 * - 一坪 = 3.305785 平方公尺，換算單價時使用此係數。
 * - 車位價格會從總價中扣除，避免「單價」被車位價格污染。
 * - 特殊關係交易（親友、員工、共有人等）仍會存入資料庫（供健檢與稽核），
 *   但會標記 is_special_relationship，統計查詢時一律排除。
 * - 以政府資料原有的「編號」欄位（source_record_id）防止重複匯入，
 *   可放心重複上傳同一批或有重疊的檔案。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Import_Service
 */
class UR_AI_Market_Price_Import_Service {

    /**
     * 一坪等於多少平方公尺。
     *
     * @var float
     */
    const SQM_PER_PING = 3.305785;

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
     * 匯入 CSV 檔案內容。
     *
     * @param string $file_path 已上傳檔案的暫存路徑。
     * @param string $city      縣市 key（taipei / new_taipei），由管理者於表單指定。
     * @return array{ created: int, duplicate: int, skipped: int, total: int, warnings: array }
     */
    public function import_from_csv($file_path, $city) {
        $result = array(
            'created'   => 0,
            'duplicate' => 0,
            'skipped'   => 0,
            'total'     => 0,
            'warnings'  => array(),
        );

        if (!$this->repository instanceof UR_AI_Market_Price_Repository) {
            $result['warnings'][] = __('資料庫服務尚未正確載入。', 'ur-ai-assistant');
            return $result;
        }

        $city = sanitize_key($city);

        if (!in_array($city, array_keys($this->get_supported_cities()), true)) {
            $result['warnings'][] = __('未指定有效的縣市。', 'ur-ai-assistant');
            return $result;
        }

        if (!file_exists($file_path) || !is_readable($file_path)) {
            $result['warnings'][] = __('找不到上傳的檔案或無法讀取。', 'ur-ai-assistant');
            return $result;
        }

        $rows = $this->read_csv($file_path);

        if (empty($rows)) {
            $result['warnings'][] = __('檔案內容為空，或無法解析為 CSV。', 'ur-ai-assistant');
            return $result;
        }

        $header_map = $this->map_header($rows[0]);

        if (!$this->has_required_columns($header_map)) {
            $result['warnings'][] = __('CSV 欄位不符預期格式，請確認是內政部實價登錄開放資料匯出的檔案。', 'ur-ai-assistant');
            return $result;
        }

        $known_districts  = class_exists('UR_AI_Schema_Market_Prices')
            ? UR_AI_Schema_Market_Prices::get_known_districts($city)
            : array();
        $mismatched_city  = false;
        $import_batch     = gmdate('Ymd\THis');
        $known_ids        = $this->repository->get_existing_source_record_ids($city);

        for ($i = 1, $len = count($rows); $i < $len; $i++) {
            $row = $this->extract_row($rows[$i], $header_map);

            if (null === $row) {
                // 非資料列（例如英文欄名列、空白列），略過但不計入 skipped 統計干擾使用者判讀。
                continue;
            }

            $result['total']++;

            if (!empty($known_districts) && '' !== $row['district'] && !in_array($row['district'], $known_districts, true)) {
                $mismatched_city = true;
            }

            $prepared = $this->prepare_record($row, $city, $import_batch);

            if (null === $prepared) {
                $result['skipped']++;
                continue;
            }

            $status = $this->repository->insert($prepared, $known_ids);

            if ('inserted' === $status) {
                $result['created']++;
            } elseif ('duplicate' === $status) {
                $result['duplicate']++;
            } else {
                $result['skipped']++;
            }
        }

        if ($mismatched_city) {
            $result['warnings'][] = __('偵測到部分資料的行政區名稱不屬於所選縣市，請確認是否上傳到正確的縣市，資料仍已匯入但建議人工複查。', 'ur-ai-assistant');
        }

        return $result;
    }

    /**
     * 支援的縣市。
     *
     * @return array
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
     * 讀取 CSV 為二維陣列（自動處理 UTF-8 BOM）。
     *
     * @param string $file_path 檔案路徑。
     * @return array
     */
    private function read_csv($file_path) {
        $rows   = array();
        $handle = fopen($file_path, 'r');

        if (false === $handle) {
            return $rows;
        }

        $first = true;

        while (false !== ($data = fgetcsv($handle))) {
            if ($first) {
                // 移除 UTF-8 BOM（若有）。
                if (isset($data[0])) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
                }
                $first = false;
            }

            // 跳過完全空白的列。
            if (1 === count($data) && (null === $data[0] || '' === trim((string) $data[0]))) {
                continue;
            }

            $rows[] = $data;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * 將表頭列轉為「欄位名稱 => 陣列索引」對照表。
     *
     * @param array $header_row CSV 第一列。
     * @return array
     */
    private function map_header($header_row) {
        $map = array();

        foreach ($header_row as $index => $name) {
            $name = trim((string) $name);

            if ('' !== $name) {
                $map[$name] = $index;
            }
        }

        return $map;
    }

    /**
     * 檢查是否包含匯入所需的必要欄位。
     *
     * @param array $header_map 欄位對照表。
     * @return bool
     */
    private function has_required_columns($header_map) {
        $required = array('鄉鎮市區', '交易標的', '交易年月日', '總價元', '建物移轉總面積平方公尺', '編號');

        foreach ($required as $column) {
            if (!isset($header_map[$column])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 依欄位對照表取出單一列的原始欄位值。
     *
     * 只保留「房地(土地+建物)」與「房地(土地+建物)+車位」兩種交易標的，
     * 純土地／純車位交易不列入「成屋行情」統計範圍。
     *
     * @param array $raw_row CSV 單一列原始資料。
     * @param array $header_map 欄位對照表。
     * @return array|null 解析後的關聯陣列；非有效資料列回傳 null。
     */
    private function extract_row($raw_row, $header_map) {
        $get = function ($column) use ($raw_row, $header_map) {
            if (!isset($header_map[$column])) {
                return '';
            }

            $index = $header_map[$column];

            return isset($raw_row[$index]) ? trim((string) $raw_row[$index]) : '';
        };

        $transaction_date_raw = $get('交易年月日');

        // 民國年格式為 6～7 碼數字；非此格式視為非資料列（例如英文欄名列）。
        if (!preg_match('/^\d{6,7}$/', $transaction_date_raw)) {
            return null;
        }

        $subject = $get('交易標的');

        if (false === mb_stripos($subject, '房地', 0, 'UTF-8')) {
            return null;
        }

        return array(
            'district'          => $get('鄉鎮市區'),
            'subject'           => $subject,
            'address_raw'       => $get('土地位置建物門牌'),
            'zone_raw'          => $get('都市土地使用分區'),
            'transaction_date'  => $transaction_date_raw,
            'building_type'     => $get('建物型態'),
            'built_date_raw'    => $get('建築完成年月'),
            'building_area_sqm' => $get('建物移轉總面積平方公尺'),
            'total_price'       => $get('總價元'),
            'parking_price'     => $get('車位總價元'),
            'remark'            => $get('備註'),
            'source_record_id'  => $get('編號'),
        );
    }

    /**
     * 把解析出的原始欄位轉換成可寫入資料庫的紀錄。
     *
     * @param array  $row 解析後的原始欄位。
     * @param string $city 縣市 key。
     * @param string $import_batch 本次匯入批次代碼。
     * @return array|null 無法組成有效紀錄時回傳 null（例如缺少必要欄位）。
     */
    private function prepare_record($row, $city, $import_batch) {
        $source_record_id = sanitize_text_field($row['source_record_id']);

        if ('' === $source_record_id) {
            return null;
        }

        $district = sanitize_text_field($row['district']);

        $transaction_date = $this->roc_date_to_ymd($row['transaction_date']);

        if (null === $transaction_date) {
            return null;
        }

        $transaction_year = (int) substr($transaction_date, 0, 4);

        $built_year = $this->roc_year($row['built_date_raw']);

        $building_age_years = 0;

        if ($built_year > 0 && $transaction_year > $built_year) {
            $building_age_years = $transaction_year - $built_year;
        }

        $building_area_sqm = $this->parse_numeric($row['building_area_sqm']);
        $total_price       = absint($this->parse_numeric($row['total_price']));
        $parking_price     = absint($this->parse_numeric($row['parking_price']));

        $house_price = $total_price - $parking_price;

        $unit_price_per_ping = 0.0;

        if ($building_area_sqm > 0 && $house_price > 0) {
            $ping = $building_area_sqm / self::SQM_PER_PING;
            $unit_price_per_ping = $ping > 0 ? round($house_price / $ping, 2) : 0.0;
        }

        $zone_raw = sanitize_text_field($row['zone_raw']);
        $zone     = class_exists('UR_AI_Market_Price_Zone_Normalizer')
            ? UR_AI_Market_Price_Zone_Normalizer::normalize($zone_raw)
            : '其他';

        $is_special_relationship = false !== mb_stripos((string) $row['remark'], '特殊關係', 0, 'UTF-8');

        return array(
            'city'                    => $city,
            'district'                => $district,
            'zone'                    => $zone,
            'zone_raw'                => $zone_raw,
            'building_type'           => sanitize_text_field($row['building_type']),
            'address_raw'             => sanitize_text_field($row['address_raw']),
            'transaction_date'        => $transaction_date,
            'built_year'              => $built_year,
            'building_age_years'      => $building_age_years,
            'building_area_sqm'       => $building_area_sqm,
            'total_price'             => $total_price,
            'parking_price'           => $parking_price,
            'unit_price_per_ping'     => $unit_price_per_ping,
            'is_special_relationship' => $is_special_relationship,
            'source_record_id'        => $source_record_id,
            'import_batch'            => $import_batch,
        );
    }

    /**
     * 將 CSV 欄位值解析為數值，容忍千分位逗號（例如 Excel 另存 CSV 時
     * 保留的 "15,000,000" 格式），避免被 is_numeric() 直接判為非數值
     * 而靜默歸零。
     *
     * @param mixed $value 原始欄位值。
     * @return float 無法解析時回傳 0.0。
     */
    private function parse_numeric($value) {
        $value = str_replace(',', '', trim((string) $value));

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * 民國年月日字串（6～7 碼）轉西元 YYYY-MM-DD。
     *
     * @param string $roc_date 民國年月日，例如 1150605 或 990605。
     * @return string|null 格式錯誤時回傳 null。
     */
    private function roc_date_to_ymd($roc_date) {
        $roc_date = (string) $roc_date;
        $len      = strlen($roc_date);

        if (7 === $len) {
            $roc_year = (int) substr($roc_date, 0, 3);
            $month    = substr($roc_date, 3, 2);
            $day      = substr($roc_date, 5, 2);
        } elseif (6 === $len) {
            $roc_year = (int) substr($roc_date, 0, 2);
            $month    = substr($roc_date, 2, 2);
            $day      = substr($roc_date, 4, 2);
        } else {
            return null;
        }

        $year = 1911 + $roc_year;

        if (!checkdate((int) $month, (int) $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, (int) $month, (int) $day);
    }

    /**
     * 民國年月字串（通常 5～7 碼，格式如 0670301）轉西元年。
     *
     * @param string $roc_date 建築完成年月。
     * @return int 無法解析時回傳 0。
     */
    private function roc_year($roc_date) {
        $roc_date = trim((string) $roc_date);

        if ('' === $roc_date || !preg_match('/^\d{5,7}$/', $roc_date)) {
            return 0;
        }

        // 月＋日各佔 2 碼，其餘為民國年（長度可能是 3 或 2 碼）。
        $roc_year = (int) substr($roc_date, 0, strlen($roc_date) - 4);

        if ($roc_year <= 0) {
            return 0;
        }

        return 1911 + $roc_year;
    }
}
