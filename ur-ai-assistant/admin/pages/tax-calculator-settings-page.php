<?php
/**
 * UR AI Assistant Tax Calculator Settings Page
 *
 * 稅賦試算：啟用開關與免責聲明設定。稅率、級距、都更減免規則屬於
 * 法定數字，不開放後台調整（見 UR_AI_Tax_Calculator_Service）。
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
            esc_html__('%s｜稅賦試算設定', 'ur-ai-assistant'),
            esc_html(class_exists('UR_AI_Admin_Menu') ? UR_AI_Admin_Menu::brand_name() : __('UR AI Assistant', 'ur-ai-assistant'))
        );
        ?>
    </h1>

    <?php if (!empty($saved)) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('設定已儲存。', 'ur-ai-assistant'); ?></p></div>
    <?php endif; ?>

    <p>
        <?php esc_html_e('提供前台「土地增值稅／契稅」試算 shortcode（[ur_ai_tax_calculator]），依土地稅法、契稅條例的公開公式概算，並可選擇套用都市更新條例第67條的減免情境（權利變換首次移轉、現金補償、協議合建等）。稅率、級距與減免比率為法定數字，不開放調整。', 'ur-ai-assistant'); ?>
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Tax_Calculator_Module::SETTINGS_SAVE_ACTION); ?>">
        <?php wp_nonce_field(UR_AI_Tax_Calculator_Module::SETTINGS_SAVE_ACTION); ?>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('基本設定', 'ur-ai-assistant'); ?></h2>
                </div>
            </div>

            <div class="ur-ai-form-row">
                <label>
                    <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                    <?php echo esc_html__('啟用稅賦試算前台功能', 'ur-ai-assistant'); ?>
                </label>
            </div>

            <div class="ur-ai-form-row">
                <label for="disclaimer"><?php echo esc_html__('免責聲明文字', 'ur-ai-assistant'); ?></label>
                <textarea id="disclaimer" name="disclaimer" rows="4" class="large-text"><?php echo esc_textarea($disclaimer); ?></textarea>
                <p class="ur-ai-form-help">
                    <?php echo esc_html__('顯示在試算結果下方，提醒使用者這只是概算，非正式稅捐核定金額。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php echo esc_html__('儲存設定', 'ur-ai-assistant'); ?></button>
        </p>
    </form>

</div>
