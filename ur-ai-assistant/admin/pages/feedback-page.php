<?php
/**
 * UR AI Assistant Feedback Page
 *
 * 回饋分析頁。
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

if (!class_exists('UR_AI_Feedback_Service')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('回饋分析', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('回饋分析服務尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

$service = new UR_AI_Feedback_Service();

$data = $service->get_admin_dashboard_data();

$summary          = isset($data['summary']) ? $data['summary'] : array();
$reason_counts    = isset($data['reason_counts']) ? $data['reason_counts'] : array();
$source_summary   = isset($data['source_summary']) ? $data['source_summary'] : array();
$not_helpful_logs = isset($data['not_helpful_logs']) ? $data['not_helpful_logs'] : array();

$logs_url = admin_url('admin.php?page=ur-ai-assistant-logs&feedback=not_helpful');
$faq_url  = admin_url('admin.php?page=ur-ai-assistant-faqs');

$total_feedback   = absint($summary['total_feedback'] ?? 0);
$helpful          = absint($summary['helpful'] ?? 0);
$not_helpful      = absint($summary['not_helpful'] ?? 0);
$feedback_rate    = isset($summary['feedback_rate']) ? (float) $summary['feedback_rate'] : 0;
$helpful_rate     = isset($summary['helpful_rate']) ? (float) $summary['helpful_rate'] : 0;
$not_helpful_rate = isset($summary['not_helpful_rate']) ? (float) $summary['not_helpful_rate'] : 0;

?>

<div class="wrap ur-ai-admin-page">

    <h1><?php echo esc_html__('都更 AI 助理｜回饋分析', 'ur-ai-assistant'); ?></h1>

    <?php if (class_exists('UR_AI_Admin_Menu')) : ?>
        <?php UR_AI_Admin_Menu::render_group_tabs('analytics'); ?>
    <?php endif; ?>

    <div class="ur-ai-help-box">
        <strong><?php echo esc_html__('分析目的：', 'ur-ai-assistant'); ?></strong>
        <?php echo esc_html__('回饋分析可協助判斷哪些回答需要修正、哪些 AI 回答適合轉成 FAQ，以及 FAQ 命中策略是否需要調整。', 'ur-ai-assistant'); ?>
    </div>

    <div class="ur-ai-summary-grid">
        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('總回饋數', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html($total_feedback); ?></p>
            <p class="ur-ai-summary-note">
                <?php
                printf(
                    esc_html__('問答回饋率：約 %s%%', 'ur-ai-assistant'),
                    esc_html($feedback_rate)
                );
                ?>
            </p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('有幫助', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html($helpful); ?></p>
            <p class="ur-ai-summary-note">
                <?php
                printf(
                    esc_html__('比例：約 %s%%', 'ur-ai-assistant'),
                    esc_html($helpful_rate)
                );
                ?>
            </p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('沒幫助', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html($not_helpful); ?></p>
            <p class="ur-ai-summary-note">
                <?php
                printf(
                    esc_html__('比例：約 %s%%', 'ur-ai-assistant'),
                    esc_html($not_helpful_rate)
                );
                ?>
            </p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('回答來源', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value">
                <?php echo esc_html(absint($summary['faq_answers'] ?? 0) + absint($summary['ai_answers'] ?? 0)); ?>
            </p>
            <p class="ur-ai-summary-note">
                <?php
                printf(
                    esc_html__('FAQ %1$d｜AI %2$d', 'ur-ai-assistant'),
                    absint($summary['faq_answers'] ?? 0),
                    absint($summary['ai_answers'] ?? 0)
                );
                ?>
            </p>
        </div>
    </div>

    <div class="ur-ai-grid ur-ai-grid-2">

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('回答來源回饋比較', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('比較 FAQ 固定回答與 AI 回答的使用者回饋。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($source_summary)) : ?>
                <div class="ur-ai-table-wrap">
                    <table class="ur-ai-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('回答來源', 'ur-ai-assistant'); ?></th>
                                <th class="ur-ai-cell-number"><?php echo esc_html__('總數', 'ur-ai-assistant'); ?></th>
                                <th class="ur-ai-cell-number"><?php echo esc_html__('有幫助', 'ur-ai-assistant'); ?></th>
                                <th class="ur-ai-cell-number"><?php echo esc_html__('沒幫助', 'ur-ai-assistant'); ?></th>
                                <th class="ur-ai-cell-number"><?php echo esc_html__('有幫助比例', 'ur-ai-assistant'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($source_summary as $source_item) : ?>
                                <?php
                                $source       = isset($source_item['source']) ? sanitize_key($source_item['source']) : '';
                                $label        = isset($source_item['label']) ? (string) $source_item['label'] : $source;
                                $source_total = absint($source_item['total'] ?? 0);
                                $source_good  = absint($source_item['helpful'] ?? 0);
                                $source_bad   = absint($source_item['not_helpful'] ?? 0);
                                $source_rate  = isset($source_item['helpful_rate']) ? (float) $source_item['helpful_rate'] : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($source ? $source : 'default'); ?>">
                                            <?php echo esc_html($label); ?>
                                        </span>
                                    </td>
                                    <td class="ur-ai-cell-number"><?php echo esc_html($source_total); ?></td>
                                    <td class="ur-ai-cell-number"><?php echo esc_html($source_good); ?></td>
                                    <td class="ur-ai-cell-number"><?php echo esc_html($source_bad); ?></td>
                                    <td class="ur-ai-cell-number">
                                        <?php echo esc_html($source_rate); ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="ur-ai-muted ur-ai-mt-12">
                    <?php echo esc_html__('若 FAQ 沒幫助比例偏高，可能代表 FAQ 內容需要改寫，或 FAQ 命中條件太寬。若 AI 沒幫助比例偏高，可檢查提示詞與知識庫缺口。', 'ur-ai-assistant'); ?>
                </p>
            <?php else : ?>
                <div class="ur-ai-empty-state">
                    <?php echo esc_html__('目前尚無足夠回饋資料可比較。', 'ur-ai-assistant'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('沒幫助原因統計', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('整理使用者標示沒幫助時選擇或填寫的原因。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($reason_counts)) : ?>
                <ul class="ur-ai-reason-list">
                    <?php foreach ($reason_counts as $reason_item) : ?>
                        <?php
                        $reason = isset($reason_item['reason']) ? (string) $reason_item['reason'] : '';
                        $total  = absint($reason_item['total'] ?? 0);

                        $percent = $not_helpful > 0
                            ? round(($total / $not_helpful) * 100, 1)
                            : 0;
                        ?>
                        <li>
                            <div>
                                <strong><?php echo esc_html($service->get_reason_label($reason)); ?></strong>
                                <div class="ur-ai-analytics-bar ur-ai-mt-12">
                                    <div
                                        class="ur-ai-analytics-bar-fill"
                                        style="width: <?php echo esc_attr(min(100, $percent)); ?>%;"
                                    ></div>
                                </div>
                            </div>
                            <div class="ur-ai-text-right">
                                <strong><?php echo esc_html($total); ?></strong>
                                <div class="ur-ai-muted ur-ai-small">
                                    <?php echo esc_html($percent); ?>%
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <div class="ur-ai-empty-state">
                    <?php echo esc_html__('目前尚無沒幫助原因資料。', 'ur-ai-assistant'); ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('需要優先改善的問答', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('以下為近期被標示「沒幫助」的問答，可優先檢查是否需要改寫 FAQ、補相關頁面或調整 AI 提示詞。', 'ur-ai-assistant'); ?>
                </p>
            </div>

            <div class="ur-ai-toolbar-actions">
                <a class="button" href="<?php echo esc_url($logs_url); ?>">
                    <?php echo esc_html__('查看全部沒幫助紀錄', 'ur-ai-assistant'); ?>
                </a>
            </div>
        </div>

        <?php if (!empty($not_helpful_logs)) : ?>
            <div class="ur-ai-table-wrap">
                <table class="ur-ai-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('問題', 'ur-ai-assistant'); ?></th>
                            <th><?php echo esc_html__('回答摘要', 'ur-ai-assistant'); ?></th>
                            <th><?php echo esc_html__('來源', 'ur-ai-assistant'); ?></th>
                            <th><?php echo esc_html__('回饋', 'ur-ai-assistant'); ?></th>
                            <th><?php echo esc_html__('時間', 'ur-ai-assistant'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($not_helpful_logs as $log) : ?>
                            <?php
                            $source = isset($log['answer_source']) ? sanitize_key($log['answer_source']) : '';
                            ?>
                            <tr>
                                <td class="ur-ai-cell-main">
                                    <strong><?php echo esc_html($log['question_excerpt'] ?? ''); ?></strong>
                                </td>
                                <td class="ur-ai-cell-wide">
                                    <?php echo esc_html($log['answer_excerpt'] ?? ''); ?>
                                </td>
                                <td>
                                    <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($source ? $source : 'default'); ?>">
                                        <?php echo esc_html($log['answer_source_label'] ?? $source); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ur-ai-badge ur-ai-badge-not_helpful">
                                        <?php echo esc_html($log['feedback_label'] ?? __('沒幫助', 'ur-ai-assistant')); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($log['created_at_label'] ?? ''); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div class="ur-ai-empty-state">
                <?php echo esc_html__('目前沒有被標示沒幫助的問答紀錄。', 'ur-ai-assistant'); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="ur-ai-grid ur-ai-grid-2">
        <div class="ur-ai-card">
            <h2><?php echo esc_html__('改善方向建議', 'ur-ai-assistant'); ?></h2>

            <ul class="ur-ai-dashboard-list">
                <li>
                    <span><?php echo esc_html__('常見問題被評為沒幫助', 'ur-ai-assistant'); ?></span>
                    <span class="ur-ai-muted"><?php echo esc_html__('優先改寫 FAQ 固定回答', 'ur-ai-assistant'); ?></span>
                </li>
                <li>
                    <span><?php echo esc_html__('AI 回答常被評為沒幫助', 'ur-ai-assistant'); ?></span>
                    <span class="ur-ai-muted"><?php echo esc_html__('檢查提示詞與回答限制', 'ur-ai-assistant'); ?></span>
                </li>
                <li>
                    <span><?php echo esc_html__('問題沒有推薦頁面', 'ur-ai-assistant'); ?></span>
                    <span class="ur-ai-muted"><?php echo esc_html__('新增網站文章或推薦頁面', 'ur-ai-assistant'); ?></span>
                </li>
                <li>
                    <span><?php echo esc_html__('同類問題反覆出現', 'ur-ai-assistant'); ?></span>
                    <span class="ur-ai-muted"><?php echo esc_html__('整理成 FAQ 或熱門問題', 'ur-ai-assistant'); ?></span>
                </li>
            </ul>

            <div class="ur-ai-dashboard-actions">
                <a class="button button-primary" href="<?php echo esc_url($faq_url); ?>">
                    <?php echo esc_html__('前往 FAQ 知識庫', 'ur-ai-assistant'); ?>
                </a>
            </div>
        </div>

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('資料解讀提醒', 'ur-ai-assistant'); ?></h2>

            <div class="ur-ai-warning-box">
                <?php echo esc_html__('回饋數量太少時，比例僅供參考，不宜單靠一兩筆回饋就大幅調整系統。建議觀察一段時間後，再依趨勢修正 FAQ、關鍵字與提示詞。', 'ur-ai-assistant'); ?>
            </div>

            <p class="ur-ai-muted">
                <?php echo esc_html__('正式營運後，可定期檢查沒幫助紀錄，把高頻問題沉澱成 FAQ，逐步降低 AI 成本並提升回答穩定度。', 'ur-ai-assistant'); ?>
            </p>
        </div>
    </div>

</div>