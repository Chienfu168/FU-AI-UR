<?php
/**
 * UR AI Assistant Quiz Page
 *
 * 知識大考驗：題庫管理、AI 出題、審核、設定與排行榜管理。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('UR_AI_Permissions')) {
    UR_AI_Permissions::require_manage_faqs();
} elseif (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'),
        esc_html__('權限不足', 'ur-ai-assistant'),
        array('response' => 403)
    );
}

if (!class_exists('UR_AI_Quiz_Service') || !class_exists('UR_AI_Quiz_Admin') || !class_exists('UR_AI_Quiz_Settings')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('知識大考驗', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('知識大考驗模組類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

$service     = new UR_AI_Quiz_Service();
$quiz_admin  = new UR_AI_Quiz_Admin($service);

$message  = isset($_GET['ur_message']) ? sanitize_key(wp_unslash($_GET['ur_message'])) : '';
$msg_type = isset($_GET['ur_msg_type']) ? sanitize_key(wp_unslash($_GET['ur_msg_type'])) : 'updated';

$admin_message = $message ? $quiz_admin->get_admin_message($message) : '';

if ('ai_draft_generated' === $message) {
    $created = isset($_GET['created']) ? absint($_GET['created']) : 0;
    $failed  = isset($_GET['failed']) ? absint($_GET['failed']) : 0;

    $admin_message = sprintf(
        /* translators: 1: 產生成功筆數 2: 產生失敗筆數 */
        __('AI 出題完成：成功產生 %1$d 題草稿、失敗 %2$d 題，請至下方題庫審核後再上線。', 'ur-ai-assistant'),
        $created,
        $failed
    );
}

if ('quiz_imported' === $message) {
    $imp_created = isset($_GET['imp_created']) ? absint($_GET['imp_created']) : 0;
    $imp_skipped = isset($_GET['imp_skipped']) ? absint($_GET['imp_skipped']) : 0;

    $admin_message = sprintf(
        /* translators: 1: 匯入成功筆數 2: 略過筆數 */
        __('CSV 匯入完成：新增 %1$d 題（停用／待審核），略過 %2$d 筆格式錯誤，請至下方題庫審核後再上線。', 'ur-ai-assistant'),
        $imp_created,
        $imp_skipped
    );
}

$difficulties    = class_exists('UR_AI_Schema_Quiz_Questions') ? UR_AI_Schema_Quiz_Questions::get_difficulties() : array();
$statuses        = class_exists('UR_AI_Schema_Quiz_Questions') ? UR_AI_Schema_Quiz_Questions::get_statuses() : array();
$review_statuses = class_exists('UR_AI_Schema_Quiz_Questions') ? UR_AI_Schema_Quiz_Questions::get_review_statuses() : array();

$editing_id       = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$editing_question = $editing_id > 0 ? $service->find_question($editing_id) : null;

$form_action = $editing_question ? 'update_question' : 'create_question';

$form_values = array(
    'id'             => $editing_question ? absint($editing_question->id) : 0,
    'question'       => $editing_question ? (string) $editing_question->question : '',
    'option_a'       => $editing_question ? (string) $editing_question->option_a : '',
    'option_b'       => $editing_question ? (string) $editing_question->option_b : '',
    'option_c'       => $editing_question ? (string) $editing_question->option_c : '',
    'option_d'       => $editing_question ? (string) $editing_question->option_d : '',
    'correct_option' => $editing_question ? (string) $editing_question->correct_option : 'a',
    'explanation'    => $editing_question ? (string) $editing_question->explanation : '',
    'difficulty'     => $editing_question ? (string) $editing_question->difficulty : 'medium',
    'category'       => $editing_question ? (string) $editing_question->category : '',
    'status'         => $editing_question ? (string) $editing_question->status : 'inactive',
    'review_status'  => $editing_question ? (string) $editing_question->review_status : 'draft',
    'admin_note'     => $editing_question ? (string) $editing_question->admin_note : '',
);

$status_filter        = isset($_GET['q_status']) ? sanitize_key(wp_unslash($_GET['q_status'])) : '';
$review_status_filter = isset($_GET['q_review_status']) ? sanitize_key(wp_unslash($_GET['q_review_status'])) : '';
$category_filter      = isset($_GET['q_category']) ? sanitize_text_field(wp_unslash($_GET['q_category'])) : '';
$search               = isset($_GET['q_s']) ? sanitize_text_field(wp_unslash($_GET['q_s'])) : '';
$paged                = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

