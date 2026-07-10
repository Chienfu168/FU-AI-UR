<?php
/**
 * UR AI Assistant Quiz Ajax
 *
 * 知識大考驗前台 AJAX 處理器。
 *
 * 安全設計重點：兩個 action 都必須通過公開 nonce 驗證；start 只回傳
 * 不含正確答案的題目資料，submit 才在伺服器端比對計分，前端永遠拿不到
 * 正確答案本身。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Quiz_Ajax
 */
class UR_AI_Quiz_Ajax {

    /**
     * 開始挑戰 action 名稱。
     *
     * @var string
     */
    const ACTION_START = 'ur_ai_quiz_start';

    /**
     * 送出作答 action 名稱。
     *
     * @var string
     */
    const ACTION_SUBMIT = 'ur_ai_quiz_submit';

    /**
     * Service.
     *
     * @var UR_AI_Quiz_Service|null
     */
    private $service;

    /**
     * 建構子。
     *
     * @param UR_AI_Quiz_Service|null $service Service。
     */
    public function __construct($service = null) {
        $this->service = $service instanceof UR_AI_Quiz_Service
            ? $service
            : (class_exists('UR_AI_Quiz_Service') ? new UR_AI_Quiz_Service() : null);
    }

    /**
     * 註冊 AJAX 掛鉤（登入與未登入皆可）。
     *
     * @return void
     */
    public function register() {
        add_action('wp_ajax_' . self::ACTION_START, array($this, 'handle_start'));
        add_action('wp_ajax_nopriv_' . self::ACTION_START, array($this, 'handle_start'));

        add_action('wp_ajax_' . self::ACTION_SUBMIT, array($this, 'handle_submit'));
        add_action('wp_ajax_nopriv_' . self::ACTION_SUBMIT, array($this, 'handle_submit'));
    }

    /**
     * 處理「開始挑戰」請求。
     *
     * @return void
     */
    public function handle_start() {
        UR_AI_Security::ajax_verify_public_nonce_or_die();

        if (!$this->is_enabled()) {
            wp_send_json_error(
                array('message' => __('知識大考驗功能目前未啟用。', 'ur-ai-assistant')),
                403
            );
        }

        if (!$this->service instanceof UR_AI_Quiz_Service) {
            wp_send_json_error(
                array('message' => __('知識大考驗服務尚未正確載入，請稍後再試。', 'ur-ai-assistant')),
                500
            );
        }

        $result = $this->service->start_attempt();

        if (!empty($result['error'])) {
            wp_send_json_error(array('message' => $result['error']), 400);
        }

        wp_send_json_success(
            array(
                'token'     => $result['token'],
                'questions' => $result['questions'],
            )
        );
    }

    /**
     * 處理「送出作答」請求。
     *
     * @return void
     */
    public function handle_submit() {
        UR_AI_Security::ajax_verify_public_nonce_or_die();

        if (!$this->is_enabled()) {
            wp_send_json_error(
                array('message' => __('知識大考驗功能目前未啟用。', 'ur-ai-assistant')),
                403
            );
        }

        if (!$this->service instanceof UR_AI_Quiz_Service) {
            wp_send_json_error(
                array('message' => __('知識大考驗服務尚未正確載入，請稍後再試。', 'ur-ai-assistant')),
                500
            );
        }

        $token    = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $nickname = isset($_POST['nickname']) ? sanitize_text_field(wp_unslash($_POST['nickname'])) : '';
        $duration = isset($_POST['duration']) ? absint($_POST['duration']) : 0;

        $answers_raw = isset($_POST['answers']) ? wp_unslash($_POST['answers']) : '';
        $answers     = $this->decode_answers($answers_raw);

        $result = $this->service->submit_attempt($token, $answers, $nickname, $duration);

        if (!empty($result['error'])) {
            wp_send_json_error(array('message' => $result['error']), 400);
        }

        wp_send_json_success($result);
    }

    /**
     * 解析前端送出的作答內容（JSON 字串，格式 {question_uid: "a"} ）。
     *
     * @param mixed $answers_raw 原始輸入。
     * @return array
     */
    private function decode_answers($answers_raw) {
        if (is_array($answers_raw)) {
            $decoded = $answers_raw;
        } elseif (is_string($answers_raw) && '' !== trim($answers_raw)) {
            $decoded = json_decode($answers_raw, true);
        } else {
            $decoded = array();
        }

        if (!is_array($decoded)) {
            return array();
        }

        $clean = array();

        foreach ($decoded as $question_uid => $selected) {
            $question_uid = sanitize_key((string) $question_uid);
            $selected     = sanitize_key((string) $selected);

            if ('' !== $question_uid) {
                $clean[$question_uid] = $selected;
            }
        }

        return $clean;
    }

    /**
     * 判斷模組是否啟用。
     *
     * @return bool
     */
    private function is_enabled() {
        return class_exists('UR_AI_Quiz_Settings') && UR_AI_Quiz_Settings::is_enabled();
    }
}
