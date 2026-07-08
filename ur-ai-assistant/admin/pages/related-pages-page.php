<?php
/**
 * UR AI Assistant Related Pages Page
 *
 * 相關頁面推薦管理頁。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('UR_AI_Permissions')) {
    UR_AI_Permissions::require_manage_related_pages();
} elseif (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'),
        esc_html__('權限不足', 'ur-ai-assistant'),
        array(
            'response' => 403,
        )
    );
}

if (!class_exists('UR_AI_Related_Page_Admin') || !class_exists('UR_AI_Related_Page_Service')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('相關頁面推薦', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('相關頁面推薦管理類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

$admin   = new UR_AI_Related_Page_Admin();
$service = new UR_AI_Related_Page_Service();

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
$search          = isset($query_args['search']) ? $query_args['search'] : '';

$current_page = isset($pagination['current']) ? absint($pagination['current']) : 1;
$total_pages  = isset($pagination['total_pages']) ? absint($pagination['total_pages']) : 1;

$categories = $service->get_categories();
$statuses   = $service->get_statuses();
$sources    = $service->get_sources();

$form_action = $editing_item ? 'update_related_page' : 'create_related_page';

$form_values = array(
    'id'          => $editing_item ? absint($editing_item->id) : 0,
    'category'    => $editing_item ? (string) $editing_item->category : '待分類',
    'title'       => $editing_item ? (string) $editing_item->title : '',
    'url'         => $editing_item ? (string) $editing_item->url : '',
    'description' => $editing_item ? (string) $editing_item->description : '',
    'keywords'    => $editing_item ? (string) $editing_item->keywords : '',
    'status'      => $editing_item ? (string) $editing_item->status : 'inactive',
    'source'      => $editing_item ? (string) $editing_item->source : 'manual',
    'sort_order'  => $editing_item ? absint($editing_item->sort_order) : 100,
    'admin_note'  => $editing_item ? (string) $editing_item->admin_note : '',
);

$base_url = admin_url('admin.php?page=ur-ai-assistant-related-pages');

$export_url = add_query_arg(
    array_merge(
        $_GET,
        array(
            'page'      => 'ur-ai-assistant-related-pages',
            'ur_action' => 'export_related_pages_csv',
            '_wpnonce'  => wp_create_nonce('ur_ai_export_nonce'),
        )
    ),
    admin_url('admin.php')
);

$import_keyword = isset($_GET['import_s']) ? sanitize_text_field(wp_unslash($_GET['import_s'])) : '';
$import_posts   = '' !== $import_keyword ? $admin->search_importable_posts($import_keyword, 20) : array();

?>

<div class="wrap ur-ai-admin-page">

    <h1><?php echo esc_html__('都更 AI 助理｜相關頁面推薦', 'ur-ai-assistant'); ?></h1>

    <?php if ('' !== $admin_message) : ?>
        <div class="notice notice-<?php echo 'error' === $msg_type ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($admin_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="ur-ai-summary-grid">
        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('推薦頁面總數', 'ur-ai-assistant'); ?></p>
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
            <p class="ur-ai-summary-label"><?php echo esc_html__('總曝光', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['total_show_count'] ?? 0)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('總點擊', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['total_click_count'] ?? 0)); ?></p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('需觀察', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($summary['shown_no_click'] ?? 0)); ?></p>
            <p class="ur-ai-summary-note">
                <?php echo esc_html__('有曝光但尚無點擊', 'ur-ai-assistant'); ?>
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
                            ? esc_html__('編輯推薦頁面', 'ur-ai-assistant')
                            : esc_html__('新增推薦頁面', 'ur-ai-assistant');
                        ?>
                    </h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('推薦頁面會依使用者問題關鍵字比對，顯示在前台「你也許想知道」。', 'ur-ai-assistant'); ?>
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
                    <input type="hidden" name="related_page_id" value="<?php echo esc_attr($form_values['id']); ?>">
                <?php endif; ?>

                <div class="ur-ai-form-row">
                    <label for="related_category"><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></label>
                    <select id="related_category" name="category">
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr($category); ?>" <?php selected($form_values['category'], $category); ?>>
                                <?php echo esc_html($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ur-ai-form-row">
                    <label for="related_title"><?php echo esc_html__('推薦標題', 'ur-ai-assistant'); ?></label>
                    <input
                        type="text"
                        id="related_title"
                        name="title"
                        value="<?php echo esc_attr($form_values['title']); ?>"
                        required
                        placeholder="<?php echo esc_attr__('例如：什麼是權利變換？', 'ur-ai-assistant'); ?>"
                    >
                </div>

                <div class="ur-ai-form-row">
                    <label for="related_url"><?php echo esc_html__('推薦網址', 'ur-ai-assistant'); ?></label>
                    <input
                        type="url"
                        id="related_url"
                        name="url"
                        value="<?php echo esc_attr($form_values['url']); ?>"
                        required
                        placeholder="https://www.ur-promoter.com/..."
                    >
                </div>

                <div class="ur-ai-form-row">
                    <label for="related_description"><?php echo esc_html__('簡短說明', 'ur-ai-assistant'); ?></label>
                    <textarea
                        id="related_description"
                        name="description"
                        rows="3"
                    ><?php echo esc_textarea($form_values['description']); ?></textarea>
                    <p class="ur-ai-form-help">
                        <?php echo esc_html__('前台會顯示在推薦標題下方，建議 50～90 字內。', 'ur-ai-assistant'); ?>
                    </p>
                </div>

                <div class="ur-ai-form-row">
                    <label for="related_keywords"><?php echo esc_html__('關鍵字', 'ur-ai-assistant'); ?></label>
                    <input
                        type="text"
                        id="related_keywords"
                        name="keywords"
                        value="<?php echo esc_attr($form_values['keywords']); ?>"
                        placeholder="<?php echo esc_attr__('例如：權利變換, 分配, 估價, 共同負擔', 'ur-ai-assistant'); ?>"
                    >
                    <p class="ur-ai-form-help">
                        <?php echo esc_html__('多個關鍵字請用逗號分隔。關鍵字越具體，推薦越準。', 'ur-ai-assistant'); ?>
                    </p>
                </div>

                <div class="ur-ai-grid ur-ai-grid-3">
                    <div class="ur-ai-form-row">
                        <label for="related_status"><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></label>
                        <select id="related_status" name="status">
                            <?php foreach ($statuses as $status_key => $status_label) : ?>
                                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($form_values['status'], $status_key); ?>>
                                    <?php echo esc_html($status_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ur-ai-form-row">
                        <label for="related_source"><?php echo esc_html__('來源', 'ur-ai-assistant'); ?></label>
                        <select id="related_source" name="source">
                            <?php foreach ($sources as $source_key => $source_label) : ?>
                                <option value="<?php echo esc_attr($source_key); ?>" <?php selected($form_values['source'], $source_key); ?>>
                                    <?php echo esc_html($source_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ur-ai-form-row">
                        <label for="related_sort_order"><?php echo esc_html__('排序', 'ur-ai-assistant'); ?></label>
                        <input
                            type="number"
                            id="related_sort_order"
                            name="sort_order"
                            value="<?php echo esc_attr($form_values['sort_order']); ?>"
                            min="0"
                            step="1"
                        >
                    </div>
                </div>

                <div class="ur-ai-form-row">
                    <label for="related_admin_note"><?php echo esc_html__('管理備註', 'ur-ai-assistant'); ?></label>
                    <textarea
                        id="related_admin_note"
                        name="admin_note"
                        rows="3"
                    ><?php echo esc_textarea($form_values['admin_note']); ?></textarea>
                </div>

                <div class="ur-ai-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php
                        echo $editing_item
                            ? esc_html__('更新推薦頁面', 'ur-ai-assistant')
                            : esc_html__('新增推薦頁面', 'ur-ai-assistant');
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
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('從 WordPress 文章 / 頁面匯入', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('搜尋既有文章，快速匯入為推薦頁面。匯入後預設停用，請檢查後再啟用。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <form method="get" class="ur-ai-filter-form ur-ai-mb-16">
                <input type="hidden" name="page" value="ur-ai-assistant-related-pages">

                <div>
                    <label for="import_s"><?php echo esc_html__('搜尋文章', 'ur-ai-assistant'); ?></label>
                    <input
                        type="text"
                        id="import_s"
                        name="import_s"
                        value="<?php echo esc_attr($import_keyword); ?>"
                        placeholder="<?php echo esc_attr__('例如：權利變換、危老、更新會', 'ur-ai-assistant'); ?>"
                    >
                </div>

                <div>
                    <button type="submit" class="button">
                        <?php echo esc_html__('搜尋可匯入內容', 'ur-ai-assistant'); ?>
                    </button>
                </div>
            </form>

            <?php if ('' !== $import_keyword) : ?>
                <?php if (!empty($import_posts)) : ?>
                    <form method="post" class="ur-ai-bulk-form">
                        <?php
                        if (class_exists('UR_AI_Security')) {
                            UR_AI_Security::admin_form_nonce_field();
                        } else {
                            wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
                        }
                        ?>

                        <input type="hidden" name="ur_ai_action" value="bulk_import_related_pages">
                        <input type="hidden" name="bulk_action" class="ur-ai-bulk-action" value="import">

                        <div class="ur-ai-table-wrap">
                            <table class="ur-ai-table">
                                <thead>
                                    <tr>
                                        <th class="check-column">
                                            <input type="checkbox" class="ur-ai-check-all">
                                        </th>
                                        <th><?php echo esc_html__('文章 / 頁面', 'ur-ai-assistant'); ?></th>
                                        <th><?php echo esc_html__('分類', 'ur-ai-assistant'); ?></th>
                                        <th><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($import_posts as $post_data) : ?>
                                        <?php
                                        $post_id          = isset($post_data['post_id']) ? absint($post_data['post_id']) : 0;
                                        $already_imported = !empty($post_data['already_imported']);
                                        ?>
                                        <tr>
                                            <td class="check-column">
                                                <?php if (!$already_imported) : ?>
                                                    <input
                                                        type="checkbox"
                                                        class="ur-ai-item-checkbox"
                                                        name="post_ids[]"
                                                        value="<?php echo esc_attr($post_id); ?>"
                                                    >
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="ur-ai-row-title">
                                                    <?php echo esc_html($post_data['title'] ?? ''); ?>
                                                </div>
                                                <div class="ur-ai-muted ur-ai-small">
                                                    <?php echo esc_html($post_data['url'] ?? ''); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="ur-ai-badge ur-ai-badge-info">
                                                    <?php echo esc_html($post_data['category'] ?? '待分類'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($already_imported) : ?>
                                                    <span class="ur-ai-badge ur-ai-badge-warning">
                                                        <?php echo esc_html__('已匯入', 'ur-ai-assistant'); ?>
                                                    </span>
                                                <?php else : ?>
                                                    <span class="ur-ai-badge ur-ai-badge-default">
                                                        <?php echo esc_html__('可匯入', 'ur-ai-assistant'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="ur-ai-form-actions">
                            <button type="submit" class="button button-primary ur-ai-import-button">
                                <?php echo esc_html__('匯入所選文章', 'ur-ai-assistant'); ?>
                            </button>
                        </div>
                    </form>
                <?php else : ?>
                    <div class="ur-ai-empty-state">
                        <?php echo esc_html__('找不到符合條件的已發布文章或頁面。', 'ur-ai-assistant'); ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="ur-ai-help-box">
                    <?php echo esc_html__('輸入關鍵字後，可搜尋網站既有文章並匯入成推薦頁面。', 'ur-ai-assistant'); ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="ur-ai-toolbar">
        <form method="get" class="ur-ai-filter-form">
            <input type="hidden" name="page" value="ur-ai-assistant-related-pages">

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
                <label for="related_search"><?php echo esc_html__('搜尋', 'ur-ai-assistant'); ?></label>
                <input
                    type="text"
                    id="related_search"
                    name="s"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php echo esc_attr__('標題、網址、關鍵字', 'ur-ai-assistant'); ?>"
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

        <input type="hidden" name="ur_ai_action" value="bulk_related_pages">

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
                    esc_html__('共 %d 筆推薦頁面', 'ur-ai-assistant'),
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
                        <th><?php echo esc_html__('推薦頁面', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('分類 / 關鍵字', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('狀態', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('曝光', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('點擊', 'ur-ai-assistant'); ?></th>
                        <th class="ur-ai-cell-number"><?php echo esc_html__('CTR', 'ur-ai-assistant'); ?></th>
                        <th><?php echo esc_html__('成效', 'ur-ai-assistant'); ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($items)) : ?>
                        <?php foreach ($items as $index => $item) : ?>
                            <?php
                            $row = isset($formatted[$index]) ? $formatted[$index] : array();

                            $id          = absint($item->id);
                            $edit_url    = add_query_arg(array('page' => 'ur-ai-assistant-related-pages', 'edit' => $id), admin_url('admin.php'));
                            $status      = (string) $item->status;
                            $status_cls  = 'active' === $status ? 'active' : 'inactive';
                            $source      = (string) $item->source;
                            $show_count  = absint($item->show_count);
                            $click_count = absint($item->click_count);
                            $ctr         = isset($row['ctr']) ? $row['ctr'] : 0;
                            ?>
                            <tr>
                                <td class="check-column">
                                    <input
                                        type="checkbox"
                                        class="ur-ai-item-checkbox"
                                        name="related_page_ids[]"
                                        value="<?php echo esc_attr($id); ?>"
                                    >
                                </td>

                                <td class="ur-ai-cell-wide">
                                    <div class="ur-ai-row-title">
                                        <a href="<?php echo esc_url($item->url); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($row['title_excerpt'] ?? $item->title); ?>
                                        </a>
                                    </div>

                                    <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                        <?php echo esc_html($row['url_label'] ?? $item->url); ?>
                                    </div>

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
                                            <input type="hidden" name="ur_ai_action" value="delete_related_page">
                                            <input type="hidden" name="related_page_id" value="<?php echo esc_attr($id); ?>">
                                            <button type="submit" class="button-link-delete ur-ai-delete-button">
                                                <?php echo esc_html__('刪除', 'ur-ai-assistant'); ?>
                                            </button>
                                        </form>

                                        <button
                                            type="button"
                                            class="button-link ur-ai-copy-button"
                                            data-copy-text="<?php echo esc_attr($item->url); ?>"
                                        >
                                            <?php echo esc_html__('複製網址', 'ur-ai-assistant'); ?>
                                        </button>
                                    </div>
                                </td>

                                <td class="ur-ai-cell-main">
                                    <span class="ur-ai-badge ur-ai-badge-info">
                                        <?php echo esc_html($item->category); ?>
                                    </span>

                                    <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                        <?php echo esc_html($item->keywords); ?>
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

                                    <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                        <?php
                                        echo isset($sources[$source])
                                            ? esc_html($sources[$source])
                                            : esc_html($source);
                                        ?>
                                    </div>
                                </td>

                                <td class="ur-ai-cell-number">
                                    <?php echo esc_html($show_count); ?>
                                </td>

                                <td class="ur-ai-cell-number">
                                    <?php echo esc_html($click_count); ?>
                                </td>

                                <td class="ur-ai-cell-number">
                                    <?php echo esc_html($ctr); ?>%
                                </td>

                                <td>
                                    <?php if (!empty($row['performance_label'])) : ?>
                                        <span class="ur-ai-badge ur-ai-badge-<?php echo esc_attr($row['performance_status'] ?? 'default'); ?>">
                                            <?php echo esc_html($row['performance_label']); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="ur-ai-badge ur-ai-badge-default">
                                            <?php echo esc_html__('觀察中', 'ur-ai-assistant'); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($row['maintenance_suggestion'])) : ?>
                                        <div class="ur-ai-muted ur-ai-small ur-ai-mt-12">
                                            <?php echo esc_html($row['maintenance_suggestion']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8">
                                <div class="ur-ai-empty-state">
                                    <?php echo esc_html__('目前沒有符合條件的推薦頁面。', 'ur-ai-assistant'); ?>
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