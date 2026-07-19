<?php
/**
 * UR AI Assistant Tax Calculator Service
 *
 * 土地增值稅／契稅試算引擎。純 PHP、無 WordPress 依賴，方便單元測試。
 *
 * 法源與公式依據：
 * - 土地增值稅：土地稅法第 33 條；公式與稅率速算表比照財政部稅務入口網
 *   「土地增值稅試算」頁面（etax.nat.gov.tw）公告內容。
 * - 契稅：契稅條例第 3 條稅率表。
 * - 都市更新減免：都市更新條例第 67 條第 1 項各款（詳見
 *   resolve_urban_renewal_reduction() 內個別條列的法條依據）。
 *
 * 設計原則：
 * - 只計算「一般稅額」與「都市更新條例第 67 條」明文列出的減免情境，
 *   不涉及「都市危險及老舊建築物加速重建條例」（危老條例）——危老條例
 *   第 8 條只有房屋稅／地價稅減半，並無土地增值稅／契稅減免規定，
 *   刻意在情境清單中明列「危老重建」並標示不適用，而不是直接不提供
 *   這個選項，避免使用者誤以為工具漏掉這個情境、或誤套用都更的減免。
 * - 都市更新條例第 67 條第 1 項第 3 款（現金補償減徵）與第 8 款
 *   （協議合建減徵）有「落日期限」，目前經行政院公告展延至民國 118 年
 *   （西元 2029 年）1 月 31 日止；超過這個日期後，這兩項減免是否會
 *   再展延屬未來未知數，需要屆時人工確認並更新
 *   URBAN_RENEWAL_SUNSET_EXPIRES_AT 常數。
 * - 這是「一般民眾／地政士參考用」的概算工具，不是正式稅捐核定；
 *   所有輸出都刻意保留完整拆解過程，而不是只給一個總金額，方便使用者
 *   核對每一步計算是否符合自己的實際情況。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Tax_Calculator_Service
 */
class UR_AI_Tax_Calculator_Service {

    /**
     * 都市更新條例第 67 條第 1 項第 3 款／第 8 款減免的目前展延截止日
     * （民國 113 年 2 月 1 日至 118 年 1 月 31 日，行政院 112 年 12 月
     * 29 日院臺建字第 1121043926 號令展延）。
     *
     * @var string
     */
    const URBAN_RENEWAL_SUNSET_EXPIRES_AT = '2029-01-31';

    /**
     * 自用住宅用地面積上限（平方公尺）：都市土地 300、非都市土地 700。
     *
     * @var array
     */
    const SELF_USE_AREA_CAP = array(
        'urban'     => 300.0,
        'non_urban' => 700.0,
    );

    /**
     * 自用住宅用地稅率（土地稅法第 34 條）。
     *
     * @var float
     */
    const SELF_USE_RATE = 0.10;

    /**
     * 一般用地稅率速算表（依持有年限分 4 級距，每級距 3 稅級）。
     *
     * 每筆為 array(rate, deduction_ratio)：
     * 應徵稅額 = 土地漲價總數額 × rate － 原地價調整後總額 × deduction_ratio。
     * 數字經與土地稅法第 33 條及財政部稅務入口網「一般用地稅率速算表」
     * 交叉核對一致。
     *
     * @var array
     */
    const GENERAL_RATE_TABLE = array(
        // 持有 20 年以下：無減徵。
        '0-20'  => array(
            1 => array('rate' => 0.20, 'deduction_ratio' => 0.00),
            2 => array('rate' => 0.30, 'deduction_ratio' => 0.10),
            3 => array('rate' => 0.40, 'deduction_ratio' => 0.30),
        ),
        // 持有超過 20 年：減徵率 20%。
        '20-30' => array(
            1 => array('rate' => 0.20, 'deduction_ratio' => 0.00),
            2 => array('rate' => 0.28, 'deduction_ratio' => 0.08),
            3 => array('rate' => 0.36, 'deduction_ratio' => 0.24),
        ),
        // 持有超過 30 年：減徵率 30%。
        '30-40' => array(
            1 => array('rate' => 0.20, 'deduction_ratio' => 0.00),
            2 => array('rate' => 0.27, 'deduction_ratio' => 0.07),
            3 => array('rate' => 0.34, 'deduction_ratio' => 0.21),
        ),
        // 持有超過 40 年：減徵率 40%。
        '40+'   => array(
            1 => array('rate' => 0.20, 'deduction_ratio' => 0.00),
            2 => array('rate' => 0.26, 'deduction_ratio' => 0.06),
            3 => array('rate' => 0.32, 'deduction_ratio' => 0.18),
        ),
    );

