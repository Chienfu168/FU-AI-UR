<?php
/**
 * UR AI Assistant Public Assets
 *
 * 前台 CSS / JS 載入管理器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Public_Assets
 */
class UR_AI_Public_Assets {

    /**
     * 前台 CSS handle。
     *
     * @var string
     */
    const STYLE_HANDLE = 'ur-ai-assistant-public';

    /**
     * 前台 JS handle。
     *
     * @var string
     */
    const SCRIPT_HANDLE = 'ur-ai-assistant-public';

    /**
     * 是否已註冊。
     *
     * @var bool
     */
    private $registered = false;

    /**
     * 註冊前台資源。
     *
     * @return void
     */
    public function register() {
        if ($this->registered) {
            return;
        }

        wp_register_style(
            self::STYLE_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/css/public.css',
            array(),
            UR_AI_ASSISTANT_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/js/public.js',
            array('jquery'),
            UR_AI_ASSISTANT_VERSION,
            true
        );

        $this->registered = true;
    }

    /**
     * 載入前台資源。
     *
     * @return void
     */
    public function enqueue() {
        if (!$this->registered) {
            $this->register();
        }

        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);

        $this->localize_script();
    }

    /**
     * 只載入 CSS（不含 JS／localize）。
     *
     * 供 FAQ 知識庫查詢頁使用：該頁純伺服器端渲染，不需要 public.js，
     * 少載一支 JS 對頁面效能／SEO 更有利。
     *
     * @return void
     */
    public function enqueue_style_only() {
        if (!$this->registered) {
            $this->register();
        }

        wp_enqueue_style(self::STYLE_HANDLE);
    }

    /**
     * 傳遞資料給前台 JavaScript。
     *
     * @return void
     */
    private function localize_script() {
        wp_localize_script(
            self::SCRIPT_HANDLE,
            'UR_AI_PUBLIC',
            array(
                'ajax_url'            => admin_url('admin-ajax.php'),
                'nonce'               => wp_create_nonce('ur_ai_assistant_public_nonce'),
                'max_question_length' => $this->get_max_question_length(),
                'site_name'           => $this->get_print_site_name(),
                'site_url'            => $this->get_print_site_url(),
                'disclaimer'          => $this->get_print_disclaimer(),
                'i18n'                => $this->get_i18n(),
            )
        );
    }

    /**
     * 取得最大問題字數。
     *
     * @return int
     */
    private function get_max_question_length() {
        if (class_exists('UR_AI_Settings')) {
            return absint(UR_AI_Settings::get_max_question_length());
        }

        return 500;
    }

    /**
     * 取得列印頁首顯示的網站名稱。
     *
     * @return string
     */
    private function get_print_site_name() {
        $name = get_bloginfo('name');

        if ('' === trim((string) $name)) {
            $name = __('自主更新指南', 'ur-ai-assistant');
        }

        return (string) $name;
    }

    /**
     * 取得列印頁首顯示的網站網址（去除通訊協定）。
     *
     * @return string
     */
    private function get_print_site_url() {
        $url = home_url('/');
        $url = preg_replace('#^https?://#i', '', (string) $url);
        $url = rtrim((string) $url, '/');

        return $url;
    }

    /**
     * 取得列印頁尾免責聲明，沿用後台設定的同一則文字。
     *
     * @return string
     */
    private function get_print_disclaimer() {
        $disclaimer = '';

        if (class_exists('UR_AI_Settings')) {
            $disclaimer = (string) UR_AI_Settings::get('disclaimer', '');
        }

        if ('' === trim($disclaimer)) {
            $disclaimer = __('本工具提供一般資訊參考，不構成法律、估價、建築、稅務或個案決策建議。若涉及個案權利、契約、訴訟、登記或稅務問題，建議洽詢相關專業人士。', 'ur-ai-assistant');
        }

        return $disclaimer;
    }

    /**
     * 取得前台 JS 多語系文字。
     *
     * @return array
     */
    private function get_i18n() {
        return array(
            'submit'                      => __('送出提問', 'ur-ai-assistant'),
            'processing'                  => __('思考中...', 'ur-ai-assistant'),
            'error'                       => __('發生錯誤，請稍後再試。', 'ur-ai-assistant'),
            'network_error'               => __('連線失敗，請稍後再試。', 'ur-ai-assistant'),
            'empty_question'              => __('請先輸入想詢問的問題。', 'ur-ai-assistant'),
            'question_too_long'           => __('問題字數過長，請縮短後再送出。', 'ur-ai-assistant'),

            'your_question'               => __('你的問題', 'ur-ai-assistant'),
            'assistant_answer'            => __('AI 助理回答', 'ur-ai-assistant'),
            'related_title'               => __('你也許想知道', 'ur-ai-assistant'),
            'related_faqs_title'          => __('你也許還想知道', 'ur-ai-assistant'),

            'print_button'                => __('列印', 'ur-ai-assistant'),
            'print_button_aria'           => __('列印這則問答', 'ur-ai-assistant'),
            'print_document_title'        => sprintf(
                /* translators: %s: 目前產業別的品牌名稱 */
                __('%s問答', 'ur-ai-assistant'),
                class_exists('UR_AI_Industry_Profiles') ? UR_AI_Industry_Profiles::get_active_brand_name() : __('都更 AI 助理', 'ur-ai-assistant')
            ),
            'print_question_label'        => __('問題', 'ur-ai-assistant'),
            'print_answer_label'          => __('回答', 'ur-ai-assistant'),
            'print_date_label'            => __('列印日期', 'ur-ai-assistant'),
            'print_disclaimer_label'      => __('免責聲明', 'ur-ai-assistant'),

            'feedback_title'              => __('這個回答對你有幫助嗎？', 'ur-ai-assistant'),
            'feedback_helpful'            => __('有幫助', 'ur-ai-assistant'),
            'feedback_not_helpful'        => __('沒幫助', 'ur-ai-assistant'),
            'feedback_reason_placeholder' => __('請選擇原因', 'ur-ai-assistant'),
            'feedback_comment_placeholder'=> __('可補充說明，讓我們知道如何改善。', 'ur-ai-assistant'),
            'feedback_submit'             => __('送出回饋', 'ur-ai-assistant'),
            'feedback_success'            => __('感謝您的回饋。', 'ur-ai-assistant'),
            'feedback_failed'             => __('回饋送出失敗，請稍後再試。', 'ur-ai-assistant'),

            'reason_unclear'              => __('回答不夠清楚', 'ur-ai-assistant'),
            'reason_not_answered'         => __('沒有回答到問題', 'ur-ai-assistant'),
            'reason_too_general'          => __('內容太籠統', 'ur-ai-assistant'),
            'reason_need_examples'        => __('需要更多實務說明', 'ur-ai-assistant'),
            'reason_other'                => __('其他', 'ur-ai-assistant'),

            'kb_loading'                  => __('載入中…', 'ur-ai-assistant'),
            'kb_no_results'               => __('找不到符合的常見問題，可以直接在下方向 AI 助理提問。', 'ur-ai-assistant'),
            'kb_error'                    => __('知識庫載入失敗，請稍後再試。', 'ur-ai-assistant'),
            'kb_prev'                     => __('上一頁', 'ur-ai-assistant'),
            'kb_next'                     => __('下一頁', 'ur-ai-assistant'),
            'kb_page_info'                => __('第 %1$s／%2$s 頁（共 %3$s 筆）', 'ur-ai-assistant'),
        );
    }
}