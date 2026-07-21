<?php
/**
 * UR AI Assistant Joint Burden (共同負擔) Estimator Service
 *
 * 都市更新「共同負擔」提列概算引擎（第一階段）。純 PHP、無 WordPress
 * 依賴（除 current_time() 供日期顯示），方便單元測試。
 *
 * 適用範圍與法源：
 * - 本引擎完全依「新北市」政府公告之「都市更新事業及權利變換計畫作業
 *   須知」附表一「提列總表」與附表二～附表八各「分項說明」之公開公式與
 *   費率表計算。臺北市／其他縣市另有各自的提列基準，公式與費率並不相同，
 *   不可套用本引擎的數字。
 * - 營建單價表之物價基準日為「民國 112 年 4 月」，實際報核時應依當月
 *   營造工程物價指數調整（見 calculate_construction_cost() 的
 *   price_index 參數）。
 *
 * 有明確公式、僅需面積／戶數／樓層等條件即可概算的項目：
 *   - 工程費用 A：拆除費用、營建費用、外接水電瓦斯管線費用。
 *   - 權利變換費用 C：都市更新規劃費、不動產估價費、土地鑑界費、鑽探
 *     費用、地籍整理費用。
 *   - 貸款利息 D（依施工期間推算）。
 *   - 管理費用 F：行政作業費 F1、人事行政管理費 F3、銷售管理費 F4、
 *     風險管理費 F5。
 *   - 稅捐 E：印花稅（概估）與營業稅（財政部 109 年令釋公式，因共同負擔
 *     含營業稅本身屬循環定義，改以代數封閉解求出，見
 *     calculate_business_tax()）。
 *   - 共同負擔比率 = 共同負擔 ÷ 更新後總權利價值（需輸入更新後總權利
 *     價值；未輸入時僅計算不含營業稅的部分，並顯示提醒）。
 * 其餘「個案認定」項目（建築設計費、工程管理費、公共及公益設施、拆遷
 * 補償／安置費、信託費 F2、容積獎勵後續管理維護費 B、都市計畫變更負擔
 * G、容積移轉費 H 等）做成選填欄位，有數字才計入。
 *
 * 設計原則：比照 UR_AI_Tax_Calculator_Service，所有輸出都保留完整拆解
 * 過程（每一項的公式與金額），而不是只給一個總數，方便使用者逐項核對。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Joint_Burden_Service
 */
class UR_AI_Joint_Burden_Service {

    /**
     * 拆除單價（元／平方公尺），附表二 分項說明一。已內含拆除工程空污費。
     *
     * @var array
     */
    const DEMOLITION_UNIT_PRICE = array(
        'steel'          => 1720, // 鋼骨造。
        'src'            => 1400, // 鋼骨鋼筋混凝土造。
        'rc'             => 1050, // 鋼筋混凝土造。
        'reinforced'     => 900,  // 加強磚造。
        'brick'          => 620,  // 磚造。
        'wood'           => 230,  // 竹、木造。
        'stone'          => 200,  // 漿砌卵石。
        'metal_shed'     => 350,  // 金屬或鋼鐵棚架。
    );

    /**
     * 建築物工程造價標準單價表（元／坪），附表三 分項說明二，
     * 物價基準日 112 年 4 月。
     *
     * 結構：[構造別][樓層區間] => [未滿2500坪, 2500至未滿7500坪, 7500坪以上]。
     * 鋼筋混凝土（rc）依原表僅列至 31-35 層。
     *
     * @var array
     */
    const CONSTRUCTION_UNIT_PRICE = array(
        'steel' => array(
            '6-10'  => array(173405, 165119, 160245),
            '11-15' => array(187785, 178889, 173527),
            '16-20' => array(220809, 210329, 203992),
            '21-25' => array(237615, 230801, 223855),
            '26-30' => array(257976, 245790, 238356),
            '31-35' => array(270649, 257854, 250055),
            '36+'   => array(281738, 268334, 260291),
        ),
        'src' => array(
            '6-10'  => array(166216, 158295, 153542),
            '11-15' => array(180229, 171577, 166459),
            '16-20' => array(199605, 190100, 184495),
            '21-25' => array(219103, 208623, 202408),
            '26-30' => array(233482, 222393, 215690),
            '31-35' => array(245302, 233604, 226658),
            '36+'   => array(256757, 244571, 237138),
        ),
        'rc' => array(
            '6-10'  => array(138797, 132095, 128196),
            '11-15' => array(155248, 147815, 143428),
            '16-20' => array(170481, 162316, 157442),
            '21-25' => array(186932, 178036, 172674),
            '26-30' => array(205698, 195827, 189978),
            '31-35' => array(226170, 215447, 208988),
        ),
    );

    /** 營建單價表物價基準日之說明字串。 */
    const CONSTRUCTION_PRICE_BASE_LABEL = '民國112年4月';

    /** 外接水電瓦斯管線費用：更新後每戶 7.5 萬元。 */
    const UTILITY_LINE_PER_HOUSEHOLD = 75000;

    /** 鑽探費用：每孔 7.5 萬元。 */
    const DRILLING_PER_HOLE = 75000;

    /** 土地鑑界費：更新前每筆地號 4 千元。 */
    const BOUNDARY_SURVEY_PER_PARCEL = 4000;

    /** 地籍整理費：原則以更新後每戶 2 萬元計列。 */
    const CADASTRAL_PER_HOUSEHOLD = 20000;

    /** 都市更新規劃費 P1（事業概要，萬元）。 */
    const PLANNING_P1_WAN = 150;
    /** 都市更新規劃費 P2 基數（計畫擬訂，萬元，另加 X＋Y）。 */
    const PLANNING_P2_BASE_WAN = 300;
    /** 都市更新規劃費 P3（以權利變換方式實施，萬元）。 */
    const PLANNING_P3_WAN = 150;

    /** 不動產估價：一家估價師事務所服務費基數（萬元）。 */
    const APPRAISAL_BASE_WAN = 40;
    /** 不動產估價：每筆加計（萬元／筆）。 */
    const APPRAISAL_PER_PARCEL_WAN = 0.45;
    /** 不動產估價：選定費率（服務費之 30%）。 */
    const APPRAISAL_SELECTION_RATE = 0.30;
    /** 不動產估價：選定費下限（萬元）。 */
    const APPRAISAL_SELECTION_MIN_WAN = 25;

