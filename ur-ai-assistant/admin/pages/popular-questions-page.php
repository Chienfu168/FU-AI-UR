<?php
/**
 * UR AI Assistant Popular Questions Page
 *
 * 熱門問題管理頁。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('UR_AI_Permissions')) {
    UR_AI_Permissions::require_manage_popular_questions();
} elseif (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'),
        esc_html__('權限不足', 'ur-ai-assistant'),
        array(
            'response' => 403,
        )
    );
}

if (!class_exists('UR_AI_Popular_Question_Admin') || !class_exists('UR_AI_Popular_Question_Service')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('熱門問題', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('熱門問題管理類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

$admin   = new UR_AI_Popular_Question_Admin();
$service = new UR_AI_Popular_Question_Service();

$list_data = $admin->get_list_data($_GET);

$items      = isset($list_data['items']) ? $list_data['items'] : array();
$formatted  = isset($list_data['formatted']) ? $list_data['formatted'] : array();
$total      = isset($list_data['total']) ? absint($list_data['total']) : 0;
$summary    = isset($list_data['summary']) ? $list_data['summary'] : array();
$pagination = isset($list_data['pagination']) ? $list_data['pagination'] : array();
$query_args = isset($list_data['query_args']) ? $list_data['query_args'] : array();

$editing_id   = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$editing_item = $editing_id > 0 ? $service->find($editing_id) : null;

$message  = isset($_GET['ur_message']) ? sanitize_key(wp_unslash($_GET['ur_message'])) : '';
$msg_type = isset($_GET['ur_msg_type']) ? sanitize_key(wp_unslash($_GET['ur_msg_type'])) : 'updated';

$admin_message = $message ? $admin->get_admin_message($message) : '';

$status_filter   = isset($query_args['status']) ? $query_args['status'] : '';
$category_filter = isset($query_args['category']) ? $query_args['category'] : '';
$source_filter   = isset($query_args['source']) ? $query_args['source'] : '';
$linked_filter   = isset($query_args['linked']) ? $query_args['linked'] : null;
$search          = isset($query_args['search']) ? $query_args['search'] : '';

$current_page = isset($pagination['current']) ? absint($pagination['current']) : 1;
$total_pages  = isset($pagination['total_pages']) ? absint($pagination['total_pages']) : 1;

$categories = $service->get_categories();
$statuses   = $service->get_statuses();
$sources    = $service->get_sources();

$form_action = $editing_item ? 'update_popular_question' : 'create_popular_question';

$form_values = array(
    'id'              => $editing_item ? absint($editing_item->id) : 0,
    'category'        => $editing_item ? (string) $editing_item->category : '其他',
    'question'        => $editing_item ? (string) $editing_item->question : '',
    'submit_question' => $editing_item ? (string) $editing_item->submit_question : '',
    'description'     => $editing_item ? (string) $editing_item->description : '',
    'status'          => $editing_item ? (string) $editing_item->status : 'inactive',
    'source'          => $editing_item ? (string) $editing_item->source : 'manual',
    'faq_id'          => $editing_item ? absint($editing_item->faq_id) : 0,
    'sort_order'      => $editing_item ? absint($editing_item->sort_order) : 100,
    'admin_note'      => $editing_item ? (string) $editing_item->admin_note : '',
);

$base_url = admin_url('admin.php?page=ur-ai-assistant-popular-questions');

$export_url = add_query_arg(
    array_merge(
        $_GET,
        array(
            'page'      => 'ur-ai-assistant-popular-questions',
            'ur_action' => 'export_popular_questions_csv',
            '_wpnonce'  => wp_create_nonce('ur_ai_export_nonce'),
        )
    ),
    admin_url('admin.php')
);

$importable_faqs = $admin->get_importable_faqs(30);

?>

<div class="wrap ur-ai-admin-page">

    <h1><?php echo esc_html__('都更 AI 助理｜熱門問題', 'ur-ai-assistant'); ?></h1>

    <?php if ('' !== $admin_message) : ?>
        <div class="notice notice-<?php echo 'error' === $msg_type ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($admin_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="ur-ai-summary-grid">
        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('熱門問題總數', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['total'] ?? 0)); ?></p>
            <p class="ur-ai-summary-note">
                <?php
                printf(
                    esc_html__('啟用 %1$d｜停用 %2$d', 'ur-ai-assistant'),
                    absint($summary['active'] ?? 0),
                    absint($summary['inactive'] ?? 0)
                );
                ?>
            </p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('總點擊', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['total_click_count'] ?? 0)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('已對應 FAQ', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['linked_faq'] ?? 0)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('未對應 FAQ', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['unlinked_faq'] ?? 0)); ?></p>
            <p class="ur-ai-summary-note">
                <?php echo esc_html__('可轉成 FAQ 草稿補強知識庫', 'ur-ai-assistant'); ?>
            </p>
        </div>
    </div>

    <div class="ur-ai-grid ur-ai-grid-2">

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title">
                        <?php
                        echo $editing_item
                            ? esc_html__('編輯熱門問題', 'ur-ai-assistant')
                            : esc_html__('新增熱門問題', 'ur-ai-assistant');
                        ?>
                    </h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('熱門問題會顯示於前台，協助訪客快速點選常見問題。', 'ur-ai-assistant'); ?>
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

                <?php if ($editing_item) : ?>
                    <input type="hidden" name="popular_question_id" value="<?php echo esc_attr($form_values['id']); ?>">
                <?php endif; ?>

                <div class="ur-ai-form-row">
                    <label for="popular_category"><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></label>
                    <select id="popular_category" name="category">
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr($category); ?>" <?php selected($form_values['category'], $category); ?>>
                                <?php echo esc_html($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ur-ai-form-row">
                    <label for="popular_question"><?php echo esc_html__('前台顯示問題', 'ur-ai-assistant'); ?></label>
                    <textarea
                        id="popular_question"
                        name="question"
                        rows="3"
                        required
                    ><?php echo esc_textarea($form_values['question']); ?></textarea>
                    <p class="ur-ai-form-help">
                        <?php echo esc_html__('顯示在前台按鈕上的文字，建議簡短清楚。', 'ur-ai-assistant'); ?>
                    </p>
                </div>

                <div class="ur-ai-form-row">
                    <label for="popular_submit_question"><?php echo esc_html__('實際送出問題', 'ur-ai-assistant'); ?></label>
                    <textarea
                        id="popular_submit_question"
                        name="submit_question"
                        rows="3"
                    ><?php echo esc_textarea($form_values['submit_question']); ?></textarea>
                    <p class="ur-ai-form-help">
                        <?php echo esc_html__('若留空，會使用前台顯示問題。可填入更完整的問題，提高 FAQ 或 AI 回答品質。', 'ur-ai-assistant'); ?>
                    </p>
                </div>

                <div class="ur-ai-form-row">
                    <label for="popular_description"><?php echo esc_html__('簡短說明', 'ur-ai-assistant'); ?></label>
                    <textarea
                        id="popular_description"
                        name="description"
                        rows="3"
                    ><?php echo esc_textarea($form_values['description']); ?></textarea>
                </div>

                <div class="ur-ai-grid ur-ai-grid-4">
                    <div class="ur-ai-form-row">
                        <label for="popular_status"><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></label>
                        <select id="popular_status" name="status">
                            <?php foreach ($statuses as $status_key => $status_label) : ?>
                                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($form_values['status'], $status_key); ?>>
                                    <?php echo esc_html($status_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ur-ai-form-row">
                        <label for="popular_source"><?php echo esc_html__('來源', 'ur-ai-assistant'); ?></label>
                        <select id="popular_source" name="source">
                            <?php foreach ($sources as $source_key => $source_label) : ?>
                                <option value="<?php echo esc_attr($source_key); ?>" <?php selected($form_values['source'], $source_key); ?>>
                                    <?php echo esc_html($source_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ur-ai-form-row">
                        <label for="popular_faq_id"><?php echo esc_html__('對應 FAQ ID', 'ur-ai-assistant'); ?></label>
                        <input
                            type="number"
                            id="popular_faq_id"
                            name="faq_id"
                            value="<?php echo esc_attr($form_values['faq_id']); ?>"
                            min="0"
                            step="1"
                        >
                    </div>

                    <div class="ur-ai-form-row">
                        <label for="popular_sort_order"><?php echo esc_html__('排序', 'ur-ai-assistant'); ?></label>
                        <input
                            type="number"
                            id="popular_sort_order"
                            name="sort_order"
                            value="<?php echo esc_attr($form_values['sort_order']); ?>"
                            min="0"
                            step="1"
                        >
                    </div>
                </div>

                <div class="ur-ai-form-row">
                    <label for="popular_admin_note"><?php echo esc_html__('管理備註', 'ur-ai-assistant'); ?></label>
                    <textarea
                        id="popular_admin_note"
                        name="admin_note"
                        rows="3"
                    ><?php echo esc_textarea($form_values['admin_note']); ?></textarea>
                </div>

                <div class="ur-ai-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php
                        echo $editing_item
                            ? esc_html__('更新熱門問題', 'ur-ai-assistant')
                            : esc_html__('新增熱門問題', 'ur-ai-assistant');
                        ?>
                    </button>

                    <?php if ($editing_item) : ?>
                        <a class="button" href="<?php echo esc_url($base_url); ?>">
                            <?php echo esc_html__('取消編輯', 'ur-ai-assistant'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('從 FAQ 匯入熱門問題', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('將常用 FAQ 匯入成前台熱門問題。匯入後預設停用，請確認後再啟用。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($importable_faqs)) : ?>
                <form method="post" class="ur-ai-bulk-form">
                    <?php
                    if (class_exists('UR_AI_Security')) {
                        UR_AI_Security::admin_form_nonce_field();
                    } else {
                        wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                    }
                    ?>

                    <input type="hidden" name="ur_ai_action" value="import_popular_questions_from_faq">
                    <input type="hidden" name="bulk_action" class="ur-ai-bulk-action" value="import">

                    <div class="ur-ai-table-wrap">
                        <table class="ur-ai-table">
                            <thead>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" class="ur-ai-check-all">
                                    </th>
                                    <th><?php echo esc_html__('FAQ 問題', 'ur-ai-assistant'); ?></th>
                                    <th><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></th>
                                    <th class="ur-ai-cell-number"><?php echo esc_html__('命中', 'ur-ai-assistant'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($importable_faqs as $faq) : ?>
                                    <tr>
                                        <td class="check-column">
                                            <input
                                                type="checkbox"
                                                class="ur-ai-item-checkbox"
                                                name="faq_ids[]"
                                                value="<?php echo esc_attr(absint($faq->id)); ?>"
                                            >
                                        </td>
                                        <td>
                                            <div class="ur-ai-row-title">
                                                <?php echo esc_html($faq->question); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="ur-ai-badge ur-ai-badge-info">
                                                <?php echo esc_html($faq->category); ?>
                                            </span>
                                        </td>
                                        <td class="ur-ai-cell-number">
                                            <?php echo esc_html(absint($faq->hit_count)); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ur-ai-form-actions">
                        <button type="submit" class="button button-primary ur-ai-import-button">
                            <?php echo esc_html__('匯入所選 FAQ', 'ur-ai-assistant'); ?>
                        </button>
                    </div>
                </form>
            <?php else : ?>
                <div class="ur-ai-empty-state">
                    <?php echo esc_html__('目前沒有可匯入的啟用 FAQ。', 'ur-ai-assistant'); ?>
                </div>
            <?php endif; ?>

            <div class="ur-ai-warning-box">
                <?php echo esc_html__('熱門問題是前台引導工具，不一定都要對應 FAQ；但高點擊又未對應 FAQ 的項目，建議優先補成 FAQ。', 'ur-ai-assistant'); ?>
            </div>
        </div>

    </div>

    <div class="ur-ai-toolbar">
        <form method="get" class="ur-ai-filter-form">
            <input type="hidden" name="page" value="ur-ai-assistant-popular-questions">

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
                <label for="filter_linked"><?php echo esc_html__('FAQ 對應', 'ur-ai-assistant'); ?></label>
                <select id="filter_linked" name="linked">
                    <option value=""><?php echo esc_html__('全部', 'ur-ai-assistant'); ?></option>
                    <option value="1" <?php selected((string) $linked_filter, '1'); ?>>
                        <?php echo esc_html__('已對應 FAQ', 'ur-ai-assistant'); ?>
                    </option>
                    <option value="0" <?php selected((string) $linked_filter, '0'); ?>>
                        <?php echo esc_html__('未對應 FAQ', 'ur-ai-assistant'); ?>
                    </option>
                </select>
            </div>

            <div>
                <label for="popular_search"><?php echo esc_html__('搜尋', 'ur-ai-assistant'); ?></label>
                <input
                    type="text"
                    id="popular_search"
                    name="s"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php echo esc_attr__('問題、說明、分類', 'ur-ai-assistant'); ?>"
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

    <form
        method="post"
        class="ur-ai-bulk-form"
        data-total-matching="<?php echo esc_attr($total); ?>"
        data-page-count="<?php echo esc_attr(count($items)); ?>"
    >
        <?php
        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::admin_form_nonce_field();
        } else {
            wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
        }
        ?>

        <input type="hidden" name="ur_ai_action" value="bulk_popular_questions">
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
                    <option value="convert_to_faq"><?php echo esc_html__('轉 FAQ 草稿', 'ur-ai-assistant'); ?></option>
                    <option value="delete"><?php echo esc_html__('刪除', 'ur-ai-assistant'); ?></option>
                </select>

                <button type="submit" class="button">
                    <?php echo esc_html__('套用', 'ur-ai-assistant'); ?>
                </button>
            </div>

            <div class="ur-ai-muted">
                <?php
                printf(
                    esc_html__('共 %d 筆熱門問題', 'ur-ai-assistant'),
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
                        <th><?php echo esc_html__('熱門問題', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('FAQ 對應', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('點擊', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('排序', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('建議', 'ur-ai-assistant'); ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($items)) : ?>
                        <?php foreach ($items as $index => $item) : ?>
                            <?php
                            $row = isset($formatted[$index]) ? $formatted[$index] : array();

                            $id         = absint($item->id);
                            $edit_url   = add_query_arg(array('page' => 'ur-ai-assistant-popular-questions', 'edit' => $id), admin_url('admin.php'));
                            $status     = (string) $item->status;
                            $status_cls = 'active' === $status ? 'active' : 'inactive';
                            $source     = (string) $item->source;
                            $faq_id     = absint($item->faq_id);
                            ?>
                            <tr>
                                <td class="check-column">
                                    <input
                                        type="checkbox"
                                        class="ur-ai-item-checkbox"
                                        name="popular_question_ids[]"
                                        value="<?php echo esc_attr($id); ?>"
                                    >
                                </td>

                                <td class="ur-ai-cell-wide">
                                    <div class="ur-ai-row-title">
                                        <?php echo esc_html($row['question_excerpt'] ?? $item->question); ?>
                                    </div>

                                    <?php if (!empty($item->submit_question) && $item->submit_question !== $item->question) : ?>
                                        <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                            <?php
                                            printf(
                                                esc_html__('送出：%s', 'ur-ai-assistant'),
                                                esc_html($item->submit_question)
                                            );
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($item->description)) : ?>
                                        <div class="ur-ai-muted ur-ai-mt-12">
                                            <?php echo esc_html($row['description_excerpt'] ?? $item->description); ?>
                                        </div>
                                    <?php endif; ?>

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
                                            <input type="hidden" name="ur_ai_action" value="convert_popular_question_to_faq">
                                            <input type="hidden" name="popular_question_id" value="<?php echo esc_attr($id); ?>">
                                            <button type="submit" class="button-link ur-ai-convert-faq-button">
                                                <?php echo esc_html__('轉 FAQ 草稿', 'ur-ai-assistant'); ?>
                                            </button>
                                        </form>

                                        <form method="post">
                                            <?php
                                            if (class_exists('UR_AI_Security')) {
                                                UR_AI_Security::admin_form_nonce_field();
                                            } else {
                                                wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                                            }
                                            ?>
                                            <input type="hidden" name="ur_ai_action" value="delete_popular_question">
                                            <input type="hidden" name="popular_question_id" value="<?php echo esc_attr($id); ?>">
                                            <button type="submit" class="button-link-delete ur-ai-delete-button">
                                                <?php echo esc_html__('刪除', 'ur-ai-assistant'); ?>
                                            </button>
                                        </form>

                                        <button
                                            type="button"
                                            class="button-link ur-ai-copy-button"
                                            data-copy-text="<?php echo esc_attr($item->submit_question ? $item->submit_question : $item->question); ?>"
                                        >
                                            <?php echo esc_html__('複製問題', 'ur-ai-assistant'); ?>
                                        </button>
                                    </div>
                                </td>

                                <td>
                                    <span class="ur-ai-badge ur-ai-badge-info">
                                        <?php echo esc_html($item->category); ?>
                                    </span>

                                    <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                        <?php
                                        echo isset($sources[$source])
                                            ? esc_html($sources[$source])
                                            : esc_html($source);
                                        ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($status_cls); ?>">
                                        <?php
                                        echo isset($statuses[$status])
                                            ? esc_html($statuses[$status])
                                            : esc_html($status);
                                        ?>
                                    </span>
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

                                        <?php if (!empty($row['faq_status'])) : ?>
                                            <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                                <?php
                                                printf(
                                                    esc_html__('狀態：%s', 'ur-ai-assistant'),
                                                    esc_html($row['faq_status'])
                                                );
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="ur-ai-badge ur-ai-badge-unlinked">
                                            <?php echo esc_html__('未對應', 'ur-ai-assistant'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="ur-ai-cell-number">
                                    <?php echo esc_html(absint($item->click_count)); ?>
                                </td>

                                <td class="ur-ai-cell-number">
                                    <?php echo esc_html(absint($item->sort_order)); ?>
                                </td>

                                <td>
                                    <?php if (!empty($row['content_status_label'])) : ?>
                                        <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($row['content_status'] ?? 'default'); ?>">
                                            <?php echo esc_html($row['content_status_label']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($row['suggestion'])) : ?>
                                        <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                            <?php echo esc_html($row['suggestion']); ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="ur-ai-muted ur-ai-small">
                                            <?php echo esc_html__('持續觀察點擊與 FAQ 對應狀態。', 'ur-ai-assistant'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8">
                                <div class="ur-ai-empty-state">
                                    <?php echo esc_html__('目前沒有符合條件的熱門問題。', 'ur-ai-assistant'); ?>
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