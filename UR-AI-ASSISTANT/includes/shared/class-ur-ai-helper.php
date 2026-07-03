<?php
/**
 * UR AI Assistant Helper
 *
 * 外掛共用輔助工具類別。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Helper
 */
class UR_AI_Helper {

    /**
     * 安全取得陣列或物件資料。
     *
     * @param mixed  $data 資料來源。
     * @param string $key 鍵名。
     * @param mixed  $default 預設值。
     * @return mixed
     */
    public static function data_get($data, $key, $default = null) {
        if (is_array($data) && array_key_exists($key, $data)) {
            return $data[$key];
        }

        if (is_object($data) && isset($data->{$key})) {
            return $data->{$key};
        }

        return $default;
    }

    /**
     * 安全取得巢狀資料。
     *
     * @param mixed  $data 資料來源。
     * @param string $path 路徑，例如 user.name。
     * @param mixed  $default 預設值。
     * @return mixed
     */
    public static function data_get_path($data, $path, $default = null) {
        if (!is_string($path) || '' === trim($path)) {
            return $default;
        }

        $segments = explode('.', $path);
        $current  = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            if (is_object($current) && isset($current->{$segment})) {
                $current = $current->{$segment};
                continue;
            }

            return $default;
        }

        return $current;
    }

    /**
     * 回答來源標籤。
     *
     * @param string $source 回答來源。
     * @return string
     */
    public static function answer_source_label($source) {
        $source = sanitize_key($source);

        $labels = array(
            'faq'   => __('FAQ 知識庫回答', 'ur-ai-assistant'),
            'ai'    => __('AI 回答', 'ur-ai-assistant'),
            'error' => __('錯誤', 'ur-ai-assistant'),
        );

        return isset($labels[$source]) ? $labels[$source] : $source;
    }

    /**
     * 回答來源 badge class。
     *
     * @param string $source 回答來源。
     * @return string
     */
    public static function answer_source_badge_class($source) {
        $source = sanitize_key($source);

        $map = array(
            'faq'   => 'faq',
            'ai'    => 'ai',
            'error' => 'error',
        );

        return isset($map[$source]) ? $map[$source] : 'default';
    }

    /**
     * 回饋標籤。
     *
     * @param string $feedback 回饋值。
     * @return string
     */
    public static function feedback_label($feedback) {
        $feedback = sanitize_key($feedback);

        $labels = array(
            'helpful'     => __('有幫助', 'ur-ai-assistant'),
            'not_helpful' => __('沒幫助', 'ur-ai-assistant'),
            ''            => __('未回饋', 'ur-ai-assistant'),
        );

        return isset($labels[$feedback]) ? $labels[$feedback] : __('未回饋', 'ur-ai-assistant');
    }

    /**
     * 狀態標籤。
     *
     * @param string $status 狀態值。
     * @return string
     */
    public static function status_label($status) {
        $status = sanitize_key($status);

        $labels = array(
            'active'      => __('啟用', 'ur-ai-assistant'),
            'inactive'    => __('停用', 'ur-ai-assistant'),
            'success'     => __('成功', 'ur-ai-assistant'),
            'error'       => __('錯誤', 'ur-ai-assistant'),
            'draft'       => __('草稿', 'ur-ai-assistant'),
            'pending'     => __('待審核', 'ur-ai-assistant'),
            'approved'    => __('已確認', 'ur-ai-assistant'),
            'rejected'    => __('不採用', 'ur-ai-assistant'),
            'manual'      => __('手動建立', 'ur-ai-assistant'),
            'ai_log'      => __('AI 問答轉入', 'ur-ai-assistant'),
            'import'      => __('匯入建立', 'ur-ai-assistant'),
            'wp_post'     => __('WordPress 文章', 'ur-ai-assistant'),
            'wp_page'     => __('WordPress 頁面', 'ur-ai-assistant'),
            'faq'         => __('FAQ 匯入', 'ur-ai-assistant'),
        );

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * 建立後台 badge HTML。
     *
     * @param string $label 文字。
     * @param string $type 類型。
     * @return string
     */
    public static function admin_badge($label, $type = 'default') {
        $type = sanitize_html_class($type);

        if ('' === $type) {
            $type = 'default';
        }

        return sprintf(
            '<span class="ur-ai-badge ur-ai-badge-%1$s">%2$s</span>',
            esc_attr($type),
            esc_html($label)
        );
    }

    /**
     * 布林值 badge。
     *
     * @param bool   $value 布林值。
     * @param string $true_label true 標籤。
     * @param string $false_label false 標籤。
     * @return string
     */
    public static function boolean_badge($value, $true_label = '', $false_label = '') {
        $true_label  = $true_label ? $true_label : __('是', 'ur-ai-assistant');
        $false_label = $false_label ? $false_label : __('否', 'ur-ai-assistant');

        return $value
            ? self::admin_badge($true_label, 'success')
            : self::admin_badge($false_label, 'default');
    }

    /**
     * 格式化百分比。
     *
     * @param int|float $part 分子。
     * @param int|float $total 分母。
     * @param int       $precision 小數位數。
     * @return float
     */
    public static function percent($part, $total, $precision = 1) {
        $part      = (float) $part;
        $total     = (float) $total;
        $precision = absint($precision);

        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, $precision);
    }

    /**
     * 格式化百分比。
     *
     * 相容舊版呼叫：
     * UR_AI_Helper::percentage()
     *
     * @param int|float $part 分子。
     * @param int|float $total 分母。
     * @param int       $precision 小數位數。
     * @return float
     */
    public static function percentage($part, $total, $precision = 1) {
        return self::percent($part, $total, $precision);
    }

    /**
     * 格式化 CTR。
     *
     * @param int $clicks 點擊數。
     * @param int $shows 曝光數。
     * @return float
     */
    public static function ctr($clicks, $shows) {
        return self::percent(absint($clicks), absint($shows), 1);
    }

    /**
     * 格式化數字。
     *
     * @param int|float $number 數字。
     * @return string
     */
    public static function number($number) {
        return number_format_i18n((float) $number);
    }

    /**
     * 格式化日期時間。
     *
     * @param string $datetime 日期時間。
     * @return string
     */
    public static function datetime($datetime) {
        if ('' === trim((string) $datetime)) {
            return '';
        }

        $timestamp = strtotime((string) $datetime);

        if (!$timestamp) {
            return (string) $datetime;
        }

        return date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
    }

    /**
     * 截斷文字。
     *
     * 給 Related Pages / Post Search 等模組使用。
     *
     * @param string $text 文字。
     * @param int    $length 長度。
     * @param string $suffix 後綴。
     * @return string
     */
    public static function truncate($text, $length = 120, $suffix = '...') {
        $text   = wp_strip_all_tags((string) $text);
        $text   = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text   = preg_replace('/\s+/u', ' ', $text);
        $text   = trim($text);
        $length = absint($length);

        if ($length <= 0) {
            $length = 120;
        }

        if (self::strlen($text) <= $length) {
            return $text;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
        }

        return substr($text, 0, $length) . $suffix;
    }

    /**
     * 將關鍵字字串轉成陣列。
     *
     * 支援逗號、頓號、中文逗號、分號、直線等分隔。
     *
     * @param string|array $keywords 關鍵字。
     * @return array
     */
    public static function keywords_to_array($keywords) {
        if (is_array($keywords)) {
            $items = $keywords;
        } else {
            $keywords = (string) $keywords;

            if ('' === trim($keywords)) {
                return array();
            }

            $keywords = str_replace(array('，', '、', ';', '；', '|'), ',', $keywords);
            $items    = explode(',', $keywords);
        }

        $clean = array();

        foreach ($items as $item) {
            $item = sanitize_text_field(trim((string) $item));

            if ('' !== $item) {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * 將關鍵字陣列轉成字串。
     *
     * @param array|string $keywords 關鍵字。
     * @return string
     */
    public static function keywords_to_string($keywords) {
        return implode(', ', self::keywords_to_array($keywords));
    }

    /**
     * 判斷資料是否有有效內容。
     *
     * @param mixed $value 原始值。
     * @return bool
     */
    public static function filled($value) {
        if (is_array($value)) {
            return !empty($value);
        }

        return '' !== trim((string) $value);
    }

    /**
     * 將 ID 字串轉陣列。
     *
     * @param string|array $ids ID 字串或陣列。
     * @return array
     */
    public static function ids_to_array($ids) {
        if (is_array($ids)) {
            $items = $ids;
        } else {
            $ids   = (string) $ids;
            $items = explode(',', $ids);
        }

        $items = array_map('absint', $items);
        $items = array_filter($items);
        $items = array_values(array_unique($items));

        return $items;
    }

    /**
     * 將陣列 ID 轉字串。
     *
     * @param array $ids ID 陣列。
     * @return string
     */
    public static function ids_to_string($ids) {
        $ids = self::ids_to_array($ids);

        return implode(',', $ids);
    }

    /**
     * 陣列只保留指定 keys。
     *
     * @param array $data 原始陣列。
     * @param array $keys 允許 keys。
     * @return array
     */
    public static function only($data, $keys) {
        if (!is_array($data) || !is_array($keys)) {
            return array();
        }

        $result = array();

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $result[$key] = $data[$key];
            }
        }

        return $result;
    }

    /**
     * 陣列排除指定 keys。
     *
     * @param array $data 原始陣列。
     * @param array $keys 排除 keys。
     * @return array
     */
    public static function except($data, $keys) {
        if (!is_array($data) || !is_array($keys)) {
            return array();
        }

        foreach ($keys as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * 建立管理頁 URL。
     *
     * @param string $page 頁面 slug。
     * @param array  $args 額外參數。
     * @return string
     */
    public static function admin_url($page, $args = array()) {
        $args = is_array($args) ? $args : array();

        $args['page'] = sanitize_key($page);

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * 建立目前頁面的清除篩選 URL。
     *
     * @param string $page 頁面 slug。
     * @return string
     */
    public static function clear_filter_url($page) {
        return self::admin_url($page);
    }

    /**
     * 取得外掛版本。
     *
     * @return string
     */
    public static function version() {
        return defined('UR_AI_ASSISTANT_VERSION')
            ? UR_AI_ASSISTANT_VERSION
            : '0.0.0';
    }

    /**
     * 取得外掛根目錄。
     *
     * @return string
     */
    public static function plugin_dir() {
        return defined('UR_AI_ASSISTANT_PLUGIN_DIR')
            ? UR_AI_ASSISTANT_PLUGIN_DIR
            : plugin_dir_path(dirname(__DIR__));
    }

    /**
     * 取得外掛 URL。
     *
     * @return string
     */
    public static function plugin_url() {
        return defined('UR_AI_ASSISTANT_PLUGIN_URL')
            ? UR_AI_ASSISTANT_PLUGIN_URL
            : plugin_dir_url(dirname(__DIR__));
    }

    /**
     * 字串長度。
     *
     * @param string $text 文字。
     * @return int
     */
    private static function strlen($text) {
        $text = (string) $text;

        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }

    /**
     * 原子遞增一個以 transient 儲存的計數器，回傳遞增後的新值。
     *
     * 一般的 get_transient()→set_transient() 屬於「先讀後寫」的非原子操作，
     * 高並發下多個請求可能讀到相同舊值、各自 +1，導致計數失準或被繞過限制。
     * 此方法改用：
     *   1. 若站台有外部物件快取（Redis/Memcached），使用 wp_cache_incr()。
     *   2. 否則對 transient 在 options 表的儲存 row，以 SQL 原子 +1
     *      （並透過 LAST_INSERT_ID() 技巧一併取回新值，避免額外查詢造成的競態）。
     * 兩種路徑都保留 transient 的自動過期特性。
     *
     * @param string $key transient key（不含 _transient_ 前綴）。
     * @param int    $ttl 過期秒數（僅在計數器首次建立時套用）。
     * @return int 遞增後的新計數值。
     */
    public static function atomic_increment_transient($key, $ttl) {
        if (wp_using_ext_object_cache()) {
            $current = get_transient($key);

            if (false === $current) {
                set_transient($key, 1, $ttl);
                return 1;
            }

            $incremented = wp_cache_incr($key, 1, 'transient');

            if (false === $incremented) {
                $incremented = absint($current) + 1;
                set_transient($key, $incremented, $ttl);
            }

            return (int) $incremented;
        }

        global $wpdb;

        $value_option   = '_transient_' . $key;
        $timeout_option = '_transient_timeout_' . $key;

        $existing_timeout = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $timeout_option
            )
        );

        if (null === $existing_timeout) {
            $expire = time() + (int) $ttl;

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

        // 先確保值 row 存在（不存在才建立，值不變動），
        // 讓下一步的 UPDATE 分支必定被觸發，LAST_INSERT_ID() 才能可靠帶回新值；
        // 若省略這步，遇到「真正首次 INSERT」時 insert_id 會是 option_id 而非計數值。
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
                 VALUES (%s, '0', 'no')
                 ON DUPLICATE KEY UPDATE option_value = option_value",
                $value_option
            )
        );

        // LAST_INSERT_ID(expr) 讓 $wpdb->insert_id 直接帶回遞增後的新值，
        // 免去「寫入後再 SELECT 一次」在兩者之間又留下的競態窗口。
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
                 VALUES (%s, '1', 'no')
                 ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(option_value + 1)",
                $value_option
            )
        );

        $new_value = (int) $wpdb->insert_id;

        wp_cache_delete($key, 'transient');

        return $new_value;
    }
}