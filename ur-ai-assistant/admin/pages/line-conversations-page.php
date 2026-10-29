<?php
/**
 * UR AI Assistant LINE 對話管理頁
 *
 * 讓管理者在 WordPress 後台檢視 LINE 客服對話、切換 AI／專人模式。
 * 實際回覆客戶仍於 LINE 官方帳號後台（Official Account Manager）操作。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'));
}

$customer_repo = class_exists('UR_AI_Line_Customer_Repository') ? new UR_AI_Line_Customer_Repository() : null;
$message_repo  = class_exists('UR_AI_Line_Message_Repository') ? new UR_AI_Line_Message_Repository() : null;

$notice = '';

// 處理「切換模式」動作。
if (
    isset($_POST['ur_ai_action']) &&
    'line_set_mode' === sanitize_key(wp_unslash($_POST['ur_ai_action']))
) {
    check_admin_referer('ur_ai_assistant_admin_action', 'ur_ai_nonce');

    $target_id = isset($_POST['line_user_id']) ? sanitize_text_field(wp_unslash($_POST['line_user_id'])) : '';
    $new_mode  = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : '';

    if ('' !== $target_id && in_array($new_mode, array('bot', 'human'), true) && $customer_repo) {
        $customer_repo->set_mode($target_id, $new_mode);
        $notice = ('human' === $new_mode)
            ? __('已切換為專人模式，AI 將停止自動回覆該使用者。', 'ur-ai-assistant')
            : __('已切換為 AI 自動回覆模式。', 'ur-ai-assistant');
    }
}

$base_url    = admin_url('admin.php?page=ur-ai-assistant-line-chats');
$view_chat   = isset($_GET['chat']) ? sanitize_text_field(wp_unslash($_GET['chat'])) : '';
$mode_filter = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : '';
$search      = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$paged       = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
$per_page    = 20;

/**
 * 模式標籤。
 *
 * @param string $mode 模式。
 * @return string
 */
