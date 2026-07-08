<?php
/**
 * UR AI Assistant Assistant Module
 *
 * AI 問答核心模組啟動器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Assistant_Module
 */
class UR_AI_Assistant_Module {

    /**
     * Answer Service.
     *
     * @var UR_AI_Answer_Service|null
     */
    private $answer_service = null;

    /**
     * 註冊 WordPress hooks。
     *
     * 目前問答核心不直接註冊 hook，
     * 前台 AJAX 入口由 UR_AI_Ajax_Module 負責。
     *
     * @return void
     */
    public function register() {
        /**
         * 保留模組註冊入口。
         *
         * 未來若要加入：
         * - REST API endpoint
         * - WP-CLI 指令
         * - 排程任務
         * 可在此處註冊。
         */
    }

    /**
     * 啟動模組。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Answer_Service')) {
            $this->answer_service = new UR_AI_Answer_Service();
        }
    }

    /**
     * 取得 Answer Service。
     *
     * @return UR_AI_Answer_Service|null
     */
    public function get_answer_service() {
        return $this->answer_service;
    }

    /**
     * 直接回答問題。
     *
     * 這個方法主要提供內部測試或未來擴充使用。
     * 前台正式流程仍建議走 AJAX Module。
     *
     * @param string $question 使用者問題。
     * @return array
     */
    public function answer($question) {
        if (!$this->answer_service instanceof UR_AI_Answer_Service && class_exists('UR_AI_Answer_Service')) {
            $this->answer_service = new UR_AI_Answer_Service();
        }

        if (!$this->answer_service instanceof UR_AI_Answer_Service) {
            return array(
                'success'       => false,
                'answer'        => '',
                'answer_source' => 'error',
                'message'       => __('問答服務尚未正確載入。', 'ur-ai-assistant'),
                'status_code'   => 500,
            );
        }

        return $this->answer_service->answer($question);
    }
}