    /**
     * 契稅稅率（契稅條例第 3 條）。
     *
     * @var array
     */
    const DEED_TAX_RATES = array(
        'buy'       => 0.06, // 買賣。
        'dian'      => 0.04, // 典權。
        'exchange'  => 0.02, // 交換。
        'gift'      => 0.06, // 贈與。
        'partition' => 0.02, // 分割。
        'possess'   => 0.06, // 占有。
    );

    /**
     * 計算土地增值稅（一般稅額，未套用都更減免）。
     *
     * @param array $args {
     *     @type string $land_type        'urban' 或 'non_urban'（決定自用住宅用地面積上限）。
     *     @type bool   $self_use         是否為自用住宅用地。
     *     @type float  $area             土地宗地面積（平方公尺，若只移轉部分持分，這裡填整筆土地面積，持分另由 share_numerator/denominator 處理）。
     *     @type float  $share_numerator   移轉持分分子（預設 1）。
     *     @type float  $share_denominator 移轉持分分母（預設 1）。
     *     @type float  $current_value    土地公告現值（元／平方公尺）。
     *     @type float  $original_value   原規定地價或前次移轉現值（元／平方公尺）。
     *     @type float  $cpi_percent      消費者物價總指數（例：105 代表 105%）。
     *     @type int    $holding_years    持有年限（年）。
     *     @type float  $land_value_tax_credit 准予抵繳之增繳地價稅額（元，預設 0）。
     * }
     * @return array 完整拆解結果，見內文各 key。
     */
    public function calculate_land_value_increment_tax(array $args) {
        $land_type   = ('non_urban' === ($args['land_type'] ?? 'urban')) ? 'non_urban' : 'urban';
        $self_use    = !empty($args['self_use']);
        $area        = max(0.0, (float) ($args['area'] ?? 0));
        $numerator   = (float) ($args['share_numerator'] ?? 1);
        $denominator = (float) ($args['share_denominator'] ?? 1);
        $share_ratio = ($numerator > 0 && $denominator > 0) ? min(1.0, $numerator / $denominator) : 1.0;

        $current_value  = max(0.0, (float) ($args['current_value'] ?? 0));
        $original_value = max(0.0, (float) ($args['original_value'] ?? 0));
        $cpi_percent    = max(0.0, (float) ($args['cpi_percent'] ?? 100));
        $holding_years  = max(0, (int) ($args['holding_years'] ?? 0));
        $credit         = max(0.0, (float) ($args['land_value_tax_credit'] ?? 0));

        $effective_area = $area * $share_ratio;

        if ($effective_area <= 0 || $current_value <= 0) {
            return $this->error('請輸入有效的土地面積與土地公告現值。');
        }

        $adjusted_original_unit  = $original_value * ($cpi_percent / 100.0);
        $current_value_total     = $current_value * $effective_area;
        $adjusted_original_total = $adjusted_original_unit * $effective_area;
        $increment_total         = max(0.0, $current_value_total - $adjusted_original_total);

        $notes = array();
        $notes[] = sprintf(
            '土地漲價總數額 = 現值總額（%s）－ 原地價調整後總額（%s） = %s 元',
            $this->fmt_money($current_value_total),
            $this->fmt_money($adjusted_original_total),
            $this->fmt_money($increment_total)
        );

        $self_use_cap = self::SELF_USE_AREA_CAP[$land_type];

        $self_use_area    = 0.0;
        $general_area     = $effective_area;
        $self_use_tax     = 0.0;
        $general_increment = $increment_total;
        $general_original  = $adjusted_original_total;

        if ($self_use) {
            $self_use_area = min($effective_area, $self_use_cap);
            $general_area  = max(0.0, $effective_area - $self_use_area);

            // 依面積比例分攤漲價總數額與調整後原地價（單價一致，可線性分攤）。
            $self_use_ratio     = $self_use_area / $effective_area;
            $self_use_increment = $increment_total * $self_use_ratio;
            $general_increment  = $increment_total - $self_use_increment;
            $general_original   = $adjusted_original_total * (1 - $self_use_ratio);

            $self_use_tax = $self_use_increment * self::SELF_USE_RATE;

            $notes[] = sprintf(
                '自用住宅用地面積上限 %s 平方公尺（%s）；移轉面積 %s 平方公尺中，%s 平方公尺適用自用稅率 10%%，超過部分 %s 平方公尺適用一般稅率。',
                $this->fmt_num($self_use_cap),
                'urban' === $land_type ? '都市土地' : '非都市土地',
                $this->fmt_num($effective_area),
                $this->fmt_num($self_use_area),
                $this->fmt_num($general_area)
            );
            $notes[] = sprintf('自用住宅用地稅額 = %s × 10%% = %s 元', $this->fmt_money($self_use_increment), $this->fmt_money($self_use_tax));
        }

        $general_tax = 0.0;
        $tier_info   = null;

        if ($general_area > 0 && $general_increment > 0) {
            $bracket = $this->holding_years_bracket($holding_years);
            $ratio   = $general_original > 0 ? $general_increment / $general_original : 0.0;
            $tier    = $this->determine_tier($ratio);

            $rate_info   = self::GENERAL_RATE_TABLE[$bracket][$tier];
            $general_tax = ($general_increment * $rate_info['rate']) - ($general_original * $rate_info['deduction_ratio']);
            $general_tax = max(0.0, $general_tax);

            $tier_info = array(
                'bracket'         => $bracket,
                'tier'            => $tier,
                'ratio'           => $ratio,
                'rate'            => $rate_info['rate'],
                'deduction_ratio' => $rate_info['deduction_ratio'],
            );

            $notes[] = sprintf(
                '一般用地部分：漲價倍數 = %s ÷ %s = %s（第 %d 級）；持有 %d 年（級距：%s），適用稅率 %s%%、扣減率 %s%%。',
                $this->fmt_money($general_increment),
                $this->fmt_money($general_original),
                $this->fmt_num($ratio * 100) . '%',
                $tier,
                $holding_years,
                $bracket,
                $this->fmt_num($rate_info['rate'] * 100),
                $this->fmt_num($rate_info['deduction_ratio'] * 100)
            );
            $notes[] = sprintf(
                '一般用地稅額 = %s × %s%% － %s × %s%% = %s 元',
                $this->fmt_money($general_increment),
                $this->fmt_num($rate_info['rate'] * 100),
                $this->fmt_money($general_original),
                $this->fmt_num($rate_info['deduction_ratio'] * 100),
                $this->fmt_money($general_tax)
            );
        }

        $assessed_tax = $self_use_tax + $general_tax;
        $base_tax     = max(0.0, $assessed_tax - $credit);

        if ($credit > 0) {
            $notes[] = sprintf('查定稅額 %s 元，扣除准予抵繳之增繳地價稅額 %s 元後，一般稅額為 %s 元。', $this->fmt_money($assessed_tax), $this->fmt_money($credit), $this->fmt_money($base_tax));
        }

        $reduction = $this->resolve_urban_renewal_reduction($args['reduction_scenario'] ?? 'none', 'land_value_tax');

        $final_tax = $this->apply_reduction($base_tax, $reduction);

        return array(
            'success'                 => true,
            'effective_area'          => $effective_area,
            'current_value_total'     => $current_value_total,
            'adjusted_original_total' => $adjusted_original_total,
            'increment_total'         => $increment_total,
            'self_use_area'           => $self_use_area,
            'general_area'            => $general_area,
            'self_use_tax'            => $self_use_tax,
            'general_tax'             => $general_tax,
            'tier_info'               => $tier_info,
            'assessed_tax'            => $assessed_tax,
            'land_value_tax_credit'   => $credit,
            'base_tax'                => $base_tax,
            'reduction'               => $reduction,
            'final_tax'               => $final_tax,
            'notes'                   => $notes,
        );
    }