function ur_ai_line_mode_label($mode) {
    return ('human' === $mode)
        ? __('專人處理中', 'ur-ai-assistant')
        : __('AI 自動回覆', 'ur-ai-assistant');
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('LINE 對話管理', 'ur-ai-assistant'); ?></h1>

    <?php if ('' !== $notice) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <?php if (!$customer_repo || !$message_repo) : ?>
        <div class="notice notice-error"><p><?php echo esc_html__('LINE 模組尚未正確載入。', 'ur-ai-assistant'); ?></p></div>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <?php if ('' !== $view_chat) : ?>
        <?php
        $customer = $customer_repo->find_by_line_user_id($view_chat);
        $messages = $message_repo->list_for_chat($view_chat, 300);
        $cur_mode = $customer && isset($customer->mode) ? (string) $customer->mode : 'bot';
        ?>
        <p><a href="<?php echo esc_url($base_url); ?>">&larr; <?php echo esc_html__('返回對話列表', 'ur-ai-assistant'); ?></a></p>

        <h2 style="word-break:break-all;">
            <?php
            $title = $customer && '' !== trim((string) $customer->display_name)
                ? $customer->display_name
                : $view_chat;
            echo esc_html($title);
            ?>
            <span class="ur-ai-line-badge" style="font-size:12px;padding:2px 8px;border-radius:10px;background:<?php echo 'human' === $cur_mode ? '#fef3cd' : '#e5f5e9'; ?>;">
                <?php echo esc_html(ur_ai_line_mode_label($cur_mode)); ?>
            </span>
        </h2>
        <p class="description" style="word-break:break-all;">userId：<code><?php echo esc_html($view_chat); ?></code></p>

        <form method="post" style="margin:12px 0;">
            <?php wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce'); ?>
            <input type="hidden" name="ur_ai_action" value="line_set_mode">
            <input type="hidden" name="line_user_id" value="<?php echo esc_attr($view_chat); ?>">
            <?php if ('human' === $cur_mode) : ?>
                <input type="hidden" name="mode" value="bot">
                <button type="submit" class="button button-primary"><?php echo esc_html__('恢復 AI 自動回覆', 'ur-ai-assistant'); ?></button>
            <?php else : ?>
                <input type="hidden" name="mode" value="human">
                <button type="submit" class="button"><?php echo esc_html__('改為專人處理（AI 停止回覆）', 'ur-ai-assistant'); ?></button>
            <?php endif; ?>
        </form>

        <div class="notice notice-info inline"><p><?php echo esc_html__('要回覆客戶請於 LINE 官方帳號後台（Official Account Manager）的聊天畫面操作；本頁用於檢視對話與切換模式。', 'ur-ai-assistant'); ?></p></div>

        <div style="max-width:760px;border:1px solid #dcdcde;border-radius:6px;padding:12px;background:#fff;">
            <?php if (empty($messages)) : ?>
                <p><?php echo esc_html__('尚無對話紀錄。', 'ur-ai-assistant'); ?></p>
            <?php else : ?>
                <?php foreach ($messages as $m) : ?>
                    <?php $is_assistant = ('assistant' === $m->role); ?>
                    <div style="margin:8px 0;display:flex;<?php echo $is_assistant ? 'justify-content:flex-start;' : 'justify-content:flex-end;'; ?>">
                        <div style="max-width:80%;padding:8px 12px;border-radius:10px;background:<?php echo $is_assistant ? '#f0f0f1' : '#d8ebff'; ?>;">
                            <div style="font-size:11px;color:#646970;margin-bottom:2px;">
                                <?php echo esc_html($is_assistant ? __('助理', 'ur-ai-assistant') : __('使用者', 'ur-ai-assistant')); ?>
                                ・<?php echo esc_html($m->created_at); ?>
                            </div>
                            <div style="white-space:pre-wrap;word-break:break-word;"><?php echo esc_html($m->content); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php else : ?>
        <?php
        $args  = array('mode' => $mode_filter, 'search' => $search, 'limit' => $per_page, 'offset' => ($paged - 1) * $per_page);
        $rows  = $customer_repo->query($args);
        $total = $customer_repo->count(array('mode' => $mode_filter, 'search' => $search));
        $pages = max(1, (int) ceil($total / $per_page));
        ?>
        <form method="get" style="margin:12px 0;">
            <input type="hidden" name="page" value="ur-ai-assistant-line-chats">
            <select name="mode">
                <option value="" <?php selected($mode_filter, ''); ?>><?php echo esc_html__('全部模式', 'ur-ai-assistant'); ?></option>
                <option value="bot" <?php selected($mode_filter, 'bot'); ?>><?php echo esc_html__('AI 自動回覆', 'ur-ai-assistant'); ?></option>
                <option value="human" <?php selected($mode_filter, 'human'); ?>><?php echo esc_html__('專人處理中', 'ur-ai-assistant'); ?></option>
            </select>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('搜尋 userId／名稱', 'ur-ai-assistant'); ?>">
            <button type="submit" class="button"><?php echo esc_html__('篩選', 'ur-ai-assistant'); ?></button>
        </form>

        <p class="description">
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %d: 對話數 */
                    __('共 %d 位使用者', 'ur-ai-assistant'),
                    absint($total)
                )
            );
            ?>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('使用者', 'ur-ai-assistant'); ?></th>
                    <th><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></th>
                    <th><?php echo esc_html__('最後訊息時間', 'ur-ai-assistant'); ?></th>
                    <th><?php echo esc_html__('操作', 'ur-ai-assistant'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="4"><?php echo esc_html__('目前沒有 LINE 對話。', 'ur-ai-assistant'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $chat_url = add_query_arg(array('page' => 'ur-ai-assistant-line-chats', 'chat' => $row->line_user_id), admin_url('admin.php'));
                        $name     = '' !== trim((string) $row->display_name) ? $row->display_name : $row->line_user_id;
                        ?>
                        <tr>
                            <td style="word-break:break-all;"><a href="<?php echo esc_url($chat_url); ?>"><?php echo esc_html($name); ?></a></td>
                            <td><?php echo esc_html(ur_ai_line_mode_label($row->mode)); ?></td>
                            <td><?php echo esc_html($row->last_message_at ? $row->last_message_at : '—'); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce'); ?>
                                    <input type="hidden" name="ur_ai_action" value="line_set_mode">
                                    <input type="hidden" name="line_user_id" value="<?php echo esc_attr($row->line_user_id); ?>">
                                    <?php if ('human' === $row->mode) : ?>
                                        <input type="hidden" name="mode" value="bot">
                                        <button type="submit" class="button button-small"><?php echo esc_html__('恢復 AI', 'ur-ai-assistant'); ?></button>
                                    <?php else : ?>
                                        <input type="hidden" name="mode" value="human">
                                        <button type="submit" class="button button-small"><?php echo esc_html__('轉專人', 'ur-ai-assistant'); ?></button>
                                    <?php endif; ?>
                                </form>
                                <a href="<?php echo esc_url($chat_url); ?>" class="button button-small"><?php echo esc_html__('檢視', 'ur-ai-assistant'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($pages > 1) : ?>
            <div class="tablenav"><div class="tablenav-pages">
                <?php
                echo wp_kses_post(
                    paginate_links(
                        array(
                            'base'      => add_query_arg('paged', '%#%', add_query_arg(array('page' => 'ur-ai-assistant-line-chats', 'mode' => $mode_filter, 's' => $search), admin_url('admin.php'))),
                            'format'    => '',
                            'current'   => $paged,
                            'total'     => $pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        )
                    )
                );
                ?>
            </div></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
