<?php
/**
 * UR AI Assistant FAQs Page
 *
 * FAQ 知識庫管理頁。
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
        array(
            'response' => 403,
        )
    );
}

if (!class_exists('UR_AI_FAQ_Service')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('FAQ 知識庫', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('FAQ 服務尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

$service = new UR_AI_FAQ_Service();

$editing_id  = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$editing_faq = $editing_id > 0 ? $service->find($editing_id) : null;

$message  = isset($_GET['ur_message']) ? sanitize_key(wp_unslash($_GET['ur_message'])) : '';
$msg_type = isset($_GET['ur_msg_type']) ? sanitize_key(wp_unslash($_GET['ur_msg_type'])) : 'updated';

$admin_message = '';

if (class_exists('UR_AI_FAQ_Admin')) {
    $faq_admin = new UR_AI_FAQ_Admin();

    if (method_exists($faq_admin, 'get_admin_message')) {
        $admin_message = $message ? $faq_admin->get_admin_message($message) : '';
    }
}

if ('' === $admin_message && '' !== $message) {
    $fallback_messages = array(
        'faq_created'       => __('FAQ 已新增。', 'ur-ai-assistant'),
        'faq_updated'       => __('FAQ 已更新。', 'ur-ai-assistant'),
        'faq_deleted'       => __('FAQ 已刪除。', 'ur-ai-assistant'),
        'faqs_activated'    => __('已批次啟用 FAQ。', 'ur-ai-assistant'),
        'faqs_deactivated'  => __('已批次停用 FAQ。', 'ur-ai-assistant'),
        'faqs_deleted'      => __('已批次刪除 FAQ。', 'ur-ai-assistant'),
        'no_items_selected' => __('請先選擇要操作的 FAQ。', 'ur-ai-assistant'),
    );

    $admin_message = isset($fallback_messages[$message])
        ? $fallback_messages[$message]
        : __('操作已完成。', 'ur-ai-assistant');
}

// 匯入完成訊息：附上新增／更新／略過統計。
if ('faq_imported' === $message) {
    $imp_created = isset($_GET['imp_created']) ? absint($_GET['imp_created']) : 0;
    $imp_updated = isset($_GET['imp_updated']) ? absint($_GET['imp_updated']) : 0;
    $imp_skipped = isset($_GET['imp_skipped']) ? absint($_GET['imp_skipped']) : 0;

    $admin_message = sprintf(
        /* translators: 1: 新增筆數 2: 更新筆數 3: 略過筆數 */
        __('CSV 匯入完成：新增 %1$d 筆、更新（覆蓋）%2$d 筆、略過 %3$d 筆。', 'ur-ai-assistant'),
        $imp_created,
        $imp_updated,
        $imp_skipped
    );
}

$status_filter   = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
$category_filter = isset($_GET['category']) ? sanitize_text_field(wp_unslash($_GET['category'])) : '';
$source_filter   = isset($_GET['source']) ? sanitize_key(wp_unslash($_GET['source'])) : '';
$search          = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$paged           = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page        = 20;

$query_args = array(
    'status'   => $status_filter,
    'category' => $category_filter,
    'source'   => $source_filter,
    'search'   => $search,
    'orderby'  => isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'sort_order',
    'order'    => isset($_GET['order']) ? sanitize_key(wp_unslash($_GET['order'])) : 'ASC',
    'limit'    => $per_page,
    'offset'   => ($paged - 1) * $per_page,
);

$faqs        = $service->query($query_args);
$total       = $service->count($query_args);
$total_pages = (int) ceil($total / $per_page);
$summary     = $service->get_summary();
$categories  = $service->get_categories();
$statuses    = $service->get_statuses();
$sources     = $service->get_sources();

$form_action = $editing_faq ? 'update_faq' : 'create_faq';

$form_values = array(
    'id'            => $editing_faq ? absint($editing_faq->id) : 0,
    'category'      => $editing_faq ? (string) $editing_faq->category : '待分類',
    'question'      => $editing_faq ? (string) $editing_faq->question : '',
    'answer'        => $editing_faq ? (string) $editing_faq->answer : '',
    'keywords'      => $editing_faq ? (string) $editing_faq->keywords : '',
    'status'        => $editing_faq ? (string) $editing_faq->status : 'inactive',
    'sort_order'    => $editing_faq ? absint($editing_faq->sort_order) : 100,
    'admin_note'    => $editing_faq ? (string) $editing_faq->admin_note : '',
    'review_status' => $editing_faq ? (string) $editing_faq->review_status : 'draft',
);

