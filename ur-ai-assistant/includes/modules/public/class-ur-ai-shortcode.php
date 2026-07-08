<?php
/**
 * UR AI Assistant Shortcode
 *
 * 前台 Shortcode 控制器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Shortcode
 */
class UR_AI_Shortcode {

    /**
     * Shortcode render.
     *
     * @param array|string $atts Shortcode 屬性。
     * @return string
     */
    public function render($atts = array()) {
        $atts = shortcode_atts(
            array(
                'title'           => '',
                'subtitle'        => '',
                'show_popular'    => '1',
                'show_groups'     => '0',
                'popular_limit'   => '6',
                'group_limit'     => '4',
                'placeholder'     => '',
                'show_kb_browse'  => '1',
                'kb_browse_limit' => '',
            ),
            is_array($atts) ? $atts : array(),
            'ur_ai_assistant'
        );

        $args = $this->build_view_args($atts);

        return $this->render_view('public/views/assistant-view.php', $args);
    }

    /**
     * 建立 View 參數。
     *
     * @param array $atts Shortcode 屬性。
     * @return array
     */
    private function build_view_args($atts) {
        $title = isset($atts['title'])
            ? sanitize_text_field($atts['title'])
            : '';

        if ('' === $title && class_exists('UR_AI_Settings')) {
            $title = UR_AI_Settings::get('frontend_title', '都更危老 AI 助理');
        }

        if ('' === $title) {
            $title = __('都更危老 AI 助理', 'ur-ai-assistant');
        }

        $subtitle = isset($atts['subtitle'])
            ? sanitize_textarea_field($atts['subtitle'])
            : '';

        if ('' === $subtitle && class_exists('UR_AI_Settings')) {
            $subtitle = UR_AI_Settings::get(
                'frontend_subtitle',
                '用白話方式，快速了解都市更新、危老重建、更新會、自主更新、權利變換與協議合建等基礎問題。'
            );
        }

        if ('' === $subtitle) {
            $subtitle = __('用白話方式，快速了解都市更新、危老重建、更新會、自主更新、權利變換與協議合建等基礎問題。', 'ur-ai-assistant');
        }

        $disclaimer = '';

        if (class_exists('UR_AI_Settings')) {
            $disclaimer = UR_AI_Settings::get(
                'disclaimer',
                '本工具提供一般資訊參考，不構成法律、估價、建築、稅務或個案決策建議。若涉及個案權利、契約、訴訟、登記或稅務問題，建議洽詢相關專業人士。'
            );
        }

        if ('' === $disclaimer) {
            $disclaimer = __('本工具提供一般資訊參考，不構成法律、估價、建築、稅務或個案決策建議。若涉及個案權利、契約、訴訟、登記或稅務問題，建議洽詢相關專業人士。', 'ur-ai-assistant');
        }

        $max_question_length = class_exists('UR_AI_Settings')
            ? UR_AI_Settings::get_max_question_length()
            : 500;

        $max_question_length = absint($max_question_length);

        if ($max_question_length <= 0) {
            $max_question_length = 500;
        }

        $placeholder = isset($atts['placeholder'])
            ? sanitize_textarea_field($atts['placeholder'])
            : '';

        if ('' === trim($placeholder)) {
            $placeholder = __('請輸入您想了解的都市更新、危老重建、更新會、權利變換或協議合建問題。', 'ur-ai-assistant');
        }

        $show_popular = $this->truthy(isset($atts['show_popular']) ? $atts['show_popular'] : '1');
        $show_groups  = $this->truthy(isset($atts['show_groups']) ? $atts['show_groups'] : '0');

        $popular_limit = isset($atts['popular_limit']) ? absint($atts['popular_limit']) : 6;
        $group_limit   = isset($atts['group_limit']) ? absint($atts['group_limit']) : 4;

        if ($popular_limit <= 0) {
            $popular_limit = 6;
        }

        if ($group_limit <= 0) {
            $group_limit = 4;
        }

        $popular_questions = array();
        $popular_groups    = array();

        if ($this->is_popular_enabled() && class_exists('UR_AI_Popular_Question_Service')) {
            $popular_service = new UR_AI_Popular_Question_Service();

            if ($show_popular) {
                $popular_questions = $popular_service->get_frontend_questions($popular_limit);
            }

            if ($show_groups) {
                $popular_groups = $popular_service->get_frontend_grouped_questions($group_limit);
            }
        }

        $show_kb_browse    = $this->truthy(isset($atts['show_kb_browse']) ? $atts['show_kb_browse'] : '1');
        $kb_browse_enabled = $show_kb_browse && $this->is_kb_browse_enabled();

        $kb_browse_categories = array();
        $kb_browse_per_page   = isset($atts['kb_browse_limit']) ? absint($atts['kb_browse_limit']) : 0;

        if ($kb_browse_per_page <= 0) {
            $kb_browse_per_page = class_exists('UR_AI_Settings') ? UR_AI_Settings::get_kb_browse_per_page() : 10;
        }

        if ($kb_browse_enabled && class_exists('UR_AI_FAQ_Service')) {
            $faq_service          = new UR_AI_FAQ_Service();
            $kb_browse_categories = $faq_service->get_active_categories();
        }

        return array(
            'title'                => $title,
            'subtitle'             => $subtitle,
            'disclaimer'           => $disclaimer,
            'popular_questions'    => $popular_questions,
            'popular_groups'       => $popular_groups,
            'max_question_length'  => $max_question_length,
            'placeholder'          => $placeholder,
            'kb_browse_enabled'    => $kb_browse_enabled,
            'kb_browse_categories' => $kb_browse_categories,
            'kb_browse_per_page'   => $kb_browse_per_page,
        );
    }

