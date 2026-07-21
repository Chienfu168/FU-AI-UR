<?php
/**
 * UR AI Assistant Joint Burden Estimator Settings Page
 *
 * 共同負擔估算：啟用開關與免責聲明設定。單價表、費率、級距屬於新北市
 * 公告之提列基準，不開放後台調整（見 UR_AI_Joint_Burden_Service）。
 *
 * @var bool   $enabled
 * @var string $disclaimer
 * @var bool   $saved
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('UR_AI_Permissions')) {
    UR_AI_Permissions::require_manage_settings();
} elseif (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'),
        esc_html__('權限不足', 'ur-ai-assistant'),
        array('response' => 403)
    );
}

?>
<div class="wrap ur-ai-admin-page">

    <h1>
        <?php
        printf(
            /* translators: %s: 目前產業別的品牌名稱 */
            esc_html__('%s｜共同負擔估算設定', 'ur-ai-assistant'),
            esc_html(class_exists('UR_AI_Admin_Menu') ? UR_AI_Admin_Menu::brand_name() : __('UR AI Assistant', 'ur-ai-assistant'))
        );
        ?>
    </h1>

    <?php if (!empty($saved)) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('設定已儲存。', 'ur-ai-assistant'); ?></p></div>
    <?php endif; ?>

    <p>
        <?php esc_html_e('提供前台「都市更新共同負擔提列估算」shortcode（[ur_ai_joint_burden]），依「新北市」都市更新提列總表與各分項說明之公開公式，概算工程費用A、權利變換費用C、貸款利息D與管理費用F（F1／F3／F5）。單價表、費率與級距為新北市公告基準，不開放調整。', 'ur-ai-assistant'); ?>
    </p>

    <div class="ur-ai-card" style="background:#fffbeb;border-color:#fde68a;">
        <p style="margin:0;">
            <strong><?php esc_html_e('適用範圍：', 'ur-ai-assistant'); ?></strong>
            <?php esc_html_e('本工具目前僅依「新北市」提列基準計算，臺北市及其他縣市之公式與費率不同，不適用本結果。第一階段尚未計入稅捐E（含營業稅）、銷售管理費F4與B項，且未計算「共同負擔比率」——這些需要主管機關核定之「更新後總權利價值」，將於後續版本補上。', 'ur-ai-assistant'); ?>
        </p>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Joint_Burden_Module::SETTINGS_SAVE_ACTION); ?>">
        <?php wp_nonce_field(UR_AI_Joint_Burden_Module::SETTINGS_SAVE_ACTION); ?>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('基本設定', 'ur-ai-assistant'); ?></h2>
                </div>
            </div>

            <div class="ur-ai-form-row">
                <label>
                    <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                    <?php echo esc_html__('啟用共同負擔估算前台功能', 'ur-ai-assistant'); ?>
                </label>
            </div>

            <div class="ur-ai-form-row">
                <label for="disclaimer"><?php echo esc_html__('免責聲明文字', 'ur-ai-assistant'); ?></label>
                <textarea id="disclaimer" name="disclaimer" rows="5" class="large-text"><?php echo esc_textarea($disclaimer); ?></textarea>
                <p class="ur-ai-form-help">
                    <?php echo esc_html__('顯示在估算結果下方，提醒使用者這只是概算，非主管機關核定金額。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php echo esc_html__('儲存設定', 'ur-ai-assistant'); ?></button>
        </p>
    </form>

</div>
