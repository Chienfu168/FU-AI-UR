<?php
/**
 * UR AI Assistant Settings
 *
 * 外掛共用設定管理類別。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Settings
 */
class UR_AI_Settings {

    /**
     * Option name.
     *
     * @var string
     */
    const OPTION_NAME = 'ur_ai_assistant_settings';

    /**
     * 取得全部設定。
     *
     * @return array
     */
    public static function get_all() {
        $settings = get_option(self::OPTION_NAME, array());

        if (!is_array($settings)) {
            $settings = array();
        }

        return wp_parse_args($settings, self::defaults());
    }

    /**
     * 取得單一設定。
     *
     * @param string $key 設定鍵。
     * @param mixed  $default 預設值。
     * @return mixed
     */
    public static function get($key, $default = null) {
        $settings = self::get_all();

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return $default;
    }

    /**
     * 更新單一設定。
     *
     * @param string $key 設定鍵。
     * @param mixed  $value 設定值。
     * @return bool
     */
    public static function update($key, $value) {
        $settings       = self::get_all();
        $settings[$key] = self::sanitize_value($key, $value);

        return update_option(self::OPTION_NAME, $settings);
    }

    /**
     * 批次更新設定。
     *
     * @param array $values 設定值。
     * @return bool
     */
    public static function update_many($values) {
        if (!is_array($values)) {
            return false;
        }

        $settings = self::get_all();

        foreach ($values as $key => $value) {
            $key = sanitize_key($key);

            if ('' === $key) {
                continue;
            }

            $settings[$key] = self::sanitize_value($key, $value);
        }

        $settings = wp_parse_args($settings, self::defaults());

        return update_option(self::OPTION_NAME, $settings);
    }

    /**
     * 重設為預設值。
     *
     * @return bool
     */
    public static function reset() {
        return update_option(self::OPTION_NAME, self::defaults());
    }

    /**
     * 初始化預設設定。
     *
     * @return void
     */
    public static function maybe_install_defaults() {
        $existing = get_option(self::OPTION_NAME, null);

        if (null === $existing || false === $existing) {
            add_option(self::OPTION_NAME, self::defaults());
        }
    }

    /**
     * 取得 API Key。
     *
     * @return string
     */
    public static function get_api_key() {
        return trim((string) self::get('api_key', ''));
    }

    /**
     * 取得模型。
     *
     * @return string
     */
    public static function get_model() {
        $model = trim((string) self::get('model', 'gpt-4o-mini'));

        return '' !== $model ? $model : 'gpt-4o-mini';
    }

    /**
     * 取得 temperature.
     *
     * @return float
     */
    public static function get_temperature() {
        $temperature = (float) self::get('temperature', 0.3);

        if ($temperature < 0) {
            $temperature = 0;
        }

        if ($temperature > 2) {
            $temperature = 2;
        }

        return $temperature;
    }

    /**
     * 取得最大回答 token。
     *
     * @return int
     */
    public static function get_max_answer_tokens() {
        $tokens = absint(self::get('max_answer_tokens', 1200));

        if ($tokens <= 0) {
            $tokens = 1200;
        }

        if ($tokens > 4000) {
            $tokens = 4000;
        }

        return $tokens;
    }

    /**
     * 取得 system prompt.
     *
     * @return string
     */
    public static function get_system_prompt() {
        $prompt = (string) self::get('system_prompt', '');

        if ('' === trim($prompt)) {
            return self::default_system_prompt();
        }

        return $prompt;
    }

    /**
     * 取得前台標題（留空時退回目前啟用中產業別的預設值）。
     *
     * @return string
     */
    public static function get_frontend_title() {
        $title = (string) self::get('frontend_title', '');

        if ('' === trim($title)) {
            return self::get_profile_assistant_default('frontend_title', '都更 AI 助理');
        }

        return $title;
    }

    /**
     * 取得前台副標題（留空時退回目前啟用中產業別的預設值）。
     *
     * @return string
     */
    public static function get_frontend_subtitle() {
        $subtitle = (string) self::get('frontend_subtitle', '');

        if ('' === trim($subtitle)) {
            return self::get_profile_assistant_default('frontend_subtitle', '');
        }

        return $subtitle;
    }

    /**
     * 取得目前啟用中的產業別 key。
     *
     * @return string
     */
    public static function get_industry() {
        $industry = sanitize_key((string) self::get('industry', UR_AI_Industry_Profiles::DEFAULT_INDUSTRY));

        if (class_exists('UR_AI_Industry_Profiles') && !UR_AI_Industry_Profiles::is_valid($industry)) {
            return UR_AI_Industry_Profiles::DEFAULT_INDUSTRY;
        }

        return $industry;
    }

