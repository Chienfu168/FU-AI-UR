<?php
/**
 * UR AI Assistant Market Price Import Page
 *
 * 行情參考：設定、CSV 匯入、樣本數健檢一覽。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('UR_AI_Permissions')) {
    UR_AI_Permissions::require_view_dashboard();
} elseif (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'),
        esc_html__('權限不足', 'ur-ai-assistant'),
        array('response' => 403)
    );
}

if (!class_exists('UR_AI_Market_Price_Settings') || !class_exists('UR_AI_Market_Price_Service')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('行情參考', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('行情參考模組類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

/*
 * 資料表若因故未成功建立（例如資料庫帳號權限不足導致 dbDelta 靜默失敗），
 * 匯入時每一列都會被判定為「略過」且沒有任何明確錯誤訊息，難以排查。
 * 這裡先明確檢查資料表是否存在，避免管理者誤以為是 CSV 檔案格式問題。
 */
if (class_exists('UR_AI_Schema_Manager') && method_exists('UR_AI_Schema_Manager', 'get_table_statuses')) {
    $ur_ai_mp_table_statuses = UR_AI_Schema_Manager::get_table_statuses();
    $ur_ai_mp_table_exists   = !isset($ur_ai_mp_table_statuses['UR_AI_Schema_Market_Prices'])
        || !empty($ur_ai_mp_table_statuses['UR_AI_Schema_Market_Prices']['exists']);

    if (!$ur_ai_mp_table_exists) {
        echo '<div class="wrap ur-ai-admin-page">';
        echo '<h1>' . esc_html__('都更 AI 助理｜行情參考', 'ur-ai-assistant') . '</h1>';
        echo '<div class="notice notice-error"><p>' . esc_html__(
            '行情參考資料表尚未成功建立，因此匯入時所有資料列都會被判定為「略過」（並非 CSV 檔案格式問題）。請先嘗試：至外掛頁面「停用」後再「重新啟用」本外掛以觸發資料表建立；若重新啟用後仍無法解決，請聯絡主機廠商確認資料庫帳號是否具備 CREATE TABLE 權限。',
            'ur-ai-assistant'
        ) . '</p></div>';
        echo '</div>';
        return;
    }
}

$message  = isset($_GET['ur_message']) ? sanitize_key(wp_unslash($_GET['ur_message'])) : '';
$msg_type = isset($_GET['ur_msg_type']) ? sanitize_key(wp_unslash($_GET['ur_msg_type'])) : 'updated';

$message_texts = array(
    'settings_saved'          => __('設定已儲存。', 'ur-ai-assistant'),
    'import_no_file'          => __('請選擇要上傳的 CSV 檔案。', 'ur-ai-assistant'),
    'import_bad_type'         => __('檔案格式不符，請上傳 CSV（.csv）檔案。', 'ur-ai-assistant'),
    'import_upload_error'     => __('檔案上傳失敗，請重新嘗試。', 'ur-ai-assistant'),
    'import_service_missing'  => __('匯入服務尚未正確載入。', 'ur-ai-assistant'),
);

$service = new UR_AI_Market_Price_Service();

$enabled           = UR_AI_Market_Price_Settings::is_enabled();
$old_age_threshold = UR_AI_Market_Price_Settings::get_old_age_threshold();
$new_age_threshold = UR_AI_Market_Price_Settings::get_new_age_threshold();
$min_sample_size   = UR_AI_Market_Price_Settings::get_min_sample_size();
$disclaimer        = UR_AI_Market_Price_Settings::get_disclaimer();

$total_count      = $service->count_all();
$last_imported_at = $service->get_last_imported_at();
$cities           = $service->get_supported_cities();

$stale_days = $service->get_stale_days();

