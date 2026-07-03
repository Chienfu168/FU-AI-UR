<?php
/**
 * 都更分回試算 後台「試算器設定」頁。
 *
 * 可用變數：
 * @var array $settings 全部設定。
 * @var array $params   單一模式計算參數組（台北組）。
 * @var bool  $saved    是否剛儲存成功。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

// 小數 → 百分比顯示。
$pct = function ($v) {
    return rtrim(rtrim(number_format((float) $v * 100, 1), '0'), '.');
};
$num = function ($v) {
    return rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
};

$general_bonus    = isset($params['general_bonus']) ? $pct($params['general_bonus']) : '50';
$build_factor     = isset($params['build_factor']) ? $num($params['build_factor']) : '1.5';
$owner_ratio_low  = isset($params['owner_ratio_low']) ? $pct($params['owner_ratio_low']) : '50';
$owner_ratio_high = isset($params['owner_ratio_high']) ? $pct($params['owner_ratio_high']) : '55';

$enabled        = !empty($settings['enabled']);
$cf7_form_id    = isset($settings['cf7_form_id']) ? (int) $settings['cf7_form_id'] : 0;
$hook_title     = isset($settings['lead_hook_title']) ? $settings['lead_hook_title'] : '';
$hook_subtitle  = isset($settings['lead_hook_subtitle']) ? $settings['lead_hook_subtitle'] : '';
$ratio_notice   = isset($settings['public_ratio_notice']) ? $settings['public_ratio_notice'] : '';
$disclaimer     = isset($settings['disclaimer']) ? $settings['disclaimer'] : '';

$adv_a   = isset($settings['adv_a_multiplier']) ? $num($settings['adv_a_multiplier']) : '1.5';
$adv_b   = isset($settings['adv_b_legal_ratio']) ? $pct($settings['adv_b_legal_ratio']) : '30';
$adv_c   = isset($settings['adv_c_multiplier']) ? $num($settings['adv_c_multiplier']) : '1.2';
$adv_cs  = isset($settings['adv_c_multiplier_special']) ? $num($settings['adv_c_multiplier_special']) : '1.3';
$adv_cap = isset($settings['adv_cap_multiplier']) ? $num($settings['adv_cap_multiplier']) : '2';

$massing_floor_height  = isset($settings['massing_floor_height']) ? $num($settings['massing_floor_height']) : '3.2';
$massing_coverage_hint = isset($settings['massing_coverage_hint']) ? $settings['massing_coverage_hint'] : '';
?>
<div class="wrap">
    <h1><?php esc_html_e('都更試算器設定', 'ur-ai-assistant'); ?></h1>

    <?php if (!empty($saved)) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('設定已儲存。', 'ur-ai-assistant'); ?></p></div>
    <?php endif; ?>

    <p class="description">
        <?php esc_html_e('這裡可調整試算的浮動參數與文案，無須改程式。修改後立即套用於前台試算。', 'ur-ai-assistant'); ?>
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Calculator_Module::SETTINGS_SAVE_ACTION); ?>">
        <?php wp_nonce_field(UR_AI_Calculator_Module::SETTINGS_SAVE_ACTION); ?>

        <h2 class="title"><?php esc_html_e('基本', 'ur-ai-assistant'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('啟用試算器', 'ur-ai-assistant'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                        <?php esc_html_e('啟用（停用後短代碼不顯示）', 'ur-ai-assistant'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cf7_form_id"><?php esc_html_e('CF7 表單 ID', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input name="cf7_form_id" id="cf7_form_id" type="number" min="0" value="<?php echo esc_attr($cf7_form_id); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('留資料表單的數字 ID（目前為「都更獎勵試算」表單）。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('計算參數（浮動數據）', 'ur-ai-assistant'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="general_bonus"><?php esc_html_e('一般都更獎勵預設（%）', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input name="general_bonus" id="general_bonus" type="number" min="0" max="50" step="1" value="<?php echo esc_attr($general_bonus); ?>" class="small-text"> %
                    <p class="description"><?php esc_html_e('前台「一般都更獎勵」欄位的預設值，上限 50%。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="build_factor"><?php esc_html_e('實設係數', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input name="build_factor" id="build_factor" type="number" min="1" max="3" step="0.01" value="<?php echo esc_attr($build_factor); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('含陽台、公設、車位等免計容積後，實際興建樓地板 ÷ 容積樓地板。市場約 1.5~1.6，保守取 1.5。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('地主分回比例（%）', 'ur-ai-assistant'); ?></th>
                <td>
                    <input name="owner_ratio_low" type="number" min="0" max="100" step="1" value="<?php echo esc_attr($owner_ratio_low); ?>" class="small-text"> %
                    <?php esc_html_e('至', 'ur-ai-assistant'); ?>
                    <input name="owner_ratio_high" type="number" min="0" max="100" step="1" value="<?php echo esc_attr($owner_ratio_high); ?>" class="small-text"> %
                    <p class="description"><?php esc_html_e('全案可銷售樓地板中，地主拿回的比例區間。受基地、成本、房價影響，保守取 50~55%。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('進階評估係數（三案擇優）', 'ur-ai-assistant'); ?></h2>
        <p class="description" style="margin-bottom:8px;">
            <?php esc_html_e('進階評估會比較三種都更獎勵公式取最有利者。以下係數對應現行法規，修法時可在此調整。', 'ur-ai-assistant'); ?>
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="adv_a_multiplier"><?php esc_html_e('A 案：法定容積 × 倍數', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input name="adv_a_multiplier" id="adv_a_multiplier" type="number" min="1" max="3" step="0.01" value="<?php echo esc_attr($adv_a); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('一般都更獎勵，法定容積 × 1.5。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="adv_b_legal_ratio"><?php esc_html_e('B 案：原容積 ＋ 法定容積 × ?%', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input name="adv_b_legal_ratio" id="adv_b_legal_ratio" type="number" min="0" max="100" step="1" value="<?php echo esc_attr($adv_b); ?>" class="small-text"> %
                    <p class="description"><?php esc_html_e('原建築容積 + 法定容積的 30%。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="adv_c_multiplier"><?php esc_html_e('C 案：原容積 × 倍數（一般）', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input name="adv_c_multiplier" id="adv_c_multiplier" type="number" min="1" max="3" step="0.01" value="<?php echo esc_attr($adv_c); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('一般建築的原容積保障倍數，預設 1.2。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="adv_c_multiplier_special"><?php esc_html_e('C 案：原容積 × 倍數（危險／海砂屋）', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input name="adv_c_multiplier_special" id="adv_c_multiplier_special" type="number" min="1" max="3" step="0.01" value="<?php echo esc_attr($adv_cs); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('危險建築或海砂屋的原容積保障倍數，預設 1.3。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="adv_cap_multiplier"><?php esc_html_e('更新後容積上限（基準容積 × 倍數）', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input name="adv_cap_multiplier" id="adv_cap_multiplier" type="number" min="1" max="5" step="0.1" value="<?php echo esc_attr($adv_cap); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('三案擇優後的硬上限，預設基準容積 2 倍。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('樓層／高度概估（進階評估附屬功能）', 'ur-ai-assistant'); ?></h2>
        <p class="description" style="margin-bottom:8px;">
            <?php esc_html_e('使用者於進階評估選擇「基地面積＋持分比例」並填入建蔽率時，會額外顯示樓層／高度概估。', 'ur-ai-assistant'); ?>
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="massing_floor_height"><?php esc_html_e('單層樓高預設值（米）', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input name="massing_floor_height" id="massing_floor_height" type="number" min="2.4" max="5" step="0.1" value="<?php echo esc_attr($massing_floor_height); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('前台「單層樓高」欄位的預設值，使用者可自行覆寫。住宅常用 3~3.3 米。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="massing_coverage_hint"><?php esc_html_e('建蔽率欄位提示文字', 'ur-ai-assistant'); ?></label></th>
                <td><textarea name="massing_coverage_hint" id="massing_coverage_hint" rows="2" class="large-text"><?php echo esc_textarea($massing_coverage_hint); ?></textarea></td>
            </tr>
        </table>

        <h2 class="title"><?php esc_html_e('文案', 'ur-ai-assistant'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="lead_hook_title"><?php esc_html_e('留資料鉤子標題', 'ur-ai-assistant'); ?></label></th>
                <td><input name="lead_hook_title" id="lead_hook_title" type="text" value="<?php echo esc_attr($hook_title); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="lead_hook_subtitle"><?php esc_html_e('留資料鉤子副標', 'ur-ai-assistant'); ?></label></th>
                <td><input name="lead_hook_subtitle" id="lead_hook_subtitle" type="text" value="<?php echo esc_attr($hook_subtitle); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="public_ratio_notice"><?php esc_html_e('坪數提醒文字', 'ur-ai-assistant'); ?></label></th>
                <td><textarea name="public_ratio_notice" id="public_ratio_notice" rows="3" class="large-text"><?php echo esc_textarea($ratio_notice); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="disclaimer"><?php esc_html_e('重要提醒（免責聲明）', 'ur-ai-assistant'); ?></label></th>
                <td><textarea name="disclaimer" id="disclaimer" rows="3" class="large-text"><?php echo esc_textarea($disclaimer); ?></textarea></td>
            </tr>
        </table>

        <?php submit_button(__('儲存設定', 'ur-ai-assistant')); ?>
    </form>
</div>