    /**
     * 取得最大問題字數。
     *
     * @return int
     */
    public static function get_max_question_length() {
        $length = absint(self::get('max_question_length', 500));

        if ($length <= 0) {
            $length = 500;
        }

        if ($length > 2000) {
            $length = 2000;
        }

        return $length;
    }

    /**
     * 取得每日限制。
     *
     * @param string $role guest/member/admin.
     * @return int
     */
    public static function get_daily_limit($role = 'guest') {
        $role = sanitize_key($role);

        $map = array(
            'guest'  => 'guest_daily_limit',
            'member' => 'member_daily_limit',
            'admin'  => 'admin_daily_limit',
        );

        $key = isset($map[$role]) ? $map[$role] : 'guest_daily_limit';

        return absint(self::get($key, 0));
    }

    /**
     * 前台是否啟用。
     *
     * @return bool
     */
    public static function is_frontend_enabled() {
        return self::truthy(self::get('frontend_enabled', 1));
    }

    /**
     * FAQ 是否啟用。
     *
     * @return bool
     */
    public static function is_faq_enabled() {
        return self::truthy(self::get('faq_enabled', 1));
    }

    /**
     * 相關頁面推薦是否啟用。
     *
     * @return bool
     */
    public static function is_related_enabled() {
        return self::truthy(self::get('related_enabled', 1));
    }

    /**
     * 熱門問題是否啟用。
     *
     * @return bool
     */
    public static function is_popular_enabled() {
        return self::truthy(self::get('popular_enabled', 1));
    }

    /**
     * 知識庫瀏覽（獨立搜尋 FAQ，不經比對／AI）是否啟用。
     *
     * 預設關閉：這是既有安裝升級後新增的前台區塊，避免既有網站在更新後
     * 未經確認就多出新的可見 UI。
     *
     * @return bool
     */
    public static function is_kb_browse_enabled() {
        return self::truthy(self::get('kb_browse_enabled', 0));
    }

    /**
     * 知識庫瀏覽每頁筆數。
     *
     * @return int
     */
    public static function get_kb_browse_per_page() {
        $per_page = absint(self::get('kb_browse_per_page', 10));

        if ($per_page <= 0) {
            $per_page = 10;
        }

        if ($per_page > 50) {
            $per_page = 50;
        }

        return $per_page;
    }

    /**
     * 問答紀錄是否啟用。
     *
     * @return bool
     */
    public static function is_logging_enabled() {
        return self::truthy(self::get('logging_enabled', 1));
    }

    /**
     * 每百萬 tokens 的預估費用（美元）。
     *
     * 這是站方自行填寫的粗估數字（依所用模型的定價換算），非 OpenAI
     * 官方即時匯率或帳單金額，僅供後台成本估算參考。
     *
     * @return float
     */
    public static function get_cost_per_million_tokens() {
        $rate = (float) self::get('cost_per_million_tokens', 0.5);

        if ($rate < 0) {
            $rate = 0;
        }

        if ($rate > 1000) {
            $rate = 1000;
        }

        return $rate;
    }

    /**
     * 「AI 對話」產生總結草稿時，每則草稿「固定回答」的最低字數門檻。
     *
     * 不同網站經營者對「怎樣算太精簡」的標準可能不同，因此開放後台
     * 調整，而不是寫死在程式碼裡；預設值 60 字沿用這個防護機制剛上線
     * 時依實際 FAQ 內容包長度估算的水準。
     *
     * @return int
     */
    public static function get_admin_chat_min_draft_answer_length() {
        $length = absint(self::get('admin_chat_min_draft_answer_length', 60));

        if ($length < 20) {
            $length = 20;
        }

        if ($length > 500) {
            $length = 500;
        }

        return $length;
    }

    /**
     * 預設設定。
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'api_key'             => '',
            'model'               => 'gpt-4o-mini',
            'temperature'         => 0.3,
            'max_answer_tokens'   => 1200,
            'system_prompt'       => self::default_system_prompt(),

            // 目前唯一支援的產業別；詳見 UR_AI_Industry_Profiles 與
            // docs/industry-expansion-architecture.md。
            'industry'            => class_exists('UR_AI_Industry_Profiles') ? UR_AI_Industry_Profiles::DEFAULT_INDUSTRY : 'urban_renewal',

            'frontend_enabled'    => 1,
            'frontend_title'      => self::get_profile_assistant_default('frontend_title', '都更危老 AI 助理'),
            'frontend_subtitle'   => self::get_profile_assistant_default('frontend_subtitle', ''),
            'disclaimer'          => '本工具提供一般資訊參考，不構成法律、估價、建築、稅務或個案決策建議。若涉及個案權利、契約、訴訟、登記或稅務問題，建議洽詢相關專業人士。',

            'max_question_length' => 500,
            'guest_daily_limit'   => 20,
            'member_daily_limit'  => 50,
            'admin_daily_limit'   => 0,

            'faq_enabled'         => 1,
            'related_enabled'     => 1,
            'popular_enabled'     => 1,
            'logging_enabled'     => 1,
            'kb_browse_enabled'   => 0,
            'kb_browse_per_page'  => 10,

            'cost_per_million_tokens' => 0.5,

            'admin_chat_min_draft_answer_length' => 60,
        );
    }

    /**
     * 預設 system prompt.
     *
     * 內容實際來源為目前啟用中的產業別設定檔（見 UR_AI_Industry_Profiles），
     * 這裡只留一段極簡的最後防線文字，供 Industry Profiles 類別本身
     * 意外無法載入時使用（不應發生於正常運作情境）。
     *
     * @return string
     */
    public static function default_system_prompt() {
        $prompt = self::get_profile_assistant_default('system_prompt', '');

        if ('' !== $prompt) {
            return $prompt;
        }

        return '你是 AI 助理，請以客觀、中立、清楚的態度提供一般性資訊參考，不做個案專業判斷。';
    }