    /** 行政作業費 F1 = 土地公告現值總值 × 2.5%。 */
    const F1_RATE = 0.025;

    /** 印花稅（承攬契據，印花稅法第5條）稅率千分之一，以營建費用為稅基概估。 */
    const STAMP_TAX_RATE = 0.001;

    /**
     * 計算共同負擔（第一階段）。
     *
     * @param array $args 見各 read_* 的鍵；缺漏的選填項目以 0 計。
     * @return array 完整拆解結果。
     */
    public function calculate(array $args) {
        $notes = array();

        // ---- 工程費用 A ----
        $demolition   = $this->calculate_demolition_cost($args);
        $construction = $this->calculate_construction_cost($args);

        $household   = max(0, (int) ($args['household_count'] ?? 0));
        $utility     = $household * self::UTILITY_LINE_PER_HOUSEHOLD;

        // 個案選填（工程費用 A）。
        $design_fee        = max(0.0, (float) ($args['design_fee'] ?? 0));
        $construction_mgmt = max(0.0, (float) ($args['construction_mgmt_fee'] ?? 0));
        $public_facility   = max(0.0, (float) ($args['public_facility_fee'] ?? 0));
        $condo_fund        = max(0.0, (float) ($args['condo_fund'] ?? 0));

        $a_items = array(
            array('key' => 'demolition',    'label' => '拆除費用',                  'amount' => $demolition['amount'],   'auto' => true,  'note' => $demolition['note']),
            array('key' => 'construction',  'label' => '營建費用',                  'amount' => $construction['amount'], 'auto' => true,  'note' => $construction['note']),
            array('key' => 'utility',       'label' => '外接水電瓦斯管線費用',        'amount' => $utility,                'auto' => true,  'note' => sprintf('更新後 %d 戶 × 每戶 %s 元 = %s 元。', $household, $this->fmt($this->UTILITY()), $this->fmt($utility))),
            array('key' => 'design_fee',    'label' => '建築設計費用（個案填入）',      'amount' => $design_fee,             'auto' => false, 'note' => ''),
            array('key' => 'construction_mgmt', 'label' => '工程管理費（個案填入）',    'amount' => $construction_mgmt,      'auto' => false, 'note' => ''),
            array('key' => 'public_facility', 'label' => '公共及公益設施費用（個案填入）', 'amount' => $public_facility,       'auto' => false, 'note' => ''),
            array('key' => 'condo_fund',    'label' => '公寓大廈公共基金（個案填入）',   'amount' => $condo_fund,             'auto' => false, 'note' => ''),
        );
        $a_total = $this->sum_items($a_items);

        // ---- 權利變換費用 C ----
        $planning  = $this->calculate_planning_fee($args);
        $appraisal = $this->calculate_appraisal_fee($args);

        $boundary_parcels = max(0, (int) ($args['boundary_survey_parcels'] ?? 0));
        $boundary_fee     = $boundary_parcels * self::BOUNDARY_SURVEY_PER_PARCEL;

        $drilling_holes = max(0, (int) ($args['drilling_holes'] ?? 0));
        $drilling_fee   = $drilling_holes * self::DRILLING_PER_HOLE;

        $cadastral_fee = $household * self::CADASTRAL_PER_HOUSEHOLD;

        // 個案選填（權利變換費用 C）。
        $demolition_compensation = max(0.0, (float) ($args['demolition_compensation'] ?? 0));
        $relocation_fee          = max(0.0, (float) ($args['relocation_fee'] ?? 0));
        $other_c_fee             = max(0.0, (float) ($args['other_c_fee'] ?? 0));

        $c_items = array(
            array('key' => 'planning',   'label' => '都市更新規劃費',                'amount' => $planning['amount'],  'auto' => true,  'note' => $planning['note']),
            array('key' => 'appraisal',  'label' => '不動產估價費用',                'amount' => $appraisal['amount'], 'auto' => true,  'note' => $appraisal['note']),
            array('key' => 'boundary',   'label' => '土地鑑界費',                    'amount' => $boundary_fee,        'auto' => true,  'note' => sprintf('更新前 %d 筆地號 × 每筆 %s 元 = %s 元。', $boundary_parcels, $this->fmt(self::BOUNDARY_SURVEY_PER_PARCEL), $this->fmt($boundary_fee))),
            array('key' => 'drilling',   'label' => '鑽探費用',                      'amount' => $drilling_fee,        'auto' => true,  'note' => sprintf('%d 孔 × 每孔 %s 元 = %s 元。', $drilling_holes, $this->fmt(self::DRILLING_PER_HOLE), $this->fmt($drilling_fee))),
            array('key' => 'cadastral',  'label' => '地籍整理費用',                  'amount' => $cadastral_fee,       'auto' => true,  'note' => sprintf('更新後 %d 戶 × 每戶 %s 元 = %s 元（不含地政機關行政規費，實務另依申報地價／造價核實加計）。', $household, $this->fmt(self::CADASTRAL_PER_HOUSEHOLD), $this->fmt($cadastral_fee))),
            array('key' => 'demolition_compensation', 'label' => '合法建築物及土地改良物拆遷補償費（個案填入）', 'amount' => $demolition_compensation, 'auto' => false, 'note' => ''),
            array('key' => 'relocation', 'label' => '拆遷安置費（個案填入）',          'amount' => $relocation_fee,      'auto' => false, 'note' => ''),
            array('key' => 'other_c',    'label' => '其他權變費用：鄰房鑑定／測量／審查等（個案填入）', 'amount' => $other_c_fee, 'auto' => false, 'note' => ''),
        );
        $c_total = $this->sum_items($c_items);

        // ---- 個案選填：都市計畫變更負擔 G、容積移轉費 H ----
        $g_cost = max(0.0, (float) ($args['g_cost'] ?? 0));
        $h_cost = max(0.0, (float) ($args['h_cost'] ?? 0));

        // ---- 貸款利息 D ----
        $loan = $this->calculate_loan_interest($args, $a_total, $c_total, $condo_fund, $demolition_compensation, $g_cost, $h_cost);
        $d_total = $loan['amount'];

        // ---- 個案選填：申請容積獎勵後續管理維護費用 B（依審定金額） ----
        $b_cost = max(0.0, (float) ($args['b_cost'] ?? 0));

        // ---- 管理費用 F（F1／F2／F3／F4／F5） ----
        $land_current_value_total = max(0.0, (float) ($args['land_current_value_total'] ?? 0));
        $f1 = $land_current_value_total * self::F1_RATE;

        // F2 信託費（個案填入全額；一般建商折半）。
        $trust_fee_full = max(0.0, (float) ($args['trust_fee'] ?? 0));
        $trust_type     = ('developer' === ($args['trust_fee_type'] ?? 'self')) ? 'developer' : 'self';
        $f2 = ('developer' === $trust_type) ? $trust_fee_full * 0.5 : $trust_fee_full;

        // 產權級別 = (門牌戶數 + 所有權人數聯集) / 2。
        $door_count  = max(0, (int) ($args['door_count'] ?? $household));
        $owner_count = max(0, (int) ($args['owner_count'] ?? 0));
        $property_grade = ($door_count + $owner_count) / 2.0;

        $base_site_area = max(0.0, (float) ($args['base_site_area_sqm'] ?? 0));
        $total_floor_area_ping = max(0.0, (float) ($args['total_floor_area_ping'] ?? 0));

        $f3_rate = $this->f3_rate($property_grade, $base_site_area);
        $f3 = ($a_total + $c_total + $g_cost) * $f3_rate;

        // F4 銷售管理費 = 實施者實際獲配之單元及車位總價值 × 費率（累進遞減）。
        $allocated_value = max(0.0, (float) ($args['allocated_value'] ?? 0));
        $f4_detail = $this->calculate_sales_management_fee($allocated_value);
        $f4 = $f4_detail['amount'];

        // F5 風險管理費（依原公式，基數不含 F4）。
        $f5_rate = $this->f5_rate($property_grade, $total_floor_area_ping);
        $f5 = ($a_total + $c_total + $d_total + $f1 + $f2 + $f3 + $g_cost + $h_cost) * $f5_rate;

        $f_items = array(
            array('key' => 'f1', 'label' => '行政作業費用 F1', 'amount' => $f1, 'auto' => true, 'note' => sprintf('更新單元內土地公告現值總值 %s 元 × 2.5%% = %s 元。', $this->fmt($land_current_value_total), $this->fmt($f1))),
            array('key' => 'f2', 'label' => '信託費用 F2（個案填入）', 'amount' => $f2, 'auto' => false, 'note' => $trust_fee_full > 0 ? sprintf('信託費全額 %s 元%s = %s 元。', $this->fmt($trust_fee_full), 'developer' === $trust_type ? '（一般建商折半）× 50%' : '（自組更新會全額）', $this->fmt($f2)) : ''),
            array('key' => 'f3', 'label' => '人事行政管理費用 F3', 'amount' => $f3, 'auto' => true, 'note' => sprintf('(A %s ＋ C %s ＋ G %s) × 費率 %s%% = %s 元。〔產權級別 %s、基地面積 %s㎡〕', $this->fmt($a_total), $this->fmt($c_total), $this->fmt($g_cost), $this->fmt_pct($f3_rate), $this->fmt($f3), $this->fmt_num($property_grade), $this->fmt_num($base_site_area))),
            array('key' => 'f4', 'label' => '銷售管理費用 F4', 'amount' => $f4, 'auto' => true, 'note' => $f4_detail['note']),
            array('key' => 'f5', 'label' => '風險管理費用 F5', 'amount' => $f5, 'auto' => true, 'note' => sprintf('(A＋C＋D＋F1＋F2＋F3＋G＋H) %s × 費率 %s%% = %s 元。〔產權級別 %s、總樓地板面積 %s坪〕', $this->fmt($a_total + $c_total + $d_total + $f1 + $f2 + $f3 + $g_cost + $h_cost), $this->fmt_pct($f5_rate), $this->fmt($f5), $this->fmt_num($property_grade), $this->fmt_num($total_floor_area_ping))),
        );
        $f_total = $this->sum_items($f_items);

        // ---- 稅捐 E（印花稅 + 營業稅） ----
        // 印花稅：承攬契據依印花稅法第 5 條千分之一，概以營建費用為稅基估算。
        $stamp_tax = $construction['amount'] * self::STAMP_TAX_RATE;

        // 營業稅：財政部 109 年令釋公式，因「更新後總權利價值 − 共同負擔」中的
        // 共同負擔本身含營業稅，屬循環定義，改以代數解出封閉解（見
        // calculate_business_tax()）。K = 共同負擔扣除營業稅後的部分。
        $k_without_business_tax = $a_total + $b_cost + $c_total + $d_total + $f_total + $g_cost + $h_cost + $stamp_tax;

        $post_renewal_total_value = max(0.0, (float) ($args['post_renewal_total_value'] ?? 0));

        $business = $this->calculate_business_tax($args, $k_without_business_tax, $post_renewal_total_value);
        $business_tax = $business['amount'];

        $e_total = $stamp_tax + $business_tax;

        $e_items = array(
            array('key' => 'stamp_tax', 'label' => '印花稅', 'amount' => $stamp_tax, 'auto' => true, 'note' => sprintf('承攬契據（印花稅法第5條，千分之一），以營建費用 %s 元為稅基概估 = %s 元。', $this->fmt($construction['amount']), $this->fmt($stamp_tax))),
            array('key' => 'business_tax', 'label' => '營業稅', 'amount' => $business_tax, 'auto' => true, 'note' => $business['note']),
        );

        // ---- 個案選填 G/H 金額列（供顯示） ----
        $gh_items = array(
            array('key' => 'b_cost', 'label' => '申請容積獎勵後續管理維護費用 B（個案填入）', 'amount' => $b_cost, 'auto' => false, 'note' => ''),
            array('key' => 'g_cost', 'label' => '都市計畫變更負擔費用 G（個案填入）', 'amount' => $g_cost, 'auto' => false, 'note' => ''),
            array('key' => 'h_cost', 'label' => '容積移轉費用 H（個案填入）',         'amount' => $h_cost, 'auto' => false, 'note' => ''),
        );

        // ---- 共同負擔總額與比率 ----
        // 共同負擔 = A+B+C+D+E+F+G+H = K（不含營業稅） + 營業稅。
        $subtotal_without_e = $a_total + $b_cost + $c_total + $d_total + $f_total + $g_cost + $h_cost;
        $total_burden       = $subtotal_without_e + $e_total;

        $has_total_value  = $post_renewal_total_value > 0;
        $burden_ratio     = $has_total_value ? ($total_burden / $post_renewal_total_value) : null;

        $notes[] = '本試算依「新北市」都市更新提列基準之公開公式計算，臺北市及其他縣市之公式與費率不同，不適用本結果。';

        if ($has_total_value) {
            $notes[] = sprintf('共同負擔比率 = 共同負擔 %s 元 ÷ 更新後總權利價值 %s 元 = %s%%。', $this->fmt($total_burden), $this->fmt($post_renewal_total_value), $this->fmt_num($burden_ratio * 100));
            if (null !== $burden_ratio && $burden_ratio < 0.40) {
                $notes[] = '共同負擔比率低於 40%，若為自組更新會或非以更新後房地折價抵付之代執行機構，風險管理費 F5 費率得提列至 14%（須經審議會審議同意）；本試算仍以費率表計算，未自動套用此上限。';
            }
        } else {
            $notes[] = '未輸入「更新後總權利價值」，故未計算營業稅與共同負擔比率（顯示金額不含營業稅）。填入後即可得出完整共同負擔與比率。';
        }

        $notes[] = '個案認定項目（B／G／H、建築設計費、公共設施、拆遷補償、信託費等）之實際認列，仍以新北市都市更新及爭議處理審議會審定為準。';

        return array(
            'success'    => true,
            'a_items'    => $a_items,
            'a_total'    => $a_total,
            'c_items'    => $c_items,
            'c_total'    => $c_total,
            'd_detail'   => $loan,
            'd_total'    => $d_total,
            'e_items'    => $e_items,
            'e_total'    => $e_total,
            'f_items'    => $f_items,
            'f_total'    => $f_total,
            'gh_items'   => $gh_items,
            'b_cost'     => $b_cost,
            'g_cost'     => $g_cost,
            'h_cost'     => $h_cost,
            'subtotal'   => $total_burden,
            'total_burden' => $total_burden,
            'has_total_value' => $has_total_value,
            'post_renewal_total_value' => $post_renewal_total_value,
            'burden_ratio' => $burden_ratio,
            'property_grade' => $property_grade,
            'notes'      => $notes,
        );
    }

