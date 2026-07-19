<?php
/**
 * UR AI Assistant Tax Calculator View
 *
 * 稅賦試算（土地增值稅／契稅）前台畫面。
 *
 * @var string $disclaimer
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$disclaimer  = isset($disclaimer) ? (string) $disclaimer : '';
$instance_id = 'ur-ai-tax-calc-' . wp_rand(1000, 999999);

$scenario_options = array(
    'none'                              => __('一般移轉（無都更減免）', 'ur-ai-assistant'),
    'rights_conversion_first_transfer'  => __('權利變換取得房地，首次移轉（土增稅／契稅減徵40%）', 'ur-ai-assistant'),
    'cash_compensation'                 => __('不願參與權利變換，改領現金補償（土增稅減徵40%，有實施期限）', 'ur-ai-assistant'),
    'undersized_cash_conversion'        => __('應分配面積未達最小分配單元，改領現金（土增稅免徵）', 'ur-ai-assistant'),
    'in_kind_offset'                    => __('以分配之土地建築物抵充負擔（土增稅／契稅免徵）', 'ur-ai-assistant'),
    'negotiated_reconstruction'         => __('協議合建移轉，經主管機關同意（土增稅／契稅減徵40%，有實施期限）', 'ur-ai-assistant'),
    'danger_old_building'               => __('危老重建（僅供對照：無土增稅／契稅減免）', 'ur-ai-assistant'),
);
?>
<div id="<?php echo esc_attr($instance_id); ?>" class="ur-ai-tax-calc">

    <div class="ur-ai-tax-calc__intro">
        <h3 class="ur-ai-tax-calc__title"><?php esc_html_e('土地增值稅／契稅試算', 'ur-ai-assistant'); ?></h3>
        <p class="ur-ai-tax-calc__lead">
            <?php esc_html_e('依土地稅法、契稅條例公開公式概算一般稅額，並可選擇套用都市更新條例第67條的減免情境。危老重建僅房屋稅／地價稅有減半優惠，並無土地增值稅或契稅減免，本工具刻意列出「危老重建」選項並說明不適用，避免與都更混淆。', 'ur-ai-assistant'); ?>
        </p>
    </div>

    <div class="ur-ai-tax-calc__tabs">
        <button type="button" class="ur-ai-tax-calc__tab is-active" data-tax-tab="land_value_tax"><?php esc_html_e('土地增值稅', 'ur-ai-assistant'); ?></button>
        <button type="button" class="ur-ai-tax-calc__tab" data-tax-tab="deed_tax"><?php esc_html_e('契稅', 'ur-ai-assistant'); ?></button>
    </div>

    <!-- 土地增值稅 -->
    <div class="ur-ai-tax-calc__panel" data-tax-panel="land_value_tax">

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('土地類型', 'ur-ai-assistant'); ?></label>
            <div class="ur-ai-tax-calc__radio-group">
                <label><input type="radio" name="<?php echo esc_attr($instance_id); ?>-land-type" value="urban" data-tax="land_type" checked> <?php esc_html_e('都市土地（自用上限300㎡）', 'ur-ai-assistant'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($instance_id); ?>-land-type" value="non_urban" data-tax="land_type"> <?php esc_html_e('非都市土地（自用上限700㎡）', 'ur-ai-assistant'); ?></label>
            </div>
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label">
                <input type="checkbox" data-tax="self_use">
                <?php esc_html_e('自用住宅用地（稅率10%，超過面積上限部分仍按一般稅率）', 'ur-ai-assistant'); ?>
            </label>
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('土地宗地面積（平方公尺）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" step="0.01" class="ur-ai-tax-calc__input" data-tax="area" placeholder="<?php esc_attr_e('例：150', 'ur-ai-assistant'); ?>">
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('移轉或設典範圍（分子／分母，選填，預設整筆）', 'ur-ai-assistant'); ?></label>
            <div class="ur-ai-tax-calc__inline-fields">
                <input type="number" min="0" step="1" class="ur-ai-tax-calc__input ur-ai-tax-calc__input--inline" data-tax="share_numerator" placeholder="<?php esc_attr_e('分子', 'ur-ai-assistant'); ?>">
                <span>/</span>
                <input type="number" min="0" step="1" class="ur-ai-tax-calc__input ur-ai-tax-calc__input--inline" data-tax="share_denominator" placeholder="<?php esc_attr_e('分母', 'ur-ai-assistant'); ?>">
            </div>
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('土地公告現值（元／平方公尺）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" step="1" class="ur-ai-tax-calc__input" data-tax="current_value" placeholder="<?php esc_attr_e('可至內政部地政司查詢', 'ur-ai-assistant'); ?>">
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('原規定地價或前次移轉現值（元／平方公尺）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" step="1" class="ur-ai-tax-calc__input" data-tax="original_value">
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('消費者物價總指數（%，預設100）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" step="0.01" class="ur-ai-tax-calc__input" data-tax="cpi_percent" value="100">
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('持有年限（年）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" step="1" class="ur-ai-tax-calc__input" data-tax="holding_years">
            <p class="ur-ai-tax-calc__hint"><?php esc_html_e('本次移轉日與前次移轉日之間的年數，決定長期持有的稅率減徵。', 'ur-ai-assistant'); ?></p>
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('准予抵繳之增繳地價稅額（元，選填）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" step="1" class="ur-ai-tax-calc__input" data-tax="land_value_tax_credit" value="0">
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('都市更新減免情境', 'ur-ai-assistant'); ?></label>
            <select class="ur-ai-tax-calc__input" data-tax="reduction_scenario">
                <?php foreach ($scenario_options as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="button" class="ur-ai-tax-calc__btn" data-tax-action="compute" data-calc-type="land_value_tax">
            <?php esc_html_e('計算土地增值稅', 'ur-ai-assistant'); ?>
        </button>
    </div>

    <!-- 契稅 -->
    <div class="ur-ai-tax-calc__panel" data-tax-panel="deed_tax" hidden>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('申報契價（房屋現值，元）', 'ur-ai-assistant'); ?></label>
            <input type="number" min="0" step="1" class="ur-ai-tax-calc__input" data-tax="declared_value" placeholder="<?php esc_attr_e('可洽稅捐機關或房屋稅單查詢', 'ur-ai-assistant'); ?>">
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('移轉原因', 'ur-ai-assistant'); ?></label>
            <select class="ur-ai-tax-calc__input" data-tax="transfer_type">
                <option value="buy"><?php esc_html_e('買賣（6%）', 'ur-ai-assistant'); ?></option>
                <option value="dian"><?php esc_html_e('典權（4%）', 'ur-ai-assistant'); ?></option>
                <option value="exchange"><?php esc_html_e('交換（2%）', 'ur-ai-assistant'); ?></option>
                <option value="gift"><?php esc_html_e('贈與（6%）', 'ur-ai-assistant'); ?></option>
                <option value="partition"><?php esc_html_e('分割（2%）', 'ur-ai-assistant'); ?></option>
                <option value="possess"><?php esc_html_e('占有（6%）', 'ur-ai-assistant'); ?></option>
            </select>
        </div>

        <div class="ur-ai-tax-calc__field">
            <label class="ur-ai-tax-calc__label"><?php esc_html_e('都市更新減免情境', 'ur-ai-assistant'); ?></label>
            <select class="ur-ai-tax-calc__input" data-tax="reduction_scenario_deed">
                <?php foreach ($scenario_options as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="button" class="ur-ai-tax-calc__btn" data-tax-action="compute" data-calc-type="deed_tax">
            <?php esc_html_e('計算契稅', 'ur-ai-assistant'); ?>
        </button>
    </div>

    <!-- 結果 -->
    <div class="ur-ai-tax-calc__result" data-tax-result hidden>
        <div class="ur-ai-tax-calc__result-final">
            <span class="ur-ai-tax-calc__result-label" data-tax-result-label></span>
            <span class="ur-ai-tax-calc__result-value" data-tax-result-value>—</span>
        </div>

        <div class="ur-ai-tax-calc__reduction-note" data-tax-reduction-note hidden></div>

        <div class="ur-ai-tax-calc__breakdown">
            <p class="ur-ai-tax-calc__breakdown-title"><?php esc_html_e('試算過程（公式透明）', 'ur-ai-assistant'); ?></p>
            <ol class="ur-ai-tax-calc__breakdown-list" data-tax-breakdown></ol>
        </div>

        <?php if ('' !== $disclaimer) : ?>
        <div class="ur-ai-tax-calc__disclaimer-box">
            <span class="ur-ai-tax-calc__disclaimer-badge"><?php esc_html_e('重要提醒', 'ur-ai-assistant'); ?></span>
            <p class="ur-ai-tax-calc__disclaimer-text"><?php echo esc_html($disclaimer); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="ur-ai-tax-calc__error" data-tax-error hidden></div>

    <?php if (class_exists('UR_AI_Industry_Profiles')) : ?>
        <?php echo UR_AI_Industry_Profiles::render_promotion_attribution(); // phpcs:ignore -- pre-escaped HTML from render_promotion_attribution(). ?>
    <?php endif; ?>
</div>
