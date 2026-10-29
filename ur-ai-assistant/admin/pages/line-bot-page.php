<?php
/**
 * UR AI Assistant LINE 客服設定頁
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'));
}

$saved = false;

if (
    isset($_POST['ur_ai_action']) &&
    'save_line_settings' === sanitize_key(wp_unslash($_POST['ur_ai_action']))
) {
    check_admin_referer('ur_ai_assistant_admin_action', 'ur_ai_nonce');

    UR_AI_Line_Settings::update_many(
        array(
            'enabled'              => !empty($_POST['enabled']) ? 1 : 0,
            'channel_secret'       => isset($_POST['channel_secret']) ? wp_unslash($_POST['channel_secret']) : '',
            'channel_access_token' => isset($_POST['channel_access_token']) ? wp_unslash($_POST['channel_access_token']) : '',
            'welcome_message'      => isset($_POST['welcome_message']) ? wp_unslash($_POST['welcome_message']) : '',
            'transfer_keywords'    => isset($_POST['transfer_keywords']) ? wp_unslash($_POST['transfer_keywords']) : '',
            'admin_notify_email'   => isset($_POST['admin_notify_email']) ? wp_unslash($_POST['admin_notify_email']) : '',
            'admin_line_ids'       => isset($_POST['admin_line_ids']) ? wp_unslash($_POST['admin_line_ids']) : '',
            'human_timeout_hours'  => isset($_POST['human_timeout_hours']) ? wp_unslash($_POST['human_timeout_hours']) : '',
            'context_messages'     => isset($_POST['context_messages']) ? wp_unslash($_POST['context_messages']) : '',
            'hourly_limit'         => isset($_POST['hourly_limit']) ? wp_unslash($_POST['hourly_limit']) : '',
            'max_message_chars'    => isset($_POST['max_message_chars']) ? wp_unslash($_POST['max_message_chars']) : '',
            'retention_days'       => isset($_POST['retention_days']) ? wp_unslash($_POST['retention_days']) : '',
        )
    );

    $saved = true;
}

$enabled              = UR_AI_Line_Settings::is_enabled();
$secret_from_const    = UR_AI_Line_Settings::credentials_from_constant();
$channel_secret_db    = (string) UR_AI_Line_Settings::get('channel_secret', '');
$channel_token_db     = (string) UR_AI_Line_Settings::get('channel_access_token', '');
$welcome_message      = (string) UR_AI_Line_Settings::get('welcome_message', '');
$transfer_keywords    = (string) UR_AI_Line_Settings::get('transfer_keywords', '');
$admin_notify_email   = (string) UR_AI_Line_Settings::get('admin_notify_email', '');
$admin_line_ids       = (string) UR_AI_Line_Settings::get('admin_line_ids', '');
$human_timeout_hours  = UR_AI_Line_Settings::get_human_timeout_hours();
$context_messages     = UR_AI_Line_Settings::get_context_messages();
$hourly_limit         = UR_AI_Line_Settings::get_hourly_limit();
$max_message_chars    = UR_AI_Line_Settings::get_max_message_chars();
$retention_days       = UR_AI_Line_Settings::get_retention_days();

$webhook_url = rest_url('ur-ai/v1/line/webhook');
?>
<div class="wrap">
    <h1><?php echo esc_html__('LINE 官方帳號客服設定', 'ur-ai-assistant'); ?></h1>

    <?php if ($saved) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('設定已儲存。', 'ur-ai-assistant'); ?></p></div>
    <?php endif; ?>

    <div class="notice notice-info">
        <p><?php echo esc_html__('請到 LINE Developers 的 Messaging API 設定，把 Webhook URL 填為下列網址並開啟「Use webhook」，同時關閉官方帳號的內建自動回應：', 'ur-ai-assistant'); ?></p>
        <p><code><?php echo esc_html($webhook_url); ?></code></p>
    </div>

    <form method="post">
        <?php wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce'); ?>
        <input type="hidden" name="ur_ai_action" value="save_line_settings">

        <h2><?php echo esc_html__('基本', 'ur-ai-assistant'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html__('啟用 LINE 客服', 'ur-ai-assistant'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                        <?php echo esc_html__('啟用後，webhook 才會真正處理訊息（未啟用時只會回應 LINE 的 Verify）。', 'ur-ai-assistant'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="channel_secret"><?php echo esc_html__('Channel secret', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <?php if ($secret_from_const) : ?>
                        <p><strong><?php echo esc_html__('目前由 wp-config.php 常數提供（較安全，優先採用）。', 'ur-ai-assistant'); ?></strong></p>
                    <?php endif; ?>
                    <input type="password" id="channel_secret" name="channel_secret" value="<?php echo esc_attr($channel_secret_db); ?>" class="regular-text" autocomplete="off">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="channel_access_token"><?php echo esc_html__('Channel access token', 'ur-ai-assistant'); ?></label></th>
                <td>
                    <input type="password" id="channel_access_token" name="channel_access_token" value="<?php echo esc_attr($channel_token_db); ?>" class="large-text" autocomplete="off">
                    <p class="description"><?php echo esc_html__('建議改在 wp-config.php 以 UR_AI_LINE_CHANNEL_SECRET／UR_AI_LINE_CHANNEL_ACCESS_TOKEN 常數設定，不存放於資料庫。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="welcome_message"><?php echo esc_html__('歡迎訊息（加好友時）', 'ur-ai-assistant'); ?></label></th>
                <td><textarea id="welcome_message" name="welcome_message" rows="3" class="large-text"><?php echo esc_textarea($welcome_message); ?></textarea>
                    <p class="description"><?php echo esc_html__('建議揭露「由 AI 協助處理並保存紀錄」以符合個資告知。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php echo esc_html__('轉真人', 'ur-ai-assistant'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="transfer_keywords"><?php echo esc_html__('轉真人關鍵字', 'ur-ai-assistant'); ?></label></th>
                <td><input type="text" id="transfer_keywords" name="transfer_keywords" value="<?php echo esc_attr($transfer_keywords); ?>" class="large-text">
                    <p class="description"><?php echo esc_html__('以逗號分隔；訊息含任一關鍵字即轉專人。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="admin_notify_email"><?php echo esc_html__('轉真人通知 Email', 'ur-ai-assistant'); ?></label></th>
                <td><input type="email" id="admin_notify_email" name="admin_notify_email" value="<?php echo esc_attr($admin_notify_email); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="admin_line_ids"><?php echo esc_html__('管理員 LINE userId', 'ur-ai-assistant'); ?></label></th>
                <td><input type="text" id="admin_line_ids" name="admin_line_ids" value="<?php echo esc_attr($admin_line_ids); ?>" class="large-text">
                    <p class="description"><?php echo esc_html__('以逗號分隔。只有清單中的人傳「#恢復 <使用者ID>」才會恢復該客戶的自動回覆（留空＝停用此指令）。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="human_timeout_hours"><?php echo esc_html__('專人模式自動恢復（小時）', 'ur-ai-assistant'); ?></label></th>
                <td><input type="number" id="human_timeout_hours" name="human_timeout_hours" value="<?php echo esc_attr($human_timeout_hours); ?>" min="1" max="720" class="small-text">
                    <p class="description"><?php echo esc_html__('轉專人後超過此時數未處理，下一則訊息起自動恢復 AI 回覆。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php echo esc_html__('AI 與成本', 'ur-ai-assistant'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="context_messages"><?php echo esc_html__('帶給 AI 的近期對話筆數', 'ur-ai-assistant'); ?></label></th>
                <td><input type="number" id="context_messages" name="context_messages" value="<?php echo esc_attr($context_messages); ?>" min="1" max="50" class="small-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="hourly_limit"><?php echo esc_html__('每人每小時 AI 回覆上限', 'ur-ai-assistant'); ?></label></th>
                <td><input type="number" id="hourly_limit" name="hourly_limit" value="<?php echo esc_attr($hourly_limit); ?>" min="0" class="small-text">
                    <p class="description"><?php echo esc_html__('0＝不限制。另有「全站 AI 呼叫上限」（功能設定頁）一併守住整體成本。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="max_message_chars"><?php echo esc_html__('單則訊息字數上限', 'ur-ai-assistant'); ?></label></th>
                <td><input type="number" id="max_message_chars" name="max_message_chars" value="<?php echo esc_attr($max_message_chars); ?>" min="1" max="20000" class="small-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="retention_days"><?php echo esc_html__('對話紀錄保留天數', 'ur-ai-assistant'); ?></label></th>
                <td><input type="number" id="retention_days" name="retention_days" value="<?php echo esc_attr($retention_days); ?>" min="0" max="3650" class="small-text">
                    <p class="description"><?php echo esc_html__('0＝不自動清理。每日排程會刪除早於此天數的紀錄。', 'ur-ai-assistant'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('儲存設定', 'ur-ai-assistant')); ?>
    </form>
</div>