    /**
     * 計算契稅（一般稅額，未套用都更減免）。
     *
     * @param array $args {
     *     @type float  $declared_value     申報契價（房屋現值，元）。
     *     @type string $transfer_type      buy/dian/exchange/gift/partition/possess。
     *     @type string $reduction_scenario 都更減免情境 key。
     * }
     * @return array
     */
    public function calculate_deed_tax(array $args) {
        $declared_value = max(0.0, (float) ($args['declared_value'] ?? 0));
        $transfer_type  = (string) ($args['transfer_type'] ?? 'buy');

        if (!isset(self::DEED_TAX_RATES[$transfer_type])) {
            $transfer_type = 'buy';
        }

        if ($declared_value <= 0) {
            return $this->error('請輸入有效的申報契價（房屋現值）。');
        }

        $rate     = self::DEED_TAX_RATES[$transfer_type];
        $base_tax = $declared_value * $rate;

        $notes = array(
            sprintf('契稅 = 申報契價 %s × 稅率 %s%% = %s 元。', $this->fmt_money($declared_value), $this->fmt_num($rate * 100), $this->fmt_money($base_tax)),
        );

        $reduction = $this->resolve_urban_renewal_reduction($args['reduction_scenario'] ?? 'none', 'deed_tax');

        $final_tax = $this->apply_reduction($base_tax, $reduction);

        return array(
            'success'        => true,
            'declared_value' => $declared_value,
            'transfer_type'  => $transfer_type,
            'rate'           => $rate,
            'base_tax'       => $base_tax,
            'reduction'      => $reduction,
            'final_tax'      => $final_tax,
            'notes'          => $notes,
        );
    }

