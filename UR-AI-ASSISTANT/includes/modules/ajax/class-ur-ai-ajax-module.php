<?php
/**
 * UR AI Assistant AJAX Module
 *
 * 前台問答 AJAX 模組啟動器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Ajax_Module
 */
class UR_AI_Ajax_Module {

    /**
     * Answer Service.
     *
     * @var UR_AI_Answer_Service|null
     */
    private $answer_service = null;

    /**
     * 註冊 WordPress hooks。
     *
     * @return void
     */
    public function register() {
        add_action('wp_ajax_ur_ai_ask', array($this, 'handle_ask'));
        add_action('wp_ajax_nopriv_ur_ai_ask', array($this, 'handle_ask'));
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
     * 處理前台提問 AJAX。
     *
     * @return void
     */
    public function handle_ask() {
        $this->verify_public_nonce();

        if (!$this->is_frontend_enabled()) {
            wp_send_json_error(
                array(
                    'message' => __('前台問答功能目前已停用。', 'ur-ai-assistant'),
                ),
                403
            );
        }

        $question = isset($_POST['question'])
            ? wp_unslash($_POST['question'])
            : '';

        $question = $this->sanitize_question($question);

        if ('' === trim($question)) {
            wp_send_json_error(
                array(
                    'message' => __('請先輸入想詢問的問題。', 'ur-ai-assistant'),
                ),
                400
            );
        }

        $max_length = $this->get_max_question_length();

        if ($max_length > 0 && $this->strlen($question) > $max_length) {
            wp_send_json_error(
                array(
                    'message' => sprintf(
                        /* translators: %d: max question length */
                        __('問題字數過長，請控制在 %d 字以內。', 'ur-ai-assistant'),
                        absint($max_length)
                    ),
                ),
                400
            );
        }

        if (!$this->check_daily_limit()) {
            wp_send_json_error(
                array(
                    'message' => __('今日提問次數已達上限，請明日再試。', 'ur-ai-assistant'),
                ),
                429
            );
        }

        if (!$this->answer_service instanceof UR_AI_Answer_Service && class_exists('UR_AI_Answer_Service')) {
            $this->answer_service = new UR_AI_Answer_Service();
        }

        if (!$this->answer_service instanceof UR_AI_Answer_Service) {
            wp_send_json_error(
                array(
                    'message' => __('問答服務尚未正確載入，請稍後再試。', 'ur-ai-assistant'),
                ),
                500
            );
        }

        $result = $this->answer_service->answer($question);

        if (!is_array($result)) {
            wp_send_json_error(
                array(
                    'message' => __('回答產生失敗，請稍後再試。', 'ur-ai-assistant'),
                ),
                500
            );
        }

        if (empty($result['success'])) {
            // H3: 僅回傳使用者可見訊息與 log_id，不曝露內部 error_code / answer_source。
            wp_send_json_error(
                array(
                    'message' => isset($result['message']) ? (string) $result['message'] : __('回答產生失敗，請稍後再試。', 'ur-ai-assistant'),
                    'log_id'  => isset($result['log_id']) ? absint($result['log_id']) : 0,
                ),
                isset($result['status_code']) ? absint($result['status_code']) : 500
            );
        }

        $this->increase_daily_count();

        wp_send_json_success(
            $this->format_response($result)
        );
    }

    /**
     * 驗證前台 nonce。
     *
     * @return void
     */
    private function verify_public_nonce() {
        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::ajax_verify_public_nonce_or_die();
            return;
        }

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

    /**
     * 清理使用者問題。
     *
     * @param mixed $question 原始問題。
     * @return string
     */
    private function sanitize_question($question) {
        if (class_exists('UR_AI_Security')) {
            return UR_AI_Security::sanitize_question($question);
        }

        $question = is_scalar($question) ? (string) $question : '';
        $question = wp_strip_all_tags($question);
        $question = sanitize_textarea_field($question);
        $question = preg_replace('/\s+/u', ' ', $question);

        return trim($question);
    }

    /**
     * 格式化回傳資料給前台 JS。
     *
     * @param array $result Answer Service 回傳資料。
     * @return array
     */
    private function format_response($result) {
        $answer = isset($result['answer']) ? (string) $result['answer'] : '';

        $answer_html = isset($result['answer_html'])
            ? (string) $result['answer_html']
            : $this->format_answer_html($answer);

        return array(
            'answer'              => $answer,
            'answer_html'         => $answer_html,
            'answer_source'       => isset($result['answer_source']) ? sanitize_key($result['answer_source']) : '',
            'answer_source_label' => isset($result['answer_source_label']) ? sanitize_text_field($result['answer_source_label']) : '',
            'log_id'              => isset($result['log_id']) ? absint($result['log_id']) : 0,
            'faq_id'              => isset($result['faq_id']) ? absint($result['faq_id']) : 0,
            'faq_match_score'     => isset($result['faq_match_score']) ? absint($result['faq_match_score']) : 0,
            'related_pages'       => isset($result['related_pages']) && is_array($result['related_pages'])
                ? $this->format_related_pages($result['related_pages'])
                : array(),
        );
    }

    /**
     * 格式化回答 HTML。
     *
     * @param string $answer 回答文字。
     * @return string
     */
    private function format_answer_html($answer) {
        if (class_exists('UR_AI_Formatter')) {
            return UR_AI_Formatter::answer_html($answer);
        }

        return wp_kses_post(wpautop($answer));
    }

    /**
     * 格式化相關頁面資料。
     *
     * @param array $pages 相關頁面。
     * @return array
     */
    private function format_related_pages($pages) {
        $items = array();

        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $id    = isset($page['id']) ? absint($page['id']) : 0;
            $title = isset($page['title']) ? sanitize_text_field($page['title']) : '';
            $url   = isset($page['url']) ? esc_url_raw($page['url']) : '';

            if ($id <= 0 || '' === $title || '' === $url) {
                continue;
            }

            $items[] = array(
                'id'          => $id,
                'title'       => $title,
                'url'         => $url,
                'description' => isset($page['description']) ? sanitize_textarea_field($page['description']) : '',
                'category'    => isset($page['category']) ? sanitize_text_field($page['category']) : '',
                'match_score' => isset($page['match_score']) ? absint($page['match_score']) : 0,
            );
        }

        return $items;
    }

