<?php
/**
 * UR AI Assistant Partners Admin Page
 *
 * 相關產業名錄後台管理：設定、CSV 匯入匯出、單筆新增／編輯／刪除、批次操作。
 *
 * @var UR_AI_Partner_Repository|null $repository
 * @var array|null                    $editing
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('UR_AI_Permissions')) {
    UR_AI_Permissions::require_manage_settings();
} elseif (!current_user_can('manage_options')) {
    wp_die(esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'), esc_html__('權限不足', 'ur-ai-assistant'), array('response' => 403));
}

$slug        = UR_AI_Partners_Module::ADMIN_MENU_SLUG;
$base_url     = admin_url('admin.php?page=' . $slug);
$post_url     = admin_url('admin-post.php');
$categories   = UR_AI_Partner_Service::builtin_categories();
$tiers        = UR_AI_Partner_Service::tiers();
$source_types = UR_AI_Partner_Service::source_types();
$brand        = class_exists('UR_AI_Admin_Menu') ? UR_AI_Admin_Menu::brand_name() : __('UR AI Assistant', 'ur-ai-assistant');

// 篩選條件（列表）。
$f_category = isset($_GET['fcat']) ? sanitize_key(wp_unslash($_GET['fcat'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$f_status   = isset($_GET['fstatus']) ? sanitize_key(wp_unslash($_GET['fstatus'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$f_search   = isset($_GET['fs']) ? sanitize_text_field(wp_unslash($_GET['fs'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged      = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$listing = $repository ? $repository->find_all(array(
    'category' => $f_category,
    'status'   => $f_status,
    'search'   => $f_search,
    'per_page' => 30,
    'page'     => $paged,
)) : array('items' => array(), 'total' => 0);

$s = UR_AI_Partner_Settings::get_all();

$notice = isset($_GET['ur_notice']) ? sanitize_key(wp_unslash($_GET['ur_notice'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap ur-ai-admin-page">
    <h1><?php echo esc_html(sprintf(/* translators: %s: brand */ __('%s｜相關產業名錄', 'ur-ai-assistant'), $brand)); ?></h1>

    <?php if ('' !== $notice) : ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php
            switch ($notice) {
                case 'added':
                    esc_html_e('名錄已新增。', 'ur-ai-assistant');
                    break;
                case 'updated':
                    esc_html_e('名錄已更新。', 'ur-ai-assistant');
                    break;
                case 'deleted':
                    esc_html_e('名錄已刪除。', 'ur-ai-assistant');
                    break;
                case 'settings':
                    esc_html_e('設定已儲存。', 'ur-ai-assistant');
                    break;
                case 'batch':
                    printf(/* translators: %d: count */ esc_html__('批次操作完成，影響 %d 筆。', 'ur-ai-assistant'), (int) ($_GET['ur_count'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    break;
                case 'imported':
                    printf(/* translators: 1: inserted, 2: failed */ esc_html__('CSV 匯入完成：新增 %1$d 筆，略過／失敗 %2$d 筆。', 'ur-ai-assistant'), (int) ($_GET['ur_ins'] ?? 0), (int) ($_GET['ur_fail'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    break;
            }
            ?>
        </p></div>
    <?php endif; ?>

    <p class="ur-ai-muted">
        <?php esc_html_e('前台以 shortcode [ur_ai_partners] 顯示。名錄僅供資訊查詢與自行洽詢參考，不代表本平台之推薦、認證或背書。', 'ur-ai-assistant'); ?>
    </p>

    <!-- 設定 -->
    <details class="ur-ai-card" style="margin:1em 0;">
        <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('名錄設定（啟用、標題、說明、免責聲明）', 'ur-ai-assistant'); ?></summary>
        <form method="post" action="<?php echo esc_url($post_url); ?>" style="margin-top:1em;">
            <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Partners_Module::SETTINGS_SAVE_ACTION); ?>">
            <?php wp_nonce_field(UR_AI_Partners_Module::SETTINGS_SAVE_ACTION); ?>
            <p><label><input type="checkbox" name="enabled" value="1" <?php checked((int) $s['enabled'], 1); ?>> <?php esc_html_e('啟用相關產業名錄前台功能', 'ur-ai-assistant'); ?></label></p>
            <p><label><?php esc_html_e('前台標題', 'ur-ai-assistant'); ?><br><input type="text" name="title" class="regular-text" value="<?php echo esc_attr($s['title']); ?>"></label></p>
            <p><label><?php esc_html_e('前台說明文字', 'ur-ai-assistant'); ?><br><textarea name="intro" rows="2" class="large-text"><?php echo esc_textarea($s['intro']); ?></textarea></label></p>
            <p><label><?php esc_html_e('免責聲明', 'ur-ai-assistant'); ?><br><textarea name="disclaimer" rows="3" class="large-text"><?php echo esc_textarea($s['disclaimer']); ?></textarea></label></p>
            <p><button type="submit" class="button button-primary"><?php esc_html_e('儲存設定', 'ur-ai-assistant'); ?></button></p>
        </form>
    </details>

    <!-- CSV 匯入 / 匯出 -->
    <div class="ur-ai-card" style="margin:1em 0;">
        <h2><?php esc_html_e('CSV 匯入 / 匯出', 'ur-ai-assistant'); ?></h2>
        <p class="ur-ai-muted"><?php esc_html_e('欄位順序：名稱, 分類, 地區, 標語, 簡介, 聯絡人, 電話, Email, 官方網站, 等級(standard/featured), 權重, 狀態(active/inactive), 已核對(0/1), 核對日, 資料來源, 來源網址。第一列若為標題會自動略過。可先「匯出 CSV」取得範本。', 'ur-ai-assistant'); ?></p>
        <form method="post" action="<?php echo esc_url($post_url); ?>" enctype="multipart/form-data" style="display:flex;gap:0.8em;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Partners_Module::IMPORT_ACTION); ?>">
            <?php wp_nonce_field(UR_AI_Partners_Module::IMPORT_ACTION); ?>
            <input type="file" name="csv_file" accept=".csv,text/csv" required>
            <button type="submit" class="button"><?php esc_html_e('匯入 CSV', 'ur-ai-assistant'); ?></button>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg('export', 'csv', $base_url), 'ur_ai_partner_export')); ?>"><?php esc_html_e('匯出 CSV', 'ur-ai-assistant'); ?></a>
        </form>
    </div>

    <!-- 新增 / 編輯 -->
    <div class="ur-ai-card" style="margin:1em 0;">
        <h2><?php echo $editing ? esc_html__('編輯名錄', 'ur-ai-assistant') : esc_html__('新增名錄', 'ur-ai-assistant'); ?></h2>
        <form method="post" action="<?php echo esc_url($post_url); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Partners_Module::SAVE_ACTION); ?>">
            <?php wp_nonce_field(UR_AI_Partners_Module::SAVE_ACTION); ?>
            <?php if ($editing) : ?><input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>"><?php endif; ?>
            <?php
            $val = function ($k) use ($editing) {
                return $editing && isset($editing[$k]) ? (string) $editing[$k] : '';
            };
            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label><?php esc_html_e('單位名稱', 'ur-ai-assistant'); ?> *</label></th>
                    <td><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr($val('name')); ?>"></td>
                    <th><label><?php esc_html_e('分類', 'ur-ai-assistant'); ?></label></th>
                    <td>
                        <input list="ur-ai-partner-cats" name="category" class="regular-text" value="<?php echo esc_attr($editing ? UR_AI_Partner_Service::category_label($editing['category']) : ''); ?>" placeholder="<?php esc_attr_e('可選內建或自訂分類（如：公協會）', 'ur-ai-assistant'); ?>">
                        <datalist id="ur-ai-partner-cats">
                            <?php foreach ($categories as $label) : ?><option value="<?php echo esc_attr($label); ?>"></option><?php endforeach; ?>
                        </datalist>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('地區', 'ur-ai-assistant'); ?></label></th>
                    <td><input type="text" name="region" class="regular-text" value="<?php echo esc_attr($val('region')); ?>" placeholder="<?php esc_attr_e('例：台北市大安區', 'ur-ai-assistant'); ?>"></td>
                    <th><label><?php esc_html_e('聯絡人', 'ur-ai-assistant'); ?></label></th>
                    <td><input type="text" name="contact_person" class="regular-text" value="<?php echo esc_attr($val('contact_person')); ?>"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('標語', 'ur-ai-assistant'); ?></label></th>
                    <td><input type="text" name="tagline" class="regular-text" value="<?php echo esc_attr($val('tagline')); ?>"></td>
                    <th><label><?php esc_html_e('電話', 'ur-ai-assistant'); ?></label></th>
                    <td><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr($val('phone')); ?>"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('簡介', 'ur-ai-assistant'); ?></label></th>
                    <td colspan="3"><textarea name="description" rows="2" class="large-text"><?php echo esc_textarea($val('description')); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Email</label></th>
                    <td><input type="email" name="email" class="regular-text" value="<?php echo esc_attr($val('email')); ?>"></td>
                    <th><label><?php esc_html_e('官方網站', 'ur-ai-assistant'); ?></label></th>
                    <td><input type="url" name="website_url" class="regular-text" value="<?php echo esc_attr($val('website_url')); ?>" placeholder="https://"></td>
                </tr>
                <tr>
                    <th><label>Logo <?php esc_html_e('圖片網址', 'ur-ai-assistant'); ?></label></th>
                    <td><input type="url" name="logo_url" class="regular-text" value="<?php echo esc_attr($val('logo_url')); ?>" placeholder="https://"></td>
                    <th><label><?php esc_html_e('等級', 'ur-ai-assistant'); ?></label></th>
                    <td>
                        <select name="tier">
                            <?php foreach ($tiers as $k => $label) : ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($val('tier') ?: 'standard', $k); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label style="margin-left:1em;"><?php esc_html_e('權重', 'ur-ai-assistant'); ?> <input type="number" name="weight" min="1" style="width:70px;" value="<?php echo esc_attr($editing ? (string) $editing['weight'] : '1'); ?>"></label>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('置頂到期日', 'ur-ai-assistant'); ?></label></th>
                    <td><input type="date" name="featured_until" value="<?php echo esc_attr($val('featured_until')); ?>"> <span class="ur-ai-muted"><?php esc_html_e('（留空＝不限期；僅等級為置頂曝光時有效）', 'ur-ai-assistant'); ?></span></td>
                    <th><label><?php esc_html_e('狀態', 'ur-ai-assistant'); ?></label></th>
                    <td>
                        <select name="status">
                            <option value="active" <?php selected($val('status') ?: 'active', 'active'); ?>><?php esc_html_e('顯示中', 'ur-ai-assistant'); ?></option>
                            <option value="inactive" <?php selected($val('status'), 'inactive'); ?>><?php esc_html_e('隱藏', 'ur-ai-assistant'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('資料來源', 'ur-ai-assistant'); ?></label></th>
                    <td>
                        <input list="ur-ai-partner-sources" name="source_type" value="<?php echo esc_attr($val('source_type')); ?>" placeholder="<?php esc_attr_e('政府名冊／公會／官方網站／業者提供', 'ur-ai-assistant'); ?>">
                        <datalist id="ur-ai-partner-sources"><?php foreach ($source_types as $k => $label) : ?><option value="<?php echo esc_attr($k); ?>"></option><?php endforeach; ?></datalist>
                    </td>
                    <th><label><?php esc_html_e('已核對', 'ur-ai-assistant'); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="is_verified" value="1" <?php checked((int) ($editing['is_verified'] ?? 0), 1); ?>> <?php esc_html_e('資料已核對', 'ur-ai-assistant'); ?></label>
                        <label style="margin-left:1em;"><?php esc_html_e('核對日', 'ur-ai-assistant'); ?> <input type="date" name="verified_at" value="<?php echo esc_attr($val('verified_at')); ?>"></label>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td colspan="3"><label><?php esc_html_e('來源網址', 'ur-ai-assistant'); ?> <input type="url" name="source_url" class="regular-text" value="<?php echo esc_attr($val('source_url')); ?>" placeholder="https://"></label></td>
                </tr>
            </table>
            <p>
                <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__('更新名錄', 'ur-ai-assistant') : esc_html__('新增名錄', 'ur-ai-assistant'); ?></button>
                <?php if ($editing) : ?><a class="button" href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('取消編輯', 'ur-ai-assistant'); ?></a><?php endif; ?>
            </p>
        </form>
    </div>

    <!-- 篩選 -->
    <form method="get" style="margin:1em 0;">
        <input type="hidden" name="page" value="<?php echo esc_attr($slug); ?>">
        <input type="search" name="fs" value="<?php echo esc_attr($f_search); ?>" placeholder="<?php esc_attr_e('搜尋名稱／地區／電話', 'ur-ai-assistant'); ?>">
        <select name="fcat">
            <option value=""><?php esc_html_e('全部分類', 'ur-ai-assistant'); ?></option>
            <?php foreach ($categories as $k => $label) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($f_category, $k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
        </select>
        <select name="fstatus">
            <option value=""><?php esc_html_e('全部狀態', 'ur-ai-assistant'); ?></option>
            <option value="active" <?php selected($f_status, 'active'); ?>><?php esc_html_e('顯示中', 'ur-ai-assistant'); ?></option>
            <option value="inactive" <?php selected($f_status, 'inactive'); ?>><?php esc_html_e('隱藏', 'ur-ai-assistant'); ?></option>
        </select>
        <button type="submit" class="button"><?php esc_html_e('篩選', 'ur-ai-assistant'); ?></button>
    </form>

    <!-- 列表 + 批次 -->
    <form method="post" action="<?php echo esc_url($post_url); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Partners_Module::BATCH_ACTION); ?>">
        <?php wp_nonce_field(UR_AI_Partners_Module::BATCH_ACTION); ?>
        <div style="margin:0.5em 0;display:flex;gap:0.5em;align-items:center;flex-wrap:wrap;">
            <select name="batch_action">
                <option value=""><?php esc_html_e('批次操作…', 'ur-ai-assistant'); ?></option>
                <option value="enable"><?php esc_html_e('設為顯示中', 'ur-ai-assistant'); ?></option>
                <option value="disable"><?php esc_html_e('設為隱藏', 'ur-ai-assistant'); ?></option>
                <option value="verify"><?php esc_html_e('標記已核對', 'ur-ai-assistant'); ?></option>
                <option value="unverify"><?php esc_html_e('取消已核對', 'ur-ai-assistant'); ?></option>
                <option value="delete"><?php esc_html_e('刪除', 'ur-ai-assistant'); ?></option>
            </select>
            <button type="submit" class="button" onclick="return confirm('<?php echo esc_js(__('確定要對勾選的名錄執行此批次操作嗎？', 'ur-ai-assistant')); ?>');"><?php esc_html_e('套用', 'ur-ai-assistant'); ?></button>
            <span class="ur-ai-muted"><?php printf(/* translators: %d: total */ esc_html__('共 %d 筆', 'ur-ai-assistant'), (int) $listing['total']); ?></span>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <td class="check-column"><input type="checkbox" onclick="var c=this.checked;this.closest('table').querySelectorAll('input[name=\'ids[]\']').forEach(function(x){x.checked=c;});"></td>
                <th><?php esc_html_e('名稱', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('分類', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('地區', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('等級/權重', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('狀態', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('核對', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('操作', 'ur-ai-assistant'); ?></th>
            </tr></thead>
            <tbody>
                <?php if (empty($listing['items'])) : ?>
                    <tr><td colspan="8"><?php esc_html_e('尚無名錄資料。可在上方新增或以 CSV 匯入。', 'ur-ai-assistant'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($listing['items'] as $p) : ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $p['id']; ?>"></th>
                            <td><strong><?php echo esc_html($p['name']); ?></strong><?php if ($p['tagline']) : ?><br><span class="ur-ai-muted"><?php echo esc_html($p['tagline']); ?></span><?php endif; ?></td>
                            <td><?php echo esc_html(UR_AI_Partner_Service::category_label($p['category'])); ?></td>
                            <td><?php echo esc_html($p['region']); ?></td>
                            <td><?php echo esc_html('featured' === $p['tier'] ? __('置頂', 'ur-ai-assistant') : __('一般', 'ur-ai-assistant')); ?> / <?php echo (int) $p['weight']; ?></td>
                            <td><?php echo esc_html('active' === $p['status'] ? __('顯示中', 'ur-ai-assistant') : __('隱藏', 'ur-ai-assistant')); ?></td>
                            <td><?php echo $p['is_verified'] ? '✔' : '—'; ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('edit', (int) $p['id'], $base_url)); ?>"><?php esc_html_e('編輯', 'ur-ai-assistant'); ?></a>
                                |
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('ur_del' => (int) $p['id']), $base_url), 'x')); ?>" onclick="event.preventDefault();var f=document.getElementById('ur-ai-del-<?php echo (int) $p['id']; ?>');if(confirm('<?php echo esc_js(__('確定刪除這筆名錄嗎？', 'ur-ai-assistant')); ?>'))f.submit();" style="color:#b32d2e;"><?php esc_html_e('刪除', 'ur-ai-assistant'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <?php // 每筆的隱藏刪除表單（避免在批次表單內巢狀 form）。 ?>
    <?php foreach ($listing['items'] as $p) : ?>
        <form id="ur-ai-del-<?php echo (int) $p['id']; ?>" method="post" action="<?php echo esc_url($post_url); ?>" style="display:none;">
            <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Partners_Module::DELETE_ACTION); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
            <?php wp_nonce_field(UR_AI_Partners_Module::DELETE_ACTION); ?>
        </form>
    <?php endforeach; ?>

    <?php
    // 簡易分頁。
    $total_pages = (int) ceil($listing['total'] / 30);
    if ($total_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $url = add_query_arg(array('paged' => $i, 'fcat' => $f_category, 'fstatus' => $f_status, 'fs' => $f_search), $base_url);
            if ($i === $paged) {
                echo '<span class="button button-primary" style="margin:0 2px;">' . (int) $i . '</span>';
            } else {
                echo '<a class="button" style="margin:0 2px;" href="' . esc_url($url) . '">' . (int) $i . '</a>';
            }
        }
        echo '</div></div>';
    }
    ?>
</div>
