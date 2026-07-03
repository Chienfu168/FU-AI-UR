<?php
/**
 * UR AI Assistant Calculator Service
 *
 * 都更分回試算「計算核心」。
 *
 * 設計原則：
 * - 本類別「不依賴 WordPress」，只做純計算，方便獨立測試。
 * - 所有浮動參數（換坪倍數、容積率、獎勵、實設係數、分回比例）皆由呼叫端傳入，
 *   不在此寫死，對應規格「浮動數據後台可調」原則。
 * - 對應公式規格 v1 FINAL：軌道 A（換坪比法）與軌道 C（基地總量回推法），
 *   獎勵以 min(總和, 100%) 套基準容積 2 倍硬上限。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Calculator_Service
 */
class UR_AI_Calculator_Service {

    /**
     * 獎勵總量硬上限（佔基準容積的比例，0.0 ~ 1.0 對應 0% ~ 100%）。
     *
     * 規格：一般獎勵 + 其他獎勵（含移轉）加總後，獎勵後容積不得超過基準容積 2 倍，
     * 即「總加成」上限為 +100%。
     *
     * @var float
     */
    const BONUS_HARD_CAP = 1.0;

    /**
     * 軌道 A：換坪比法（地主主畫面）。
     *
     * 公式：地主可分回坪數 = 地主現有權狀坪數 × 換坪倍數
     * 以區間（low ~ high）呈現。
     *
     * @param float $current_pings   地主現有權狀坪數。
     * @param float $multiplier_low  換坪倍數下緣（保守，預設 0.9）。
     * @param float $multiplier_high 換坪倍數上緣（保守，預設 1.1）。
     * @return array{
     *     current_pings: float,
     *     multiplier_low: float,
     *     multiplier_high: float,
     *     result_low: float,
     *     result_high: float
     * }
     */
    public function calculate_swap($current_pings, $multiplier_low = 0.9, $multiplier_high = 1.1) {
        $current_pings = $this->to_positive_float($current_pings);

        $multiplier_low  = $this->to_positive_float($multiplier_low);
        $multiplier_high = $this->to_positive_float($multiplier_high);

        // 確保 low <= high，避免後台填反造成顯示錯亂。
        if ($multiplier_high < $multiplier_low) {
            $tmp             = $multiplier_low;
            $multiplier_low  = $multiplier_high;
            $multiplier_high = $tmp;
        }

        $result_low  = $current_pings * $multiplier_low;
        $result_high = $current_pings * $multiplier_high;

        return array(
            'current_pings'   => $this->round_ping($current_pings),
            'multiplier_low'  => $multiplier_low,
            'multiplier_high' => $multiplier_high,
            'result_low'      => $this->round_ping($result_low),
            'result_high'     => $this->round_ping($result_high),
        );
    }

    /**
     * 計算「有效獎勵比例」，套用 2 倍硬上限。
     *
     * 有效獎勵% = min(一般獎勵% + 其他獎勵1% + 其他獎勵2%, 100%)
     *
     * @param float $general_bonus 一般都更獎勵（小數，0.5 = 50%）。
     * @param float $other_bonus_1 其他獎勵 1（小數）。
     * @param float $other_bonus_2 其他獎勵 2（小數）。
     * @return array{
     *     total_bonus: float,
     *     effective_bonus: float,
     *     capped: bool
     * }
     */
    public function resolve_bonus($general_bonus, $other_bonus_1 = 0.0, $other_bonus_2 = 0.0) {
        $general_bonus = $this->to_non_negative_float($general_bonus);
        $other_bonus_1 = $this->to_non_negative_float($other_bonus_1);
        $other_bonus_2 = $this->to_non_negative_float($other_bonus_2);

        $total_bonus = $general_bonus + $other_bonus_1 + $other_bonus_2;

        $effective_bonus = min($total_bonus, self::BONUS_HARD_CAP);

        return array(
            'total_bonus'     => $total_bonus,
            'effective_bonus' => $effective_bonus,
            'capped'          => ($total_bonus > self::BONUS_HARD_CAP),
        );
    }

