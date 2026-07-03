<?php
/**
 * UR AI Assistant FAQ Knowledge Base Page Shortcode
 *
 * 獨立的知識庫查詢頁面（SEO 導向）。
 *
 * 與 UR_AI_Shortcode（AI 助理 widget 內的知識庫瀏覽區塊）不同：
 * - 純伺服器端渲染，不依賴 AJAX，內容一開始就在 HTML 原始碼中，利於搜尋引擎索引。
 * - 搜尋／分類篩選／換頁皆用 GET 表單與真實連結（?kb_q=／?kb_cat=／?kb_page=），
 *   不需要 JavaScript 也能運作。
 * - 輸出 FAQPage JSON-LD 結構化資料，供 Google 顯示常見問題摘要。
 *
 * 使用方式：在任一 WordPress 頁面／文章加入 [ur_ai_faq_kb_page]，
 * 建議另外建立一個獨立頁面（例如「常見問題」）並設定固定網址。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_KB_Page_Shortcode
 */
class UR_AI_FAQ_KB_Page_Shortcode {

    /**
     * Shortcode render.
     *
     * @param array|string $atts Shortcode 屬性。
     * @return string
     */
    public function render($atts = array()) {
        $atts = shortcode_atts(
            array(
                'title'    => '',
                'per_page' => '20',
            ),
            is_array($atts) ? $atts : array(),
            'ur_ai_faq_kb_page'
        );

        if (!$this->is_faq_enabled()) {
            return $this->render_disabled_notice();
        }

        if (!class_exists('UR_AI_FAQ_Service')) {
            return $this->render_missing_service_notice();
        }

        $args = $this->build_view_args($atts);

        return $this->render_view('public/views/faq-kb-page-view.php', $args);
    }

    /**
     * 建立 View 參數。
     *
     * @param array $atts Shortcode 屬性。
     * @return array
     */
    private function build_view_args($atts) {
        $title = isset($atts['title']) ? sanitize_text_field($atts['title']) : '';

        if ('' === trim($title)) {
            $title = __('常見問題知識庫', 'ur-ai-assistant');
        }

        $per_page = absint($atts['per_page']);
        $per_page = class_exists('UR_AI_Security') ? UR_AI_Security::int_range($per_page, 1, 50, 20) : 20;

        $search   = isset($_GET['kb_q']) ? sanitize_text_field(wp_unslash($_GET['kb_q'])) : '';
        $category = isset($_GET['kb_cat']) ? sanitize_text_field(wp_unslash($_GET['kb_cat'])) : '';
        $paged    = isset($_GET['kb_page']) ? absint($_GET['kb_page']) : 1;
        $paged    = $paged > 0 ? $paged : 1;

        $service    = new UR_AI_FAQ_Service();
        $categories = $service->get_active_categories();

        $result = $service->browse(
            array(
                'search'   => $search,
                'category' => $category,
                'paged'    => $paged,
                'per_page' => $per_page,
            )
        );

        $items = array();

        foreach ($result['items'] as $item) {
            $items[] = array(
                'id'          => isset($item['id']) ? absint($item['id']) : 0,
                'category'    => isset($item['category']) ? (string) $item['category'] : '',
                'question'    => isset($item['question']) ? (string) $item['question'] : '',
                'answer_html' => $this->format_answer_html(isset($item['answer']) ? (string) $item['answer'] : ''),
                'answer_text' => isset($item['answer']) ? wp_strip_all_tags((string) $item['answer']) : '',
            );
        }

        return array(
            'title'       => $title,
            'search'      => $search,
            'category'    => $category,
            'categories'  => $categories,
            'items'       => $items,
            'total'       => $result['total'],
            'paged'       => $result['paged'],
            'per_page'    => $result['per_page'],
            'total_pages' => $result['total_pages'],
        );
    }

    /**
     * 格式化答案 HTML。
     *
     * @param string $answer 原始答案文字。
     * @return string
     */
    private function format_answer_html($answer) {
        if (class_exists('UR_AI_Formatter')) {
            return UR_AI_Formatter::answer_html($answer);
        }

        return wp_kses_post(wpautop($answer));
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
            if (!current_user_can('manage_options')) {
                return '';
            }

            return '<div class="ur-ai-kb-page"><div class="ur-ai-error">'
                . esc_html(
                    sprintf(
                        /* translators: %s: missing view path */
                        __('UR AI Assistant 前台 View 檔案不存在：%s', 'ur-ai-assistant'),
                        $relative_path
                    )
                )
                . '</div></div>';
        }

        ob_start();

        include $full_path;

        return ob_get_clean();
    }

    /**
     * 判斷 FAQ 是否啟用。
     *
     * @return bool
     */
    private function is_faq_enabled() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::is_faq_enabled();
        }

        return true;
    }

    /**
     * FAQ 未啟用提示。
     *
     * @return string
     */
    private function render_disabled_notice() {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="ur-ai-kb-page"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant FAQ 知識庫目前已停用。管理員可至後台功能設定重新啟用。', 'ur-ai-assistant')
            . '</div></div>';
    }

    /**
     * FAQ 服務缺漏提示。
     *
     * @return string
     */
    private function render_missing_service_notice() {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="ur-ai-kb-page"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant FAQ 服務類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')
            . '</div></div>';
    }
}