    /**
     * 判斷前台是否啟用。
     *
     * @return bool
     */
    private function is_frontend_enabled() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::is_frontend_enabled();
        }

        return true;
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
     * 檢查每日提問限制。
     *
     * 此方法維持「唯讀」語意：只判斷是否已達上限，不改變計數。
     * 計數遞增由 increase_daily_count() 在「成功回答後」執行，
     * 與原始行為一致（失敗的提問不扣次數）。
     *
     * @return bool true = 允許提問，false = 已達上限。
     */
    private function check_daily_limit() {
        $limit = $this->get_daily_limit();

        if ($limit <= 0) {
            return true;
        }

        $count = $this->get_daily_count();

        return $count < $limit;
    }

    /**
     * 增加每日提問次數（成功回答後呼叫）。
     *
     * H2 修正：原本的 get_transient()→set_transient() 為非原子操作，
     * 高並發下可能多個請求讀到相同舊值、各自 +1，導致計數落後。
     * 此處改用「原子遞增」：
     *   1. 若站台有外部物件快取（Redis/Memcached），使用 wp_cache_incr()。
     *   2. 否則對 transient 在 options 表的儲存 row 直接以 SQL 原子 +1。
     * 兩種路徑都保留 transient 的自動過期特性，不會造成 wp_options 膨脹。
     *
     * @return void
     */
    private function increase_daily_count() {
        $limit = $this->get_daily_limit();

        if ($limit <= 0) {
            return;
        }

        $key = $this->get_daily_count_key();

        // 路徑 1：使用外部物件快取的原子遞增（Redis / Memcached）。
        if (wp_using_ext_object_cache()) {
            $this->increase_count_object_cache($key);
            return;
        }

        // 路徑 2：DB transient，使用 SQL 原子遞增 transient 的儲存值。
        $this->increase_count_db_transient($key);
    }

    /**
     * 以外部物件快取原子遞增每日計數。
     *
     * transient 在物件快取模式下儲存於 cache group 'transient'，
     * key 即 transient 名稱。使用 wp_cache_incr() 原子 +1；
     * 首次（key 不存在）時改用 set_transient 建立並設定過期。
     *
     * @param string $key transient key。
     * @return void
     */
    private function increase_count_object_cache($key) {
        $current = get_transient($key);

        if (false === $current) {
            // 首次建立，設定當日過期。
            set_transient($key, 1, $this->get_daily_count_ttl());
            return;
        }

        // 已存在，原子遞增。group 名稱 'transient' 為 WordPress 內部慣例。
        $incremented = wp_cache_incr($key, 1, 'transient');

        // 極少數情況下 incr 失敗（例如 key 剛過期），保守回退。
        if (false === $incremented) {
            set_transient($key, absint($current) + 1, $this->get_daily_count_ttl());
        }
    }

    /**
     * 以 DB transient 的 SQL 原子遞增每日計數。
     *
     * DB transient 實際儲存於 wp_options：
     *   option_name = '_transient_' . $key            （值）
     *   option_name = '_transient_timeout_' . $key     （過期 UNIX 時間）
     * 此方法：
     *   1. 確保 timeout row 存在（首次建立並設定當日過期）。
     *   2. 對值 row 執行 INSERT ... ON DUPLICATE KEY UPDATE +1（原子）。
     *
     * @param string $key transient key。
     * @return void
     */
    private function increase_count_db_transient($key) {
        global $wpdb;

        $value_option   = '_transient_' . $key;
        $timeout_option = '_transient_timeout_' . $key;

        // 先確保過期時間 row 存在。若已存在則不更動原有過期時間。
        $existing_timeout = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $timeout_option
            )
        );

        if (null === $existing_timeout) {
            $expire = time() + $this->get_daily_count_ttl();

            // autoload = no，避免進入 alloptions 快取。
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
                     VALUES (%s, %s, 'no')
                     ON DUPLICATE KEY UPDATE option_value = option_value",
                    $timeout_option,
                    (string) $expire
                )
            );
        }

        // 原子遞增值 row。
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
                 VALUES (%s, '1', 'no')
                 ON DUPLICATE KEY UPDATE option_value = option_value + 1",
                $value_option
            )
        );

        // 清掉可能存在的本地（非持久）快取，避免後續同請求讀到舊值。
        wp_cache_delete($key, 'transient');
    }

    /**
     * 每日計數過期秒數。
     *
     * 取「至當日結束」的剩餘秒數，最少保底 1 小時，
     * 確保跨日後計數自然失效，無需依賴外部排程。
     *
     * @return int
     */
    private function get_daily_count_ttl() {
        $now              = current_time('timestamp');
        $end_of_day       = strtotime('tomorrow midnight', $now) - 1;
        $seconds_to_reset = $end_of_day - $now;

        if ($seconds_to_reset < HOUR_IN_SECONDS) {
            return HOUR_IN_SECONDS;
        }

        return $seconds_to_reset;
    }

    /**
     * 取得每日提問限制。
     *
     * @return int
     */
    private function get_daily_limit() {
        if (!class_exists('UR_AI_Settings')) {
            return 0;
        }

        if (current_user_can('manage_options')) {
            return absint(UR_AI_Settings::get_daily_limit('admin'));
        }

        if (is_user_logged_in()) {
            return absint(UR_AI_Settings::get_daily_limit('member'));
        }

        return absint(UR_AI_Settings::get_daily_limit('guest'));
    }

    /**
     * 取得今日已使用次數。
     *
     * @return int
     */
    private function get_daily_count() {
        return absint(get_transient($this->get_daily_count_key()));
    }

    /**
     * 取得每日計數 transient key。
     *
     * @return string
     */
    private function get_daily_count_key() {
        $date = current_time('Ymd');

        if (is_user_logged_in()) {
            return 'ur_ai_daily_' . $date . '_user_' . get_current_user_id();
        }

        $ip = '';

        if (class_exists('UR_AI_Security')) {
            $ip = UR_AI_Security::get_user_ip();
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return 'ur_ai_daily_' . $date . '_guest_' . md5($ip);
    }

    /**
     * 計算字串長度。
     *
     * @param string $text 文字。
     * @return int
     */
    private function strlen($text) {
        $text = (string) $text;

        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }
}