    /**
     * 軌道 C：基地總量回推法（整合公司進階）。
     *
     * 計算鏈：
     *   ① 基準容積樓地板 = 基地總面積 × 法定容積率
     *   ② 總獎勵%   = 一般獎勵% + 其他獎勵1% + 其他獎勵2%
     *   ③ 有效獎勵% = min(總獎勵%, 100%)
     *   ④ 獎勵後容積樓地板 = ① × (1 + 有效獎勵%)
     *   ⑤ 總可銷售樓地板  = ④ × 實設係數
     *   ⑥ 地主總分回樓地板 = ⑤ × 地主總分回比例（以 low ~ high 區間）
     *   ⑦ 個別分回（選填） = ⑥ × (自身持分 ÷ 基地總面積)
     *
     * @param array $args {
     *     @type float $site_area       基地總面積（坪），必填。
     *     @type float $far             法定容積率（小數，2.25 = 225%）。
     *     @type float $general_bonus   一般都更獎勵（小數，預設 0.5）。
     *     @type float $other_bonus_1   其他獎勵 1（小數，預設 0）。
     *     @type float $other_bonus_2   其他獎勵 2（小數，預設 0）。
     *     @type float $build_factor    實設係數（預設 1.5）。
     *     @type float $owner_ratio_low 地主總分回比例下緣（小數，預設 0.50）。
     *     @type float $owner_ratio_high 地主總分回比例上緣（小數，預設 0.55）。
     *     @type float $own_share       自身土地持分（坪），選填；> 0 時回推個人分回。
     * }
     * @return array
     */
    public function calculate_site_total(array $args) {
        $site_area = $this->to_positive_float($args['site_area'] ?? 0);
        $far       = $this->to_positive_float($args['far'] ?? 0);

        $general_bonus = $this->to_non_negative_float($args['general_bonus'] ?? 0.5);
        $other_bonus_1 = $this->to_non_negative_float($args['other_bonus_1'] ?? 0.0);
        $other_bonus_2 = $this->to_non_negative_float($args['other_bonus_2'] ?? 0.0);

        $build_factor = $this->to_positive_float($args['build_factor'] ?? 1.5);

        $owner_ratio_low  = $this->to_non_negative_float($args['owner_ratio_low'] ?? 0.50);
        $owner_ratio_high = $this->to_non_negative_float($args['owner_ratio_high'] ?? 0.55);

        if ($owner_ratio_high < $owner_ratio_low) {
            $tmp              = $owner_ratio_low;
            $owner_ratio_low  = $owner_ratio_high;
            $owner_ratio_high = $tmp;
        }

        $own_share = $this->to_non_negative_float($args['own_share'] ?? 0.0);

        // ① 基準容積樓地板
        $base_floor = $site_area * $far;

        // ②③ 獎勵與 2 倍硬上限
        $bonus = $this->resolve_bonus($general_bonus, $other_bonus_1, $other_bonus_2);

        // ④ 獎勵後容積樓地板
        $bonus_floor = $base_floor * (1 + $bonus['effective_bonus']);

        // ⑤ 總可銷售樓地板
        $sellable_floor = $bonus_floor * $build_factor;

        // ⑥ 地主總分回樓地板（區間）
        $owner_total_low  = $sellable_floor * $owner_ratio_low;
        $owner_total_high = $sellable_floor * $owner_ratio_high;

        $result = array(
            'site_area'        => $this->round_ping($site_area),
            'far'              => $far,
            'base_floor'       => $this->round_ping($base_floor),
            'total_bonus'      => $bonus['total_bonus'],
            'effective_bonus'  => $bonus['effective_bonus'],
            'bonus_capped'     => $bonus['capped'],
            'build_factor'     => $build_factor,
            'bonus_floor'      => $this->round_ping($bonus_floor),
            'sellable_floor'   => $this->round_ping($sellable_floor),
            'owner_ratio_low'  => $owner_ratio_low,
            'owner_ratio_high' => $owner_ratio_high,
            'owner_total_low'  => $this->round_ping($owner_total_low),
            'owner_total_high' => $this->round_ping($owner_total_high),
            'has_individual'   => false,
        );

        // ⑦ 個別分回（選填）：自身持分佔基地總面積之比例。
        if ($own_share > 0 && $site_area > 0) {
            $share_ratio = $own_share / $site_area;

            // 個人持分不應超過基地總面積；超過則夾回 1.0 並標記。
            $share_ratio_capped = ($share_ratio > 1.0);
            if ($share_ratio_capped) {
                $share_ratio = 1.0;
            }

            $result['has_individual']     = true;
            $result['own_share']          = $this->round_ping($own_share);
            $result['share_ratio']        = $share_ratio;
            $result['share_ratio_capped'] = $share_ratio_capped;
            $result['individual_low']     = $this->round_ping($owner_total_low * $share_ratio);
            $result['individual_high']    = $this->round_ping($owner_total_high * $share_ratio);
        }

        return $result;
    }