    /**
     * 判斷知識庫瀏覽是否啟用。
     *
     * @return bool
     */
    private function is_kb_browse_enabled() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::is_kb_browse_enabled();
        }

        return false;
    }

    /**
     * 載入 View 並回傳 HTML。
     *
     * @param string $relative_path View 相對路徑。
     * @param array  $args View 參數。
     * @return string
     */
    private function render_view($relative_path, $args = array()) {
        $relative_path = $this->normalize_relative_path($relative_path);

        if ('' === $relative_path) {
            return $this->render_missing_view_notice('');
        }

        $full_path = UR_AI_ASSISTANT_PLUGIN_DIR . $relative_path;

        if (!file_exists($full_path)) {
            return $this->render_missing_view_notice($relative_path);
        }

        if (!is_array($args)) {
            $args = array();
        }

        ob_start();

        include $full_path;

        return ob_get_clean();
    }

    /**
     * 正規化相對路徑。
     *
     * @param string $relative_path 相對路徑。
     * @return string
     */
    private function normalize_relative_path($relative_path) {
        if (!is_string($relative_path)) {
            return '';
        }

        $relative_path = trim($relative_path);

        if ('' === $relative_path) {
            return '';
        }

        $relative_path = str_replace('\\', '/', $relative_path);
        $relative_path = ltrim($relative_path, '/');

        if (false !== strpos($relative_path, '../')) {
            return '';
        }

        if (false !== strpos($relative_path, '..\\')) {
            return '';
        }

        return $relative_path;
    }

    /**
     * View 缺漏提示。
     *
     * @param string $relative_path 缺漏 View 路徑。
     * @return string
     */
    private function render_missing_view_notice($relative_path) {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $message = $relative_path
            ? sprintf(
                /* translators: %s: missing view path */
                __('UR AI Assistant 前台 View 檔案不存在：%s', 'ur-ai-assistant'),
                $relative_path
            )
            : __('UR AI Assistant 前台 View 路徑不正確。', 'ur-ai-assistant');

        return '<div class="ur-ai-assistant"><div class="ur-ai-error">'
            . esc_html($message)
            . '</div></div>';
    }

    /**
     * 判斷熱門問題是否啟用。
     *
     * @return bool
     */
    private function is_popular_enabled() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::is_popular_enabled();
        }

        return true;
    }

    /**
     * 判斷 shortcode 屬性是否為 true。
     *
     * @param mixed $value 原始值。
     * @return bool
     */
    private function truthy($value) {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, array('1', 'true', 'yes', 'on'), true);
    }
}