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

$notice_type = 'success';

// 處理「從對話一鍵加入知識庫」動作：把客服對話中的一問一答，直接存成
// FAQ（狀態 active、立即可被比對命中），用於把 AI 答過的常見題轉為知識庫
// 罐頭答案，提高命中率、降低後續 AI 呼叫。
if (
    isset($_POST['ur_ai_action']) &&
    'line_add_faq' === sanitize_key(wp_unslash($_POST['ur_ai_action']))
) {
    check_admin_referer('ur_ai_assistant_admin_action', 'ur_ai_nonce');

    $faq_question = isset($_POST['faq_question']) ? sanitize_textarea_field(wp_unslash($_POST['faq_question'])) : '';
    $faq_answer   = isset($_POST['faq_answer']) ? sanitize_textarea_field(wp_unslash($_POST['faq_answer'])) : '';
    $faq_category = isset($_POST['faq_category']) ? sanitize_text_field(wp_unslash($_POST['faq_category'])) : '';
    $faq_keywords = isset($_POST['faq_keywords']) ? sanitize_text_field(wp_unslash($_POST['faq_keywords'])) : '';

    if ('' === trim($faq_question) || '' === trim($faq_answer)) {
        $notice      = __('問題與答案都要填寫，才能加入知識庫。', 'ur-ai-assistant');
        $notice_type = 'error';
    } elseif (!class_exists('UR_AI_FAQ_Service')) {
        $notice      = __('FAQ 知識庫模組尚未載入，無法加入。', 'ur-ai-assistant');
        $notice_type = 'error';
    } else {
        $faq_service = new UR_AI_FAQ_Service();
        $new_id      = $faq_service->create(
            array(
                'category'      => '' !== $faq_category ? $faq_category : __('LINE 對話整理', 'ur-ai-assistant'),
                'question'      => $faq_question,
                'answer'        => $faq_answer,
                'keywords'      => $faq_keywords,
                'status'        => 'active',
                'source'        => 'ai_log',
                'review_status' => 'approved',
            )
        );

        if ($new_id) {
            $notice = sprintf(
                /* translators: %s: 問題文字 */
                __('已加入知識庫並立即生效：「%s」。之後同類問題將優先由知識庫回答。', 'ur-ai-assistant'),
                wp_trim_words($faq_question, 20, '…')
            );
        } else {
            $notice      = __('加入知識庫失敗，請確認內容或稍後再試。', 'ur-ai-assistant');
            $notice_type = 'error';
        }
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

/**
 * 回覆來源標籤與顏色。
 *
 * @param string $source 來源（faq｜ai｜system｜空）。
 * @return array array{label, bg}
 */
function ur_ai_line_source_badge($source) {
    switch ($source) {
        case 'faq':
            return array('label' => __('FAQ 知識庫', 'ur-ai-assistant'), 'bg' => '#e5f5e9');
        case 'ai':
            return array('label' => __('AI 回答', 'ur-ai-assistant'), 'bg' => '#d8ebff');
        case 'system':
            return array('label' => __('系統', 'ur-ai-assistant'), 'bg' => '#eceff1');
        default:
            return array('label' => '—', 'bg' => '#f0f0f1');
    }
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('LINE 對話管理', 'ur-ai-assistant'); ?></h1>

    <?php if ('' !== $notice) : ?>
        <div class="notice notice-<?php echo esc_attr('error' === $notice_type ? 'error' : 'success'); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
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

        <?php
        // 預先算出「每則使用者訊息後面，第一則助理回覆」的內容，作為一鍵加入
        // 知識庫時的預設答案（把實際一問一答直接帶進 FAQ 表單）。
        $messages_list = is_array($messages) ? array_values($messages) : array();
        $next_answer   = array();
        $msg_count     = count($messages_list);

        foreach ($messages_list as $i => $mm) {
            if ('assistant' === $mm->role) {
                continue;
            }
            for ($j = $i + 1; $j < $msg_count; $j++) {
                if ('assistant' === $messages_list[$j]->role) {
                    $src = isset($messages_list[$j]->source) ? (string) $messages_list[$j]->source : '';
                    // 系統訊息（轉專人、忙碌提示等）不適合當罐頭答案，略過。
                    if ('system' !== $src) {
                        $next_answer[$i] = (string) $messages_list[$j]->content;
                    }
                    break;
                }
            }
        }
        ?>

        <p class="description" style="margin:6px 0 10px;">
            <?php echo esc_html__('點任一則訊息旁的「加入知識庫」，即可把該問答收進 FAQ（會帶入問題與下一則回覆當答案，可再編輯）。存檔後立即生效，之後同類問題會優先由知識庫回答、減少 AI 呼叫。', 'ur-ai-assistant'); ?>
        </p>

        <div style="max-width:760px;border:1px solid #dcdcde;border-radius:6px;padding:12px;background:#fff;">
            <?php if (empty($messages_list)) : ?>
                <p><?php echo esc_html__('尚無對話紀錄。', 'ur-ai-assistant'); ?></p>
            <?php else : ?>
                <?php foreach ($messages_list as $i => $m) : ?>
                    <?php
                    $is_assistant = ('assistant' === $m->role);
                    $msg_source   = isset($m->source) ? (string) $m->source : '';
                    ?>
                    <div style="margin:8px 0;display:flex;<?php echo $is_assistant ? 'justify-content:flex-start;' : 'justify-content:flex-end;'; ?>">
                        <div style="max-width:80%;padding:8px 12px;border-radius:10px;background:<?php echo $is_assistant ? '#f0f0f1' : '#d8ebff'; ?>;">
                            <div style="font-size:11px;color:#646970;margin-bottom:2px;">
                                <?php echo esc_html($is_assistant ? __('助理', 'ur-ai-assistant') : __('使用者', 'ur-ai-assistant')); ?>
                                <?php
                                if ($is_assistant) {
                                    $badge = ur_ai_line_source_badge($msg_source);
                                    printf(
                                        ' <span style="padding:1px 6px;border-radius:8px;background:%1$s;">%2$s</span>',
                                        esc_attr($badge['bg']),
                                        esc_html($badge['label'])
                                    );
                                }
                                ?>
                                ・<?php echo esc_html($m->created_at); ?>
                            </div>
                            <div style="white-space:pre-wrap;word-break:break-word;"><?php echo esc_html($m->content); ?></div>
                            <div style="margin-top:6px;">
                                <?php if (!$is_assistant) : ?>
                                    <button type="button" class="button button-small ur-faq-fill"
                                        data-q="<?php echo esc_attr($m->content); ?>"
                                        data-a="<?php echo esc_attr(isset($next_answer[$i]) ? $next_answer[$i] : ''); ?>">
                                        <?php echo esc_html__('＋ 加入知識庫', 'ur-ai-assistant'); ?>
                                    </button>
                                <?php elseif ('system' !== $msg_source) : ?>
                                    <button type="button" class="button button-small ur-faq-fill"
                                        data-a="<?php echo esc_attr($m->content); ?>">
                                        <?php echo esc_html__('設為答案', 'ur-ai-assistant'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="ur-faq-box" style="max-width:760px;margin-top:16px;border:1px solid #c3c4c7;border-radius:6px;padding:16px;background:#fff;">
            <h2 style="margin-top:0;font-size:15px;"><?php echo esc_html__('快速加入知識庫（FAQ）', 'ur-ai-assistant'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce'); ?>
                <input type="hidden" name="ur_ai_action" value="line_add_faq">
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th scope="row" style="width:90px;padding:8px 10px 8px 0;"><label for="ur-faq-q"><?php echo esc_html__('問題', 'ur-ai-assistant'); ?></label></th>
                        <td style="padding:8px 0;"><textarea id="ur-faq-q" name="faq_question" rows="2" class="large-text" required placeholder="<?php echo esc_attr__('使用者問的問題（可編輯、濃縮成常見問法）', 'ur-ai-assistant'); ?>"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding:8px 10px 8px 0;"><label for="ur-faq-a"><?php echo esc_html__('答案', 'ur-ai-assistant'); ?></label></th>
                        <td style="padding:8px 0;"><textarea id="ur-faq-a" name="faq_answer" rows="4" class="large-text" required placeholder="<?php echo esc_attr__('要回覆的固定答案（可修正 AI 回覆內容）', 'ur-ai-assistant'); ?>"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding:8px 10px 8px 0;"><label for="ur-faq-cat"><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></label></th>
                        <td style="padding:8px 0;"><input type="text" id="ur-faq-cat" name="faq_category" class="regular-text" value="<?php echo esc_attr__('LINE 對話整理', 'ur-ai-assistant'); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding:8px 10px 8px 0;"><label for="ur-faq-kw"><?php echo esc_html__('關鍵字', 'ur-ai-assistant'); ?></label></th>
                        <td style="padding:8px 0;">
                            <input type="text" id="ur-faq-kw" name="faq_keywords" class="large-text" placeholder="<?php echo esc_attr__('以分號或逗號分隔，例如：海砂屋;補助;重建', 'ur-ai-assistant'); ?>">
                            <p class="description" style="margin:4px 0 0;"><?php echo esc_html__('填上主題詞與常見問法，命中率會更高（例如同義詞、口語說法）。', 'ur-ai-assistant'); ?></p>
                        </td>
                    </tr>
                </table>
                <p style="margin:8px 0 0;">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('加入知識庫（立即生效）', 'ur-ai-assistant'); ?></button>
                    <span class="description" style="margin-left:8px;"><?php echo esc_html__('加入後狀態為「啟用」，會立刻參與比對。', 'ur-ai-assistant'); ?></span>
                </p>
            </form>
        </div>

        <script>
        (function () {
            var box = document.getElementById('ur-faq-box');
            var q = document.getElementById('ur-faq-q');
            var a = document.getElementById('ur-faq-a');
            if (!box || !q || !a) { return; }
            document.querySelectorAll('.ur-faq-fill').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (typeof btn.dataset.q !== 'undefined') { q.value = btn.dataset.q; }
                    if (typeof btn.dataset.a !== 'undefined' && btn.dataset.a !== '') { a.value = btn.dataset.a; }
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    (q.value && !a.value ? a : (q.value ? a : q)).focus();
                });
            });
        })();
        </script>

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