    /**
     * 銷售管理費 F4 ＝ 實施者實際獲配之單元及車位總價值 × 費率（累進遞減）。
     *
     * @param float $allocated_value 實施者實際獲配之單元及車位總價值（元）。
     * @return array{amount: float, note: string, rate_effective: float}
     */
    public function calculate_sales_management_fee($allocated_value) {
        $allocated_value = max(0.0, (float) $allocated_value);

        // 25 億以下 6%、25-50 億 5.5%、50 億以上 5%（分級累加）。
        $brackets = array(
            array('limit' => 2500000000.0, 'rate' => 0.06),
            array('limit' => 5000000000.0, 'rate' => 0.055),
            array('limit' => INF,          'rate' => 0.05),
        );

        $amount = $this->progressive_sum($allocated_value, $brackets);
        $rate_effective = $allocated_value > 0 ? $amount / $allocated_value : 0.0;

        $note = $allocated_value > 0
            ? sprintf('獲配總價值 %s 元，分級累加（25億以下6%%、25-50億5.5%%、50億以上5%%）= %s 元（等效費率 %s%%）。', $this->fmt($allocated_value), $this->fmt($amount), $this->fmt_num($rate_effective * 100))
            : '未輸入實施者實際獲配之單元及車位總價值，F4 以 0 計。';

        return array('amount' => $amount, 'note' => $note, 'rate_effective' => $rate_effective);
    }

