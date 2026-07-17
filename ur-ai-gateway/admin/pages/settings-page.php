<?php
/**
 * UR AI Gateway Settings Page
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('您沒有權限檢視此頁面。', 'ur-ai-gateway'));
}

$message  = isset($_GET['ur_message']) ? sanitize_key(wp_unslash($_GET['ur_message'])) : '';
$msg_type = isset($_GET['ur_msg_type']) ? sanitize_key(wp_unslash($_GET['ur_msg_type'])) : 'updated';

$messages = array(
    'settings_saved' => __('設定已儲存。', 'ur-ai-gateway'),
);

$api_key             = class_exists('UR_AI_Gateway_Settings') ? UR_AI_Gateway_Settings::get_openai_api_key() : '';
$default_daily_limit = class_exists('UR_AI_Gateway_Settings') ? UR_AI_Gateway_Settings::get_default_daily_limit() : 200;

$rest_endpoint = rest_url('ur-ai-gateway/v1/chat');
?>
<div class="wrap">
    <h1><?php echo esc_html__('AI 代管服務設定', 'ur-ai-gateway'); ?></h1>

    <?php if ('' !== $message && isset($messages[$message])) : ?>
        <div class="notice notice-<?php echo esc_attr('error' === $msg_type ? 'error' : 'success'); ?> is-dismissible">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <div class="notice notice-info">
        <p>
            <?php echo esc_html__('客戶端的 UR AI Assistant 外掛請在「功能設定」頁的「AI 服務來源」選擇「使用代管服務」，並填入以下端點網址與各自的授權碼：', 'ur-ai-gateway'); ?>
        </p>
        <p><code><?php echo esc_html($rest_endpoint); ?></code></p>
    </div>

    <form method="post">
        <?php
        if (class_exists('UR_AI_Gateway_Security')) {
            UR_AI_Gateway_Security::admin_form_nonce_field();
        } else {
            wp_nonce_field('ur_ai_gateway_admin_action', 'ur_ai_gateway_nonce');
        }
        ?>
        <input type="hidden" name="ur_ai_gateway_action" value="save_settings">

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="openai_api_key"><?php echo esc_html__('OpenAI API Key（服務提供者自己的）', 'ur-ai-gateway'); ?></label>
                </th>
                <td>
                    <input type="password" id="openai_api_key" name="openai_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off">
                    <p class="description">
                        <?php echo esc_html__('這組 API Key 由服務提供者自行負擔費用，代理所有客戶端的呼叫，請妥善保管。', 'ur-ai-gateway'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="default_daily_limit"><?php echo esc_html__('新授權碼預設每日呼叫上限', 'ur-ai-gateway'); ?></label>
                </th>
                <td>
                    <input type="number" id="default_daily_limit" name="default_daily_limit" value="<?php echo esc_attr($default_daily_limit); ?>" min="1" max="100000" class="small-text">
                    <p class="description">
                        <?php echo esc_html__('新建立的授權碼（訂閱開通或手動發放）預設使用這個上限，個別授權碼仍可在「授權碼管理」頁各自調整。', 'ur-ai-gateway'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('儲存設定', 'ur-ai-gateway')); ?>
    </form>
</div>
