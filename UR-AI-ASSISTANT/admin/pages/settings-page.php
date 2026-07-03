<?php
/**
 * UR AI Assistant Settings Page
 *
 * 功能設定頁。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('UR_AI_Permissions')) {
    UR_AI_Permissions::require_manage_settings();
} elseif (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'),
        esc_html__('權限不足', 'ur-ai-assistant'),
        array(
            'response' => 403,
        )
    );
}

if (!class_exists('UR_AI_Settings')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('功能設定', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('設定類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

/**
 * 頁面訊息。
 */
$message  = isset($_GET['ur_message']) ? sanitize_key(wp_unslash($_GET['ur_message'])) : '';
$msg_type = isset($_GET['ur_msg_type']) ? sanitize_key(wp_unslash($_GET['ur_msg_type'])) : 'updated';

/**
 * 儲存設定。
 *
 * 注意：
 * 這個 page 檔案是由 WordPress 後台 callback include 進來的。
 * 若在此處執行 wp_safe_redirect() + exit，可能因為後台 HTML 已經輸出，
 * 導致資料有寫入但頁面變空白。
 *
 * 因此這裡採用「儲存後留在原頁，顯示訊息」的保守方式。
 */
if (
    isset($_POST['ur_ai_action']) &&
    'save_settings' === sanitize_key(wp_unslash($_POST['ur_ai_action']))
) {
    if (class_exists('UR_AI_Security')) {
        UR_AI_Security::verify_admin_form_nonce_or_die();
    } else {
        check_admin_referer('ur_ai_assistant_admin_action', 'ur_ai_nonce');
    }

    $settings_to_save = array(
        'api_key'             => isset($_POST['api_key']) ? wp_unslash($_POST['api_key']) : '',
        'model'               => isset($_POST['model']) ? wp_unslash($_POST['model']) : '',
        'temperature'         => isset($_POST['temperature']) ? wp_unslash($_POST['temperature']) : '',
        'max_answer_tokens'   => isset($_POST['max_answer_tokens']) ? wp_unslash($_POST['max_answer_tokens']) : '',
        'system_prompt'       => isset($_POST['system_prompt']) ? wp_unslash($_POST['system_prompt']) : '',
        'frontend_enabled'    => !empty($_POST['frontend_enabled']) ? 1 : 0,
        'frontend_title'      => isset($_POST['frontend_title']) ? wp_unslash($_POST['frontend_title']) : '',
        'frontend_subtitle'   => isset($_POST['frontend_subtitle']) ? wp_unslash($_POST['frontend_subtitle']) : '',
        'disclaimer'          => isset($_POST['disclaimer']) ? wp_unslash($_POST['disclaimer']) : '',
        'max_question_length' => isset($_POST['max_question_length']) ? wp_unslash($_POST['max_question_length']) : '',
        'guest_daily_limit'   => isset($_POST['guest_daily_limit']) ? wp_unslash($_POST['guest_daily_limit']) : '',
        'member_daily_limit'  => isset($_POST['member_daily_limit']) ? wp_unslash($_POST['member_daily_limit']) : '',
        'admin_daily_limit'   => isset($_POST['admin_daily_limit']) ? wp_unslash($_POST['admin_daily_limit']) : '',
        'faq_enabled'         => !empty($_POST['faq_enabled']) ? 1 : 0,
        'related_enabled'     => !empty($_POST['related_enabled']) ? 1 : 0,
        'popular_enabled'     => !empty($_POST['popular_enabled']) ? 1 : 0,
        'logging_enabled'     => !empty($_POST['logging_enabled']) ? 1 : 0,
        'kb_browse_enabled'   => !empty($_POST['kb_browse_enabled']) ? 1 : 0,
        'kb_browse_per_page'  => isset($_POST['kb_browse_per_page']) ? wp_unslash($_POST['kb_browse_per_page']) : '',
    );

    $updated = UR_AI_Settings::update_many($settings_to_save);

    $message  = 'settings_saved';
    $msg_type = $updated ? 'updated' : 'error';
}

$settings = UR_AI_Settings::get_all();