    /**
     * 營業稅（財政部 109 年 9 月 14 日台財稅字第 10900611910 號令釋）。
     *
     * 令釋原式：營業稅 = (更新後總權利價值 V − 共同負擔) × 比例 r × 5%。
     * 其中共同負擔含營業稅本身，屬循環定義；令 K = 共同負擔 − 營業稅，
     * 則 營業稅 = (V − K − 營業稅) × r × 0.05，解得封閉解：
     *   營業稅 = (V − K) × r × 0.05 ÷ (1 + r × 0.05)。
     *
     * r 依令釋「擇一計算」提供兩種：
     * - house_ratio（房屋評定比例法）：r = 房評 ÷ (土地公告現值 + 房評)。
     * - cost_ratio（費用比例法）：r = (K − 公共設施用地負擔) ÷ V。
     *
     * @param array $args 參數。
     * @param float $k    共同負擔扣除營業稅後之金額。
     * @param float $v    更新後總權利價值。
     * @return array{amount: float, note: string, ratio: float, method: string}
     */
    public function calculate_business_tax(array $args, $k, $v) {
        $v = max(0.0, (float) $v);

        if ($v <= 0) {
            return array('amount' => 0.0, 'note' => '未輸入更新後總權利價值，營業稅以 0 計。', 'ratio' => 0.0, 'method' => 'none');
        }

        $method = ('cost_ratio' === ($args['business_tax_method'] ?? 'house_ratio')) ? 'cost_ratio' : 'house_ratio';

        if ('cost_ratio' === $method) {
            $pub = max(0.0, (float) ($args['public_facility_land_burden'] ?? 0));
            $ratio = max(0.0, ($k - $pub) / $v);
            $ratio_note = sprintf('費用比例法：r = (K %s − 公設用地負擔 %s) ÷ V %s = %s%%', $this->fmt($k), $this->fmt($pub), $this->fmt($v), $this->fmt_num($ratio * 100));
        } else {
            $house = max(0.0, (float) ($args['house_assessed_value'] ?? 0));
            $land  = (float) ($args['land_announced_value_for_tax'] ?? 0);
            if ($land <= 0) {
                $land = max(0.0, (float) ($args['land_current_value_total'] ?? 0));
            }
            $denom = $house + $land;
            if ($denom <= 0) {
                return array('amount' => 0.0, 'note' => '房屋評定比例法需輸入房屋評定標準價格與土地公告現值，資料不足，營業稅暫以 0 計。', 'ratio' => 0.0, 'method' => $method);
            }
            $ratio = $house / $denom;
            $ratio_note = sprintf('房屋評定比例法：r = 房評 %s ÷ (土地公告現值 %s + 房評 %s) = %s%%', $this->fmt($house), $this->fmt($land), $this->fmt($house), $this->fmt_num($ratio * 100));
        }

        $factor = $ratio * 0.05;
        $amount = ($v - $k) * $factor / (1 + $factor);
        $amount = max(0.0, $amount);

        $note = sprintf('%s；營業稅 = (V %s − K %s) × r×5%% ÷ (1 + r×5%%) = %s 元（K 為共同負擔扣除營業稅後之金額，已解循環定義）。', $ratio_note, $this->fmt($v), $this->fmt($k), $this->fmt($amount));

        return array('amount' => $amount, 'note' => $note, 'ratio' => $ratio, 'method' => $method);
    }