?>
<div class="wrap ur-ai-admin-page">

    <h1><?php echo esc_html__('都更 AI 助理｜行情參考', 'ur-ai-assistant'); ?></h1>

    <?php if ('imported' === $message) : ?>
        <?php
        $created   = isset($_GET['imp_created']) ? absint($_GET['imp_created']) : 0;
        $duplicate = isset($_GET['imp_duplicate']) ? absint($_GET['imp_duplicate']) : 0;
        $skipped   = isset($_GET['imp_skipped']) ? absint($_GET['imp_skipped']) : 0;
        $total     = isset($_GET['imp_total']) ? absint($_GET['imp_total']) : 0;
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: 1: total rows, 2: created, 3: duplicate, 4: skipped */
                    esc_html__('匯入完成。共讀取 %1$d 筆成屋交易，新增 %2$d 筆、略過重複 %3$d 筆、略過格式錯誤 %4$d 筆。', 'ur-ai-assistant'),
                    $total,
                    $created,
                    $duplicate,
                    $skipped
                );
                ?>
            </p>
        </div>
    <?php elseif ('import_city_mismatch' === $message) : ?>
        <?php
        $detected_key   = isset($_GET['imp_detected']) ? sanitize_key(wp_unslash($_GET['imp_detected'])) : '';
        $detected_label = isset($cities[$detected_key]) ? $cities[$detected_key] : '';
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php if ('' !== $detected_label) : ?>
                    <?php
                    printf(
                        /* translators: %s: 系統自動偵測到的縣市名稱 */
                        esc_html__('已取消匯入：這份 CSV 內容看起來屬於「%s」，但您選擇的縣市不同。系統已自動比對行政區名稱判斷不符，為避免資料錯置，請重新確認後再上傳。', 'ur-ai-assistant'),
                        esc_html($detected_label)
                    );
                    ?>
                <?php else : ?>
                    <?php echo esc_html__('已取消匯入：無法從資料內容判斷所屬縣市（目前僅支援台北市／新北市），請確認上傳的是雙北實價登錄開放資料。', 'ur-ai-assistant'); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php elseif ('' !== $message && isset($message_texts[$message])) : ?>
        <div class="notice notice-<?php echo 'error' === $msg_type ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($message_texts[$message]); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($service->is_stale()) : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    /* translators: %d: days since last import */
                    esc_html__('行情資料已 %d 天未更新，建議至內政部實價登錄開放資料下載新一季資料並重新匯入。', 'ur-ai-assistant'),
                    $stale_days
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="ur-ai-summary-grid">
        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('累計成交紀錄', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(number_format_i18n($total_count)); ?></p>
        </div>
        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('最後匯入時間', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value" style="font-size:16px;">
                <?php echo $last_imported_at ? esc_html(mysql2date('Y-m-d H:i', $last_imported_at)) : esc_html__('尚未匯入', 'ur-ai-assistant'); ?>
            </p>
        </div>
    </div>

    <div class="ur-ai-grid ur-ai-grid-2">

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('行情資料匯入', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('請先至內政部不動產交易實價查詢服務下載開放資料，另存為 CSV（UTF-8）格式後上傳。可重複上傳不同季度的檔案，系統會自動略過重複紀錄。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Market_Price_Module::IMPORT_ACTION); ?>">
                <?php wp_nonce_field(UR_AI_Market_Price_Module::IMPORT_ACTION); ?>

                <div class="ur-ai-form-row">
                    <label for="mp_city"><?php echo esc_html__('檔案所屬縣市', 'ur-ai-assistant'); ?></label>
                    <select name="city" id="mp_city">
                        <?php foreach ($cities as $city_key => $city_label) : ?>
                            <option value="<?php echo esc_attr($city_key); ?>"><?php echo esc_html($city_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ur-ai-form-row">
                    <label for="mp_csv"><?php echo esc_html__('CSV 檔案', 'ur-ai-assistant'); ?></label>
                    <input type="file" name="ur_ai_market_price_csv" id="mp_csv" accept=".csv,.txt">
                </div>

                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('上傳並匯入', 'ur-ai-assistant'); ?>
                </button>
            </form>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('功能設定', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('設定前台是否顯示行情參考區塊，以及統計計算的相關門檻。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Market_Price_Module::SETTINGS_SAVE_ACTION); ?>">
                <?php wp_nonce_field(UR_AI_Market_Price_Module::SETTINGS_SAVE_ACTION); ?>

                <div class="ur-ai-form-row">
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                        <?php echo esc_html__('啟用前台行情參考區塊', 'ur-ai-assistant'); ?>
                    </label>
                    <p class="ur-ai-form-help"><?php echo esc_html__('預設關閉，啟用後 [ur_ai_market_price] 短碼才會顯示內容。', 'ur-ai-assistant'); ?></p>
                </div>

                <div class="ur-ai-grid ur-ai-grid-3">
                    <div class="ur-ai-form-row">
                        <label for="mp_old_age"><?php echo esc_html__('老屋門檻（年以上）', 'ur-ai-assistant'); ?></label>
                        <input type="number" id="mp_old_age" name="old_age_threshold" value="<?php echo esc_attr($old_age_threshold); ?>" min="10" max="100">
                    </div>
                    <div class="ur-ai-form-row">
                        <label for="mp_new_age"><?php echo esc_html__('新成屋門檻（年內）', 'ur-ai-assistant'); ?></label>
                        <input type="number" id="mp_new_age" name="new_age_threshold" value="<?php echo esc_attr($new_age_threshold); ?>" min="1" max="20">
                    </div>
                    <div class="ur-ai-form-row">
                        <label for="mp_min_sample"><?php echo esc_html__('最低樣本數', 'ur-ai-assistant'); ?></label>
                        <input type="number" id="mp_min_sample" name="min_sample_size" value="<?php echo esc_attr($min_sample_size); ?>" min="1" max="50">
                    </div>
                </div>

                <div class="ur-ai-form-row">
                    <label for="mp_disclaimer"><?php echo esc_html__('免責聲明', 'ur-ai-assistant'); ?></label>
                    <textarea id="mp_disclaimer" name="disclaimer" rows="5"><?php echo esc_textarea($disclaimer); ?></textarea>
                </div>

                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('儲存設定', 'ur-ai-assistant'); ?>
                </button>
            </form>
        </div>

    </div>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('樣本數健檢', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('依行政區列出老屋／新成屋的樣本數，方便判斷哪些行政區還需要補充歷史資料。低於最低樣本數門檻的格子會標示提醒色。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <?php foreach ($cities as $city_key => $city_label) : ?>
            <?php $health = $service->get_sample_health($city_key); ?>

            <h3><?php echo esc_html($city_label); ?></h3>

            <?php if (empty($health)) : ?>
                <p class="ur-ai-muted"><?php echo esc_html__('尚無資料。', 'ur-ai-assistant'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="margin-bottom:20px;">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('行政區', 'ur-ai-assistant'); ?></th>
                            <th><?php echo esc_html__('老屋樣本數', 'ur-ai-assistant'); ?></th>
                            <th><?php echo esc_html__('新成屋樣本數', 'ur-ai-assistant'); ?></th>
                            <th><?php echo esc_html__('總筆數', 'ur-ai-assistant'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($health as $row) : ?>
                            <?php
                            $old_count = absint($row->old_count);
                            $new_count = absint($row->new_count);
                            ?>
                            <tr>
                                <td><?php echo esc_html($row->district); ?></td>
                                <td<?php echo $old_count < $min_sample_size ? ' style="color:#dc2626;"' : ''; ?>>
                                    <?php echo esc_html($old_count); ?>
                                </td>
                                <td<?php echo $new_count < $min_sample_size ? ' style="color:#dc2626;"' : ''; ?>>
                                    <?php echo esc_html($new_count); ?>
                                </td>
                                <td><?php echo esc_html(absint($row->total_count)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('前台使用方式', 'ur-ai-assistant'); ?></h2>
            </div>
        </div>
        <p>
            <code class="ur-ai-code" id="ur-ai-market-price-shortcode">[ur_ai_market_price]</code>
            <button type="button" class="button ur-ai-copy-button" data-copy-target="#ur-ai-market-price-shortcode">
                <?php echo esc_html__('複製', 'ur-ai-assistant'); ?>
            </button>
        </p>
        <p class="ur-ai-muted"><?php echo esc_html__('將此短碼放到任一頁面或文章，即可顯示行情查詢區塊（需先啟用上方的「啟用前台行情參考區塊」）。', 'ur-ai-assistant'); ?></p>
    </div>

</div>
