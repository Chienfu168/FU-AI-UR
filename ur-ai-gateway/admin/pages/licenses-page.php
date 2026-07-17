<?php
/**
 * UR AI Gateway Licenses Page
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
    'license_created'  => __('已手動建立授權碼。', 'ur-ai-gateway'),
    'license_updated'  => __('授權碼狀態已更新。', 'ur-ai-gateway'),
    'invalid_request'  => __('請求不正確。', 'ur-ai-gateway'),
);

$repository = class_exists('UR_AI_Gateway_License_Repository') ? new UR_AI_Gateway_License_Repository() : null;
$licenses   = $repository ? $repository->query(array('limit' => 100)) : array();
$statuses   = class_exists('UR_AI_Gateway_Schema_Licenses') ? UR_AI_Gateway_Schema_Licenses::get_statuses() : array();
?>
<div class="wrap">
    <h1><?php echo esc_html__('授權碼管理', 'ur-ai-gateway'); ?></h1>

    <?php if ('' !== $message && isset($messages[$message])) : ?>
        <div class="notice notice-<?php echo esc_attr('error' === $msg_type ? 'error' : 'success'); ?> is-dismissible">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <h2><?php echo esc_html__('手動發放授權碼', 'ur-ai-gateway'); ?></h2>
    <p class="description">
        <?php echo esc_html__('大部分授權碼會在客戶透過 WooCommerce 訂閱付款後自動建立；這裡的手動發放供測試或特殊個案使用。', 'ur-ai-gateway'); ?>
    </p>

    <form method="post" style="max-width: 640px;">
        <?php
        if (class_exists('UR_AI_Gateway_Security')) {
            UR_AI_Gateway_Security::admin_form_nonce_field();
        } else {
            wp_nonce_field('ur_ai_gateway_admin_action', 'ur_ai_gateway_nonce');
        }
        ?>
        <input type="hidden" name="ur_ai_gateway_action" value="create_manual_license">

        <table class="form-table">
            <tr>
                <th scope="row"><label for="customer_email"><?php echo esc_html__('客戶 Email', 'ur-ai-gateway'); ?></label></th>
                <td><input type="email" id="customer_email" name="customer_email" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="plan"><?php echo esc_html__('方案備註', 'ur-ai-gateway'); ?></label></th>
                <td><input type="text" id="plan" name="plan" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="daily_limit"><?php echo esc_html__('每日呼叫上限', 'ur-ai-gateway'); ?></label></th>
                <td><input type="number" id="daily_limit" name="daily_limit" min="1" max="100000" class="small-text" placeholder="200"></td>
            </tr>
            <tr>
                <th scope="row"><label for="admin_note"><?php echo esc_html__('備註', 'ur-ai-gateway'); ?></label></th>
                <td><textarea id="admin_note" name="admin_note" rows="3" class="large-text"></textarea></td>
            </tr>
        </table>

        <?php submit_button(__('建立授權碼', 'ur-ai-gateway')); ?>
    </form>

    <hr>

    <h2><?php echo esc_html__('授權碼清單', 'ur-ai-gateway'); ?></h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('授權碼', 'ur-ai-gateway'); ?></th>
                <th><?php echo esc_html__('客戶 Email', 'ur-ai-gateway'); ?></th>
                <th><?php echo esc_html__('方案', 'ur-ai-gateway'); ?></th>
                <th><?php echo esc_html__('狀態', 'ur-ai-gateway'); ?></th>
                <th><?php echo esc_html__('今日用量', 'ur-ai-gateway'); ?></th>
                <th><?php echo esc_html__('累計用量', 'ur-ai-gateway'); ?></th>
                <th><?php echo esc_html__('建立時間', 'ur-ai-gateway'); ?></th>
                <th><?php echo esc_html__('操作', 'ur-ai-gateway'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($licenses)) : ?>
                <tr>
                    <td colspan="8"><?php echo esc_html__('目前沒有任何授權碼。', 'ur-ai-gateway'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($licenses as $license) : ?>
                    <tr>
                        <td><code><?php echo esc_html($license->license_key); ?></code></td>
                        <td><?php echo esc_html($license->customer_email); ?></td>
                        <td><?php echo esc_html($license->plan); ?></td>
                        <td><?php echo esc_html(isset($statuses[$license->status]) ? $statuses[$license->status] : $license->status); ?></td>
                        <td><?php echo esc_html($license->daily_usage_count . ' / ' . $license->daily_limit); ?></td>
                        <td><?php echo esc_html($license->total_usage_count); ?></td>
                        <td><?php echo esc_html($license->created_at); ?></td>
                        <td>
                            <form method="post" style="display:inline-flex; gap:6px; align-items:center;">
                                <?php
                                if (class_exists('UR_AI_Gateway_Security')) {
                                    UR_AI_Gateway_Security::admin_form_nonce_field();
                                } else {
                                    wp_nonce_field('ur_ai_gateway_admin_action', 'ur_ai_gateway_nonce');
                                }
                                ?>
                                <input type="hidden" name="ur_ai_gateway_action" value="update_license_status">
                                <input type="hidden" name="license_id" value="<?php echo esc_attr(absint($license->id)); ?>">
                                <select name="status">
                                    <?php foreach ($statuses as $status_key => $status_label) : ?>
                                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($license->status, $status_key); ?>>
                                            <?php echo esc_html($status_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="button"><?php echo esc_html__('更新', 'ur-ai-gateway'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