    /* ---------------------------------------------------------------------
     * 工程費用 A
     * ------------------------------------------------------------------- */

    /**
     * 拆除費用 ＝ 拆除面積 × 拆除單價（依構造別）。
     *
     * @param array $args 需要 demolition_area、demolition_structure。
     * @return array{amount: float, note: string}
     */
    public function calculate_demolition_cost(array $args) {
        $area      = max(0.0, (float) ($args['demolition_area'] ?? 0));
        $structure = (string) ($args['demolition_structure'] ?? 'rc');

        if (!isset(self::DEMOLITION_UNIT_PRICE[$structure])) {
            $structure = 'rc';
        }

        $unit   = self::DEMOLITION_UNIT_PRICE[$structure];
        $amount = $area * $unit;

        return array(
            'amount' => $amount,
            'note'   => sprintf('拆除面積 %s㎡ × %s構造單價 %s 元/㎡ = %s 元（已內含拆除工程空污費）。', $this->fmt_num($area), $this->demolition_structure_label($structure), $this->fmt($unit), $this->fmt($amount)),
        );
    }

    /**
     * 營建費用 ＝ 總樓地板面積(坪) × 營建單價。
     *
     * 營建單價 = 標準單價表(構造別×面積級距×樓層區間) 依下列調整：
     * - 加成率 surcharge_rate（選填，涵蓋地下層加成、樓層高度加成、綠建築／
     *   智慧建築加計等，使用者依附表三核計原則自行帶入，套用於標準單價）。
     * - 物價指數調整（選填，需帶入基準日與調整日之營造工程物價指數；僅就
     *   指數增減率絕對值超過 2.5% 的部分調整）。
     *
     * @param array $args total_floor_area_ping、structure、floors_above、
     *                    surcharge_rate、price_index_base、price_index_current。
     * @return array{amount: float, note: string, unit_price: float, base_unit_price: float}
     */
    public function calculate_construction_cost(array $args) {
        $ping      = max(0.0, (float) ($args['total_floor_area_ping'] ?? 0));
        $structure = (string) ($args['structure'] ?? 'rc');
        $floors    = max(0, (int) ($args['floors_above'] ?? 0));

        if (!isset(self::CONSTRUCTION_UNIT_PRICE[$structure])) {
            $structure = 'rc';
        }

        $col_index    = $this->construction_area_column($ping);
        $floor_bucket = $this->construction_floor_bucket($structure, $floors);

        $base_unit = self::CONSTRUCTION_UNIT_PRICE[$structure][$floor_bucket['key']][$col_index];

        $sub_notes = array();
        $sub_notes[] = sprintf('標準單價：%s、總樓地板面積 %s坪（%s）、%s = %s 元/坪（物價基準日 %s）。', $this->construction_structure_label($structure), $this->fmt_num($ping), $this->construction_area_label($col_index), $floor_bucket['label'], $this->fmt($base_unit), self::CONSTRUCTION_PRICE_BASE_LABEL);

        if (!empty($floor_bucket['note'])) {
            $sub_notes[] = $floor_bucket['note'];
        }

        $unit = (float) $base_unit;

        // 物價指數調整（選填）。
        $idx_base    = (float) ($args['price_index_base'] ?? 0);
        $idx_current = (float) ($args['price_index_current'] ?? 0);
        if ($idx_base > 0 && $idx_current > 0) {
            $rate = ($idx_current - $idx_base) / $idx_base;
            if (abs($rate) > 0.025) {
                $adj = $base_unit * (abs($rate) - 0.025);
                $adj = round($adj / 100) * 100; // 計算至百元。
                $signed = ($rate > 0) ? $adj : -$adj;
                $unit += $signed;
                $sub_notes[] = sprintf('物價指數調整：指數增減率 %s%%（基準 %s → 現行 %s），就超過 2.5%% 部分調整 %s 元/坪。', $this->fmt_num($rate * 100), $this->fmt_num($idx_base), $this->fmt_num($idx_current), $this->fmt($signed));
            } else {
                $sub_notes[] = sprintf('物價指數增減率 %s%% 未超過 2.5%%，不調整單價。', $this->fmt_num($rate * 100));
            }
        }

        // 加成率（選填）：套用於標準單價。
        $surcharge_rate = max(0.0, (float) ($args['surcharge_rate'] ?? 0));
        if ($surcharge_rate > 0) {
            $surcharge = round(($base_unit * $surcharge_rate) / 100) * 100;
            $unit += $surcharge;
            $sub_notes[] = sprintf('加成調整（地下層／樓高／綠建築等，依核計原則）：標準單價 × %s%% = ＋%s 元/坪。', $this->fmt_num($surcharge_rate * 100), $this->fmt($surcharge));
        }

        $unit   = max(0.0, $unit);
        $amount = $ping * $unit;

        $sub_notes[] = sprintf('營建費用 = %s坪 × %s 元/坪 = %s 元。', $this->fmt_num($ping), $this->fmt($unit), $this->fmt($amount));

        return array(
            'amount'          => $amount,
            'note'            => implode(' ', $sub_notes),
            'unit_price'      => $unit,
            'base_unit_price' => (float) $base_unit,
        );
    }