    /**
     * 進階評估：都更獎勵三案擇優（更新後容積取最有利者）。
     *
     * 三條路徑（皆以「土地持分」為基礎，回傳更新後容積樓地板）：
     *   A. 法定容積 × a_multiplier          （預設 ×1.5）
     *   B. 原容積 + 法定容積 × b_legal_ratio （預設 +法容30%）
     *   C. 原容積 × c_multiplier            （一般 1.2；危險建築／海砂屋 1.3）
     *
     * 取 max(A, B, C)，並以「基準容積 × cap_multiplier」（預設 2 倍）為硬上限。
     * 之後 × 實設係數 → × 分回比例，得分回坪數區間。
     *
     * @param array $args {
     *     @type float  $site_area        土地面積（坪），必填。雙軌輸入：
     *                                     未提供 $own_share 時，本欄位視為「個人持分坪數」直接計算（相容舊版行為）；
     *                                     提供 $own_share 時，本欄位視為「基地總面積」，計算整棟後再依持分比例回推個人分回。
     *     @type float  $far_legal        法定容積率（小數）。
     *     @type float  $far_origin       原建築容積率（小數）。
     *     @type string $building_type    normal / risk / seasand。
     *     @type float  $build_factor     實設係數（預設 1.5）。
     *     @type float  $owner_ratio_low  分回比例下緣（小數，預設 0.50）。
     *     @type float  $owner_ratio_high 分回比例上緣（小數，預設 0.55）。
     *     @type float  $a_multiplier         A 案係數（預設 1.5）。
     *     @type float  $b_legal_ratio        B 案法容比例（預設 0.3）。
     *     @type float  $c_multiplier         C 案一般係數（預設 1.2）。
     *     @type float  $c_multiplier_special C 案危險／海砂屋係數（預設 1.3）。
     *     @type float  $cap_multiplier       上限倍數（預設 2.0，相對基準容積）。
     *     @type float  $own_share            自身土地持分（坪），選填；> 0 時將 $site_area 視為基地總面積，回推個人分回。
     * }
     * @return array
     */
    public function calculate_best_incentive(array $args) {
        $site_area  = $this->to_positive_float($args['site_area'] ?? 0);
        $far_legal  = $this->to_positive_float($args['far_legal'] ?? 0);
        $far_origin = $this->to_non_negative_float($args['far_origin'] ?? 0);
        $own_share  = $this->to_non_negative_float($args['own_share'] ?? 0.0);

        $building_type = isset($args['building_type']) ? (string) $args['building_type'] : 'normal';

        $build_factor     = $this->to_positive_float($args['build_factor'] ?? 1.5);
        $owner_ratio_low  = $this->to_non_negative_float($args['owner_ratio_low'] ?? 0.50);
        $owner_ratio_high = $this->to_non_negative_float($args['owner_ratio_high'] ?? 0.55);

        if ($owner_ratio_high < $owner_ratio_low) {
            $tmp              = $owner_ratio_low;
            $owner_ratio_low  = $owner_ratio_high;
            $owner_ratio_high = $tmp;
        }

        // 可調係數。
        $a_multiplier         = $this->to_positive_float($args['a_multiplier'] ?? 1.5);
        $b_legal_ratio        = $this->to_non_negative_float($args['b_legal_ratio'] ?? 0.3);
        $c_multiplier         = $this->to_positive_float($args['c_multiplier'] ?? 1.2);
        $c_multiplier_special = $this->to_positive_float($args['c_multiplier_special'] ?? 1.3);
        $cap_multiplier       = $this->to_positive_float($args['cap_multiplier'] ?? 2.0);

        // 容積樓地板基礎。
        $base_floor   = $site_area * $far_legal;   // 法定（基準）容積樓地板
        $origin_floor = $site_area * $far_origin;  // 原容積樓地板

        // C 案係數依建物類型。
        $is_special = in_array($building_type, array('risk', 'seasand'), true);
        $c_used     = $is_special ? $c_multiplier_special : $c_multiplier;

        // 三案更新後容積樓地板。
        $path_a = $base_floor * $a_multiplier;
        $path_b = $origin_floor + $base_floor * $b_legal_ratio;
        $path_c = $origin_floor * $c_used;

        $paths = array(
            'A' => $path_a,
            'B' => $path_b,
            'C' => $path_c,
        );

        // 擇優（取最大）。
        $best_key   = 'A';
        $best_value = $path_a;
        foreach ($paths as $k => $v) {
            if ($v > $best_value) {
                $best_value = $v;
                $best_key   = $k;
            }
        }

        // 硬上限：基準容積 × cap_multiplier。
        $cap          = $base_floor * $cap_multiplier;
        $capped       = ($best_value > $cap && $cap > 0);
        $post_floor   = $capped ? $cap : $best_value;

        // 下游：實設係數 → 分回比例。
        $sellable_floor   = $post_floor * $build_factor;
        $owner_total_low  = $sellable_floor * $owner_ratio_low;
        $owner_total_high = $sellable_floor * $owner_ratio_high;

        $result = array(
            'site_area'        => $this->round_ping($site_area),
            'far_legal'        => $far_legal,
            'far_origin'       => $far_origin,
            'building_type'    => $building_type,
            'is_special'       => $is_special,
            'base_floor'       => $this->round_ping($base_floor),
            'origin_floor'     => $this->round_ping($origin_floor),
            'c_multiplier_used' => $c_used,
            'a_multiplier'     => $a_multiplier,
            'b_legal_ratio'    => $b_legal_ratio,
            'path_a'           => $this->round_ping($path_a),
            'path_b'           => $this->round_ping($path_b),
            'path_c'           => $this->round_ping($path_c),
            'best_key'         => $best_key,
            'best_value'       => $this->round_ping($best_value),
            'cap_multiplier'   => $cap_multiplier,
            'capped'           => $capped,
            'post_floor'       => $this->round_ping($post_floor),
            'build_factor'     => $build_factor,
            'sellable_floor'   => $this->round_ping($sellable_floor),
            'owner_ratio_low'  => $owner_ratio_low,
            'owner_ratio_high' => $owner_ratio_high,
            'owner_total_low'  => $this->round_ping($owner_total_low),
            'owner_total_high' => $this->round_ping($owner_total_high),
            'has_individual'   => false,
        );

        // 個人持分回推（選填）：僅在呼叫端把 $site_area 當「基地總面積」使用時才有意義。
        if ($own_share > 0 && $site_area > 0) {
            $share_ratio = $own_share / $site_area;

            // 個人持分不應超過基地總面積；超過則夾回 1.0 並標記。
            $share_ratio_capped = ($share_ratio > 1.0);
            if ($share_ratio_capped) {
                $share_ratio = 1.0;
            }

            $result['has_individual']     = true;
            $result['own_share']          = $this->round_ping($own_share);
            $result['share_ratio']        = $share_ratio;
            $result['share_ratio_capped'] = $share_ratio_capped;
            $result['individual_low']     = $this->round_ping($owner_total_low * $share_ratio);
            $result['individual_high']    = $this->round_ping($owner_total_high * $share_ratio);
        }

        return $result;
    }

