<?php
/**
 * UR AI Assistant FAQ Ajax
 *
 * 後台「FAQ 知識庫」AJAX 處理器：把既有 FAQ 一鍵擴寫成文章草稿。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_Ajax
 */
class UR_AI_FAQ_Ajax {

    /**
     * 產生文章草稿 action。
     *
     * @var string
     */
    const ACTION_GENERATE_ARTICLE = 'ur_ai_generate_article_from_faq';

    /**
     * FAQ 文章草稿服務。
     *
     * @var UR_AI_FAQ_Article_Service|null
     */
    private $article_service;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->article_service = class_exists('UR_AI_FAQ_Article_Service') ? new UR_AI_FAQ_Article_Service() : null;
    }

    /**
     * 註冊 AJAX 掛鉤。
     *
     * @return void
     */
    public function register() {
        add_action('wp_ajax_' . self::ACTION_GENERATE_ARTICLE, array($this, 'handle_generate_article'));
    }

    /**
     * 處理「產生文章草稿」：把指定 FAQ 的問答擴寫成一篇 WordPress 文章草稿。
     *
     * @return void
     */
    public function handle_generate_article() {
        $this->require_access();

        if (!$this->article_service instanceof UR_AI_FAQ_Article_Service) {
            wp_send_json_error(array('message' => __('文章草稿服務尚未正確載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')));
        }

        $faq_id = isset($_POST['faq_id']) ? absint(wp_unslash($_POST['faq_id'])) : 0;

        $result = $this->article_service->create_from_faq($faq_id);

        if (empty($result['success'])) {
            wp_send_json_error(
                array('message' => !empty($result['message']) ? $result['message'] : __('產生文章草稿失敗，請稍後再試。', 'ur-ai-assistant'))
            );
        }

        wp_send_json_success(
            array(
                'post_id'  => $result['post_id'],
                'edit_url' => $result['edit_url'],
                'message'  => __('已產生文章草稿，請於文章編輯畫面核對內容後再發布。', 'ur-ai-assistant'),
            )
        );
    }

    /**
     * 權限與 nonce 驗證，失敗則直接回傳 JSON 錯誤並中止。
     *
     * @return void
     */
    private function require_access() {
        if (class_exists('UR_AI_Permissions')) {
            UR_AI_Permissions::ajax_require('faqs');
        } elseif (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('您沒有權限執行此操作。', 'ur-ai-assistant')), 403);
        }

        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::ajax_verify_admin_nonce_or_die();
        }
    }
}