    /* ---------------------------------------------------------------------
     * 權利變換費用 C
     * ------------------------------------------------------------------- */

    /**
     * 都市更新規劃費 ＝ P1 ＋ P2(300萬＋X＋Y) ＋ P3 ＋ 特殊加計（選填）。
     *
     * X：更新單元面積累進級距；Y：權利人人數累進級距（附表五）。
     *
     * @param array $args unit_area_sqm、rights_holders、planning_extra_wan（選填）。
     * @return array{amount: float, note: string}
     */
    public function calculate_planning_fee(array $args) {
        $unit_area     = max(0.0, (float) ($args['unit_area_sqm'] ?? 0));
        $rights_holders = max(0, (int) ($args['rights_holders'] ?? 0));
        $extra_wan     = max(0.0, (float) ($args['planning_extra_wan'] ?? 0));

        $x = $this->planning_area_component($unit_area);   // 萬元。
        $y = $this->planning_person_component($rights_holders); // 萬元。

        $p1 = self::PLANNING_P1_WAN;
        $p2 = self::PLANNING_P2_BASE_WAN + $x['wan'] + $y['wan'];
        $p3 = self::PLANNING_P3_WAN;

        $total_wan = $p1 + $p2 + $p3 + $extra_wan;
        $amount    = $total_wan * 10000.0;

        $note = sprintf(
            'P1 %s萬 ＋ P2(300萬＋X %s萬＋Y %s萬) ＋ P3 %s萬%s = %s萬元 = %s 元。〔X：%s；Y：%s〕',
            $this->fmt_num($p1),
            $this->fmt_num($x['wan']),
            $this->fmt_num($y['wan']),
            $this->fmt_num($p3),
            $extra_wan > 0 ? sprintf(' ＋ 特殊加計 %s萬', $this->fmt_num($extra_wan)) : '',
            $this->fmt_num($total_wan),
            $this->fmt($amount),
            $x['note'],
            $y['note']
        );

        return array('amount' => $amount, 'note' => $note);
    }

    /**
     * 更新面積規模 X（累進），單位萬元。
     *
     * @param float $area 更新單元面積（含公共設施用地，㎡）。
     * @return array{wan: float, note: string}
     */
    private function planning_area_component($area) {
        // 面積規模於 1000㎡ 以下者，均以 1000㎡ 計算。
        $effective = ($area > 0 && $area < 1000) ? 1000.0 : $area;

        $brackets = array(
            array('limit' => 2000.0,  'rate' => 0.1),
            array('limit' => 4000.0,  'rate' => 0.08),
            array('limit' => 6000.0,  'rate' => 0.06),
            array('limit' => 10000.0, 'rate' => 0.04),
            array('limit' => INF,     'rate' => 0.02),
        );

        $wan  = $this->progressive_sum($effective, $brackets);
        $note = sprintf('更新單元面積 %s㎡（累進）= %s萬元', $this->fmt_num($effective), $this->fmt_num($wan));

        return array('wan' => $wan, 'note' => $note);
    }

    /**
     * 權屬情形 Y（累進），單位萬元。
     *
     * @param int $persons 權利人人數。
     * @return array{wan: float, note: string}
     */
    private function planning_person_component($persons) {
        // 20 人以下，均以 20 人計算。
        $effective = ($persons > 0 && $persons < 20) ? 20 : $persons;

        $brackets = array(
            array('limit' => 20.0,  'rate' => 6.0),
            array('limit' => 100.0, 'rate' => 4.0),
            array('limit' => 200.0, 'rate' => 2.5),
            array('limit' => INF,   'rate' => 1.5),
        );

        $wan  = $this->progressive_sum($effective, $brackets);
        $note = sprintf('權利人 %s人（累進）= %s萬元', $this->fmt_num($effective), $this->fmt_num($wan));

        return array('wan' => $wan, 'note' => $note);
    }

    /**
     * 累進級距加總。
     *
     * @param float $value    數值。
     * @param array $brackets 各級距 array(limit, rate)（limit 為該級距上限累計值）。
     * @return float
     */
    private function progressive_sum($value, array $brackets) {
        $sum  = 0.0;
        $prev = 0.0;

        foreach ($brackets as $b) {
            if ($value <= $prev) {
                break;
            }
            $upper = min($value, $b['limit']);
            $sum  += ($upper - $prev) * $b['rate'];
            $prev  = $b['limit'];
        }

        return $sum;
    }

    /**
     * 不動產估價費 ＝ 服務費 ＋ 選定費。
     *
     * 服務費 = 40萬 ＋ (更新前主建物筆數＋土地筆數)×0.45萬 ＋ 更新後主建物筆數×0.45萬。
     * 選定費 = 服務費 × 30%，且不低於 25 萬。
     *
     * @param array $args main_building_parcels_before、land_parcels、
     *                    main_building_parcels_after、include_selection_fee（選填，預設含）。
     * @return array{amount: float, note: string}
     */
    public function calculate_appraisal_fee(array $args) {
        $before_main = max(0, (int) ($args['main_building_parcels_before'] ?? 0));
        $land        = max(0, (int) ($args['land_parcels'] ?? 0));
        $after_main  = max(0, (int) ($args['main_building_parcels_after'] ?? 0));

        $service_wan = self::APPRAISAL_BASE_WAN
            + ($before_main + $land) * self::APPRAISAL_PER_PARCEL_WAN
            + $after_main * self::APPRAISAL_PER_PARCEL_WAN;

        $include_selection = !isset($args['include_selection_fee']) || !empty($args['include_selection_fee']);

        $selection_wan = 0.0;
        if ($include_selection && $service_wan > 0) {
            $selection_wan = max(self::APPRAISAL_SELECTION_MIN_WAN, $service_wan * self::APPRAISAL_SELECTION_RATE);
        }

        $total_wan = $service_wan + $selection_wan;
        $amount    = $total_wan * 10000.0;

        $note = sprintf(
            '服務費 = 40萬 ＋ (更新前主建物%d＋土地%d 筆)×0.45萬 ＋ 更新後主建物%d筆×0.45萬 = %s萬%s = %s萬元 = %s 元。',
            $before_main,
            $land,
            $after_main,
            $this->fmt_num($service_wan),
            $include_selection ? sprintf('；選定費 = max(服務費×30%%, 25萬) = %s萬', $this->fmt_num($selection_wan)) : '（未計選定費）',
            $this->fmt_num($total_wan),
            $this->fmt($amount)
        );

        return array('amount' => $amount, 'note' => $note);
    }