$api_key             = UR_AI_Settings::get_api_key();
$model               = UR_AI_Settings::get_model();
$temperature         = UR_AI_Settings::get_temperature();
$max_answer_tokens   = UR_AI_Settings::get_max_answer_tokens();
$system_prompt       = UR_AI_Settings::get_system_prompt();
$frontend_enabled    = UR_AI_Settings::is_frontend_enabled();
$frontend_title      = UR_AI_Settings::get('frontend_title', '都更危老 AI 助理');
$frontend_subtitle   = UR_AI_Settings::get('frontend_subtitle', '用白話方式，快速了解都市更新、危老重建、更新會、自主更新、權利變換與協議合建等基礎問題。');
$disclaimer          = UR_AI_Settings::get('disclaimer', '本工具提供一般資訊參考，不構成法律、估價、建築、稅務或個案決策建議。若涉及個案權利、契約、訴訟、登記或稅務問題，建議洽詢相關專業人士。');
$max_question_length = UR_AI_Settings::get_max_question_length();

$guest_daily_limit  = UR_AI_Settings::get_daily_limit('guest');
$member_daily_limit = UR_AI_Settings::get_daily_limit('member');
$admin_daily_limit  = UR_AI_Settings::get_daily_limit('admin');

$faq_enabled     = UR_AI_Settings::is_faq_enabled();
$related_enabled = UR_AI_Settings::is_related_enabled();
$popular_enabled = UR_AI_Settings::is_popular_enabled();
$logging_enabled = UR_AI_Settings::is_logging_enabled();

$kb_browse_enabled  = UR_AI_Settings::is_kb_browse_enabled();
$kb_browse_per_page = UR_AI_Settings::get_kb_browse_per_page();

?>