$base_url = admin_url('admin.php?page=ur-ai-assistant-faqs');

?>

<div class="wrap ur-ai-admin-page">

    <h1>
        <?php
        printf(
            /* translators: %s: 目前產業別的品牌名稱 */
            esc_html__('%s｜FAQ 知識庫', 'ur-ai-assistant'),
            esc_html(UR_AI_Admin_Menu::brand_name())
        );
        ?>
    </h1>

    <?php if (class_exists('UR_AI_Admin_Menu')) : ?>
        <?php UR_AI_Admin_Menu::render_group_tabs('knowledge'); ?>
    <?php endif; ?>

    <?php if ('' !== $admin_message) : ?>
        <div class="notice notice-<?php echo 'error' === $msg_type ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($admin_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="ur-ai-summary-grid">
        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('FAQ 總數', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['total'])); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('啟用中', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['active'])); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('停用 / 草稿', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['inactive'])); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('AI 轉入草稿', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['ai_drafts'])); ?></p>
        </div>
    </div>

    <?php
    $faq_export_url = class_exists('UR_AI_Exporter')
        ? UR_AI_Exporter::export_url('ur-ai-assistant-faqs', 'export_faqs_csv')
        : '';
    ?>
    <div class="ur-ai-card ur-ai-faq-io">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('CSV 匯入／匯出', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('用於搬移網站或一次大量新增 FAQ。建議先「匯出」一份現有資料作為備份與範本，在 Excel 編輯後再「匯入」。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <div class="ur-ai-faq-io-body">
            <div class="ur-ai-faq-io-col">
                <h3 class="ur-ai-faq-io-subtitle"><?php echo esc_html__('匯出 CSV', 'ur-ai-assistant'); ?></h3>
                <p class="ur-ai-faq-io-hint"><?php echo esc_html__('匯出目前篩選條件下的 FAQ（含分類、關鍵字、狀態、排序）。檔案為 UTF-8，可直接用 Excel 開啟編輯。', 'ur-ai-assistant'); ?></p>
                <?php if ('' !== $faq_export_url) : ?>
                    <a class="button button-secondary" href="<?php echo esc_url($faq_export_url); ?>">
                        <?php echo esc_html__('下載 CSV', 'ur-ai-assistant'); ?>
                    </a>
                <?php else : ?>
                    <p class="ur-ai-faq-io-hint"><?php echo esc_html__('匯出工具尚未載入。', 'ur-ai-assistant'); ?></p>
                <?php endif; ?>
            </div>

            <div class="ur-ai-faq-io-col">
                <h3 class="ur-ai-faq-io-subtitle"><?php echo esc_html__('匯入 CSV', 'ur-ai-assistant'); ?></h3>

                <div class="notice notice-warning inline ur-ai-faq-io-warning">
                    <p>
                        <strong><?php echo esc_html__('匯入前請注意：', 'ur-ai-assistant'); ?></strong>
                        <?php echo esc_html__('系統會以「標準問題」文字比對；題目完全相同的既有 FAQ 會被「覆蓋更新」，其餘則新增。覆蓋後無法復原，強烈建議先匯出一份備份。', 'ur-ai-assistant'); ?>
                    </p>
                    <p>
                        <?php echo esc_html__('CSV 必須包含「標準問題」與「固定回答」兩欄（必填）；分類、關鍵字、狀態、排序可留空，留空時分類為「待分類」、狀態為「停用」、排序為 100。', 'ur-ai-assistant'); ?>
                    </p>
                </div>

                <form method="post" enctype="multipart/form-data" class="ur-ai-faq-import-form">
                    <?php
                    if (class_exists('UR_AI_Security')) {
                        UR_AI_Security::admin_form_nonce_field();
                    } else {
                        wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                    }
                    ?>
                    <input type="hidden" name="ur_ai_action" value="import_faqs" />
                    <input type="file" name="ur_ai_faq_csv" accept=".csv,text/csv" required />
                    <button type="submit" class="button button-primary"
                        onclick="return confirm('<?php echo esc_js(__('確認要匯入嗎？題目相同的既有 FAQ 將被覆蓋更新，此動作無法復原。', 'ur-ai-assistant')); ?>');">
                        <?php echo esc_html__('開始匯入', 'ur-ai-assistant'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="ur-ai-grid ur-ai-grid-2">

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title">
                        <?php
                        echo $editing_faq
                            ? esc_html__('編輯 FAQ', 'ur-ai-assistant')
                            : esc_html__('新增 FAQ', 'ur-ai-assistant');
                        ?>
                    </h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('FAQ 啟用後，前台問答若命中此問題，會優先使用固定回答，不呼叫 AI。', 'ur-ai-assistant'); ?>
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

                <input type="hidden" name="ur_ai_action" value="<?php echo esc_attr($form_action); ?>">

                <?php if ($editing_faq) : ?>
                    <input type="hidden" name="faq_id" value="<?php echo esc_attr($form_values['id']); ?>">
                <?php endif; ?>

                <div class="ur-ai-form-row">
                    <label for="faq_category"><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></label>
                    <select id="faq_category" name="category">
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr($category); ?>" <?php selected($form_values['category'], $category); ?>>
                                <?php echo esc_html($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ur-ai-form-row">
                    <label for="faq_question"><?php echo esc_html__('標準問題', 'ur-ai-assistant'); ?></label>
                    <textarea
                        id="faq_question"
                        name="question"
                        rows="3"
                        required
                    ><?php echo esc_textarea($form_values['question']); ?></textarea>
                    <p class="ur-ai-form-help">
                        <?php echo esc_html__('建議使用一般民眾會問的白話問題，例如：什麼是權利變換？', 'ur-ai-assistant'); ?>
                    </p>
                </div>

                <div class="ur-ai-form-row">
                    <label for="faq_answer"><?php echo esc_html__('固定回答', 'ur-ai-assistant'); ?></label>
                    <textarea
                        id="faq_answer"
                        name="answer"
                        rows="8"
                        required
                    ><?php echo esc_textarea($form_values['answer']); ?></textarea>
                    <p class="ur-ai-form-help">
                        <?php echo esc_html__('建議回答客觀、中立、白話，並避免替個案作法律、估價或分配判斷。', 'ur-ai-assistant'); ?>
                    </p>
                </div>

                <div class="ur-ai-form-row">
                    <label for="faq_keywords"><?php echo esc_html__('關鍵字', 'ur-ai-assistant'); ?></label>
                    <input
                        type="text"
                        id="faq_keywords"
                        name="keywords"
                        value="<?php echo esc_attr($form_values['keywords']); ?>"
                        placeholder="<?php echo esc_attr__('例如：權利變換, 分配, 估價, 共同負擔', 'ur-ai-assistant'); ?>"
                    >
                    <p class="ur-ai-form-help">
                        <?php echo esc_html__('多個關鍵字請用逗號分隔。關鍵字越具體，FAQ 命中越穩定。', 'ur-ai-assistant'); ?>
                    </p>
                </div>

                <div class="ur-ai-grid ur-ai-grid-3">
                    <div class="ur-ai-form-row">
                        <label for="faq_status"><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></label>
                        <select id="faq_status" name="status">
                            <?php foreach ($statuses as $status_key => $status_label) : ?>
                                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($form_values['status'], $status_key); ?>>
                                    <?php echo esc_html($status_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ur-ai-form-row">
                        <label for="faq_review_status"><?php echo esc_html__('審核狀態', 'ur-ai-assistant'); ?></label>
                        <select id="faq_review_status" name="review_status">
                            <option value="draft" <?php selected($form_values['review_status'], 'draft'); ?>>
                                <?php echo esc_html__('草稿', 'ur-ai-assistant'); ?>
                            </option>
                            <option value="pending" <?php selected($form_values['review_status'], 'pending'); ?>>
                                <?php echo esc_html__('待審核', 'ur-ai-assistant'); ?>
                            </option>
                            <option value="approved" <?php selected($form_values['review_status'], 'approved'); ?>>
                                <?php echo esc_html__('已確認', 'ur-ai-assistant'); ?>
                            </option>
                            <option value="rejected" <?php selected($form_values['review_status'], 'rejected'); ?>>
                                <?php echo esc_html__('不採用', 'ur-ai-assistant'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="ur-ai-form-row">
                        <label for="faq_sort_order"><?php echo esc_html__('排序', 'ur-ai-assistant'); ?></label>
                        <input
                            type="number"
                            id="faq_sort_order"
                            name="sort_order"
                            value="<?php echo esc_attr($form_values['sort_order']); ?>"
                            min="0"
                            step="1"
                        >
                    </div>
                </div>

                <div class="ur-ai-form-row">
                    <label for="faq_admin_note"><?php echo esc_html__('管理備註', 'ur-ai-assistant'); ?></label>
                    <textarea
                        id="faq_admin_note"
                        name="admin_note"
                        rows="3"
                    ><?php echo esc_textarea($form_values['admin_note']); ?></textarea>
                </div>

                <div class="ur-ai-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php
                        echo $editing_faq
                            ? esc_html__('更新 FAQ', 'ur-ai-assistant')
                            : esc_html__('新增 FAQ', 'ur-ai-assistant');
                        ?>
                    </button>

                    <?php if ($editing_faq) : ?>
                        <a class="button" href="<?php echo esc_url($base_url); ?>">
                            <?php echo esc_html__('取消編輯', 'ur-ai-assistant'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('FAQ 使用建議', 'ur-ai-assistant'); ?></h2>

            <div class="ur-ai-help-box">
                <p>
                    <strong><?php echo esc_html__('建議原則：', 'ur-ai-assistant'); ?></strong>
                    <?php echo esc_html__('FAQ 適合放「穩定、常見、標準化」的問題，不建議放高度個案判斷。', 'ur-ai-assistant'); ?>
                </p>
            </div>

            <ul class="ur-ai-dashboard-list">
                <li>
                    <span><?php echo esc_html__('適合 FAQ', 'ur-ai-assistant'); ?></span>
                    <span class="ur-ai-muted"><?php echo esc_html__('制度說明、流程介紹、名詞解釋', 'ur-ai-assistant'); ?></span>
                </li>
                <li>
                    <span><?php echo esc_html__('不適合 FAQ', 'ur-ai-assistant'); ?></span>
                    <span class="ur-ai-muted"><?php echo esc_html__('個案分配、合約判斷、訴訟勝敗', 'ur-ai-assistant'); ?></span>
                </li>
                <li>
                    <span><?php echo esc_html__('關鍵字策略', 'ur-ai-assistant'); ?></span>
                    <span class="ur-ai-muted"><?php echo esc_html__('具體詞優先，避免只放「都更」', 'ur-ai-assistant'); ?></span>
                </li>
                <li>
                    <span><?php echo esc_html__('正式啟用前', 'ur-ai-assistant'); ?></span>
                    <span class="ur-ai-muted"><?php echo esc_html__('人工檢查內容與分類', 'ur-ai-assistant'); ?></span>
                </li>
            </ul>

            <div class="ur-ai-warning-box">
                <?php echo esc_html__('AI 問答轉成 FAQ 草稿後，請務必人工審閱。確認內容適合長期固定回答後，再改為啟用。', 'ur-ai-assistant'); ?>
            </div>
        </div>

    </div>

    <div class="ur-ai-toolbar">
        <form method="get" class="ur-ai-filter-form">
            <input type="hidden" name="page" value="ur-ai-assistant-faqs">

            <div>
                <label for="filter_status"><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></label>
                <select id="filter_status" name="status">
                    <option value=""><?php echo esc_html__('全部狀態', 'ur-ai-assistant'); ?></option>
                    <?php foreach ($statuses as $status_key => $status_label) : ?>
                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>>
                            <?php echo esc_html($status_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_category"><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></label>
                <select id="filter_category" name="category">
                    <option value=""><?php echo esc_html__('全部分類', 'ur-ai-assistant'); ?></option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr($category); ?>" <?php selected($category_filter, $category); ?>>
                            <?php echo esc_html($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_source"><?php echo esc_html__('來源', 'ur-ai-assistant'); ?></label>
                <select id="filter_source" name="source">
                    <option value=""><?php echo esc_html__('全部來源', 'ur-ai-assistant'); ?></option>
                    <?php foreach ($sources as $source_key => $source_label) : ?>
                        <option value="<?php echo esc_attr($source_key); ?>" <?php selected($source_filter, $source_key); ?>>
                            <?php echo esc_html($source_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="faq_search"><?php echo esc_html__('搜尋', 'ur-ai-assistant'); ?></label>
                <input
                    type="text"
                    id="faq_search"
                    name="s"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php echo esc_attr__('問題、回答、關鍵字', 'ur-ai-assistant'); ?>"
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
    </div>

    <form
        method="post"
        id="ur-ai-faq-bulk-form"
        class="ur-ai-bulk-form"
        data-total-matching="<?php echo esc_attr($total); ?>"
        data-page-count="<?php echo esc_attr(count($faqs)); ?>"
    >
        <?php
        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::admin_form_nonce_field();
        } else {
            wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
        }
        ?>

        <input type="hidden" name="ur_ai_action" value="bulk_faqs">
        <input type="hidden" name="filter_status" value="<?php echo esc_attr($status_filter); ?>">
        <input type="hidden" name="filter_category" value="<?php echo esc_attr($category_filter); ?>">
        <input type="hidden" name="filter_source" value="<?php echo esc_attr($source_filter); ?>">
        <input type="hidden" name="filter_search" value="<?php echo esc_attr($search); ?>">
        <input type="hidden" name="select_all_matching" value="0" class="ur-ai-select-all-flag">

        <div class="ur-ai-select-all-banner" hidden>
            <span class="ur-ai-select-all-banner-text"></span>
            <button type="button" class="button ur-ai-select-all-confirm">
                <?php echo esc_html__('選取全部', 'ur-ai-assistant'); ?>
            </button>
            <button type="button" class="button-link ur-ai-select-all-cancel">
                <?php echo esc_html__('僅本頁', 'ur-ai-assistant'); ?>
            </button>
        </div>

        <div class="ur-ai-toolbar">
            <div class="ur-ai-filter-form">
                <select name="bulk_action" class="ur-ai-bulk-action">
                    <option value=""><?php echo esc_html__('批次操作', 'ur-ai-assistant'); ?></option>
                    <option value="activate"><?php echo esc_html__('啟用', 'ur-ai-assistant'); ?></option>
                    <option value="deactivate"><?php echo esc_html__('停用', 'ur-ai-assistant'); ?></option>
                    <option value="delete"><?php echo esc_html__('刪除', 'ur-ai-assistant'); ?></option>
                </select>

                <button type="submit" class="button">
                    <?php echo esc_html__('套用', 'ur-ai-assistant'); ?>
                </button>
            </div>

            <div class="ur-ai-muted">
                <?php
                printf(
                    esc_html__('共 %d 筆 FAQ', 'ur-ai-assistant'),
                    absint($total)
                );
                ?>
            </div>
        </div>
    </form>

    <?php
    /*
     * 表格與每一列的「刪除」小表單刻意放在批次表單標籤之外：若放在裡面
     * 會形成瀏覽器不允許的巢狀 <form>，導致送出「套用」批次操作時，
     * 實際送到後台的 ur_ai_action 被某一列小表單的欄位覆蓋，批次操作
     * 因此完全失效卻沒有任何錯誤訊息。勾選框改用 HTML5 的 form="" 屬性
     * 歸屬回批次表單，效果與原本巢狀在表單內完全相同，但不會有巢狀
     * 表單的解析問題。
     */
    ?>

    <div class="ur-ai-table-wrap">
            <table class="ur-ai-table">
                <thead>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" class="ur-ai-check-all" form="ur-ai-faq-bulk-form">
                        </th>
                        <th><?php echo esc_html__('問題 / 回答', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('關鍵字', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('命中', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('排序', 'ur-ai-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($faqs)) : ?>
                        <?php foreach ($faqs as $faq) : ?>
                            <?php
                            $faq_id     = absint($faq->id);
                            $edit_url   = add_query_arg(array('page' => 'ur-ai-assistant-faqs', 'edit' => $faq_id), admin_url('admin.php'));
                            $status     = (string) $faq->status;
                            $source     = (string) $faq->source;
                            $status_cls = 'active' === $status ? 'active' : 'inactive';

                            $answer_excerpt = class_exists('UR_AI_Formatter')
                                ? UR_AI_Formatter::admin_excerpt($faq->answer, 120)
                                : wp_trim_words(wp_strip_all_tags($faq->answer), 32);

                            $question_excerpt = class_exists('UR_AI_Formatter')
                                ? UR_AI_Formatter::admin_excerpt($faq->question, 90)
                                : wp_trim_words(wp_strip_all_tags($faq->question), 24);
                            ?>
                            <tr>
                                <td class="check-column">
                                    <input
                                        type="checkbox"
                                        class="ur-ai-item-checkbox"
                                        name="faq_ids[]"
                                        value="<?php echo esc_attr($faq_id); ?>"
                                        form="ur-ai-faq-bulk-form"
                                    >
                                </td>

                                <td class="ur-ai-cell-wide">
                                    <div class="ur-ai-row-title">
                                        <?php echo esc_html($question_excerpt); ?>
                                    </div>

                                    <div class="ur-ai-muted ur-ai-mt-12">
                                        <?php echo esc_html($answer_excerpt); ?>
                                    </div>

                                    <div class="ur-ai-row-actions">
                                        <a href="<?php echo esc_url($edit_url); ?>">
                                            <?php echo esc_html__('編輯', 'ur-ai-assistant'); ?>
                                        </a>

                                        <form method="post">
                                            <?php
                                            if (class_exists('UR_AI_Security')) {
                                                UR_AI_Security::admin_form_nonce_field();
                                            } else {
                                                wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                                            }
                                            ?>
                                            <input type="hidden" name="ur_ai_action" value="delete_faq">
                                            <input type="hidden" name="faq_id" value="<?php echo esc_attr($faq_id); ?>">
                                            <button type="submit" class="button-link-delete ur-ai-delete-button">
                                                <?php echo esc_html__('刪除', 'ur-ai-assistant'); ?>
                                            </button>
                                        </form>

                                        <button
                                            type="button"
                                            class="button-link ur-ai-copy-button"
                                            data-copy-text="<?php echo esc_attr($faq->question); ?>"
                                        >
                                            <?php echo esc_html__('複製問題', 'ur-ai-assistant'); ?>
                                        </button>
                                    </div>
                                </td>

                                <td>
                                    <span class="ur-ai-badge ur-ai-badge-info">
                                        <?php echo esc_html($faq->category); ?>
                                    </span>
                                    <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                        <?php
                                        echo isset($sources[$source])
                                            ? esc_html($sources[$source])
                                            : esc_html($source);
                                        ?>
                                    </div>
                                </td>

                                <td class="ur-ai-cell-main">
                                    <?php echo esc_html($faq->keywords); ?>
                                </td>

                                <td>
                                    <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($status_cls); ?>">
                                        <?php
                                        echo isset($statuses[$status])
                                            ? esc_html($statuses[$status])
                                            : esc_html($status);
                                        ?>
                                    </span>

                                    <?php if (!empty($faq->review_status)) : ?>
                                        <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                            <?php echo esc_html($faq->review_status); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="ur-ai-cell-number">
                                    <?php echo esc_html(absint($faq->hit_count)); ?>
                                </td>

                                <td class="ur-ai-cell-number">
                                    <?php echo esc_html(absint($faq->sort_order)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7">
                                <div class="ur-ai-empty-state">
                                    <?php echo esc_html__('目前沒有符合條件的 FAQ。', 'ur-ai-assistant'); ?>
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
                    esc_html__('第 %1$d / %2$d 頁', 'ur-ai-assistant'),
                    absint($paged),
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
                            'paged' => max(1, $paged - 1),
                        )
                    ),
                    admin_url('admin.php')
                );

                $next_url = add_query_arg(
                    array_merge(
                        $_GET,
                        array(
                            'paged' => min($total_pages, $paged + 1),
                        )
                    ),
                    admin_url('admin.php')
                );
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

</div>