    /* ---------------------------------------------------------------------
     * 貸款利息 D
     * ------------------------------------------------------------------- */

    /**
     * 貸款利息 D＝(1)＋(2)。
     *
     * (1) = 〔拆遷補償費＋G＋H〕× 年利率 × 貸款期間。
     * (2) = 〔(A−公寓大廈管理基金)＋(C−拆遷補償費)〕× 年利率 × 貸款期間 × 0.5。
     * 年利率 = 自有資金比率×郵政定存利率 ＋ 融資比率×五大銀行基準利率。
     * 貸款期間 = 施工期間 ＋ 12 個月。
     * 施工期間 = 地下層(首層4月，每多一層＋2月) ＋ 地上層(每層1月)；逆打工法
     *           地下層每層減 1.5 月。
     *
     * @param array $args               利率／樓層／工法等參數。
     * @param float $a_total            工程費用 A 合計。
     * @param float $c_total            權利變換費用 C 合計。
     * @param float $condo_fund         公寓大廈管理基金。
     * @param float $demolition_comp    合法建築物拆遷補償費。
     * @param float $g_cost             G。
     * @param float $h_cost             H。
     * @return array
     */
    public function calculate_loan_interest(array $args, $a_total, $c_total, $condo_fund, $demolition_comp, $g_cost, $h_cost) {
        $floors_above = max(0, (int) ($args['floors_above'] ?? 0));
        $floors_below = max(0, (int) ($args['floors_below'] ?? 0));
        $top_down     = !empty($args['top_down_construction']);

        // 施工期間（月）。
        $below_months = 0.0;
        if ($floors_below >= 1) {
            $below_months = 4 + ($floors_below - 1) * 2;
            if ($top_down) {
                $below_months = max(0.0, $below_months - $floors_below * 1.5);
            }
        }
        $above_months = $floors_above * 1.0;
        $build_months = $below_months + $above_months;
        $loan_months  = $build_months + 12;
        $loan_years   = $loan_months / 12.0;

        // 年利率。
        $own_ratio  = min(1.0, max(0.0, (float) ($args['own_capital_ratio'] ?? 0)));
        $postal     = max(0.0, (float) ($args['postal_rate'] ?? 0)) / 100.0;
        $bank       = max(0.0, (float) ($args['bank_rate'] ?? 0)) / 100.0;
        $annual_rate = $own_ratio * $postal + (1 - $own_ratio) * $bank;

        $part1_principal = $demolition_comp + $g_cost + $h_cost;
        $part1 = $part1_principal * $annual_rate * $loan_years;

        $part2_principal = ($a_total - $condo_fund) + ($c_total - $demolition_comp);
        $part2 = $part2_principal * $annual_rate * $loan_years * 0.5;

        $amount = max(0.0, $part1 + $part2);

        $notes = array();
        $notes[] = sprintf(
            '施工期間 = 地下層 %s月（%s）＋ 地上層 %d月 = %s月；貸款期間 = 施工期間 ＋ 12月 = %s月（%s年）。',
            $this->fmt_num($below_months),
            $floors_below >= 1 ? sprintf('首層4月＋每多一層2月，共%d層%s', $floors_below, $top_down ? '，逆打每層減1.5月' : '') : '無地下層',
            (int) $above_months,
            $this->fmt_num($build_months),
            $this->fmt_num($loan_months),
            $this->fmt_num($loan_years)
        );
        $notes[] = sprintf(
            '年利率 = 自有資金比率 %s%% × 郵政定存 %s%% ＋ 融資比率 %s%% × 五大銀行基準 %s%% = %s%%。',
            $this->fmt_num($own_ratio * 100),
            $this->fmt_num($postal * 100),
            $this->fmt_num((1 - $own_ratio) * 100),
            $this->fmt_num($bank * 100),
            $this->fmt_num($annual_rate * 100)
        );
        $notes[] = sprintf('(1) (拆遷補償 %s ＋ G %s ＋ H %s) × %s%% × %s年 = %s 元。', $this->fmt($demolition_comp), $this->fmt($g_cost), $this->fmt($h_cost), $this->fmt_num($annual_rate * 100), $this->fmt_num($loan_years), $this->fmt($part1));
        $notes[] = sprintf('(2) ((A %s − 公共基金 %s) ＋ (C %s − 拆遷補償 %s)) × %s%% × %s年 × 0.5 = %s 元。', $this->fmt($a_total), $this->fmt($condo_fund), $this->fmt($c_total), $this->fmt($demolition_comp), $this->fmt_num($annual_rate * 100), $this->fmt_num($loan_years), $this->fmt($part2));

        return array(
            'amount'      => $amount,
            'annual_rate' => $annual_rate,
            'loan_months' => $loan_months,
            'part1'       => $part1,
            'part2'       => $part2,
            'note'        => implode(' ', $notes),
            'notes'       => $notes,
        );
    }

    /* ---------------------------------------------------------------------
     * 管理費用費率表
     * ------------------------------------------------------------------- */

    /**
     * 人事行政管理費率 F3（產權級別 × 基地面積）。
     *
     * @param float $grade      產權級別（筆）。
     * @param float $site_area  基地面積（㎡）。
     * @return float
     */
    public function f3_rate($grade, $site_area) {
        // 產權級別欄：<30、30-150、>150。
        if ($grade < 30) {
            $col = 0;
        } elseif ($grade <= 150) {
            $col = 1;
        } else {
            $col = 2;
        }

        // 基地面積列：<1500、1500-2500、>=2500。
        if ($site_area < 1500) {
            $row = 0;
        } elseif ($site_area < 2500) {
            $row = 1;
        } else {
            $row = 2;
        }

        $table = array(
            array(0.04,  0.045, 0.05),
            array(0.045, 0.05,  0.055),
            array(0.05,  0.055, 0.06),
        );

        return $table[$row][$col];
    }