$list_data  = $quiz_admin->get_list_data(
    array(
        'status'        => $status_filter,
        'review_status' => $review_status_filter,
        'category'      => $category_filter,
        'search'        => $search,
        'paged'         => $paged,
    )
);
$questions   = $list_data['items'];
$total       = $list_data['total'];
$total_pages = isset($list_data['pagination']['pages']) ? absint($list_data['pagination']['pages']) : 1;

$total_questions  = $service->count_questions();
$active_count     = $service->count_active_questions();
$pending_count    = $service->count_questions(array('review_status' => 'draft'));
$total_attempts   = $service->count_attempts();

$settings = UR_AI_Quiz_Settings::get_all();

$attempts_paged = isset($_GET['attempts_paged']) ? max(1, absint($_GET['attempts_paged'])) : 1;
$attempts_per_page = 20;
$attempts = $service->query_attempts($attempts_per_page, ($attempts_paged - 1) * $attempts_per_page);
$attempts_total_pages = $attempts_per_page > 0 ? (int) ceil($total_attempts / $attempts_per_page) : 1;

$faq_candidates = array();
if (class_exists('UR_AI_FAQ_Service')) {
    $faq_service_for_quiz = new UR_AI_FAQ_Service();
    $faq_candidates       = $faq_service_for_quiz->query(
        array(
            'status'  => 'active',
            'orderby' => 'category',
            'order'   => 'ASC',
            'limit'   => 500,
            'offset'  => 0,
        )
    );
}

$base_url = admin_url('admin.php?page=ur-ai-assistant-quiz');

?>

