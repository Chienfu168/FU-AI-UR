<?php
/**
 * UR AI Assistant Dashboard Page
 *
 * 後台總覽頁。
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
        array(
            'response' => 403,
        )
    );
}

$faq_summary = array(
    'total'    => 0,
    'active'   => 0,
    'inactive' => 0,
);

$log_summary = array(
    'total'           => 0,
    'faq'             => 0,
    'ai'              => 0,
    'error'           => 0,
    'tokens_used'     => 0,
    'with_related'    => 0,
    'without_related' => 0,
);

$related_summary = array(
    'total'             => 0,
    'active'            => 0,
    'inactive'          => 0,
    'total_show_count'  => 0,
    'total_click_count' => 0,
);

$popular_summary = array(
    'total'             => 0,
    'active'            => 0,
    'inactive'          => 0,
    'total_click_count' => 0,
);

$feedback_summary = array(
    'total_feedback'   => 0,
    'helpful'          => 0,
    'not_helpful'      => 0,
    'helpful_rate'     => 0,
    'not_helpful_rate' => 0,
);

if (class_exists('UR_AI_FAQ_Service')) {
    $faq_service = new UR_AI_FAQ_Service();
    $faq_summary = wp_parse_args($faq_service->get_summary(), $faq_summary);
}

if (class_exists('UR_AI_Log_Service')) {
    $log_service = new UR_AI_Log_Service();
    $log_summary = wp_parse_args($log_service->get_summary(), $log_summary);
}

if (class_exists('UR_AI_Related_Page_Service')) {
    $related_service = new UR_AI_Related_Page_Service();
    $related_summary = wp_parse_args($related_service->get_summary(), $related_summary);
}

if (class_exists('UR_AI_Popular_Question_Service')) {
    $popular_service = new UR_AI_Popular_Question_Service();
    $popular_summary = wp_parse_args($popular_service->get_summary(), $popular_summary);
}

if (class_exists('UR_AI_Feedback_Service')) {
    $feedback_service = new UR_AI_Feedback_Service();
    $feedback_summary = wp_parse_args($feedback_service->get_summary(), $feedback_summary);
}

$settings_url          = admin_url('admin.php?page=ur-ai-assistant-settings');
$faqs_url              = admin_url('admin.php?page=ur-ai-assistant-faqs');
$logs_url              = admin_url('admin.php?page=ur-ai-assistant-logs');
$related_pages_url     = admin_url('admin.php?page=ur-ai-assistant-related-pages');
$popular_questions_url = admin_url('admin.php?page=ur-ai-assistant-popular-questions');
$feedback_url          = admin_url('admin.php?page=ur-ai-assistant-feedback');

$shortcode             = '[ur_ai_assistant]';
$faq_kb_page_shortcode = '[ur_ai_faq_kb_page]';
$calculator_shortcode  = '[ur_ai_calculator]';
$market_price_shortcode = '[ur_ai_market_price]';

$market_price_stale_days = null;

if (class_exists('UR_AI_Market_Price_Service')) {
    $market_price_service    = new UR_AI_Market_Price_Service();
    $market_price_stale_days = $market_price_service->get_stale_days();
}

$market_price_admin_url = admin_url('admin.php?page=ur-ai-assistant-market-price');

$api_key_set = false;

if (class_exists('UR_AI_Settings')) {
    $api_key_set = '' !== trim((string) UR_AI_Settings::get_api_key());
}

$frontend_enabled = true;

if (class_exists('UR_AI_Settings')) {
    $frontend_enabled = UR_AI_Settings::is_frontend_enabled();
}

$logging_enabled = true;

if (class_exists('UR_AI_Settings')) {
    $logging_enabled = UR_AI_Settings::is_logging_enabled();
}

$faq_enabled = true;

if (class_exists('UR_AI_Settings')) {
    $faq_enabled = UR_AI_Settings::is_faq_enabled();
}

$related_enabled = true;

if (class_exists('UR_AI_Settings')) {
    $related_enabled = UR_AI_Settings::is_related_enabled();
}

$popular_enabled = true;

if (class_exists('UR_AI_Settings')) {
    $popular_enabled = UR_AI_Settings::is_popular_enabled();
}

?>

<div class="wrap ur-ai-admin-page">

    <h1><?php echo esc_html__('都更 AI 助理｜總覽', 'ur-ai-assistant'); ?></h1>

    <?php if (!$api_key_set) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html__('尚未設定 OpenAI API Key。', 'ur-ai-assistant'); ?></strong>
                <?php echo esc_html__('如果 FAQ 未命中，系統將無法呼叫 AI 回答。請先至「功能設定」填入 API Key。', 'ur-ai-assistant'); ?>
                <a href="<?php echo esc_url($settings_url); ?>">
                    <?php echo esc_html__('前往設定', 'ur-ai-assistant'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($market_price_service) && $market_price_service->is_stale()) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html__('行情參考資料已久未更新。', 'ur-ai-assistant'); ?></strong>
                <?php
                printf(
                    /* translators: %d: days since last import */
                    esc_html__('已 %d 天未匯入新資料，建議至內政部實價登錄開放資料下載新一季資料並重新匯入。', 'ur-ai-assistant'),
                    $market_price_stale_days
                );
                ?>
                <a href="<?php echo esc_url($market_price_admin_url); ?>">
                    <?php echo esc_html__('前往行情參考', 'ur-ai-assistant'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!$frontend_enabled) : ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html__('前台問答目前已停用。', 'ur-ai-assistant'); ?></strong>
                <?php echo esc_html__('訪客將無法使用 AI 助理。', 'ur-ai-assistant'); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php
    $faq_import_url = admin_url('admin.php?page=ur-ai-assistant-faqs');
    ?>
    <details class="ur-ai-card ur-ai-setup-guide"<?php echo (0 === (int) $faq_summary['total']) ? ' open' : ''; ?>>
        <summary class="ur-ai-setup-guide-summary">
            <span class="ur-ai-setup-guide-title"><?php echo esc_html__('安裝後設定指南', 'ur-ai-assistant'); ?></span>
            <span class="ur-ai-setup-guide-hint"><?php echo esc_html__('（首次安裝或搬移網站請先看這裡；點擊可展開／收合）', 'ur-ai-assistant'); ?></span>
        </summary>

        <div class="ur-ai-setup-guide-body">
            <p class="ur-ai-setup-guide-intro">
                <?php echo esc_html__('本外掛的檔案可直接安裝到任何 WordPress，但「內容」（FAQ 知識庫、API Key、試算器參數）儲存在資料庫與設定中，不會隨外掛檔案一起搬移。全新安裝或搬移網站後，請依序完成下列項目，功能才會完整運作。', 'ur-ai-assistant'); ?>
            </p>

            <ol class="ur-ai-setup-steps">
                <li>
                    <strong><?php echo esc_html__('1. 設定 OpenAI API Key', 'ur-ai-assistant'); ?></strong>
                    <span class="ur-ai-setup-status <?php echo $api_key_set ? 'is-done' : 'is-todo'; ?>">
                        <?php echo $api_key_set ? esc_html__('已設定', 'ur-ai-assistant') : esc_html__('尚未設定', 'ur-ai-assistant'); ?>
                    </span>
                    <p><?php echo esc_html__('未設定時，FAQ 未命中的問題將無法由 AI 回答。', 'ur-ai-assistant'); ?>
                        <a href="<?php echo esc_url($settings_url); ?>"><?php echo esc_html__('前往功能設定', 'ur-ai-assistant'); ?></a>
                    </p>
                </li>
                <li>
                    <strong><?php echo esc_html__('2. 建立 FAQ 知識庫', 'ur-ai-assistant'); ?></strong>
                    <span class="ur-ai-setup-status <?php echo ((int) $faq_summary['total'] > 0) ? 'is-done' : 'is-todo'; ?>">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: FAQ 筆數 */
                                __('目前 %d 筆', 'ur-ai-assistant'),
                                (int) $faq_summary['total']
                            )
                        );
                        ?>
                    </span>
                    <p><?php echo esc_html__('FAQ 命中越多，回答越穩定、也越省 API 費用。搬站或大量新增時，可用 CSV 匯入一次匯入多筆；匯入時題目相同的既有 FAQ 會被覆蓋更新，其餘新增（匯入前建議先匯出備份）。', 'ur-ai-assistant'); ?>
                        <a href="<?php echo esc_url($faq_import_url); ?>"><?php echo esc_html__('前往 FAQ 知識庫（含匯入／匯出）', 'ur-ai-assistant'); ?></a>
                    </p>
                </li>
                <li>
                    <strong><?php echo esc_html__('3. 確認前台問答已啟用', 'ur-ai-assistant'); ?></strong>
                    <span class="ur-ai-setup-status <?php echo $frontend_enabled ? 'is-done' : 'is-todo'; ?>">
                        <?php echo $frontend_enabled ? esc_html__('已啟用', 'ur-ai-assistant') : esc_html__('已停用', 'ur-ai-assistant'); ?>
                    </span>
                    <p><?php echo esc_html__('於功能設定中啟用，並將短代碼 [ur_ai_assistant] 放到要顯示問答的頁面。', 'ur-ai-assistant'); ?></p>
                </li>
                <li>
                    <strong><?php echo esc_html__('4. 試算器與名單表單（選用）', 'ur-ai-assistant'); ?></strong>
                    <p><?php echo esc_html__('若要使用分回試算器的名單捕捉功能，需安裝 Contact Form 7，並在「試算器設定」中把 CF7 表單 ID 改成新網站自己的表單 ID（預設值為原網站的表單，搬站後必須修改）。試算器的容積率、獎勵係數等參數也建議依需求重新確認。', 'ur-ai-assistant'); ?></p>
                </li>
            </ol>

            <p class="ur-ai-setup-guide-note">
                <?php echo esc_html__('提示：完成上述項目後，本指南會自動收合（有 FAQ 資料時預設收合）。日後仍可隨時點擊標題重新展開。', 'ur-ai-assistant'); ?>
            </p>
        </div>
    </details>

    <details class="ur-ai-card ur-ai-setup-guide" id="ur-ai-shortcode-guide">
        <summary class="ur-ai-setup-guide-summary">
            <span class="ur-ai-setup-guide-title"><?php echo esc_html__('Shortcode 使用說明', 'ur-ai-assistant'); ?></span>
            <span class="ur-ai-setup-guide-hint"><?php echo esc_html__('（本外掛全部 4 組前台 Shortcode 與參數一覽；搬到新網站安裝時可直接照這裡設定）', 'ur-ai-assistant'); ?></span>
        </summary>

        <div class="ur-ai-setup-guide-body">

            <h3><?php echo esc_html__('1. AI 助理問答', 'ur-ai-assistant'); ?></h3>
            <p>
                <code class="ur-ai-code" id="ur-ai-guide-shortcode-assistant"><?php echo esc_html($shortcode); ?></code>
                <button type="button" class="button ur-ai-copy-button" data-copy-target="#ur-ai-guide-shortcode-assistant">
                    <?php echo esc_html__('複製', 'ur-ai-assistant'); ?>
                </button>
            </p>
            <p class="ur-ai-muted"><?php echo esc_html__('放到任一頁面或文章即可顯示問答框，訪客可直接提問，FAQ 命中則優先回答，未命中才呼叫 AI。', 'ur-ai-assistant'); ?></p>
            <ul class="ur-ai-shortcode-params">
                <li><code>title</code> — <?php echo esc_html__('自訂前台標題，留空採用功能設定的預設標題。', 'ur-ai-assistant'); ?></li>
                <li><code>subtitle</code> — <?php echo esc_html__('自訂前台副標題。', 'ur-ai-assistant'); ?></li>
                <li><code>placeholder</code> — <?php echo esc_html__('自訂輸入框提示文字。', 'ur-ai-assistant'); ?></li>
                <li><code>show_popular</code> — <?php echo esc_html__('是否顯示熱門問題清單，預設 1（顯示）。', 'ur-ai-assistant'); ?></li>
                <li><code>popular_limit</code> — <?php echo esc_html__('熱門問題顯示數量，預設 6。', 'ur-ai-assistant'); ?></li>
                <li><code>show_groups</code> — <?php echo esc_html__('是否顯示依分類分組的熱門問題，預設 0（不顯示）。', 'ur-ai-assistant'); ?></li>
                <li><code>group_limit</code> — <?php echo esc_html__('每組分類熱門問題顯示數量，預設 4。', 'ur-ai-assistant'); ?></li>
                <li><code>show_kb_browse</code> — <?php echo esc_html__('是否顯示知識庫瀏覽區塊，預設 1；需另於下方「功能設定」啟用知識庫瀏覽此參數才有作用。', 'ur-ai-assistant'); ?></li>
                <li><code>kb_browse_limit</code> — <?php echo esc_html__('知識庫瀏覽每頁筆數，留空採用功能設定的預設值。', 'ur-ai-assistant'); ?></li>
            </ul>
            <p class="ur-ai-muted">
                <?php
                printf(
                    /* translators: %s: example shortcode */
                    esc_html__('範例：%s', 'ur-ai-assistant'),
                    '<code>[ur_ai_assistant title="都更危老 AI 助理" show_popular="1" show_groups="0" popular_limit="6"]</code>'
                );
                ?>
            </p>

            <hr>

            <h3><?php echo esc_html__('2. FAQ 知識庫查詢頁（SEO 導向）', 'ur-ai-assistant'); ?></h3>
            <p>
                <code class="ur-ai-code" id="ur-ai-guide-shortcode-faqkb"><?php echo esc_html($faq_kb_page_shortcode); ?></code>
                <button type="button" class="button ur-ai-copy-button" data-copy-target="#ur-ai-guide-shortcode-faqkb">
                    <?php echo esc_html__('複製', 'ur-ai-assistant'); ?>
                </button>
            </p>
            <p class="ur-ai-muted"><?php echo esc_html__('與上面的 AI 助理是完全獨立的功能，建議另外新建一個獨立頁面（例如「常見問題」）放這個 Shortcode。伺服器端直接輸出問答內容，不需要 JavaScript 就能瀏覽；搜尋／分類／換頁皆使用網址參數（?kb_q=、?kb_cat=、?kb_page=），並會自動輸出 Google 支援的 FAQPage 結構化資料。僅在「功能設定」的 FAQ 功能啟用時才會顯示內容。', 'ur-ai-assistant'); ?></p>
            <ul class="ur-ai-shortcode-params">
                <li><code>title</code> — <?php echo esc_html__('頁面標題（H1），留空預設為「常見問題知識庫」。', 'ur-ai-assistant'); ?></li>
                <li><code>per_page</code> — <?php echo esc_html__('每頁筆數（1～50），預設 20。', 'ur-ai-assistant'); ?></li>
            </ul>
            <p class="ur-ai-muted">
                <?php
                printf(
                    /* translators: %s: example shortcode */
                    esc_html__('範例：%s', 'ur-ai-assistant'),
                    '<code>[ur_ai_faq_kb_page title="常見問題" per_page="20"]</code>'
                );
                ?>
            </p>

            <hr>

            <h3><?php echo esc_html__('3. 都更分回效益試算器', 'ur-ai-assistant'); ?></h3>
            <p>
                <code class="ur-ai-code" id="ur-ai-guide-shortcode-calculator"><?php echo esc_html($calculator_shortcode); ?></code>
                <button type="button" class="button ur-ai-copy-button" data-copy-target="#ur-ai-guide-shortcode-calculator">
                    <?php echo esc_html__('複製', 'ur-ai-assistant'); ?>
                </button>
            </p>
            <p class="ur-ai-muted"><?php echo esc_html__('提供都更／危老分回效益試算，並可透過 Contact Form 7 整合留下試算名單。搬到新網站使用前，請先到「試算器設定」確認容積率、獎勵係數等參數，並把 CF7 表單 ID 換成新網站自己的表單 ID。', 'ur-ai-assistant'); ?></p>
            <ul class="ur-ai-shortcode-params">
                <li><code>mode="owner"</code> — <?php echo esc_html__('地主版（預設）：只有「換坪比」試算，最簡單，適合一般屋主。', 'ur-ai-assistant'); ?></li>
                <li><code>mode="pro"</code> — <?php echo esc_html__('含整合公司進階：額外提供三案擇優、樓層／高度概估等進階評估。', 'ur-ai-assistant'); ?></li>
            </ul>
            <p class="ur-ai-muted">
                <?php
                printf(
                    /* translators: %s: example shortcode */
                    esc_html__('範例：%s', 'ur-ai-assistant'),
                    '<code>[ur_ai_calculator mode="pro"]</code>'
                );
                ?>
            </p>

            <hr>

            <h3><?php echo esc_html__('4. 雙北成屋行情參考', 'ur-ai-assistant'); ?></h3>
            <p>
                <code class="ur-ai-code" id="ur-ai-guide-shortcode-market-price"><?php echo esc_html($market_price_shortcode); ?></code>
                <button type="button" class="button ur-ai-copy-button" data-copy-target="#ur-ai-guide-shortcode-market-price">
                    <?php echo esc_html__('複製', 'ur-ai-assistant'); ?>
                </button>
            </p>
            <p class="ur-ai-muted"><?php echo esc_html__('查詢雙北（台北市／新北市）近期「老屋現況」與「新成屋」成交行情，並排比較都更／危老重建前後的價值落差。僅供歷史成交行情參考，不構成估價。需先於「行情參考」頁面上傳內政部實價登錄開放資料並啟用此功能。', 'ur-ai-assistant'); ?></p>
            <ul class="ur-ai-shortcode-params">
                <li><code>title</code> — <?php echo esc_html__('自訂標題，留空預設為「雙北成屋行情參考」。', 'ur-ai-assistant'); ?></li>
            </ul>

        </div>
    </details>

    <div class="ur-ai-summary-grid">
        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('FAQ 知識庫', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($faq_summary['total'])); ?></p>
            <p class="ur-ai-summary-note">
                <?php
                printf(
                    esc_html__('啟用 %1$d｜停用 %2$d', 'ur-ai-assistant'),
                    absint($faq_summary['active']),
                    absint($faq_summary['inactive'])
                );
                ?>
            </p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('問答紀錄', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($log_summary['total'])); ?></p>
            <p class="ur-ai-summary-note">
                <?php
                printf(
                    esc_html__('FAQ %1$d｜AI %2$d｜錯誤 %3$d', 'ur-ai-assistant'),
                    absint($log_summary['faq']),
                    absint($log_summary['ai']),
                    absint($log_summary['error'])
                );
                ?>
            </p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('相關頁面推薦', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($related_summary['total'])); ?></p>
            <p class="ur-ai-summary-note">
                <?php
                printf(
                    esc_html__('曝光 %1$d｜點擊 %2$d', 'ur-ai-assistant'),
                    absint($related_summary['total_show_count']),
                    absint($related_summary['total_click_count'])
                );
                ?>
            </p>
        </div>

        <div class="ur-ai-summary-card">
            <p class="ur-ai-summary-label"><?php echo esc_html__('熱門問題', 'ur-ai-assistant'); ?></p>
            <p class="ur-ai-summary-value"><?php echo esc_html(absint($popular_summary['total'])); ?></p>
            <p class="ur-ai-summary-note">
                <?php
                printf(
                    esc_html__('啟用 %1$d｜點擊 %2$d', 'ur-ai-assistant'),
                    absint($popular_summary['active']),
                    absint($popular_summary['total_click_count'])
                );
                ?>
            </p>
        </div>
    </div>

    <div class="ur-ai-grid ur-ai-grid-2">

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('系統狀態', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('確認目前主要功能是否啟用。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <ul class="ur-ai-dashboard-list">
                <li>
                    <span><?php echo esc_html__('前台問答', 'ur-ai-assistant'); ?></span>
                    <?php echo $frontend_enabled ? '<span class="ur-ai-badge ur-ai-badge-active">' . esc_html__('啟用', 'ur-ai-assistant') . '</span>' : '<span class="ur-ai-badge ur-ai-badge-error">' . esc_html__('停用', 'ur-ai-assistant') . '</span>'; ?>
                </li>
                <li>
                    <span><?php echo esc_html__('FAQ 優先回答', 'ur-ai-assistant'); ?></span>
                    <?php echo $faq_enabled ? '<span class="ur-ai-badge ur-ai-badge-active">' . esc_html__('啟用', 'ur-ai-assistant') . '</span>' : '<span class="ur-ai-badge ur-ai-badge-inactive">' . esc_html__('停用', 'ur-ai-assistant') . '</span>'; ?>
                </li>
                <li>
                    <span><?php echo esc_html__('問答紀錄', 'ur-ai-assistant'); ?></span>
                    <?php echo $logging_enabled ? '<span class="ur-ai-badge ur-ai-badge-active">' . esc_html__('啟用', 'ur-ai-assistant') . '</span>' : '<span class="ur-ai-badge ur-ai-badge-inactive">' . esc_html__('停用', 'ur-ai-assistant') . '</span>'; ?>
                </li>
                <li>
                    <span><?php echo esc_html__('相關頁面推薦', 'ur-ai-assistant'); ?></span>
                    <?php echo $related_enabled ? '<span class="ur-ai-badge ur-ai-badge-active">' . esc_html__('啟用', 'ur-ai-assistant') . '</span>' : '<span class="ur-ai-badge ur-ai-badge-inactive">' . esc_html__('停用', 'ur-ai-assistant') . '</span>'; ?>
                </li>
                <li>
                    <span><?php echo esc_html__('熱門問題', 'ur-ai-assistant'); ?></span>
                    <?php echo $popular_enabled ? '<span class="ur-ai-badge ur-ai-badge-active">' . esc_html__('啟用', 'ur-ai-assistant') . '</span>' : '<span class="ur-ai-badge ur-ai-badge-inactive">' . esc_html__('停用', 'ur-ai-assistant') . '</span>'; ?>
                </li>
                <li>
                    <span><?php echo esc_html__('OpenAI API Key', 'ur-ai-assistant'); ?></span>
                    <?php echo $api_key_set ? '<span class="ur-ai-badge ur-ai-badge-active">' . esc_html__('已設定', 'ur-ai-assistant') . '</span>' : '<span class="ur-ai-badge ur-ai-badge-warning">' . esc_html__('未設定', 'ur-ai-assistant') . '</span>'; ?>
                </li>
            </ul>

            <div class="ur-ai-dashboard-actions">
                <a class="button button-primary" href="<?php echo esc_url($settings_url); ?>">
                    <?php echo esc_html__('前往功能設定', 'ur-ai-assistant'); ?>
                </a>
            </div>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('前台使用方式', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('請將 Shortcode 放到 WordPress 頁面或文章中。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <p><?php echo esc_html__('基本 Shortcode：', 'ur-ai-assistant'); ?></p>

            <p>
                <code class="ur-ai-code" id="ur-ai-dashboard-shortcode"><?php echo esc_html($shortcode); ?></code>
                <button
                    type="button"
                    class="button ur-ai-copy-button"
                    data-copy-target="#ur-ai-dashboard-shortcode"
                >
                    <?php echo esc_html__('複製', 'ur-ai-assistant'); ?>
                </button>
            </p>

            <p class="ur-ai-muted">
                <?php echo esc_html__('可放在「都更危老 AI 助理」頁面，讓訪客直接提問。', 'ur-ai-assistant'); ?>
            </p>

            <div class="ur-ai-help-box">
                <strong><?php echo esc_html__('建議：', 'ur-ai-assistant'); ?></strong>
                <?php echo esc_html__('正式上線前，先用 FAQ 與相關頁面推薦建立基本知識庫，可降低 AI API 成本，也能讓回答更穩定。', 'ur-ai-assistant'); ?>
            </div>

            <p class="ur-ai-muted">
                <?php echo esc_html__('本外掛共有 4 組 Shortcode（AI 助理、FAQ 知識庫查詢頁、試算器、行情參考），完整參數與範例請見上方「Shortcode 使用說明」。', 'ur-ai-assistant'); ?>
                <a href="#ur-ai-shortcode-guide"><?php echo esc_html__('前往完整說明', 'ur-ai-assistant'); ?></a>
            </p>
        </div>

    </div>

    <div class="ur-ai-grid ur-ai-grid-3">

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('FAQ 知識庫', 'ur-ai-assistant'); ?></h2>
            <p>
                <?php echo esc_html__('FAQ 命中時會優先使用固定回答，不呼叫 AI，適合作為成本控管與標準答案來源。', 'ur-ai-assistant'); ?>
            </p>
            <div class="ur-ai-dashboard-actions">
                <a class="button button-primary" href="<?php echo esc_url($faqs_url); ?>">
                    <?php echo esc_html__('管理 FAQ', 'ur-ai-assistant'); ?>
                </a>
            </div>
        </div>

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('問答紀錄', 'ur-ai-assistant'); ?></h2>
            <p>
                <?php echo esc_html__('查看使用者提問、回答來源、FAQ 命中、Token 使用量與是否可轉成 FAQ 草稿。', 'ur-ai-assistant'); ?>
            </p>
            <div class="ur-ai-dashboard-actions">
                <a class="button button-primary" href="<?php echo esc_url($logs_url); ?>">
                    <?php echo esc_html__('查看紀錄', 'ur-ai-assistant'); ?>
                </a>
            </div>
        </div>

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('相關頁面推薦', 'ur-ai-assistant'); ?></h2>
            <p>
                <?php echo esc_html__('依使用者問題推薦網站內文章，讓 AI 助理同時帶動網站內容曝光。', 'ur-ai-assistant'); ?>
            </p>
            <div class="ur-ai-dashboard-actions">
                <a class="button button-primary" href="<?php echo esc_url($related_pages_url); ?>">
                    <?php echo esc_html__('管理推薦頁面', 'ur-ai-assistant'); ?>
                </a>
            </div>
        </div>

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('熱門問題', 'ur-ai-assistant'); ?></h2>
            <p>
                <?php echo esc_html__('在前台提供常見問題入口，降低訪客不知道如何提問的門檻。', 'ur-ai-assistant'); ?>
            </p>
            <div class="ur-ai-dashboard-actions">
                <a class="button button-primary" href="<?php echo esc_url($popular_questions_url); ?>">
                    <?php echo esc_html__('管理熱門問題', 'ur-ai-assistant'); ?>
                </a>
            </div>
        </div>

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('回饋分析', 'ur-ai-assistant'); ?></h2>
            <p>
                <?php
                printf(
                    esc_html__('目前共有 %1$d 筆回饋，有幫助比例約 %2$s%%。', 'ur-ai-assistant'),
                    absint($feedback_summary['total_feedback']),
                    esc_html($feedback_summary['helpful_rate'])
                );
                ?>
            </p>
            <div class="ur-ai-dashboard-actions">
                <a class="button button-primary" href="<?php echo esc_url($feedback_url); ?>">
                    <?php echo esc_html__('查看回饋分析', 'ur-ai-assistant'); ?>
                </a>
            </div>
        </div>

        <div class="ur-ai-card">
            <h2><?php echo esc_html__('開發提醒', 'ur-ai-assistant'); ?></h2>
            <p>
                <?php echo esc_html__('本版採模組化架構，後續新增功能時，建議維持 Repository、Service、Admin、Page 分層，避免檔案再度膨脹。', 'ur-ai-assistant'); ?>
            </p>
            <div class="ur-ai-warning-box">
                <?php echo esc_html__('正式網站更新前，建議先在測試站啟用，確認資料表、前台問答、後台管理都正常後再上線。', 'ur-ai-assistant'); ?>
            </div>
        </div>

    </div>

</div>