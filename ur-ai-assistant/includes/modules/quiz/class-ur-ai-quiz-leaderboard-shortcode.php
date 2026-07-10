<?php
/**
 * UR AI Assistant Quiz Leaderboard Shortcode
 *
 * 「知識大考驗」排行榜獨立頁面。
 *
 * 純伺服器端渲染，不需要 AJAX，內容一開始就在 HTML 原始碼中。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Quiz_Leaderboard_Shortcode
 */
class UR_AI_Quiz_Leaderboard_Shortcode {

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
     * Shortcode render.
     *
     * @param array|string $atts Shortcode 屬性。
     * @return string
     */
    public function render($atts = array()) {
        $atts = shortcode_atts(
            array(
                'title' => '',
                'limit' => 20,
            ),
            is_array($atts) ? $atts : array(),
            'ur_ai_quiz_leaderboard'
        );

        if (!class_exists('UR_AI_Quiz_Settings') || !UR_AI_Quiz_Settings::is_enabled()) {
            return $this->render_notice(__('知識大考驗功能目前已停用。管理員可至後台「知識大考驗」設定重新啟用。', 'ur-ai-assistant'));
        }

        if (!$this->service instanceof UR_AI_Quiz_Service) {
            return $this->render_notice(__('知識大考驗服務類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant'));
        }

        $title = sanitize_text_field($atts['title']);

        if ('' === trim($title)) {
            $title = __('知識大考驗排行榜', 'ur-ai-assistant');
        }

        $args = array(
            'title'       => $title,
            'leaderboard' => $this->service->get_leaderboard(absint($atts['limit'])),
        );

        return $this->render_view('public/views/quiz-leaderboard-view.php', $args);
    }

    /**
     * 載入 View 並回傳 HTML。
     *
     * @param string $relative_path View 相對路徑。
     * @param array  $args View 參數。
     * @return string
     */
    private function render_view($relative_path, $args = array()) {
        $full_path = UR_AI_ASSISTANT_PLUGIN_DIR . $relative_path;

        if (!file_exists($full_path)) {
            return $this->render_notice(
                sprintf(
                    /* translators: %s: missing view path */
                    __('UR AI Assistant 前台 View 檔案不存在：%s', 'ur-ai-assistant'),
                    $relative_path
                )
            );
        }

        ob_start();

        include $full_path;

        return ob_get_clean();
    }

    /**
     * 提示訊息（僅管理員可見）。
     *
     * @param string $message 訊息內容。
     * @return string
     */
    private function render_notice($message) {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="ur-ai-quiz-leaderboard"><div class="ur-ai-error">' . esc_html($message) . '</div></div>';
    }
}