    /**
     * 依都更減免情境與稅目，查出適用的減免規則。
     *
     * 每個情境的法條依據、是否有落日期限，皆列於下方陣列，供前台顯示、
     * 也供未來法規異動時集中一處修改。
     *
     * @param string $scenario 情境 key。
     * @param string $tax_type 'land_value_tax' 或 'deed_tax'。
     * @return array{ eligible: bool, rate: float, exempt: bool, label: string, legal_basis: string, sunset_expires_at: string|null, note: string }
     */
    public function resolve_urban_renewal_reduction($scenario, $tax_type) {
        $scenarios = $this->urban_renewal_scenarios();

        if (!isset($scenarios[$scenario])) {
            $scenario = 'none';
        }

        $config = $scenarios[$scenario];
        $applies_to = $config['applies_to'];

        // 情境不適用於這個稅目（例如現金補償沒有契稅事件）。
        if ('none' !== $scenario && !in_array($tax_type, $applies_to, true)) {
            return array(
                'scenario'          => $scenario,
                'eligible'          => false,
                'rate'              => 0.0,
                'exempt'            => false,
                'label'             => $config['label'],
                'legal_basis'       => $config['legal_basis'],
                'sunset_expires_at' => null,
                'note'              => $config['label'] . '此情境不涉及' . ('land_value_tax' === $tax_type ? '土地增值稅' : '契稅') . '，本欄不適用減免。',
            );
        }

        $eligible = $config['eligible'];
        $note     = $config['note'];

        // 落日期限檢查。
        if ($eligible && null !== $config['sunset_expires_at']) {
            $today = function_exists('current_time') ? current_time('Y-m-d') : gmdate('Y-m-d');

            if ($today > $config['sunset_expires_at']) {
                $eligible = false;
                $note     = '此項減免優惠實施期限已於 ' . $config['sunset_expires_at'] . ' 屆滿，是否再展延請洽詢主管機關確認最新公告，本次試算暫以「不適用」計算。';
            }
        }

        return array(
            'scenario'          => $scenario,
            'eligible'          => $eligible,
            'rate'              => $eligible ? $config['rate'] : 0.0,
            'exempt'            => $eligible && $config['exempt'],
            'label'             => $config['label'],
            'legal_basis'       => $config['legal_basis'],
            'sunset_expires_at' => $config['sunset_expires_at'],
            'note'              => $note,
        );
    }