    /**
     * 樓層／高度概估（附屬於進階評估，僅在「基地總面積＋建蔽率」皆已知時才有意義）。
     *
     * 設計原則（對應規劃）：
     * - 用「基地總面積 × 建蔽率」概估每層樓地板，回推整棟樓層數與高度。
     * - 樓層數採無條件進位（保守，符合實際蓋房子不會有小數樓層的邏輯）。
     * - 單層樓高採平均值處理，不逐層試算，避免變因過多失真。
     * - 本估算完全不考慮退縮、斜線限制、防火間隔、結構系統、航高與都審限制，
     *   僅供「量體想像」，前台必須明確附註這些限制。
     *
     * @param array $args {
     *     @type float $site_area      基地總面積（坪），必填。
     *     @type float $coverage_ratio 建蔽率（小數，0.45 = 45%），必填。
     *     @type float $total_floor    整棟總樓地板（坪，通常為可銷售樓地板 sellable_floor），必填。
     *     @type float $floor_height   單層樓高（米，預設 3.2）。
     * }
     * @return array{
     *     site_area: float,
     *     coverage_ratio: float,
     *     total_floor: float,
     *     floor_plate: float,
     *     floors: int,
     *     floor_height: float,
     *     height: float
     * }|null null 表示輸入不足，無法估算。
     */
    public function estimate_massing(array $args) {
        $site_area      = $this->to_positive_float($args['site_area'] ?? 0);
        $coverage_ratio = $this->to_positive_float($args['coverage_ratio'] ?? 0);
        $total_floor    = $this->to_positive_float($args['total_floor'] ?? 0);
        $floor_height   = $this->to_positive_float($args['floor_height'] ?? 3.2);

        if ($site_area <= 0 || $coverage_ratio <= 0 || $total_floor <= 0) {
            return null;
        }

        // 每層樓地板 ≈ 基地總面積 × 建蔽率。
        $floor_plate = $site_area * $coverage_ratio;

        if ($floor_plate <= 0) {
            return null;
        }

        // 樓層數：無條件進位，保守估算。
        $floors = (int) ceil($total_floor / $floor_plate);
        $floors = max(1, $floors);

        $height = $floors * $floor_height;

        return array(
            'site_area'      => $this->round_ping($site_area),
            'coverage_ratio' => $coverage_ratio,
            'total_floor'    => $this->round_ping($total_floor),
            'floor_plate'    => $this->round_ping($floor_plate),
            'floors'         => $floors,
            'floor_height'   => $floor_height,
            'height'         => round($floors * $floor_height, 1),
        );
    }

    /**
     * 坪數四捨五入至小數 2 位。
     *
     * @param float $value 數值。
     * @return float
     */
    private function round_ping($value) {
        return round((float) $value, 2);
    }

    /**
     * 轉為正浮點數（負值與非數字視為 0）。
     *
     * @param mixed $value 原始值。
     * @return float
     */
    private function to_positive_float($value) {
        $value = is_numeric($value) ? (float) $value : 0.0;

        return $value > 0 ? $value : 0.0;
    }

    /**
     * 轉為非負浮點數。
     *
     * @param mixed $value 原始值。
     * @return float
     */
    private function to_non_negative_float($value) {
        $value = is_numeric($value) ? (float) $value : 0.0;

        return $value >= 0 ? $value : 0.0;
    }
}