    /**
     * 從目前啟用中的產業別設定檔取得 assistant 相關預設值。
     *
     * @param string $field assistant 設定欄位名稱（system_prompt／frontend_title／frontend_subtitle）。
     * @param string $fallback 取不到時的備援值。
     * @return string
     */
    private static function get_profile_assistant_default($field, $fallback = '') {
        if (!class_exists('UR_AI_Industry_Profiles')) {
            return $fallback;
        }

        $profile = UR_AI_Industry_Profiles::get_active();

        if (is_array($profile) && !empty($profile['assistant'][$field])) {
            return (string) $profile['assistant'][$field];
        }

        return $fallback;
    }

    /**
     * 清理設定值。
     *
     * @param string $key 設定鍵。
     * @param mixed  $value 設定值。
     * @return mixed
     */
    private static function sanitize_value($key, $value) {
        $key = sanitize_key($key);

        switch ($key) {
            case 'api_key':
                return sanitize_text_field((string) $value);

            case 'model':
                return sanitize_text_field((string) $value);

            case 'temperature':
                $temperature = (float) $value;

                if ($temperature < 0) {
                    $temperature = 0;
                }

                if ($temperature > 2) {
                    $temperature = 2;
                }

                return $temperature;

            case 'max_answer_tokens':
                $tokens = absint($value);

                if ($tokens < 100) {
                    $tokens = 100;
                }

                if ($tokens > 4000) {
                    $tokens = 4000;
                }

                return $tokens;

            case 'system_prompt':
            case 'frontend_subtitle':
            case 'disclaimer':
                return sanitize_textarea_field((string) $value);

            case 'frontend_title':
                return sanitize_text_field((string) $value);

            case 'industry':
                $industry = sanitize_key((string) $value);

                if (class_exists('UR_AI_Industry_Profiles') && UR_AI_Industry_Profiles::is_valid($industry)) {
                    return $industry;
                }

                return class_exists('UR_AI_Industry_Profiles') ? UR_AI_Industry_Profiles::DEFAULT_INDUSTRY : 'urban_renewal';

            case 'frontend_enabled':
            case 'faq_enabled':
            case 'related_enabled':
            case 'popular_enabled':
            case 'logging_enabled':
            case 'kb_browse_enabled':
                return !empty($value) ? 1 : 0;

            case 'kb_browse_per_page':
                $per_page = absint($value);

                if ($per_page < 1) {
                    $per_page = 1;
                }

                if ($per_page > 50) {
                    $per_page = 50;
                }

                return $per_page;

            case 'max_question_length':
                $length = absint($value);

                if ($length < 20) {
                    $length = 20;
                }

                if ($length > 2000) {
                    $length = 2000;
                }

                return $length;

            case 'guest_daily_limit':
            case 'member_daily_limit':
            case 'admin_daily_limit':
                $limit = absint($value);

                if ($limit > 9999) {
                    $limit = 9999;
                }

                return $limit;

            case 'cost_per_million_tokens':
                $rate = (float) $value;

                if ($rate < 0) {
                    $rate = 0;
                }

                if ($rate > 1000) {
                    $rate = 1000;
                }

                return $rate;

            case 'admin_chat_min_draft_answer_length':
                $length = absint($value);

                if ($length < 20) {
                    $length = 20;
                }

                if ($length > 500) {
                    $length = 500;
                }

                return $length;

            default:
                if (is_scalar($value)) {
                    return sanitize_text_field((string) $value);
                }

                return '';
        }
    }

    /**
     * 判斷布林設定。
     *
     * @param mixed $value 原始值。
     * @return bool
     */
    private static function truthy($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, array('1', 'true', 'yes', 'on'), true);
    }
}