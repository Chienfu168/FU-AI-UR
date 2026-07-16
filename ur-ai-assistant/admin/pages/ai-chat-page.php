<?php
/**
 * UR AI Assistant Admin Chat Page
 *
 * 後台「AI 對話」頁：讓管理者能與 AI 助理多輪對話，腦力激盪知識庫
 * 內容方向，對話結束後可請 AI 整理成 FAQ 草稿。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('UR_AI_Permissions')) {
    UR_AI_Permissions::require_manage_admin_chat();
} elseif (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('您沒有權限檢視此頁面。', 'ur-ai-assistant'),
        esc_html__('權限不足', 'ur-ai-assistant'),
        array(
            'response' => 403,
        )
    );
}

if (!class_exists('UR_AI_OpenAI_Client') || !class_exists('UR_AI_FAQ_Draft_Service')) {
    echo '<div class="wrap ur-ai-admin-page">';
    echo '<h1>' . esc_html__('AI 對話', 'ur-ai-assistant') . '</h1>';
    echo '<div class="notice notice-error"><p>' . esc_html__('AI 對話功能所需類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant') . '</p></div>';
    echo '</div>';
    return;
}

$api_key_set = false;

if (class_exists('UR_AI_Settings')) {
    $api_key_set = '' !== trim((string) UR_AI_Settings::get_api_key());
}

$settings_url = admin_url('admin.php?page=ur-ai-assistant-settings');

?>
<div class="wrap ur-ai-admin-page">

    <h1>
        <?php
        printf(
            /* translators: %s: 目前產業別的品牌名稱 */
            esc_html__('%s｜AI 對話', 'ur-ai-assistant'),
            esc_html(UR_AI_Admin_Menu::brand_name())
        );
        ?>
    </h1>

    <?php if (!$api_key_set) : ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: 設定頁連結 */
                    wp_kses(
                        __('尚未設定 OpenAI API Key，AI 對話功能無法使用。請先至<a href="%s">功能設定</a>頁設定。', 'ur-ai-assistant'),
                        array('a' => array('href' => array()))
                    ),
                    esc_url($settings_url)
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="ur-ai-card">
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('與 AI 助理討論知識庫內容', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('可以自由跟 AI 討論訪客可能會問的問題、請它協助整理說明方式、或請教目前知識庫還缺少哪些內容。對話內容不會自動儲存，重新整理頁面就會清空；討論完畢後，按下方「產生總結草稿」讓 AI 把這段對話整理成 FAQ 草稿，逐則確認後再加入知識庫。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <div id="ur-ai-chat-messages" class="ur-ai-chat-messages">
            <p class="ur-ai-muted ur-ai-chat-empty">
                <?php echo esc_html__('目前還沒有對話內容，請在下方輸入想討論的方向開始對話。', 'ur-ai-assistant'); ?>
            </p>
        </div>

        <div class="ur-ai-chat-input-row">
            <textarea
                id="ur-ai-chat-input"
                rows="3"
                placeholder="<?php echo esc_attr__('輸入想討論的知識庫內容方向……', 'ur-ai-assistant'); ?>"
                <?php disabled(!$api_key_set); ?>
            ></textarea>
            <button
                type="button"
                id="ur-ai-chat-send"
                class="button button-primary"
                <?php disabled(!$api_key_set); ?>
            >
                <?php echo esc_html__('傳送', 'ur-ai-assistant'); ?>
            </button>
        </div>

        <div class="ur-ai-chat-actions">
            <button
                type="button"
                id="ur-ai-chat-summarize"
                class="button"
                <?php disabled(!$api_key_set); ?>
            >
                <?php echo esc_html__('產生總結草稿', 'ur-ai-assistant'); ?>
            </button>
        </div>
    </div>

    <div id="ur-ai-chat-drafts-card" class="ur-ai-card" hidden>
        <div class="ur-ai-card-header">
            <div>
                <h2 class="ur-ai-card-title"><?php echo esc_html__('AI 整理出的 FAQ 草稿建議', 'ur-ai-assistant'); ?></h2>
                <p class="ur-ai-card-description">
                    <?php echo esc_html__('可個別編輯問題／回答／分類／關鍵字內容，確認後點「加入知識庫」逐則存成草稿；存入的草稿一律為「停用／待審核」狀態，仍需至 FAQ 知識庫頁人工審核後再啟用。', 'ur-ai-assistant'); ?>
                </p>
            </div>
        </div>

        <div id="ur-ai-chat-drafts-list"></div>
    </div>

</div>
