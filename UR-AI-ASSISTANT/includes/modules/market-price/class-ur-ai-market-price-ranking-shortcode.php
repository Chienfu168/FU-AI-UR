<?php
/**
 * UR AI Assistant Market Price Ranking Shortcode
 *
 * 獨立的「雙北都更效益排行榜」頁面（SEO 導向）。
 *
 * 與 [ur_ai_market_price]（需先選縣市／行政區才能查詢的 widget）不同：
 * - 不需要使用者輸入任何條件，直接列出雙北全部行政區的排名。
 * - 純伺服器端渲染，不依賴 AJAX，內容一開始就在 HTML 原始碼中，利於搜尋引擎索引。
 * - 只納入老屋、新成屋皆樣本充足的行政區，避免把不具統計意義的漲幅排進榜單。
 *
 * 使用方式：在任一 WordPress 頁面／文章加入 [ur_ai_market_price_ranking]，
 * 建議另外建立一個獨立頁面（例如「都更效益排行榜」）並設定固定網址。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Ranking_Shortcode
 */
class UR_AI_Market_Price_Ranking_Shortcode {

    /**
     * Service。
     *
     * @var UR_AI_Market_Price_Service|null
     */
    private $service;

    /**
     * 建構子。
     *
     * @param UR_AI_Market_Price_Service|null $service Service。
     */
    public function __construct($service = null) {
        $this->service = $service instanceof UR_AI_Market_Price_Service
            ? $service
            : (class_exists('UR_AI_Market_Price_Service') ? new UR_AI_Market_Price_Service() : null);
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
            ),
            is_array($atts) ? $atts : array(),
            'ur_ai_market_price_ranking'
        );

        if (!class_exists('UR_AI_Market_Price_Settings') || !UR_AI_Market_Price_Settings::is_enabled()) {
            return $this->render_disabled_notice();
        }

        if (!$this->service instanceof UR_AI_Market_Price_Service) {
            return $this->render_missing_service_notice();
        }

        $args = $this->build_view_args($atts);

        return $this->render_view('public/views/market-price-ranking-view.php', $args);
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
            $title = __('雙北都更效益排行榜', 'ur-ai-assistant');
        }

        $cities   = $this->service->get_supported_cities();
        $rankings = array();

        foreach ($cities as $city_key => $city_label) {
            $rankings[$city_key] = array(
                'label' => $city_label,
                'rows'  => $this->service->get_ranking($city_key),
            );
        }

        return array(
            'title'             => $title,
            'rankings'          => $rankings,
            'last_imported_at'  => $this->service->get_last_imported_at(),
            'disclaimer'        => class_exists('UR_AI_Market_Price_Settings') ? UR_AI_Market_Price_Settings::get_disclaimer() : '',
        );
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

            return '<div class="ur-ai-market-price-ranking"><div class="ur-ai-error">'
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
     * 未啟用提示。
     *
     * @return string
     */
    private function render_disabled_notice() {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="ur-ai-market-price-ranking"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant 行情參考功能目前已停用。管理員可至後台「行情參考」設定重新啟用。', 'ur-ai-assistant')
            . '</div></div>';
    }

    /**
     * 服務缺漏提示。
     *
     * @return string
     */
    private function render_missing_service_notice() {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="ur-ai-market-price-ranking"><div class="ur-ai-error">'
            . esc_html__('UR AI Assistant 行情參考服務類別尚未載入，請確認外掛檔案是否完整。', 'ur-ai-assistant')
            . '</div></div>';
    }
}