    /**
     * 風險管理費率 F5（產權級別 × 總樓地板面積坪）。
     *
     * @param float $grade      產權級別（筆）。
     * @param float $floor_ping 總樓地板面積（坪）。
     * @return float
     */
    public function f5_rate($grade, $floor_ping) {
        // 產權級別欄：<30、30-100、>100。
        if ($grade < 30) {
            $col = 0;
        } elseif ($grade <= 100) {
            $col = 1;
        } else {
            $col = 2;
        }

        // 總樓地板面積列：<=2500坪、2500-7500坪、>=7500坪。
        if ($floor_ping <= 2500) {
            $row = 0;
        } elseif ($floor_ping < 7500) {
            $row = 1;
        } else {
            $row = 2;
        }

        $table = array(
            array(0.12,  0.125, 0.13),
            array(0.125, 0.13,  0.135),
            array(0.13,  0.135, 0.14),
        );

        return $table[$row][$col];
    }

    /* ---------------------------------------------------------------------
     * 查表輔助
     * ------------------------------------------------------------------- */

    /**
     * 營建單價表面積欄位（0：<2500坪、1：2500-7500坪、2：>=7500坪）。
     *
     * @param float $ping 總樓地板面積（坪）。
     * @return int
     */
    private function construction_area_column($ping) {
        if ($ping < 2500) {
            return 0;
        }
        if ($ping < 7500) {
            return 1;
        }
        return 2;
    }

    /**
     * 營建單價表面積欄位標籤。
     *
     * @param int $col 欄位索引。
     * @return string
     */
    private function construction_area_label($col) {
        $labels = array('未滿2500坪', '2500至未滿7500坪', '7500坪以上');
        return isset($labels[$col]) ? $labels[$col] : '';
    }

    /**
     * 依構造別與地上樓層數決定樓層區間 key。
     *
     * @param string $structure 構造別。
     * @param int    $floors    地上樓層數。
     * @return array{key: string, label: string, note: string}
     */
    private function construction_floor_bucket($structure, $floors) {
        $note = '';

        if ($floors < 6) {
            $note = '註：標準單價表最低級距為 6～10 層，未滿 6 層之建物暫以 6～10 層單價概算，實際請依主管機關認定。';
            return array('key' => '6-10', 'label' => '6～10層', 'note' => $note);
        }

        $buckets = array(
            array('key' => '6-10',  'max' => 10, 'label' => '6～10層'),
            array('key' => '11-15', 'max' => 15, 'label' => '11～15層'),
            array('key' => '16-20', 'max' => 20, 'label' => '16～20層'),
            array('key' => '21-25', 'max' => 25, 'label' => '21～25層'),
            array('key' => '26-30', 'max' => 30, 'label' => '26～30層'),
            array('key' => '31-35', 'max' => 35, 'label' => '31～35層'),
            array('key' => '36+',   'max' => PHP_INT_MAX, 'label' => '36層以上'),
        );

        $available = self::CONSTRUCTION_UNIT_PRICE[$structure];

        foreach ($buckets as $b) {
            if ($floors <= $b['max']) {
                if (!isset($available[$b['key']])) {
                    // 例如鋼筋混凝土無 36+ 級距，退回最高可用級距並註明。
                    $keys = array_keys($available);
                    $last = end($keys);
                    return array(
                        'key'   => $last,
                        'label' => $this->floor_key_label($last),
                        'note'  => sprintf('註：%s於原表無「%s」級距，暫以最高級距「%s」單價概算。', $this->construction_structure_label($structure), $b['label'], $this->floor_key_label($last)),
                    );
                }
                return array('key' => $b['key'], 'label' => $b['label'], 'note' => $note);
            }
        }

        $keys = array_keys($available);
        $last = end($keys);
        return array('key' => $last, 'label' => $this->floor_key_label($last), 'note' => $note);
    }

    /**
     * 樓層 key 對應標籤。
     *
     * @param string $key key。
     * @return string
     */
    private function floor_key_label($key) {
        $map = array(
            '6-10' => '6～10層', '11-15' => '11～15層', '16-20' => '16～20層',
            '21-25' => '21～25層', '26-30' => '26～30層', '31-35' => '31～35層', '36+' => '36層以上',
        );
        return isset($map[$key]) ? $map[$key] : $key;
    }

    /**
     * 構造別標籤（營建）。
     *
     * @param string $structure key。
     * @return string
     */
    private function construction_structure_label($structure) {
        $map = array('steel' => '鋼骨造', 'src' => '鋼骨鋼筋混凝土造', 'rc' => '鋼筋混凝土造');
        return isset($map[$structure]) ? $map[$structure] : $structure;
    }

    /**
     * 構造別標籤（拆除）。
     *
     * @param string $structure key。
     * @return string
     */
    private function demolition_structure_label($structure) {
        $map = array(
            'steel' => '鋼骨造', 'src' => '鋼骨鋼筋混凝土造', 'rc' => '鋼筋混凝土造',
            'reinforced' => '加強磚造', 'brick' => '磚造', 'wood' => '竹木造',
            'stone' => '漿砌卵石', 'metal_shed' => '金屬或鋼鐵棚架',
        );
        return isset($map[$structure]) ? $map[$structure] : $structure;
    }

    /* ---------------------------------------------------------------------
     * 小工具
     * ------------------------------------------------------------------- */

    /**
     * 常數存取（供 note 內插）。
     *
     * @return int
     */
    private function UTILITY() {
        return self::UTILITY_LINE_PER_HOUSEHOLD;
    }

    /**
     * 加總項目金額。
     *
     * @param array $items 項目陣列。
     * @return float
     */
    private function sum_items(array $items) {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += (float) $item['amount'];
        }
        return $sum;
    }

    /**
     * 金額格式化（整數元，千分位）。
     *
     * @param float $n 數字。
     * @return string
     */
    private function fmt($n) {
        return number_format((float) $n, 0);
    }

    /**
     * 一般數字格式化（去除無意義小數）。
     *
     * @param float $n 數字。
     * @return string
     */
    private function fmt_num($n) {
        $n = (float) $n;
        return rtrim(rtrim(number_format($n, 2), '0'), '.');
    }

    /**
     * 費率百分比格式化。
     *
     * @param float $rate 比率（0.045）。
     * @return string
     */
    private function fmt_pct($rate) {
        return $this->fmt_num($rate * 100);
    }
}
