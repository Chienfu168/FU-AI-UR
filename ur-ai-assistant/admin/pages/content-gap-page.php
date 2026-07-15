<?php
/**
 * UR AI Assistant Content Gap Page
 *
 * 內容缺口總覽：整合熱門問題、問答紀錄、使用者回饋三個既有資料源，
 * 找出「該優先補上或修正哪些 FAQ」的候選清單。純讀取彙整，不新增
 * 任何資料表，也不提供表單送出動作——實際的新增／轉換／編輯操作，
 * 一律導回各自既有的管理頁面完成。
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
        array('response' => 403)
    );
}

if (!class_exists('UR_AI_Log_Service') || !class_exists('UR_AI_Popular_Question_Service') || !class_exists('UR_AI_FAQ_Service')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('內容缺口', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('內容缺口所需的服務類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

$log_service    = new UR_AI_Log_Service();
$popular_service = new UR_AI_Popular_Question_Service();
$faq_service    = new UR_AI_FAQ_Service();

$unlinked_popular   = $popular_service->get_high_click_unlinked_questions(15);
$frequent_ai        = $log_service->get_frequent_ai_questions(2, 15);
$not_helpful_faqs   = $log_service->get_not_helpful_faq_summary(15);

$popular_admin_url = admin_url('admin.php?page=ur-ai-assistant-popular-questions');
$faq_admin_url     = admin_url('admin.php?page=ur-ai-assistant-faqs');
$logs_admin_url    = admin_url('admin.php?page=ur-ai-assistant-logs');

?>

<div class="wrap ur-ai-admin-page">

    <h1>
        <?php
        printf(
            /* translators: %s: 目前產業別的品牌名稱 */
            esc_html__('%s｜內容缺口總覽', 'ur-ai-assistant'),
            esc_html(UR_AI_Admin_Menu::brand_name())
        );
        ?>
    </h1>

    <?php if (class_exists('UR_AI_Admin_Menu')) : ?>
        <?php UR_AI_Admin_Menu::render_group_tabs('knowledge'); ?>
    <?php endif; ?>

    <div class="ur-ai-help-box">
        <strong><?php echo esc_html__('這頁在做什麼：', 'ur-ai-assistant'); ?></strong>
        <?php echo esc_html__('整合熱門問題、問答紀錄與使用者回饋三個既有資料源，整理出「知識庫可能缺什麼」「哪些 FAQ 該優先改寫」的候選清單，方便定期檢視。這裡不會自動新增或修改任何內容，實際動作請透過下方連結前往對應管理頁完成。', 'ur-ai-assistant'); ?>
    </div>

    <div class="ur-ai-grid ur-ai-grid-2">

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('熱門但尚未建立 FAQ 的問題', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('點擊次數已達 5 次以上、卻還沒連結任何 FAQ 的熱門問題按鈕，代表使用者常點但目前沒有固定答案。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <?php if (empty($unlinked_popular)) : ?>
                <div class="ur-ai-empty-state">
                    <?php echo esc_html__('目前沒有符合條件的熱門問題，做得不錯。', 'ur-ai-assistant'); ?>
                </div>
            <?php else : ?>
                <div class="ur-ai-table-wrap">
                    <table class="ur-ai-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('問題', 'ur-ai-assistant'); ?></th>
                                <th class="ur-ai-cell-number"><?php echo esc_html__('點擊', 'ur-ai-assistant'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unlinked_popular as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html(wp_trim_words((string) $item->question, 16)); ?></td>
                                    <td class="ur-ai-cell-number"><?php echo esc_html(number_format_i18n(absint($item->click_count))); ?></td>
                                    <td>
                                        <div class="ur-ai-row-actions">
                                            <a href="<?php echo esc_url($popular_admin_url); ?>"><?php echo esc_html__('前往熱門問題頁', 'ur-ai-assistant'); ?></a>

                                            <form method="post">
                                                <?php
                                                if (class_exists('UR_AI_Security')) {
                                                    UR_AI_Security::admin_form_nonce_field();
                                                } else {
                                                    wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                                                }
                                                ?>
                                                <input type="hidden" name="ur_ai_action" value="convert_popular_question_to_faq">
                                                <input type="hidden" name="popular_question_id" value="<?php echo esc_attr(absint($item->id)); ?>">
                                                <button type="submit" class="button-link ur-ai-convert-faq-button">
                                                    <?php echo esc_html__('轉 FAQ 草稿', 'ur-ai-assistant'); ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="ur-ai-muted ur-ai-mt-12">
                    <?php echo esc_html__('點擊「轉 FAQ 草稿」會建立草稿；若已設定 OpenAI API Key，系統會先嘗試用 AI 草擬一版回答內容（未設定或呼叫失敗時則建立空白佔位草稿）。無論哪種情況，草稿一律以「停用／待審核」狀態建立，AI 草擬的內容尤其需要核對事實正確性後才能啟用。', 'ur-ai-assistant'); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('重複被問、一直靠 AI 回答的問題', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('同樣文字的問題被問過 2 次以上，且每次都沒命中 FAQ、需要呼叫 AI 回答。依完全相同文字比對，不同措辭的相同問題不會被合併計算。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <?php if (empty($frequent_ai)) : ?>
                <div class="ur-ai-empty-state">
                    <?php echo esc_html__('目前沒有符合條件的重複問題。', 'ur-ai-assistant'); ?>
                </div>
            <?php else : ?>
                <div class="ur-ai-table-wrap">
                    <table class="ur-ai-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('問題', 'ur-ai-assistant'); ?></th>
                                <th class="ur-ai-cell-number"><?php echo esc_html__('被問次數', 'ur-ai-assistant'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($frequent_ai as $item) : ?>
                                <?php
                                $log_search_url = add_query_arg(
                                    array(
                                        'page' => 'ur-ai-assistant-logs',
                                        's'    => rawurlencode((string) $item->question),
                                    ),
                                    admin_url('admin.php')
                                );
                                ?>
                                <tr>
                                    <td><?php echo esc_html(wp_trim_words((string) $item->question, 16)); ?></td>
                                    <td class="ur-ai-cell-number"><?php echo esc_html(number_format_i18n(absint($item->total))); ?></td>
                                    <td>
                                        <div class="ur-ai-row-actions">
                                            <a href="<?php echo esc_url($log_search_url); ?>"><?php echo esc_html__('查看紀錄', 'ur-ai-assistant'); ?></a>

                                            <form method="post">
                                                <?php
                                                if (class_exists('UR_AI_Security')) {
                                                    UR_AI_Security::admin_form_nonce_field();
                                                } else {
                                                    wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                                                }
                                                ?>
                                                <input type="hidden" name="ur_ai_action" value="convert_log_to_faq">
                                                <input type="hidden" name="log_id" value="<?php echo esc_attr(absint($item->sample_log_id)); ?>">
                                                <button type="submit" class="button-link ur-ai-convert-faq-button">
                                                    <?php echo esc_html__('轉 FAQ 草稿', 'ur-ai-assistant'); ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="ur-ai-muted ur-ai-mt-12">
                    <?php echo esc_html__('點擊「轉 FAQ 草稿」會取這批重複問題中最新一筆紀錄的內容建立草稿，完成後會導向問答紀錄頁；草稿仍需人工審閱後才會啟用。', 'ur-ai-assistant'); ?>
                </p>
            <?php endif; ?>
        </div>

    </div>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('「沒幫助」次數最多的 FAQ', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('使用者對這些 FAQ 固定回答標示過「沒幫助」，累積次數最多的排在最前面，建議優先檢視內容是否需要改寫。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <?php if (empty($not_helpful_faqs)) : ?>
            <div class="ur-ai-empty-state">
                <?php echo esc_html__('目前沒有 FAQ 累積到沒幫助回饋，做得不錯。', 'ur-ai-assistant'); ?>
            </div>
        <?php else : ?>
            <div class="ur-ai-table-wrap">
                <table class="ur-ai-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('FAQ 問題', 'ur-ai-assistant'); ?></th>
                            <th><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></th>
                            <th><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></th>
                            <th class="ur-ai-cell-number"><?php echo esc_html__('沒幫助次數', 'ur-ai-assistant'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($not_helpful_faqs as $row) : ?>
                            <?php
                            $faq_id  = absint($row->faq_id);
                            $faq     = $faq_service->find($faq_id);

                            if (!$faq) {
                                continue;
                            }

                            $faq_edit_url = add_query_arg(array('page' => 'ur-ai-assistant-faqs', 'edit' => $faq_id), admin_url('admin.php'));
                            ?>
                            <tr>
                                <td><?php echo esc_html(wp_trim_words((string) $faq->question, 16)); ?></td>
                                <td><?php echo esc_html((string) $faq->category); ?></td>
                                <td>
                                    <span class="ur-ai-badge ur-ai-badge-<?php echo 'active' === $faq->status ? 'active' : 'inactive'; ?>">
                                        <?php echo 'active' === $faq->status ? esc_html__('啟用', 'ur-ai-assistant') : esc_html__('停用', 'ur-ai-assistant'); ?>
                                    </span>
                                </td>
                                <td class="ur-ai-cell-number"><?php echo esc_html(number_format_i18n(absint($row->not_helpful_count))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($faq_edit_url); ?>"><?php echo esc_html__('編輯', 'ur-ai-assistant'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>
