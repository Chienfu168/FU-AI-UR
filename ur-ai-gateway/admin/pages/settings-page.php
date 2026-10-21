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

$settings_available = class_exists('UR_AI_Gateway_Settings');

$key_from_constant   = $settings_available ? UR_AI_Gateway_Settings::is_api_key_from_constant() : false;
// 金鑰來自 wp-config.php 常數時，不把實際金鑰回填到表單欄位（它本來就不在資料庫），
// 只顯示資料庫另存的備用值（通常為空），避免把常數金鑰誤寫進資料庫。
$api_key_db          = $settings_available ? trim((string) UR_AI_Gateway_Settings::get('openai_api_key', '')) : '';
$default_daily_limit = $settings_available ? UR_AI_Gateway_Settings::get_default_daily_limit() : 200;
$allowed_models      = $settings_available ? (string) UR_AI_Gateway_Settings::get('allowed_models', '') : '';
$max_output_tokens   = $settings_available ? absint(UR_AI_Gateway_Settings::get('max_output_tokens', 16384)) : 16384;
$max_messages        = $settings_available ? absint(UR_AI_Gateway_Settings::get('max_messages', 100)) : 100;
$max_input_chars     = $settings_available ? absint(UR_AI_Gateway_Settings::get('max_input_chars', 200000)) : 200000;

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
                    <?php if ($key_from_constant) : ?>
                        <p>
                            <span class="dashicons dashicons-lock" style="color:#46b450;"></span>
                            <strong><?php echo esc_html__('目前由 wp-config.php 的 UR_AI_GATEWAY_OPENAI_KEY 常數提供。', 'ur-ai-gateway'); ?></strong>
                        </p>
                        <p class="description">
                            <?php echo esc_html__('金鑰未存放於資料庫（較安全，避免備份或其他外掛漏洞外洩）。此設定優先於下方欄位；要停用常數金鑰，請於 wp-config.php 移除該常數。', 'ur-ai-gateway'); ?>
                        </p>
                    <?php endif; ?>
                    <input type="password" id="openai_api_key" name="openai_api_key" value="<?php echo esc_attr($api_key_db); ?>" class="regular-text" autocomplete="off"<?php echo $key_from_constant ? ' placeholder="' . esc_attr__('（已改由 wp-config.php 常數提供，此欄位為備援）', 'ur-ai-gateway') . '"' : ''; ?>>
                    <p class="description">
                        <?php echo esc_html__('這組 API Key 由服務提供者自行負擔費用，代理所有客戶端的呼叫，請妥善保管。建議改在 wp-config.php 以 UR_AI_GATEWAY_OPENAI_KEY 常數設定，不要存放於資料庫。', 'ur-ai-gateway'); ?>
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

        <h2><?php echo esc_html__('轉發請求成本防護', 'ur-ai-gateway'); ?></h2>
        <p class="description">
            <?php echo esc_html__('以下限制會在轉發給 OpenAI「之前」檢查，用來擋掉異常龐大或惡意的請求、避免成本失控。超過限制的請求會直接退回，不佔用客戶每日額度。', 'ur-ai-gateway'); ?>
        </p>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allowed_models"><?php echo esc_html__('允許的模型（白名單）', 'ur-ai-gateway'); ?></label>
                </th>
                <td>
                    <input type="text" id="allowed_models" name="allowed_models" value="<?php echo esc_attr($allowed_models); ?>" class="regular-text" placeholder="gpt-4o-mini, gpt-4o">
                    <p class="description">
                        <?php echo esc_html__('以逗號分隔，例如：gpt-4o-mini, gpt-4o。留空＝不限制模型（允許任何模型）。設定後，請求使用清單以外的模型會被退回。', 'ur-ai-gateway'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="max_output_tokens"><?php echo esc_html__('單次回應上限 tokens', 'ur-ai-gateway'); ?></label>
                </th>
                <td>
                    <input type="number" id="max_output_tokens" name="max_output_tokens" value="<?php echo esc_attr($max_output_tokens); ?>" min="1" max="128000" class="small-text">
                    <p class="description">
                        <?php echo esc_html__('轉發時會把每次請求的 max_tokens 壓到不超過此值（請求未指定時則以此值為準），避免單次回應過長灌爆成本。', 'ur-ai-gateway'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="max_messages"><?php echo esc_html__('單次訊息則數上限', 'ur-ai-gateway'); ?></label>
                </th>
                <td>
                    <input type="number" id="max_messages" name="max_messages" value="<?php echo esc_attr($max_messages); ?>" min="1" max="1000" class="small-text">
                    <p class="description">
                        <?php echo esc_html__('單次請求 messages 陣列的最大則數，超過即退回。', 'ur-ai-gateway'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="max_input_chars"><?php echo esc_html__('單次輸入字元上限', 'ur-ai-gateway'); ?></label>
                </th>
                <td>
                    <input type="number" id="max_input_chars" name="max_input_chars" value="<?php echo esc_attr($max_input_chars); ?>" min="1" max="5000000" class="regular-text">
                    <p class="description">
                        <?php echo esc_html__('單次請求所有訊息內容的總字元數上限，超過即退回（粗略對應輸入 token 成本）。', 'ur-ai-gateway'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('儲存設定', 'ur-ai-gateway')); ?>
    </form>
</div>
