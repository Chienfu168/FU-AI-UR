<?php
/**
 * 都更分回試算 前台 view（單一計算模式：容積率＋獎勵驅動，附公式透明拆解與友善列印）。
 *
 * @var array $cities     縣市選項（已不再使用，保留相容）。
 * @var array $settings   全部設定。
 * @var array $bonus_opts 其他獎勵選項。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$cf7_id = UR_AI_Calculator_Settings::get_cf7_form_id();
$hook_t = isset($settings['lead_hook_title']) ? $settings['lead_hook_title'] : '';
$hook_s = isset($settings['lead_hook_subtitle']) ? $settings['lead_hook_subtitle'] : '';
$notice = isset($settings['public_ratio_notice']) ? $settings['public_ratio_notice'] : '';
$discl  = isset($settings['disclaimer']) ? $settings['disclaimer'] : '';

$massing_params        = UR_AI_Calculator_Settings::get_massing_params();
$massing_floor_height  = $massing_params['floor_height'];
$massing_coverage_hint = '' !== $massing_params['coverage_hint'] ? $massing_params['coverage_hint'] : __('不確定可向地政或都發局查詢；住三常見約 45%，各分區規定不同。', 'ur-ai-assistant');
?>
<div class="ur-ai-calc">

    <div class="ur-ai-calc__intro">
        <h3 class="ur-ai-calc__title"><?php esc_html_e('都更分回效益試算', 'ur-ai-assistant'); ?></h3>
        <p class="ur-ai-calc__lead"><?php esc_html_e('依「土地持分 × 容積率 × 容積獎勵 × 實設係數 × 分回比例」估算重建後可分回坪數（台北市／新北市都市更新）。每個欄位都會影響結果，並附完整試算過程。', 'ur-ai-assistant'); ?></p>
    </div>

    <!-- 土地面積輸入（共用：立即試算／進階試算都用同一組值） -->
    <div class="ur-ai-calc__panel ur-ai-calc__panel--shared">

        <div class="ur-ai-calc__field">
            <label class="ur-ai-calc__label"><?php esc_html_e('土地面積輸入方式', 'ur-ai-assistant'); ?></label>
            <div class="ur-ai-calc__mode-switch">
                <label class="ur-ai-calc__mode-option">
                    <input type="radio" name="ur-ai-calc-share-mode" value="pings" data-calc="share_mode" checked>
                    <?php esc_html_e('我知道持分坪數', 'ur-ai-assistant'); ?>
                </label>
                <label class="ur-ai-calc__mode-option">
                    <input type="radio" name="ur-ai-calc-share-mode" value="ratio" data-calc="share_mode">
                    <?php esc_html_e('用基地面積＋持分比例推算', 'ur-ai-assistant'); ?>
                </label>
            </div>
            <p class="ur-ai-calc__hint"><?php esc_html_e('都更以整塊基地共同辦理，單一土地無法自行都更。若你知道基地總面積與自己的持分比例（土地權狀「權利範圍」），改用右邊方式可額外算出樓層／高度概估；下方「立即試算」與「進階試算」都會使用這裡填的土地面積。', 'ur-ai-assistant'); ?></p>
        </div>

        <!-- 持分坪數模式欄位 -->
        <div class="ur-ai-calc__field" data-calc-pings-field>
            <label class="ur-ai-calc__label"><?php esc_html_e('土地持分坪數（坪）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" step="0.01" class="ur-ai-calc__input" data-calc="site_area" placeholder="<?php esc_attr_e('例：10', 'ur-ai-assistant'); ?>">
            <p class="ur-ai-calc__hint"><?php esc_html_e('請填你的土地持分坪數（見土地所有權狀的「權利範圍／持分面積」）。整合公司評估全案時，可改填基地總面積。', 'ur-ai-assistant'); ?></p>
        </div>

        <!-- 比例模式欄位（預設隱藏） -->
        <div class="ur-ai-calc__ratio-fields" data-calc-ratio-fields hidden>

            <div class="ur-ai-calc__field">
                <label class="ur-ai-calc__label"><?php esc_html_e('基地總面積（坪）', 'ur-ai-assistant'); ?></label>
                <input type="number" min="0" step="0.01" class="ur-ai-calc__input" data-calc="site_total_area" placeholder="<?php esc_attr_e('例：150', 'ur-ai-assistant'); ?>">
                <p class="ur-ai-calc__hint"><?php esc_html_e('全體地主土地合計的總面積，非你個人持分。', 'ur-ai-assistant'); ?></p>
            </div>

            <div class="ur-ai-calc__field">
                <label class="ur-ai-calc__label"><?php esc_html_e('土地持分（分子／分母）', 'ur-ai-assistant'); ?></label>
                <div class="ur-ai-calc__inline-fields">
                    <input type="number" min="0" step="1" class="ur-ai-calc__input ur-ai-calc__input--inline" data-calc="share_numerator" placeholder="<?php esc_attr_e('分子，例：120', 'ur-ai-assistant'); ?>">
                    <span class="ur-ai-calc__inline-sep">/</span>
                    <input type="number" min="0" step="1" class="ur-ai-calc__input ur-ai-calc__input--inline" data-calc="share_denominator" placeholder="<?php esc_attr_e('分母，例：1000', 'ur-ai-assistant'); ?>">
                </div>
                <p class="ur-ai-calc__hint"><?php esc_html_e('請照土地所有權狀「權利範圍」欄位填寫，例如 120/1000。', 'ur-ai-assistant'); ?></p>
            </div>

            <div class="ur-ai-calc__field">
                <label class="ur-ai-calc__label"><?php esc_html_e('建蔽率（%，選填）', 'ur-ai-assistant'); ?></label>
                <input type="number" min="0" max="100" step="1" class="ur-ai-calc__input" data-calc="coverage_ratio" placeholder="<?php esc_attr_e('例：45', 'ur-ai-assistant'); ?>">
                <p class="ur-ai-calc__hint"><?php echo esc_html($massing_coverage_hint); ?></p>
            </div>

            <div class="ur-ai-calc__field">
                <label class="ur-ai-calc__label"><?php esc_html_e('單層樓高（米，選填）', 'ur-ai-assistant'); ?></label>
                <input type="number" min="0" step="0.1" class="ur-ai-calc__input" data-calc="floor_height" value="<?php echo esc_attr($massing_floor_height); ?>">
                <p class="ur-ai-calc__hint"><?php esc_html_e('住宅常見約 3~3.3 米／層，不確定可保留預設值。填了建蔽率才會顯示樓層／高度概估。', 'ur-ai-assistant'); ?></p>
            </div>

        </div>
    </div>

    <div class="ur-ai-calc__panel" data-calc-track="single">

        <div class="ur-ai-calc__field">
            <label class="ur-ai-calc__label"><?php esc_html_e('使用分區（選填）', 'ur-ai-assistant'); ?></label>
            <input type="text" class="ur-ai-calc__input" data-calc="zone-site" placeholder="<?php esc_attr_e('例：住三', 'ur-ai-assistant'); ?>">
            <p class="ur-ai-calc__hint"><?php esc_html_e('可向地政事務所或各縣市都市發展局查詢。此欄為標示用，實際計算以下方容積率為準。', 'ur-ai-assistant'); ?></p>
        </div>

        <div class="ur-ai-calc__field">
            <label class="ur-ai-calc__label"><?php esc_html_e('法定（基準）容積率（%）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" step="1" class="ur-ai-calc__input" data-calc="far" placeholder="<?php esc_attr_e('例：225', 'ur-ai-assistant'); ?>">
            <p class="ur-ai-calc__hint"><?php esc_html_e('請填入該分區的法定容積率。台北市常見：住二 120、住三 225、住三之一 300、商二 630；新北市請依各計畫區規定查填。', 'ur-ai-assistant'); ?></p>
        </div>

        <div class="ur-ai-calc__field">
            <label class="ur-ai-calc__label"><?php esc_html_e('一般都更容積獎勵（%）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" max="50" step="1" class="ur-ai-calc__input" data-calc="general_bonus" value="50">
            <p class="ur-ai-calc__hint"><?php esc_html_e('一般都更容積獎勵上限為 50%（基準容積 1.5 倍）。不確定可保留 50。', 'ur-ai-assistant'); ?></p>
        </div>

        <?php foreach (array('other_bonus_1', 'other_bonus_2') as $slot) : ?>
        <div class="ur-ai-calc__field">
            <label class="ur-ai-calc__label">
                <?php echo esc_html('other_bonus_1' === $slot ? __('其他獎勵 1（選填）', 'ur-ai-assistant') : __('其他獎勵 2（選填）', 'ur-ai-assistant')); ?>
            </label>
            <select class="ur-ai-calc__input" data-calc="<?php echo esc_attr($slot); ?>">
                <?php foreach ($bonus_opts as $opt) : ?>
                    <option value="<?php echo esc_attr($opt['key']); ?>" data-custom="<?php echo !empty($opt['custom']) ? '1' : '0'; ?>">
                        <?php echo esc_html($opt['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="number" min="0" max="100" step="1" class="ur-ai-calc__input ur-ai-calc__custom" data-calc="<?php echo esc_attr($slot); ?>_custom" placeholder="<?php esc_attr_e('容積移轉 %', 'ur-ai-assistant'); ?>" style="display:none;">
        </div>
        <?php endforeach; ?>

        <p class="ur-ai-calc__cap-note"><?php esc_html_e('註：一般獎勵與其他獎勵加總後，總獎勵上限為基準容積 2 倍（+100%），超過自動以上限計算。', 'ur-ai-assistant'); ?></p>

        <button type="button" class="ur-ai-calc__btn" data-calc-action="compute" data-track="single">
            <?php esc_html_e('立即試算', 'ur-ai-assistant'); ?>
        </button>
    </div>

    <!-- 進階評估：三案擇優 -->
    <details class="ur-ai-calc__advanced">
        <summary class="ur-ai-calc__advanced-summary"><?php esc_html_e('進階評估：三種都更獎勵擇優（老屋更準）', 'ur-ai-assistant'); ?></summary>

        <div class="ur-ai-calc__panel" data-calc-track="advanced">
            <p class="ur-ai-calc__hint" style="margin-top:0;">
                <?php esc_html_e('進階版會以最上方填入的「土地面積」與「法定容積率」為基礎，再加計你的原建築條件，自動比較三種都更獎勵公式取最有利者。適合原本就蓋得較滿的老屋。', 'ur-ai-assistant'); ?>
            </p>

            <div class="ur-ai-calc__field">
                <label class="ur-ai-calc__label"><?php esc_html_e('原建築容積率（%）', 'ur-ai-assistant'); ?></label>
                <input type="number" min="0" step="1" class="ur-ai-calc__input" data-calc="far_origin" placeholder="<?php esc_attr_e('例：300', 'ur-ai-assistant'); ?>">
                <p class="ur-ai-calc__hint"><?php esc_html_e('你原本建物的容積率。不確定可用「樓層數 × 建蔽率」概估，老公寓常見約 200~360%。例如 4 層 × 建蔽率 60% ≈ 240%。', 'ur-ai-assistant'); ?></p>
            </div>

            <div class="ur-ai-calc__field">
                <label class="ur-ai-calc__label"><?php esc_html_e('建物類型', 'ur-ai-assistant'); ?></label>
                <select class="ur-ai-calc__input" data-calc="building_type">
                    <option value="normal"><?php esc_html_e('一般建築', 'ur-ai-assistant'); ?></option>
                    <option value="risk"><?php esc_html_e('危險建築（結構危險）', 'ur-ai-assistant'); ?></option>
                    <option value="seasand"><?php esc_html_e('海砂屋', 'ur-ai-assistant'); ?></option>
                </select>
                <p class="ur-ai-calc__hint"><?php esc_html_e('危險建築或海砂屋，原容積保障係數較高（C 案由 1.2 提高為 1.3）。', 'ur-ai-assistant'); ?></p>
            </div>

            <button type="button" class="ur-ai-calc__btn" data-calc-action="compute" data-track="advanced">
                <?php esc_html_e('進階試算（三案擇優）', 'ur-ai-assistant'); ?>
            </button>
        </div>
    </details>

    <!-- 結果區 -->
    <div class="ur-ai-calc__result" data-calc-result hidden>

        <div class="ur-ai-calc__result-head">
            <span class="ur-ai-calc__result-date" data-calc-date></span>
            <div class="ur-ai-calc__actions">
                <button type="button" class="ur-ai-calc__share-btn ur-ai-calc__share-btn--line" data-calc-action="share-line">
                    <?php esc_html_e('分享到 LINE', 'ur-ai-assistant'); ?>
                </button>
                <button type="button" class="ur-ai-calc__share-btn ur-ai-calc__share-btn--fb" data-calc-action="share-fb">
                    <?php esc_html_e('分享到 FB', 'ur-ai-assistant'); ?>
                </button>
                <button type="button" class="ur-ai-calc__share-btn" data-calc-action="share-copy">
                    <?php esc_html_e('複製連結', 'ur-ai-assistant'); ?>
                </button>
                <button type="button" class="ur-ai-calc__print-btn" data-calc-action="print">
                    <?php esc_html_e('🖨 列印', 'ur-ai-assistant'); ?>
                </button>
            </div>
        </div>
        <p class="ur-ai-calc__share-hint" data-calc-share-hint hidden></p>

        <div class="ur-ai-calc__compare">
            <div class="ur-ai-calc__compare-box ur-ai-calc__compare-box--before">
                <span class="ur-ai-calc__compare-label"><?php esc_html_e('土地面積', 'ur-ai-assistant'); ?></span>
                <span class="ur-ai-calc__compare-value" data-calc-before>—</span>
            </div>
            <div class="ur-ai-calc__compare-arrow">→</div>
            <div class="ur-ai-calc__compare-box ur-ai-calc__compare-box--after">
                <span class="ur-ai-calc__compare-label"><?php esc_html_e('重建後預估可分回', 'ur-ai-assistant'); ?></span>
                <span class="ur-ai-calc__compare-value" data-calc-after>—</span>
            </div>
        </div>

        <!-- 三案擇優比較（僅進階模式顯示） -->
        <div class="ur-ai-calc__paths" data-calc-paths hidden></div>

        <!-- 樓層／高度概估（基地面積＋持分比例模式，且填了建蔽率時顯示；立即試算／進階試算皆適用） -->
        <div class="ur-ai-calc__massing" data-calc-massing hidden></div>

        <!-- 公式透明拆解 -->
        <div class="ur-ai-calc__breakdown">
            <p class="ur-ai-calc__breakdown-title"><?php esc_html_e('試算過程（公式透明）', 'ur-ai-assistant'); ?></p>
            <ol class="ur-ai-calc__breakdown-list" data-calc-breakdown></ol>
        </div>

        <?php if ('' !== $notice) : ?>
        <div class="ur-ai-calc__public-notice">
            <span class="ur-ai-calc__public-notice-badge"><?php esc_html_e('坪數提醒', 'ur-ai-assistant'); ?></span>
            <span class="ur-ai-calc__public-notice-text"><?php echo esc_html($notice); ?></span>
        </div>
        <?php endif; ?>

        <!-- 影響分配的因素 -->
        <div class="ur-ai-calc__factors">
            <p class="ur-ai-calc__factors-title"><?php esc_html_e('試算只是起點', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-calc__factors-text"><?php esc_html_e('本試算為概估，實際分回受營建成本、房價、實施方式、基地整合難度、獎勵審議結果及原屋條件等因素影響，請以正式評估為準。', 'ur-ai-assistant'); ?></p>
        </div>

        <!-- 重要提醒（免責聲明，放大醒目） -->
        <?php if ('' !== $discl) : ?>
        <div class="ur-ai-calc__disclaimer-box">
            <span class="ur-ai-calc__disclaimer-badge"><?php esc_html_e('重要提醒', 'ur-ai-assistant'); ?></span>
            <p class="ur-ai-calc__disclaimer-text"><?php echo esc_html($discl); ?></p>
        </div>
        <?php endif; ?>

        <!-- 只在列印時顯示的品牌頁尾 -->
        <div class="ur-ai-calc__print-footer">
            <?php echo esc_html(get_bloginfo('name')); ?> ・ <?php echo esc_html(home_url('/')); ?>
        </div>

        <!-- 半遮罩鉤子（列印時隱藏） -->
        <div class="ur-ai-calc__locked">
            <div class="ur-ai-calc__locked-blur" aria-hidden="true">
                <p><?php esc_html_e('容積獎勵項目明細：綠建築 ＋ 智慧建築 ＋ 耐震 ＋ 時程……', 'ur-ai-assistant'); ?></p>
                <p><?php esc_html_e('每坪概估市值：＊＊＊＊ 萬／坪', 'ur-ai-assistant'); ?></p>
                <p><?php esc_html_e('共同負擔（營建＋費用）概算：＊＊＊＊', 'ur-ai-assistant'); ?></p>
                <p><?php esc_html_e('本案保守可行性區間：＊＊＊＊', 'ur-ai-assistant'); ?></p>
            </div>

            <div class="ur-ai-calc__hook">
                <?php if ('' !== $hook_t) : ?>
                    <p class="ur-ai-calc__hook-title"><?php echo esc_html($hook_t); ?></p>
                <?php endif; ?>
                <?php if ('' !== $hook_s) : ?>
                    <p class="ur-ai-calc__hook-subtitle"><?php echo esc_html($hook_s); ?></p>
                <?php endif; ?>

                <?php
                if ($cf7_id > 0 && shortcode_exists('contact-form-7')) {
                    echo do_shortcode('[contact-form-7 id="' . absint($cf7_id) . '"]');
                } else {
                    echo '<p class="ur-ai-calc__hook-missing">' . esc_html__('（聯絡表單尚未設定）', 'ur-ai-assistant') . '</p>';
                }
                ?>
            </div>
        </div>

    </div>

    <div class="ur-ai-calc__error" data-calc-error hidden></div>
</div>