    /**
     * 都更減免情境定義表。
     *
     * @return array
     */
    private function urban_renewal_scenarios() {
        return array(
            'none' => array(
                'label'             => '一般移轉（無都更減免）',
                'applies_to'        => array('land_value_tax', 'deed_tax'),
                'eligible'          => false,
                'rate'              => 0.0,
                'exempt'            => false,
                'legal_basis'       => '',
                'sunset_expires_at' => null,
                'note'              => '一般稅額，未套用任何都市更新條例減免。',
            ),
            'rights_conversion_first_transfer' => array(
                'label'             => '都市更新：權利變換取得房地，首次移轉',
                'applies_to'        => array('land_value_tax', 'deed_tax'),
                'eligible'          => true,
                'rate'              => 0.4,
                'exempt'            => false,
                'legal_basis'       => '都市更新條例第 67 條第 1 項第 2 款',
                'sunset_expires_at' => null,
                'note'              => '權利變換取得之土地及建築物，於更新後第一次移轉時，減徵土地增值稅及契稅 40%。',
            ),
            'cash_compensation' => array(
                'label'             => '都市更新：不願參與權利變換，改領現金補償',
                'applies_to'        => array('land_value_tax'),
                'eligible'          => true,
                'rate'              => 0.4,
                'exempt'            => false,
                'legal_basis'       => '都市更新條例第 67 條第 1 項第 3 款',
                'sunset_expires_at' => self::URBAN_RENEWAL_SUNSET_EXPIRES_AT,
                'note'              => '不願參與權利變換而領取現金補償者，減徵土地增值稅 40%（無對應契稅事件）。此項優惠有實施期限。',
            ),
            'undersized_cash_conversion' => array(
                'label'             => '都市更新：應分配面積未達最小分配面積單元，改領現金',
                'applies_to'        => array('land_value_tax'),
                'eligible'          => true,
                'rate'              => 1.0,
                'exempt'            => true,
                'legal_basis'       => '都市更新條例第 67 條第 1 項第 6 款',
                'sunset_expires_at' => null,
                'note'              => '實施權利變換時，應分配之土地未達最小分配面積單元而改領現金者，免徵土地增值稅（無對應契稅事件）。',
            ),
            'in_kind_offset' => array(
                'label'             => '都市更新：以分配之土地及建築物折抵負擔',
                'applies_to'        => array('land_value_tax', 'deed_tax'),
                'eligible'          => true,
                'rate'              => 1.0,
                'exempt'            => true,
                'legal_basis'       => '都市更新條例第 67 條第 1 項第 7 款',
                'sunset_expires_at' => null,
                'note'              => '實施權利變換時，以分配之土地及建築物折抵應負擔之費用者，免徵土地增值稅及契稅。',
            ),
            'negotiated_reconstruction' => array(
                'label'             => '都市更新：協議合建移轉，經主管機關同意',
                'applies_to'        => array('land_value_tax', 'deed_tax'),
                'eligible'          => true,
                'rate'              => 0.4,
                'exempt'            => false,
                'legal_basis'       => '都市更新條例第 67 條第 1 項第 8 款',
                'sunset_expires_at' => self::URBAN_RENEWAL_SUNSET_EXPIRES_AT,
                'note'              => '原所有權人與實施者依協議合建方式辦理產權移轉，經地方主管機關同意者，減徵土地增值稅及契稅 40%。此項優惠有實施期限。',
            ),
            'danger_old_building' => array(
                'label'             => '危老重建（都市危險及老舊建築物加速重建條例）',
                'applies_to'        => array('land_value_tax', 'deed_tax'),
                'eligible'          => false,
                'rate'              => 0.0,
                'exempt'            => false,
                'legal_basis'       => '都市危險及老舊建築物加速重建條例第 8 條',
                'sunset_expires_at' => null,
                'note'              => '危老條例第 8 條僅有房屋稅／地價稅減半優惠，並無土地增值稅或契稅減免規定，都市更新條例第 67 條的 40% 減徵僅適用於「都市更新」（權利變換／協議合建），不適用於危老重建。本項僅供對照說明，試算一律以一般稅額計算。',
            ),
        );
    }

    /**
     * 依減免規則套用到稅額上。
     *
     * @param float $base_tax  一般稅額。
     * @param array $reduction resolve_urban_renewal_reduction() 回傳值。
     * @return float
     */
    private function apply_reduction($base_tax, array $reduction) {
        if (!$reduction['eligible']) {
            return $base_tax;
        }

        if ($reduction['exempt']) {
            return 0.0;
        }

        return max(0.0, $base_tax * (1 - $reduction['rate']));
    }

    /**
     * 依持有年限決定級距 key。
     *
     * @param int $years 持有年限。
     * @return string
     */
    private function holding_years_bracket($years) {
        if ($years > 40) {
            return '40+';
        }
        if ($years > 30) {
            return '30-40';
        }
        if ($years > 20) {
            return '20-30';
        }
        return '0-20';
    }

    /**
     * 依漲價倍數決定稅級（1/2/3）。
     *
     * @param float $ratio 漲價倍數（土地漲價總數額 ÷ 原地價調整後總額）。
     * @return int
     */
    private function determine_tier($ratio) {
        if ($ratio >= 2.0) {
            return 3;
        }
        if ($ratio >= 1.0) {
            return 2;
        }
        return 1;
    }

    /**
     * 錯誤回應。
     *
     * @param string $message 訊息。
     * @return array
     */
    private function error($message) {
        return array('success' => false, 'message' => $message);
    }

    /**
     * 金額格式化（整數元，千分位）。
     *
     * @param float $n 數字。
     * @return string
     */
    private function fmt_money($n) {
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
}