<div class="wrap ur-ai-admin-page">

    <h1><?php echo esc_html__('都更 AI 助理｜功能設定', 'ur-ai-assistant'); ?></h1>

    <?php if ('settings_saved' === $message) : ?>
        <div class="notice notice-<?php echo 'error' === $msg_type ? 'error' : 'success'; ?> is-dismissible">
            <p>
                <?php
                echo 'error' === $msg_type
                    ? esc_html__('設定儲存時發生問題，請稍後再試。', 'ur-ai-assistant')
                    : esc_html__('設定已儲存。', 'ur-ai-assistant');
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" class="ur-ai-admin-form">
        <?php
        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::admin_form_nonce_field();
        } else {
            wp_nonce_field('ur_ai_assistant_admin_action', 'ur_ai_nonce');
        }
        ?>

        <input type="hidden" name="ur_ai_action" value="save_settings">

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('AI API 設定', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('設定 OpenAI API Key、模型與回答參數。FAQ 命中時不會呼叫 AI。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <div class="ur-ai-form-row">
                <label for="api_key"><?php echo esc_html__('OpenAI API Key', 'ur-ai-assistant'); ?></label>
                <input
                    type="password"
                    id="api_key"
                    name="api_key"
                    value="<?php echo esc_attr($api_key); ?>"
                    autocomplete="off"
                    class="regular-text"
                >
                <p class="ur-ai-form-help">
                    <?php echo esc_html__('請妥善保管 API Key。若只使用 FAQ 固定回答，可暫不設定，但 FAQ 未命中時就無法呼叫 AI。', 'ur-ai-assistant'); ?>
                </p>
            </div>

            <div class="ur-ai-grid ur-ai-grid-3">
                <div class="ur-ai-form-row">
                    <label for="model"><?php echo esc_html__('AI 模型', 'ur-ai-assistant'); ?></label>
                    <input
                        type="text"
                        id="model"
                        name="model"
                        value="<?php echo esc_attr($model); ?>"
                        placeholder="gpt-4o-mini"
                    >
                </div>

                <div class="ur-ai-form-row">
                    <label for="temperature"><?php echo esc_html__('Temperature', 'ur-ai-assistant'); ?></label>
                    <input
                        type="number"
                        id="temperature"
                        name="temperature"
                        value="<?php echo esc_attr($temperature); ?>"
                        min="0"
                        max="2"
                        step="0.1"
                    >
                    <p class="ur-ai-form-help"><?php echo esc_html__('建議 0.3～0.5，回答較穩定。', 'ur-ai-assistant'); ?></p>
                </div>

                <div class="ur-ai-form-row">
                    <label for="max_answer_tokens"><?php echo esc_html__('最大回答 Token', 'ur-ai-assistant'); ?></label>
                    <input
                        type="number"
                        id="max_answer_tokens"
                        name="max_answer_tokens"
                        value="<?php echo esc_attr($max_answer_tokens); ?>"
                        min="100"
                        max="4000"
                        step="50"
                    >
                </div>
            </div>

            <div class="ur-ai-form-row">
                <label for="system_prompt"><?php echo esc_html__('系統提示詞', 'ur-ai-assistant'); ?></label>
                <textarea
                    id="system_prompt"
                    name="system_prompt"
                    rows="9"
                ><?php echo esc_textarea($system_prompt); ?></textarea>
                <p class="ur-ai-form-help">
                    <?php echo esc_html__('建議明確限制回答範圍、語氣、免責提醒與不可替個案作法律或估價判斷。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('前台顯示設定', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('設定前台 AI 助理標題、說明與使用提醒。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <div class="ur-ai-form-row">
                <label>
                    <input
                        type="checkbox"
                        name="frontend_enabled"
                        value="1"
                        <?php checked($frontend_enabled); ?>
                    >
                    <?php echo esc_html__('啟用前台問答功能', 'ur-ai-assistant'); ?>
                </label>
            </div>

            <div class="ur-ai-form-row">
                <label for="frontend_title"><?php echo esc_html__('前台標題', 'ur-ai-assistant'); ?></label>
                <input
                    type="text"
                    id="frontend_title"
                    name="frontend_title"
                    value="<?php echo esc_attr($frontend_title); ?>"
                >
            </div>

            <div class="ur-ai-form-row">
                <label for="frontend_subtitle"><?php echo esc_html__('前台副標題', 'ur-ai-assistant'); ?></label>
                <textarea
                    id="frontend_subtitle"
                    name="frontend_subtitle"
                    rows="3"
                ><?php echo esc_textarea($frontend_subtitle); ?></textarea>
            </div>

            <div class="ur-ai-form-row">
                <label for="disclaimer"><?php echo esc_html__('免責提醒', 'ur-ai-assistant'); ?></label>
                <textarea
                    id="disclaimer"
                    name="disclaimer"
                    rows="4"
                ><?php echo esc_textarea($disclaimer); ?></textarea>
            </div>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('使用限制與成本控管', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('控制每日提問次數與問題字數，避免 API 成本失控。設定 0 代表不限制。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <div class="ur-ai-grid ur-ai-grid-4">
                <div class="ur-ai-form-row">
                    <label for="max_question_length"><?php echo esc_html__('最大問題字數', 'ur-ai-assistant'); ?></label>
                    <input
                        type="number"
                        id="max_question_length"
                        name="max_question_length"
                        value="<?php echo esc_attr($max_question_length); ?>"
                        min="20"
                        max="2000"
                        step="10"
                    >
                </div>

                <div class="ur-ai-form-row">
                    <label for="guest_daily_limit"><?php echo esc_html__('訪客每日次數', 'ur-ai-assistant'); ?></label>
                    <input
                        type="number"
                        id="guest_daily_limit"
                        name="guest_daily_limit"
                        value="<?php echo esc_attr($guest_daily_limit); ?>"
                        min="0"
                        max="9999"
                    >
                </div>

                <div class="ur-ai-form-row">
                    <label for="member_daily_limit"><?php echo esc_html__('會員每日次數', 'ur-ai-assistant'); ?></label>
                    <input
                        type="number"
                        id="member_daily_limit"
                        name="member_daily_limit"
                        value="<?php echo esc_attr($member_daily_limit); ?>"
                        min="0"
                        max="9999"
                    >
                </div>

                <div class="ur-ai-form-row">
                    <label for="admin_daily_limit"><?php echo esc_html__('管理員每日次數', 'ur-ai-assistant'); ?></label>
                    <input
                        type="number"
                        id="admin_daily_limit"
                        name="admin_daily_limit"
                        value="<?php echo esc_attr($admin_daily_limit); ?>"
                        min="0"
                        max="9999"
                    >
                </div>
            </div>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('模組開關', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('可依網站需求啟用或停用部分功能。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <div class="ur-ai-grid ur-ai-grid-2">
                <div class="ur-ai-form-row">
                    <label>
                        <input
                            type="checkbox"
                            name="faq_enabled"
                            value="1"
                            <?php checked($faq_enabled); ?>
                        >
                        <?php echo esc_html__('啟用 FAQ 知識庫優先回答', 'ur-ai-assistant'); ?>
                    </label>
                    <p class="ur-ai-form-help"><?php echo esc_html__('建議啟用，可降低 AI API 成本並維持固定答案。', 'ur-ai-assistant'); ?></p>
                </div>

                <div class="ur-ai-form-row">
                    <label>
                        <input
                            type="checkbox"
                            name="logging_enabled"
                            value="1"
                            <?php checked($logging_enabled); ?>
                        >
                        <?php echo esc_html__('啟用問答紀錄', 'ur-ai-assistant'); ?>
                    </label>
                    <p class="ur-ai-form-help"><?php echo esc_html__('建議啟用，方便後續轉 FAQ 草稿與分析使用者需求。', 'ur-ai-assistant'); ?></p>
                </div>

                <div class="ur-ai-form-row">
                    <label>
                        <input
                            type="checkbox"
                            name="related_enabled"
                            value="1"
                            <?php checked($related_enabled); ?>
                        >
                        <?php echo esc_html__('啟用相關頁面推薦', 'ur-ai-assistant'); ?>
                    </label>
                </div>

                <div class="ur-ai-form-row">
                    <label>
                        <input
                            type="checkbox"
                            name="popular_enabled"
                            value="1"
                            <?php checked($popular_enabled); ?>
                        >
                        <?php echo esc_html__('啟用熱門問題與主題導覽', 'ur-ai-assistant'); ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="ur-ai-card">
            <div class="ur-ai-card-header">
                <div>
                    <h2 class="ur-ai-card-title"><?php echo esc_html__('知識庫瀏覽', 'ur-ai-assistant'); ?></h2>
                    <p class="ur-ai-card-description">
                        <?php echo esc_html__('讓使用者不必先問 AI，就能直接搜尋／瀏覽已啟用的 FAQ 問答內容。此功能不經過 FAQ 比對，也不會呼叫 AI。', 'ur-ai-assistant'); ?>
                    </p>
                </div>
            </div>

            <div class="ur-ai-form-row">
                <label>
                    <input
                        type="checkbox"
                        name="kb_browse_enabled"
                        value="1"
                        <?php checked($kb_browse_enabled); ?>
                    >
                    <?php echo esc_html__('啟用前台知識庫瀏覽區塊', 'ur-ai-assistant'); ?>
                </label>
                <p class="ur-ai-form-help"><?php echo esc_html__('預設關閉，啟用後會在前台 AI 助理下方新增可搜尋的常見問題列表。', 'ur-ai-assistant'); ?></p>
            </div>

            <div class="ur-ai-form-row">
                <label for="kb_browse_per_page"><?php echo esc_html__('每頁筆數', 'ur-ai-assistant'); ?></label>
                <input
                    type="number"
                    id="kb_browse_per_page"
                    name="kb_browse_per_page"
                    value="<?php echo esc_attr($kb_browse_per_page); ?>"
                    min="1"
                    max="50"
                    class="small-text"
                >
            </div>
        </div>

        <div class="ur-ai-form-actions">
            <button type="submit" class="button button-primary">
                <?php echo esc_html__('儲存設定', 'ur-ai-assistant'); ?>
            </button>

            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ur-ai-assistant')); ?>">
                <?php echo esc_html__('返回總覽', 'ur-ai-assistant'); ?>
            </a>
        </div>

    </form>

</div>