<?php
/**
 * UR AI Assistant Logs Page
 *
 * 問答紀錄管理頁。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('UR_AI_Permissions')) {
    UR_AI_Permissions::require_view_logs();
} elseif (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'),
        esc_html__('權限不足', 'ur-ai-assistant'),
        array(
            'response' => 403,
        )
    );
}

if (!class_exists('UR_AI_Log_Admin')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('問答紀錄', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('問答紀錄管理類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

$admin       = new UR_AI_Log_Admin();
$log_service = new UR_AI_Log_Service();

$list_data = $admin->get_list_data($_GET);

$logs       = isset($list_data['items']) ? $list_data['items'] : array();
$formatted  = isset($list_data['formatted']) ? $list_data['formatted'] : array();
$total      = isset($list_data['total']) ? absint($list_data['total']) : 0;
$summary    = isset($list_data['summary']) ? $list_data['summary'] : array();
$pagination = isset($list_data['pagination']) ? $list_data['pagination'] : array();
$query_args = isset($list_data['query_args']) ? $list_data['query_args'] : array();

$message  = isset($_GET['ur_message']) ? sanitize_key(wp_unslash($_GET['ur_message'])) : '';
$msg_type = isset($_GET['ur_msg_type']) ? sanitize_key(wp_unslash($_GET['ur_msg_type'])) : 'updated';

$admin_message = $message ? $admin->get_admin_message($message) : '';

$base_url = admin_url('admin.php?page=ur-ai-assistant-logs');

$export_url = add_query_arg(
    array_merge(
        $_GET,
        array(
            'page'      => 'ur-ai-assistant-logs',
            'ur_action' => 'export_logs_csv',
            '_wpnonce'  => wp_create_nonce('ur_ai_export_nonce'),
        )
    ),
    admin_url('admin.php')
);

$answer_source_filter     = isset($query_args['answer_source']) ? $query_args['answer_source'] : '';
$status_filter            = isset($query_args['status']) ? $query_args['status'] : '';
$feedback_filter          = isset($query_args['feedback']) ? $query_args['feedback'] : '';
$has_related_pages_filter = isset($query_args['has_related_pages']) ? $query_args['has_related_pages'] : null;
$converted_filter         = isset($query_args['converted']) ? $query_args['converted'] : null;
$search                   = isset($query_args['search']) ? $query_args['search'] : '';
$date_from                = isset($query_args['date_from']) ? $query_args['date_from'] : '';
$date_to                  = isset($query_args['date_to']) ? $query_args['date_to'] : '';

$current_page = isset($pagination['current']) ? absint($pagination['current']) : 1;
$total_pages  = isset($pagination['total_pages']) ? absint($pagination['total_pages']) : 1;

$cost_recent   = $log_service->get_cost_estimate(30);
$cost_all_time = $log_service->get_cost_estimate(0);
$settings_url  = admin_url('admin.php?page=ur-ai-assistant-settings');

?>

<div class="wrap ur-ai-admin-page">

    <h1><?php echo esc_html__('都更 AI 助理｜問答紀錄', 'ur-ai-assistant'); ?></h1>

    <?php if (class_exists('UR_AI_Admin_Menu')) : ?>
        <?php UR_AI_Admin_Menu::render_group_tabs('analytics'); ?>
    <?php endif; ?>

    <?php if ('' !== $admin_message) : ?>
        <div class="notice notice-<?php echo 'error' === $msg_type ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($admin_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="ur-ai-summary-grid">
        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('問答總數', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['total'] ?? 0)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('FAQ 回答', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['faq'] ?? 0)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('AI 回答', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['ai'] ?? 0)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('Token 使用量', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['tokens_used'] ?? 0)); ?></p>
        </div>
    </div>

    <div class="ur-ai-grid ur-ai-grid-3">
        <div class="ur-ai-card">
            <h2><?php echo esc_html__('FAQ 候選資料', 'ur-ai-assistant'); ?></h2>
            <p>
                <?php
                printf(
                    esc_html__('尚未轉 FAQ 的 AI 回答：%d 筆。', 'ur-ai-assistant'),
                    absint($summary['not_converted'] ?? 0)
                );
                ?>
            </p>
            <p class="ur-ai-muted">
                <?php echo esc_html__('可從問答紀錄中挑選穩定、常見的 AI 回答，轉成 FAQ 草稿後人工檢查。', 'ur-ai-assistant'); ?>
            </p>
        </div>

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('相關頁面推薦', 'ur-ai-assistant'); ?></h2>
            <p>
                <?php
                printf(
                    esc_html__('有推薦頁面：%1$d｜無推薦頁面：%2$d', 'ur-ai-assistant'),
                    absint($summary['with_related'] ?? 0),
                    absint($summary['without_related'] ?? 0)
                );
                ?>
            </p>
            <p class="ur-ai-muted">
                <?php echo esc_html__('無推薦頁面的問題，可作為新增網站文章或推薦頁面的參考。', 'ur-ai-assistant'); ?>
            </p>
        </div>

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('使用者回饋', 'ur-ai-assistant'); ?></h2>
            <p>
                <?php
                printf(
                    esc_html__('有幫助：%1$d｜沒幫助：%2$d', 'ur-ai-assistant'),
                    absint($summary['helpful'] ?? 0),
                    absint($summary['not_helpful'] ?? 0)
                );
                ?>
            </p>
            <p class="ur-ai-muted">
                <?php echo esc_html__('被標示沒幫助的回答，建議優先檢查是否需要新增 FAQ 或調整提示詞。', 'ur-ai-assistant'); ?>
            </p>
        </div>
    </div>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('API 花費估算', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php
                    printf(
                        /* translators: %s: 目前設定的費率連結文字 */
                        esc_html__('依「功能設定」頁填寫的每百萬 Tokens 費率（目前 %s 美元）粗估，僅供內部參考，非 OpenAI 官方帳單金額。', 'ur-ai-assistant'),
                        esc_html($cost_all_time['rate'])
                    );
                    ?>
                    <a href="<?php echo esc_url($settings_url); ?>"><?php echo esc_html__('前往調整費率', 'ur-ai-assistant'); ?></a>
                </p>
            </div>
        </div>

        <div class="ur-ai-grid ur-ai-grid-2">
            <div>
                <p class="ur-ai-summary-label"><?php echo esc_html__('近 30 天估算花費', 'ur-ai-assistant'); ?></p>
                <p class="ur-ai-summary-value">US$ <?php echo esc_html(number_format_i18n($cost_recent['estimated_cost'], 2)); ?></p>
                <p class="ur-ai-muted">
                    <?php
                    printf(
                        /* translators: 1: 請求數 2: token 用量 */
                        esc_html__('%1$s 次 AI 回答，共 %2$s tokens', 'ur-ai-assistant'),
                        esc_html(number_format_i18n($cost_recent['total_requests'])),
                        esc_html(number_format_i18n($cost_recent['total_tokens']))
                    );
                    ?>
                </p>
            </div>

            <div>
                <p class="ur-ai-summary-label"><?php echo esc_html__('全部歷史估算花費', 'ur-ai-assistant'); ?></p>
                <p class="ur-ai-summary-value">US$ <?php echo esc_html(number_format_i18n($cost_all_time['estimated_cost'], 2)); ?></p>
                <p class="ur-ai-muted">
                    <?php
                    printf(
                        /* translators: 1: 請求數 2: token 用量 */
                        esc_html__('%1$s 次 AI 回答，共 %2$s tokens', 'ur-ai-assistant'),
                        esc_html(number_format_i18n($cost_all_time['total_requests'])),
                        esc_html(number_format_i18n($cost_all_time['total_tokens']))
                    );
                    ?>
                </p>
            </div>
        </div>

        <?php if (!empty($cost_all_time['rows'])) : ?>
            <div class="ur-ai-table-wrap ur-ai-mt-12">
                <table class="ur-ai-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('模型', 'ur-ai-assistant'); ?></th>
                            <th class="ur-ai-cell-number"><?php echo esc_html__('請求數（全部歷史）', 'ur-ai-assistant'); ?></th>
                            <th class="ur-ai-cell-number"><?php echo esc_html__('Tokens（全部歷史）', 'ur-ai-assistant'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cost_all_time['rows'] as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->model ? $row->model : __('（未記錄）', 'ur-ai-assistant')); ?></td>
                                <td class="ur-ai-cell-number"><?php echo esc_html(number_format_i18n(absint($row->requests))); ?></td>
                                <td class="ur-ai-cell-number"><?php echo esc_html(number_format_i18n(absint($row->tokens))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="ur-ai-toolbar">
        <form method="get" class="ur-ai-filter-form">
            <input type="hidden" name="page" value="ur-ai-assistant-logs">

            <div>
                <label for="filter_answer_source"><?php echo esc_html__('回答來源', 'ur-ai-assistant'); ?></label>
                <select id="filter_answer_source" name="answer_source">
                    <option value=""><?php echo esc_html__('全部來源', 'ur-ai-assistant'); ?></option>
                    <option value="faq" <?php selected($answer_source_filter, 'faq'); ?>>
                        <?php echo esc_html__('FAQ', 'ur-ai-assistant'); ?>
                    </option>
                    <option value="ai" <?php selected($answer_source_filter, 'ai'); ?>>
                        <?php echo esc_html__('AI', 'ur-ai-assistant'); ?>
                    </option>
                    <option value="error" <?php selected($answer_source_filter, 'error'); ?>>
                        <?php echo esc_html__('錯誤', 'ur-ai-assistant'); ?>
                    </option>
                </select>
            </div>

            <div>
                <label for="filter_status"><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></label>
                <select id="filter_status" name="status">
                    <option value=""><?php echo esc_html__('全部狀態', 'ur-ai-assistant'); ?></option>
                    <option value="success" <?php selected($status_filter, 'success'); ?>>
                        <?php echo esc_html__('成功', 'ur-ai-assistant'); ?>
                    </option>
                    <option value="error" <?php selected($status_filter, 'error'); ?>>
                        <?php echo esc_html__('錯誤', 'ur-ai-assistant'); ?>
                    </option>
                </select>
            </div>

            <div>
                <label for="filter_feedback"><?php echo esc_html__('回饋', 'ur-ai-assistant'); ?></label>
                <select id="filter_feedback" name="feedback">
                    <option value=""><?php echo esc_html__('全部回饋', 'ur-ai-assistant'); ?></option>
                    <option value="helpful" <?php selected($feedback_filter, 'helpful'); ?>>
                        <?php echo esc_html__('有幫助', 'ur-ai-assistant'); ?>
                    </option>
                    <option value="not_helpful" <?php selected($feedback_filter, 'not_helpful'); ?>>
                        <?php echo esc_html__('沒幫助', 'ur-ai-assistant'); ?>
                    </option>
                </select>
            </div>

            <div>
                <label for="filter_related"><?php echo esc_html__('推薦頁面', 'ur-ai-assistant'); ?></label>
                <select id="filter_related" name="has_related_pages">
                    <option value=""><?php echo esc_html__('全部', 'ur-ai-assistant'); ?></option>
                    <option value="1" <?php selected((string) $has_related_pages_filter, '1'); ?>>
                        <?php echo esc_html__('有推薦', 'ur-ai-assistant'); ?>
                    </option>
                    <option value="0" <?php selected((string) $has_related_pages_filter, '0'); ?>>
                        <?php echo esc_html__('無推薦', 'ur-ai-assistant'); ?>
                    </option>
                </select>
            </div>

            <div>
                <label for="filter_converted"><?php echo esc_html__('FAQ 轉換', 'ur-ai-assistant'); ?></label>
                <select id="filter_converted" name="converted">
                    <option value=""><?php echo esc_html__('全部', 'ur-ai-assistant'); ?></option>
                    <option value="1" <?php selected((string) $converted_filter, '1'); ?>>
                        <?php echo esc_html__('已轉 FAQ', 'ur-ai-assistant'); ?>
                    </option>
                    <option value="0" <?php selected((string) $converted_filter, '0'); ?>>
                        <?php echo esc_html__('未轉 FAQ', 'ur-ai-assistant'); ?>
                    </option>
                </select>
            </div>

            <div>
                <label for="filter_date_from"><?php echo esc_html__('起日', 'ur-ai-assistant'); ?></label>
                <input
                    type="date"
                    id="filter_date_from"
                    name="date_from"
                    value="<?php echo esc_attr($date_from); ?>"
                >
            </div>

            <div>
                <label for="filter_date_to"><?php echo esc_html__('迄日', 'ur-ai-assistant'); ?></label>
                <input
                    type="date"
                    id="filter_date_to"
                    name="date_to"
                    value="<?php echo esc_attr($date_to); ?>"
                >
            </div>

            <div>
                <label for="logs_search"><?php echo esc_html__('搜尋', 'ur-ai-assistant'); ?></label>
                <input
                    type="text"
                    id="logs_search"
                    name="s"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php echo esc_attr__('問題、回答、錯誤訊息', 'ur-ai-assistant'); ?>"
                >
            </div>

            <div>
                <button type="submit" class="button">
                    <?php echo esc_html__('篩選', 'ur-ai-assistant'); ?>
                </button>

                <a class="button" href="<?php echo esc_url($base_url); ?>">
                    <?php echo esc_html__('清除', 'ur-ai-assistant'); ?>
                </a>
            </div>
        </form>

        <div class="ur-ai-toolbar-actions">
            <a class="button" href="<?php echo esc_url($export_url); ?>">
                <?php echo esc_html__('匯出 CSV', 'ur-ai-assistant'); ?>
            </a>
        </div>
    </div>

    <form method="post" class="ur-ai-bulk-form">
        <?php
        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::admin_form_nonce_field();
        } else {
            wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
        }
        ?>

        <input type="hidden" name="ur_ai_action" value="bulk_logs">

        <div class="ur-ai-toolbar">
            <div class="ur-ai-filter-form">
                <select name="bulk_action" class="ur-ai-bulk-action">
                    <option value=""><?php echo esc_html__('批次操作', 'ur-ai-assistant'); ?></option>
                    <option value="delete"><?php echo esc_html__('刪除', 'ur-ai-assistant'); ?></option>
                </select>

                <button type="submit" class="button">
                    <?php echo esc_html__('套用', 'ur-ai-assistant'); ?>
                </button>
            </div>

            <div class="ur-ai-muted">
                <?php
                printf(
                    esc_html__('共 %d 筆紀錄', 'ur-ai-assistant'),
                    absint($total)
                );
                ?>
            </div>
        </div>

        <div class="ur-ai-table-wrap">
            <table class="ur-ai-table">
                <thead>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" class="ur-ai-check-all">
                        </th>
                        <th><?php echo esc_html__('問題 / 回答摘要', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('來源', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('FAQ', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('推薦頁面', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('回饋', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('Token', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('時間', 'ur-ai-assistant'); ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($logs)) : ?>
                        <?php foreach ($logs as $index => $log) : ?>
                            <?php
                            $row = isset($formatted[$index]) ? $formatted[$index] : array();

                            $log_id           = absint($log->id);
                            $question         = (string) $log->question;
                            $answer           = (string) $log->answer;
                            $answer_source    = (string) $log->answer_source;
                            $status           = (string) $log->status;
                            $error_message    = isset($log->error_message) ? (string) $log->error_message : '';
                            $faq_id           = absint($log->faq_id);
                            $converted_faq_id = absint($log->converted_faq_id);

                            $question_excerpt = isset($row['question_excerpt'])
                                ? $row['question_excerpt']
                                : wp_trim_words(wp_strip_all_tags($question), 24);

                            $answer_excerpt = isset($row['answer_excerpt'])
                                ? $row['answer_excerpt']
                                : wp_trim_words(wp_strip_all_tags($answer), 36);

                            $source_label = isset($row['answer_source_label'])
                                ? $row['answer_source_label']
                                : $answer_source;

                            $feedback_label = isset($row['feedback_label'])
                                ? $row['feedback_label']
                                : (string) $log->feedback;

                            $created_at_label = isset($row['created_at_label'])
                                ? $row['created_at_label']
                                : (string) $log->created_at;

                            $source_badge = 'ai' === $answer_source ? 'ai' : ('faq' === $answer_source ? 'faq' : 'error');
                            ?>
                            <tr>
                                <td class="check-column">
                                    <input
                                        type="checkbox"
                                        class="ur-ai-item-checkbox"
                                        name="log_ids[]"
                                        value="<?php echo esc_attr($log_id); ?>"
                                    >
                                </td>

                                <td class="ur-ai-cell-wide">
                                    <div class="ur-ai-row-title">
                                        <?php echo esc_html($question_excerpt); ?>
                                    </div>

                                    <?php if ('error' === $status || 'error' === $answer_source) : ?>
                                        <div class="ur-ai-danger-box ur-ai-mt-12">
                                            <?php echo esc_html($error_message ? $error_message : __('此筆問答發生錯誤。', 'ur-ai-assistant')); ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="ur-ai-muted ur-ai-mt-12">
                                            <?php echo esc_html($answer_excerpt); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="ur-ai-row-actions">
                                        <button
                                            type="button"
                                            class="button-link ur-ai-copy-button"
                                            data-copy-text="<?php echo esc_attr($question); ?>"
                                        >
                                            <?php echo esc_html__('複製問題', 'ur-ai-assistant'); ?>
                                        </button>

                                        <?php if ('ai' === $answer_source && $converted_faq_id <= 0 && '' !== trim($answer)) : ?>
                                            <form method="post">
                                                <?php
                                                if (class_exists('UR_AI_Security')) {
                                                    UR_AI_Security::admin_form_nonce_field();
                                                } else {
                                                    wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                                                }
                                                ?>
                                                <input type="hidden" name="ur_ai_action" value="convert_log_to_faq">
                                                <input type="hidden" name="log_id" value="<?php echo esc_attr($log_id); ?>">
                                                <button type="submit" class="button-link ur-ai-convert-faq-button">
                                                    <?php echo esc_html__('轉 FAQ 草稿', 'ur-ai-assistant'); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post">
                                            <?php
                                            if (class_exists('UR_AI_Security')) {
                                                UR_AI_Security::admin_form_nonce_field();
                                            } else {
                                                wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                                            }
                                            ?>
                                            <input type="hidden" name="ur_ai_action" value="delete_log">
                                            <input type="hidden" name="log_id" value="<?php echo esc_attr($log_id); ?>">
                                            <button type="submit" class="button-link-delete ur-ai-delete-button">
                                                <?php echo esc_html__('刪除', 'ur-ai-assistant'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>

                                <td>
                                    <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($source_badge); ?>">
                                        <?php echo esc_html($source_label); ?>
                                    </span>

                                    <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                        <?php echo esc_html($status); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php if ($faq_id > 0) : ?>
                                        <span class="ur-ai-badge ur-ai-badge-linked">
                                            <?php
                                            printf(
                                                esc_html__('FAQ #%d', 'ur-ai-assistant'),
                                                absint($faq_id)
                                            );
                                            ?>
                                        </span>

                                        <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                            <?php
                                            printf(
                                                esc_html__('分數：%d', 'ur-ai-assistant'),
                                                absint($log->faq_match_score)
                                            );
                                            ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="ur-ai-badge ur-ai-badge-unlinked">
                                            <?php echo esc_html__('未命中', 'ur-ai-assistant'); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($converted_faq_id > 0) : ?>
                                        <div class="ur-ai-mt-12">
                                            <span class="ur-ai-badge ur-ai-badge-success">
                                                <?php
                                                printf(
                                                    esc_html__('已轉 FAQ #%d', 'ur-ai-assistant'),
                                                    absint($converted_faq_id)
                                                );
                                                ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($log->has_related_pages)) : ?>
                                        <span class="ur-ai-badge ur-ai-badge-success">
                                            <?php echo esc_html__('有推薦', 'ur-ai-assistant'); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="ur-ai-badge ur-ai-badge-warning">
                                            <?php echo esc_html__('無推薦', 'ur-ai-assistant'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($log->feedback)) : ?>
                                        <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($log->feedback); ?>">
                                            <?php echo esc_html($feedback_label); ?>
                                        </span>

                                        <?php if (!empty($log->feedback_reason)) : ?>
                                            <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                                <?php echo esc_html($log->feedback_reason); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="ur-ai-badge ur-ai-badge-default">
                                            <?php echo esc_html__('未回饋', 'ur-ai-assistant'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="ur-ai-cell-number">
                                    <?php echo esc_html(absint($log->tokens_used)); ?>
                                </td>

                                <td>
                                    <?php echo esc_html($created_at_label); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8">
                                <div class="ur-ai-empty-state">
                                    <?php echo esc_html__('目前沒有符合條件的問答紀錄。', 'ur-ai-assistant'); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php if ($total_pages > 1) : ?>
        <div class="ur-ai-pagination">
            <div class="ur-ai-pagination-info">
                <?php
                printf(
                    esc_html__('第 %1$d / %2$d 頁', 'ur-ai-assistant'),
                    absint($current_page),
                    absint($total_pages)
                );
                ?>
            </div>

            <div class="ur-ai-pagination-links">
                <?php
                $prev_url = add_query_arg(
                    array_merge(
                        $_GET,
                        array(
                            'paged' => max(1, $current_page - 1),
                        )
                    ),
                    admin_url('admin.php')
                );

                $next_url = add_query_arg(
                    array_merge(
                        $_GET,
                        array(
                            'paged' => min($total_pages, $current_page + 1),
                        )
                    ),
                    admin_url('admin.php')
                );
                ?>

                <?php if ($current_page > 1) : ?>
                    <a class="button" href="<?php echo esc_url($prev_url); ?>">
                        <?php echo esc_html__('上一頁', 'ur-ai-assistant'); ?>
                    </a>
                <?php endif; ?>

                <?php if ($current_page < $total_pages) : ?>
                    <a class="button" href="<?php echo esc_url($next_url); ?>">
                        <?php echo esc_html__('下一頁', 'ur-ai-assistant'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>