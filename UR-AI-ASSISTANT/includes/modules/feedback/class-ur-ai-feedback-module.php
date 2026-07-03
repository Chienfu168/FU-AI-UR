<?php
/**
 * UR AI Assistant Feedback Module
 *
 * 使用者回饋模組啟動器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Feedback_Module
 */
class UR_AI_Feedback_Module {

    /**
     * Feedback Service.
     *
     * @var UR_AI_Feedback_Service|null
     */
    private $service = null;

    /**
     * 註冊 WordPress hooks。
     *
     * @return void
     */
    public function register() {
        add_action('wp_ajax_ur_ai_feedback', array($this, 'handle_feedback_ajax'));
        add_action('wp_ajax_nopriv_ur_ai_feedback', array($this, 'handle_feedback_ajax'));
    }

    /**
     * 啟動模組。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Feedback_Service')) {
            $this->service = new UR_AI_Feedback_Service();
        }
    }

    /**
     * 處理前台回饋 AJAX。
     *
     * @return void
     */
    public function handle_feedback_ajax() {
        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::ajax_verify_public_nonce_or_die();
        } else {
            $nonce = isset($_POST['nonce'])
                ? sanitize_text_field(wp_unslash($_POST['nonce']))
                : '';

            if (!wp_verify_nonce($nonce, 'ur_ai_assistant_public_nonce')) {
                wp_send_json_error(
                    array(
                        'message' => __('安全驗證失敗，請重新整理頁面後再試。', 'ur-ai-assistant'),
                    ),
                    403
                );
            }
        }

        $log_id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;

        $feedback = isset($_POST['feedback'])
            ? sanitize_key(wp_unslash($_POST['feedback']))
            : '';

        $reason = isset($_POST['reason'])
            ? sanitize_text_field(wp_unslash($_POST['reason']))
            : '';

        $comment = isset($_POST['comment'])
            ? sanitize_textarea_field(wp_unslash($_POST['comment']))
            : '';

        if ($log_id <= 0) {
            wp_send_json_error(
                array(
                    'message' => __('問答紀錄 ID 不正確，無法送出回饋。', 'ur-ai-assistant'),
                ),
                400
            );
        }

        if (!in_array($feedback, array('helpful', 'not_helpful'), true)) {
            wp_send_json_error(
                array(
                    'message' => __('回饋類型不正確。', 'ur-ai-assistant'),
                ),
                400
            );
        }

        if (!$this->service instanceof UR_AI_Feedback_Service && class_exists('UR_AI_Feedback_Service')) {
            $this->service = new UR_AI_Feedback_Service();
        }

        if (!$this->service instanceof UR_AI_Feedback_Service) {
            wp_send_json_error(
                array(
                    'message' => __('回饋服務尚未正確載入，請稍後再試。', 'ur-ai-assistant'),
                ),
                500
            );
        }

        $updated = $this->service->submit_feedback($log_id, $feedback, $reason, $comment);

        if (!$updated) {
            wp_send_json_error(
                array(
                    'message' => __('回饋送出失敗，請稍後再試。', 'ur-ai-assistant'),
                ),
                500
            );
        }

        wp_send_json_success(
            array(
                'message' => __('感謝您的回饋，我們會用來改善回答品質。', 'ur-ai-assistant'),
                'updated' => true,
            )
        );
    }

    /**
     * 取得 Feedback Service。
     *
     * @return UR_AI_Feedback_Service|null
     */
    public function get_service() {
        return $this->service;
    }
}