<div class="wrap ur-ai-admin-page">

    <h1><?php echo esc_html__('都更 AI 助理｜知識大考驗', 'ur-ai-assistant'); ?></h1>

    <?php if ('' !== $admin_message) : ?>
        <div class="notice notice-<?php echo 'error' === $msg_type ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($admin_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!UR_AI_Quiz_Settings::is_enabled()) : ?>
        <div class="notice notice-warning">
            <p><?php echo esc_html__('知識大考驗目前尚未啟用，前台短碼不會顯示內容。請至下方「功能設定」勾選啟用。', 'ur-ai-assistant'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($active_count < 4) : ?>
        <div class="notice notice-warning">
            <p><?php echo esc_html__('目前「已啟用且已審核」的題目不足 4 題，前台無法開始挑戰。請新增題目或審核通過下方待審題目。', 'ur-ai-assistant'); ?></p>
        </div>
    <?php endif; ?>

    <div class="ur-ai-summary-grid">
        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('題庫總數', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($total_questions)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('可上場（啟用且已審核）', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($active_count)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('待審核', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($pending_count)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('累計挑戰次數', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($total_attempts)); ?></p>
        </div>
    </div>

    <div class="ur-ai-grid ur-ai-grid-2">

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('功能設定', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('設定前台是否顯示知識大考驗，以及每次挑戰的題數與同一 IP 的作答節流上限。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <form method="post" class="ur-ai-admin-form">
                <?php
                if (class_exists('UR_AI_Security')) {
                    UR_AI_Security::admin_form_nonce_field();
                } else {
                    wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                }
                ?>
                <input type="hidden" name="ur_ai_quiz_action" value="save_settings">

                <div class="ur-ai-form-row">
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                        <?php echo esc_html__('啟用前台知識大考驗', 'ur-ai-assistant'); ?>
                    </label>
                    <p class="ur-ai-form-help"><?php echo esc_html__('預設關閉，啟用後 [ur_ai_quiz] 短碼才會顯示內容。', 'ur-ai-assistant'); ?></p>
                </div>

                <div class="ur-ai-form-row">
                    <label for="quiz_title"><?php echo esc_html__('挑戰標題', 'ur-ai-assistant'); ?></label>
                    <input type="text" id="quiz_title" name="title" value="<?php echo esc_attr($settings['title']); ?>">
                </div>

                <div class="ur-ai-grid ur-ai-grid-2">
                    <div class="ur-ai-form-row">
                        <label for="quiz_question_count"><?php echo esc_html__('每次挑戰題數', 'ur-ai-assistant'); ?></label>
                        <input type="number" id="quiz_question_count" name="question_count" value="<?php echo esc_attr($settings['question_count']); ?>" min="5" max="30">
                        <p class="ur-ai-form-help"><?php echo esc_html__('範圍 5～30 題，若題庫不足會自動改抽現有全部題目。', 'ur-ai-assistant'); ?></p>
                    </div>

                    <div class="ur-ai-form-row">
                        <label for="quiz_rate_limit"><?php echo esc_html__('每小時作答上限（同 IP）', 'ur-ai-assistant'); ?></label>
                        <input type="number" id="quiz_rate_limit" name="rate_limit_per_hour" value="<?php echo esc_attr($settings['rate_limit_per_hour']); ?>" min="1" max="20">
                        <p class="ur-ai-form-help"><?php echo esc_html__('避免同一人重複洗分，範圍 1～20 次。', 'ur-ai-assistant'); ?></p>
                    </div>
                </div>

                <div class="ur-ai-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('儲存設定', 'ur-ai-assistant'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('AI 依 FAQ 出題', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('勾選要出題的 FAQ，AI 會依其問答內容產生選擇題草稿。草稿一律為「停用／待審核」狀態，需人工審核通過後才會上線。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <?php if (empty($faq_candidates)) : ?>
                <p class="ur-ai-muted"><?php echo esc_html__('目前沒有啟用中的 FAQ 可供出題，請先至「FAQ 知識庫」新增並啟用內容。', 'ur-ai-assistant'); ?></p>
            <?php else : ?>
                <form method="post" class="ur-ai-admin-form">
                    <?php
                    if (class_exists('UR_AI_Security')) {
                        UR_AI_Security::admin_form_nonce_field();
                    } else {
                        wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                    }
                    ?>
                    <input type="hidden" name="ur_ai_quiz_action" value="generate_ai_draft">

                    <div class="ur-ai-form-row">
                        <div class="ur-ai-quiz-faq-picker">
                            <?php foreach ($faq_candidates as $faq) : ?>
                                <label class="ur-ai-quiz-faq-picker-item">
                                    <input type="checkbox" name="faq_ids[]" value="<?php echo esc_attr(absint($faq->id)); ?>">
                                    <span>
                                        <span class="ur-ai-badge ur-ai-badge-info"><?php echo esc_html($faq->category); ?></span>
                                        <?php echo esc_html(wp_trim_words(wp_strip_all_tags($faq->question), 20)); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="ur-ai-form-help"><?php echo esc_html__('可複選多筆一次產生。需先於「AI 設定」頁設定 OpenAI API Key。', 'ur-ai-assistant'); ?></p>
                    </div>

                    <div class="ur-ai-form-actions">
                        <button type="submit" class="button button-primary">
                            <?php echo esc_html__('產生題目草稿', 'ur-ai-assistant'); ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    </div>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('CSV 批次匯入題目', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('適合一次匯入大量題目（例如整批草稿）。CSV 需包含「題目」「選項A」「選項B」欄位（選項C／D、正確答案、難度、分類、解析可留空）。匯入後一律為「停用／待審核」，需人工審核通過後才會上線。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="ur-ai-admin-form">
            <?php
            if (class_exists('UR_AI_Security')) {
                UR_AI_Security::admin_form_nonce_field();
            } else {
                wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
            }
            ?>
            <input type="hidden" name="ur_ai_quiz_action" value="import_questions">
            <input type="file" name="ur_ai_quiz_csv" accept=".csv,text/csv" required />
            <button type="submit" class="button button-primary">
                <?php echo esc_html__('開始匯入', 'ur-ai-assistant'); ?>
            </button>
        </form>
    </div>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title">
                    <?php
                    echo $editing_question
                        ? esc_html__('編輯題目', 'ur-ai-assistant')
                        : esc_html__('新增題目', 'ur-ai-assistant');
                    ?>
                </h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('題目新增後預設為「停用／待審核」，請先於下方列表確認內容正確後再核准上線。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <form method="post" class="ur-ai-admin-form">
            <?php
            if (class_exists('UR_AI_Security')) {
                UR_AI_Security::admin_form_nonce_field();
            } else {
                wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
            }
            ?>

            <input type="hidden" name="ur_ai_quiz_action" value="<?php echo esc_attr($form_action); ?>">
            <input type="hidden" name="source" value="manual">

            <?php if ($editing_question) : ?>
                <input type="hidden" name="question_id" value="<?php echo esc_attr($form_values['id']); ?>">
            <?php endif; ?>

            <div class="ur-ai-form-row">
                <label for="quiz_question"><?php echo esc_html__('題目', 'ur-ai-assistant'); ?></label>
                <textarea id="quiz_question" name="question" rows="2" required><?php echo esc_textarea($form_values['question']); ?></textarea>
            </div>

            <div class="ur-ai-grid ur-ai-grid-2">
                <div class="ur-ai-form-row">
                    <label for="quiz_option_a"><?php echo esc_html__('選項 A', 'ur-ai-assistant'); ?></label>
                    <input type="text" id="quiz_option_a" name="option_a" value="<?php echo esc_attr($form_values['option_a']); ?>" required>
                </div>
                <div class="ur-ai-form-row">
                    <label for="quiz_option_b"><?php echo esc_html__('選項 B', 'ur-ai-assistant'); ?></label>
                    <input type="text" id="quiz_option_b" name="option_b" value="<?php echo esc_attr($form_values['option_b']); ?>" required>
                </div>
                <div class="ur-ai-form-row">
                    <label for="quiz_option_c"><?php echo esc_html__('選項 C', 'ur-ai-assistant'); ?></label>
                    <input type="text" id="quiz_option_c" name="option_c" value="<?php echo esc_attr($form_values['option_c']); ?>">
                </div>
                <div class="ur-ai-form-row">
                    <label for="quiz_option_d"><?php echo esc_html__('選項 D', 'ur-ai-assistant'); ?></label>
                    <input type="text" id="quiz_option_d" name="option_d" value="<?php echo esc_attr($form_values['option_d']); ?>">
                </div>
            </div>

            <div class="ur-ai-form-row">
                <label for="quiz_explanation"><?php echo esc_html__('解析（作答完成後顯示）', 'ur-ai-assistant'); ?></label>
                <textarea id="quiz_explanation" name="explanation" rows="3"><?php echo esc_textarea($form_values['explanation']); ?></textarea>
            </div>

            <div class="ur-ai-grid ur-ai-grid-3">
                <div class="ur-ai-form-row">
                    <label for="quiz_correct_option"><?php echo esc_html__('正確答案', 'ur-ai-assistant'); ?></label>
                    <select id="quiz_correct_option" name="correct_option">
                        <?php foreach (array('a', 'b', 'c', 'd') as $letter) : ?>
                            <option value="<?php echo esc_attr($letter); ?>" <?php selected($form_values['correct_option'], $letter); ?>>
                                <?php echo esc_html(strtoupper($letter)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ur-ai-form-row">
                    <label for="quiz_difficulty"><?php echo esc_html__('難度', 'ur-ai-assistant'); ?></label>
                    <select id="quiz_difficulty" name="difficulty">
                        <?php foreach ($difficulties as $diff_key => $diff_label) : ?>
                            <option value="<?php echo esc_attr($diff_key); ?>" <?php selected($form_values['difficulty'], $diff_key); ?>>
                                <?php echo esc_html($diff_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ur-ai-form-row">
                    <label for="quiz_category"><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></label>
                    <input type="text" id="quiz_category" name="category" value="<?php echo esc_attr($form_values['category']); ?>" placeholder="<?php echo esc_attr__('例如：都市更新基礎', 'ur-ai-assistant'); ?>">
                </div>
            </div>

            <div class="ur-ai-grid ur-ai-grid-2">
                <div class="ur-ai-form-row">
                    <label for="quiz_status"><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></label>
                    <select id="quiz_status" name="status">
                        <?php foreach ($statuses as $status_key => $status_label) : ?>
                            <option value="<?php echo esc_attr($status_key); ?>" <?php selected($form_values['status'], $status_key); ?>>
                                <?php echo esc_html($status_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ur-ai-form-row">
                    <label for="quiz_review_status"><?php echo esc_html__('審核狀態', 'ur-ai-assistant'); ?></label>
                    <select id="quiz_review_status" name="review_status">
                        <?php foreach ($review_statuses as $review_key => $review_label) : ?>
                            <option value="<?php echo esc_attr($review_key); ?>" <?php selected($form_values['review_status'], $review_key); ?>>
                                <?php echo esc_html($review_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="ur-ai-form-row">
                <label for="quiz_admin_note"><?php echo esc_html__('管理備註', 'ur-ai-assistant'); ?></label>
                <textarea id="quiz_admin_note" name="admin_note" rows="2"><?php echo esc_textarea($form_values['admin_note']); ?></textarea>
            </div>

            <div class="ur-ai-form-actions">
                <button type="submit" class="button button-primary">
                    <?php
                    echo $editing_question
                        ? esc_html__('更新題目', 'ur-ai-assistant')
                        : esc_html__('新增題目', 'ur-ai-assistant');
                    ?>
                </button>

                <?php if ($editing_question) : ?>
                    <a class="button" href="<?php echo esc_url($base_url); ?>">
                        <?php echo esc_html__('取消編輯', 'ur-ai-assistant'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="ur-ai-toolbar">
        <form method="get" class="ur-ai-filter-form">
            <input type="hidden" name="page" value="ur-ai-assistant-quiz">

            <div>
                <label for="filter_q_status"><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></label>
                <select id="filter_q_status" name="q_status">
                    <option value=""><?php echo esc_html__('全部狀態', 'ur-ai-assistant'); ?></option>
                    <?php foreach ($statuses as $status_key => $status_label) : ?>
                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>>
                            <?php echo esc_html($status_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_q_review_status"><?php echo esc_html__('審核狀態', 'ur-ai-assistant'); ?></label>
                <select id="filter_q_review_status" name="q_review_status">
                    <option value=""><?php echo esc_html__('全部審核狀態', 'ur-ai-assistant'); ?></option>
                    <?php foreach ($review_statuses as $review_key => $review_label) : ?>
                        <option value="<?php echo esc_attr($review_key); ?>" <?php selected($review_status_filter, $review_key); ?>>
                            <?php echo esc_html($review_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_q_category"><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></label>
                <input type="text" id="filter_q_category" name="q_category" value="<?php echo esc_attr($category_filter); ?>">
            </div>

            <div>
                <label for="filter_q_s"><?php echo esc_html__('搜尋', 'ur-ai-assistant'); ?></label>
                <input type="text" id="filter_q_s" name="q_s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('題目內容', 'ur-ai-assistant'); ?>">
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
    </div>

    <div class="ur-ai-muted ur-ai-mt-12">
        <?php
        printf(
            /* translators: %d: 題目筆數 */
            esc_html__('共 %d 筆題目', 'ur-ai-assistant'),
            absint($total)
        );
        ?>
    </div>

    <div class="ur-ai-table-wrap">
        <table class="ur-ai-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('題目', 'ur-ai-assistant'); ?></th>
                    <th><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></th>
                    <th><?php echo esc_html__('難度', 'ur-ai-assistant'); ?></th>
                    <th><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></th>
                    <th><?php echo esc_html__('審核', 'ur-ai-assistant'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($questions)) : ?>
                    <?php foreach ($questions as $question) : ?>
                        <?php
                        $question_id      = absint($question->id);
                        $edit_url         = add_query_arg(array('page' => 'ur-ai-assistant-quiz', 'edit' => $question_id), admin_url('admin.php'));
                        $question_status  = (string) $question->status;
                        $review_status    = (string) $question->review_status;
                        $status_cls       = 'active' === $question_status ? 'active' : 'inactive';
                        $review_cls       = 'approved' === $review_status ? 'active' : ('rejected' === $review_status ? 'danger' : 'draft');
                        $question_excerpt = wp_trim_words(wp_strip_all_tags($question->question), 24);
                        ?>
                        <tr>
                            <td class="ur-ai-cell-wide">
                                <div class="ur-ai-row-title"><?php echo esc_html($question_excerpt); ?></div>

                                <div class="ur-ai-row-actions">
                                    <a href="<?php echo esc_url($edit_url); ?>">
                                        <?php echo esc_html__('編輯', 'ur-ai-assistant'); ?>
                                    </a>

                                    <?php if ('approved' !== $review_status) : ?>
                                        <form method="post">
                                            <?php
                                            if (class_exists('UR_AI_Security')) {
                                                UR_AI_Security::admin_form_nonce_field();
                                            } else {
                                                wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                                            }
                                            ?>
                                            <input type="hidden" name="ur_ai_quiz_action" value="review_question">
                                            <input type="hidden" name="question_id" value="<?php echo esc_attr($question_id); ?>">
                                            <input type="hidden" name="decision" value="approve">
                                            <button type="submit" class="button-link">
                                                <?php echo esc_html__('核准上線', 'ur-ai-assistant'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ('rejected' !== $review_status) : ?>
                                        <form method="post">
                                            <?php
                                            if (class_exists('UR_AI_Security')) {
                                                UR_AI_Security::admin_form_nonce_field();
                                            } else {
                                                wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                                            }
                                            ?>
                                            <input type="hidden" name="ur_ai_quiz_action" value="review_question">
                                            <input type="hidden" name="question_id" value="<?php echo esc_attr($question_id); ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <button type="submit" class="button-link">
                                                <?php echo esc_html__('退回', 'ur-ai-assistant'); ?>
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
                                        <input type="hidden" name="ur_ai_quiz_action" value="delete_question">
                                        <input type="hidden" name="question_id" value="<?php echo esc_attr($question_id); ?>">
                                        <button
                                            type="submit"
                                            class="button-link-delete ur-ai-delete-button"
                                            onclick="return confirm('<?php echo esc_js(__('確定要刪除此題目嗎？此動作無法復原。', 'ur-ai-assistant')); ?>');"
                                        >
                                            <?php echo esc_html__('刪除', 'ur-ai-assistant'); ?>
                                        </button>
                                    </form>
                                </div>
                            </td>

                            <td>
                                <?php echo $question->category ? esc_html($question->category) : '<span class="ur-ai-muted">' . esc_html__('未分類', 'ur-ai-assistant') . '</span>'; ?>
                            </td>

                            <td>
                                <span class="ur-ai-badge ur-ai-badge-info">
                                    <?php echo isset($difficulties[$question->difficulty]) ? esc_html($difficulties[$question->difficulty]) : esc_html($question->difficulty); ?>
                                </span>
                            </td>

                            <td>
                                <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($status_cls); ?>">
                                    <?php echo isset($statuses[$question_status]) ? esc_html($statuses[$question_status]) : esc_html($question_status); ?>
                                </span>
                            </td>

                            <td>
                                <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($review_cls); ?>">
                                    <?php echo isset($review_statuses[$review_status]) ? esc_html($review_statuses[$review_status]) : esc_html($review_status); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">
                            <div class="ur-ai-empty-state">
                                <?php echo esc_html__('目前沒有符合條件的題目。', 'ur-ai-assistant'); ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1) : ?>
        <div class="ur-ai-pagination">
            <div class="ur-ai-pagination-info">
                <?php
                printf(
                    /* translators: 1: 目前頁次 2: 總頁數 */
                    esc_html__('第 %1$d / %2$d 頁', 'ur-ai-assistant'),
                    absint($paged),
                    absint($total_pages)
                );
                ?>
            </div>

            <div class="ur-ai-pagination-links">
                <?php
                $prev_url = add_query_arg(array_merge($_GET, array('paged' => max(1, $paged - 1))), admin_url('admin.php'));
                $next_url = add_query_arg(array_merge($_GET, array('paged' => min($total_pages, $paged + 1))), admin_url('admin.php'));
                ?>
                <?php if ($paged > 1) : ?>
                    <a class="button" href="<?php echo esc_url($prev_url); ?>">
                        <?php echo esc_html__('上一頁', 'ur-ai-assistant'); ?>
                    </a>
                <?php endif; ?>
                <?php if ($paged < $total_pages) : ?>
                    <a class="button" href="<?php echo esc_url($next_url); ?>">
                        <?php echo esc_html__('下一頁', 'ur-ai-assistant'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('排行榜管理', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('列出目前作答紀錄，可刪除不當暱稱或明顯異常的紀錄。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <div class="ur-ai-table-wrap">
            <table class="ur-ai-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('暱稱', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('分數', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('答對題數', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('作答時間', 'ur-ai-assistant'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($attempts)) : ?>
                        <?php foreach ($attempts as $attempt) : ?>
                            <?php $nickname = trim((string) $attempt->nickname); ?>
                            <tr>
                                <td>
                                    <?php echo '' !== $nickname ? esc_html($nickname) : '<span class="ur-ai-muted">' . esc_html__('匿名', 'ur-ai-assistant') . '</span>'; ?>
                                </td>
                                <td class="ur-ai-cell-number"><?php echo esc_html(absint($attempt->score)); ?></td>
                                <td class="ur-ai-cell-number">
                                    <?php
                                    printf(
                                        /* translators: 1: correct 2: total */
                                        esc_html__('%1$d / %2$d', 'ur-ai-assistant'),
                                        absint($attempt->correct_count),
                                        absint($attempt->total_questions)
                                    );
                                    ?>
                                </td>
                                <td><?php echo esc_html(mysql2date('Y-m-d H:i', $attempt->created_at)); ?></td>
                                <td>
                                    <form method="post">
                                        <?php
                                        if (class_exists('UR_AI_Security')) {
                                            UR_AI_Security::admin_form_nonce_field();
                                        } else {
                                            wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                                        }
                                        ?>
                                        <input type="hidden" name="ur_ai_quiz_action" value="delete_attempt">
                                        <input type="hidden" name="attempt_id" value="<?php echo esc_attr(absint($attempt->id)); ?>">
                                        <button
                                            type="submit"
                                            class="button-link-delete ur-ai-delete-button"
                                            onclick="return confirm('<?php echo esc_js(__('確定要刪除此筆排行榜紀錄嗎？', 'ur-ai-assistant')); ?>');"
                                        >
                                            <?php echo esc_html__('刪除', 'ur-ai-assistant'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">
                                <div class="ur-ai-empty-state">
                                    <?php echo esc_html__('目前還沒有任何作答紀錄。', 'ur-ai-assistant'); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($attempts_total_pages > 1) : ?>
            <div class="ur-ai-pagination">
                <div class="ur-ai-pagination-info">
                    <?php
                    printf(
                        /* translators: 1: 目前頁次 2: 總頁數 */
                        esc_html__('第 %1$d / %2$d 頁', 'ur-ai-assistant'),
                        absint($attempts_paged),
                        absint($attempts_total_pages)
                    );
                    ?>
                </div>
                <div class="ur-ai-pagination-links">
                    <?php
                    $attempts_prev_url = add_query_arg(array_merge($_GET, array('attempts_paged' => max(1, $attempts_paged - 1))), admin_url('admin.php'));
                    $attempts_next_url = add_query_arg(array_merge($_GET, array('attempts_paged' => min($attempts_total_pages, $attempts_paged + 1))), admin_url('admin.php'));
                    ?>
                    <?php if ($attempts_paged > 1) : ?>
                        <a class="button" href="<?php echo esc_url($attempts_prev_url); ?>">
                            <?php echo esc_html__('上一頁', 'ur-ai-assistant'); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($attempts_paged < $attempts_total_pages) : ?>
                        <a class="button" href="<?php echo esc_url($attempts_next_url); ?>">
                            <?php echo esc_html__('下一頁', 'ur-ai-assistant'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('使用說明', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('將以下短碼放到任一頁面或文章，即可顯示對應區塊。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <div class="ur-ai-help-box">
            <p>
                <strong><?php echo esc_html__('建議流程：', 'ur-ai-assistant'); ?></strong>
                <?php echo esc_html__('先於上方新增或以 AI 產生題目草稿並審核通過，再啟用功能設定，最後將短碼放到前台頁面。', 'ur-ai-assistant'); ?>
            </p>
        </div>

        <p>
            <code class="ur-ai-code" id="ur-ai-quiz-shortcode">[ur_ai_quiz]</code>
            <button type="button" class="button ur-ai-copy-button" data-copy-target="#ur-ai-quiz-shortcode">
                <?php echo esc_html__('複製', 'ur-ai-assistant'); ?>
            </button>
        </p>
        <p class="ur-ai-muted"><?php echo esc_html__('挑戰作答區塊：使用者隨機抽題作答、送出後查看分數，並可留暱稱上榜（需先啟用上方的「啟用前台知識大考驗」）。', 'ur-ai-assistant'); ?></p>

        <hr>

        <p>
            <code class="ur-ai-code" id="ur-ai-quiz-leaderboard-shortcode">[ur_ai_quiz_leaderboard]</code>
            <button type="button" class="button ur-ai-copy-button" data-copy-target="#ur-ai-quiz-leaderboard-shortcode">
                <?php echo esc_html__('複製', 'ur-ai-assistant'); ?>
            </button>
        </p>
        <p class="ur-ai-muted"><?php echo esc_html__('排行榜區塊：列出目前分數最高的挑戰者，建議另外建立一個獨立頁面（例如「知識挑戰排行榜」）並放上此短碼。', 'ur-ai-assistant'); ?></p>
    </div>

</div>
