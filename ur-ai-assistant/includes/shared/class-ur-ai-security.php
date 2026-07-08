<?php
/**
 * UR AI Assistant Security
 *
 * 外掛共用安全工具類別。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Security
 */
class UR_AI_Security {

    /**
     * 後台 nonce action.
     *
     * @var string
     */
    const ADMIN_NONCE_ACTION = 'ur_ai_assistant_admin_action';

    /**
     * 後台 nonce name.
     *
     * @var string
     */
    const ADMIN_NONCE_NAME = 'ur_ai_nonce';

    /**
     * 前台 nonce action.
     *
     * @var string
     */
    const PUBLIC_NONCE_ACTION = 'ur_ai_assistant_public_nonce';

    /**
     * 輸出後台表單 nonce 欄位。
     *
     * @return void
     */
    public static function admin_form_nonce_field() {
        wp_nonce_field(self::ADMIN_NONCE_ACTION, self::ADMIN_NONCE_NAME);
    }

    /**
     * 驗證後台表單 nonce，失敗則中止。
     *
     * @return void
     */
    public static function verify_admin_form_nonce_or_die() {
        check_admin_referer(self::ADMIN_NONCE_ACTION, self::ADMIN_NONCE_NAME);
    }

    /**
     * 驗證前台 AJAX nonce，失敗則回傳 JSON error。
     *
     * @return void
     */
    public static function ajax_verify_public_nonce_or_die() {
        $nonce = isset($_POST['nonce'])
            ? sanitize_text_field(wp_unslash($_POST['nonce']))
            : '';

        if (!wp_verify_nonce($nonce, self::PUBLIC_NONCE_ACTION)) {
            wp_send_json_error(
                array(
                    'message' => __('安全驗證失敗，請重新整理頁面後再試。', 'ur-ai-assistant'),
                ),
                403
            );
        }
    }

    /**
     * 驗證後台 AJAX nonce，失敗則回傳 JSON error。
     *
     * @return void
     */
    public static function ajax_verify_admin_nonce_or_die() {
        $nonce = isset($_POST['nonce'])
            ? sanitize_text_field(wp_unslash($_POST['nonce']))
            : '';

        if (!wp_verify_nonce($nonce, 'ur_ai_assistant_admin_nonce')) {
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
    public static function sanitize_question($question) {
        $question = is_scalar($question) ? (string) $question : '';
        $question = wp_strip_all_tags($question);
        $question = html_entity_decode($question, ENT_QUOTES, 'UTF-8');
        $question = sanitize_textarea_field($question);
        $question = preg_replace('/\s+/u', ' ', $question);

        return trim($question);
    }

    /**
     * 清理 textarea 文字。
     *
     * @param mixed $text 原始文字。
     * @return string
     */
    public static function sanitize_textarea($text) {
        $text = is_scalar($text) ? (string) $text : '';
        $text = wp_strip_all_tags($text);
        $text = sanitize_textarea_field($text);
        $text = preg_replace("/\r\n|\r|\n/u", "\n", $text);

        return trim($text);
    }

    /**
     * 清理一般文字。
     *
     * @param mixed $text 原始文字。
     * @return string
     */
    public static function sanitize_text($text) {
        $text = is_scalar($text) ? (string) $text : '';
        $text = wp_strip_all_tags($text);
        $text = sanitize_text_field($text);

        return trim($text);
    }

    /**
     * 清理分類名稱。
     *
     * @param mixed $category 分類。
     * @return string
     */
    public static function sanitize_category($category) {
        $category = self::sanitize_text($category);

        if ('' === $category) {
            return '待分類';
        }

        if (function_exists('mb_substr')) {
            $category = mb_substr($category, 0, 50, 'UTF-8');
        } else {
            $category = substr($category, 0, 50);
        }

        return $category;
    }

    /**
     * 清理關鍵字。
     *
     * @param mixed $keywords 關鍵字文字。
     * @return string
     */
    public static function sanitize_keywords($keywords) {
        $keywords = is_scalar($keywords) ? (string) $keywords : '';

        if ('' === trim($keywords)) {
            return '';
        }

        $keywords = wp_strip_all_tags($keywords);
        $keywords = str_replace(array('，', '、', ';', '；', '|'), ',', $keywords);
        $parts    = explode(',', $keywords);

        $clean = array();

        foreach ($parts as $part) {
            $part = sanitize_text_field(trim($part));

            if ('' !== $part) {
                $clean[] = $part;
            }
        }

        $clean = array_values(array_unique($clean));
        $clean = array_slice($clean, 0, 30);

        return implode(', ', $clean);
    }

    /**
     * 清理 URL。
     *
     * @param mixed $url URL。
     * @return string
     */
    public static function sanitize_url($url) {
        $url = is_scalar($url) ? (string) $url : '';
        $url = esc_url_raw(trim($url));

        return $url;
    }

    /**
     * 清理 ID 陣列。
     *
     * @param mixed $ids ID 陣列。
     * @return array
     */
    public static function sanitize_ids($ids) {
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);
        $ids = array_values(array_unique($ids));

        return $ids;
    }

    /**
     * 清理狀態值。
     *
     * @param mixed  $status 狀態。
     * @param array  $allowed 允許值。
     * @param string $default 預設值。
     * @return string
     */
    public static function sanitize_status($status, $allowed = array(), $default = '') {
        $status = sanitize_key((string) $status);

        if (empty($allowed)) {
            return $status;
        }

        return in_array($status, $allowed, true) ? $status : $default;
    }

    /**
     * 清理日期 YYYY-MM-DD。
     *
     * @param mixed $date 日期。
     * @return string
     */
    public static function sanitize_date($date) {
        $date = sanitize_text_field((string) $date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return '';
    }

    /**
     * 清理排序方向。
     *
     * @param mixed  $order 排序方向。
     * @param string $default 預設值。
     * @return string
     */
    public static function sanitize_order($order, $default = 'DESC') {
        $order = strtoupper(sanitize_key((string) $order));

        if (!in_array($order, array('ASC', 'DESC'), true)) {
            return strtoupper($default) === 'ASC' ? 'ASC' : 'DESC';
        }

        return $order;
    }

    /**
     * 限制整數範圍。
     *
     * @param mixed $value 原始值。
     * @param int   $min 最小值。
     * @param int   $max 最大值。
     * @param int   $default 預設值。
     * @return int
     */
    public static function int_range($value, $min, $max, $default = 0) {
        $value = absint($value);

        if ($value < $min) {
            return absint($default);
        }

        if ($value > $max) {
            return absint($max);
        }

        return $value;
    }

    /**
     * 取得使用者真實 IP。
     *
     * 安全邏輯：
     * 1. 先取得直連 IP（REMOTE_ADDR），這是唯一無法偽造的來源。
     * 2. 只有當直連 IP 屬於可信代理（Cloudflare、內部反向代理）時，
     *    才信任其轉發標頭（CF-Connecting-IP、X-Forwarded-For）。
     * 3. 轉發標頭中的 IP 必須通過驗證：為合法公網 IP，不可為私有或保留位址。
     * 4. 若任何條件不符，回退至 REMOTE_ADDR。
     *
     * 可透過 filter 自訂信任代理清單：
     *   add_filter( 'ur_ai_trusted_proxy_cidrs', function( $cidrs ) {
     *       $cidrs[] = '10.0.0.0/8'; // 自訂內部代理
     *       return $cidrs;
     *   } );
     *
     * @return string
     */
    public static function get_user_ip() {
        $remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : '';

        if ( '' === $remote_addr || ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
            return '';
        }

        // 若直連 IP 屬於可信代理，才嘗試讀取轉發標頭
        if ( self::is_trusted_proxy( $remote_addr ) ) {
            // Cloudflare: CF-Connecting-IP 永遠只含單一 IP
            if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
                $cf_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
                $cf_ip = trim( $cf_ip );

                if ( self::is_valid_public_ip( $cf_ip ) ) {
                    return $cf_ip;
                }
            }

            // 一般反向代理: X-Forwarded-For（取第一個，即最原始的來源）
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                $forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
                $parts     = explode( ',', $forwarded );
                $candidate = trim( $parts[0] );

                if ( self::is_valid_public_ip( $candidate ) ) {
                    return $candidate;
                }
            }
        }

        // 回退：直接使用 REMOTE_ADDR
        return $remote_addr;
    }

    /**
     * 判斷 IP 是否屬於可信代理範圍。
     *
     * 預設信任 Cloudflare 公佈的 IP 段（IPv4）與本機環回。
     * 可透過 filter 'ur_ai_trusted_proxy_cidrs' 新增自訂範圍。
     *
     * @param string $ip 直連 IP。
     * @return bool
     */
    private static function is_trusted_proxy( $ip ) {
        /**
         * Cloudflare IPv4 ranges (https://www.cloudflare.com/ips-v4/)
         * 以及常見私有網段（本機反向代理）。
         */
        $default_cidrs = array(
            // Cloudflare
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            // 本機環回
            '127.0.0.0/8',
            // 私有網段（本機反向代理如 Nginx/Apache）
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        );

        /**
         * Filter: 允許自訂信任代理 CIDR 清單。
         *
         * @param string[] $cidrs CIDR 格式字串陣列。
         */
        $trusted_cidrs = apply_filters( 'ur_ai_trusted_proxy_cidrs', $default_cidrs );

        foreach ( $trusted_cidrs as $cidr ) {
            if ( self::ip_in_cidr( $ip, $cidr ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判斷 IP 是否為合法的公網 IP（非私有、非保留位址）。
     *
     * @param string $ip IP 位址。
     * @return bool
     */
    private static function is_valid_public_ip( $ip ) {
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return false;
        }

        // 排除私有、保留、環回、多播等非公網位址
        $is_public = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        return false !== $is_public;
    }

    /**
     * 判斷 IP 是否屬於指定 CIDR 範圍。
     *
     * 支援 IPv4。CIDR 格式：'192.168.0.0/16'。
     * 若 CIDR 不含斜線，視為單一 IP 完全比對。
     *
     * @param string $ip   待判斷 IP。
     * @param string $cidr CIDR 範圍。
     * @return bool
     */
    private static function ip_in_cidr( $ip, $cidr ) {
        if ( false === strpos( $cidr, '/' ) ) {
            return $ip === $cidr;
        }

        list( $subnet, $prefix_len ) = explode( '/', $cidr, 2 );

        $prefix_len = absint( $prefix_len );

        $ip_long     = ip2long( $ip );
        $subnet_long = ip2long( $subnet );

        if ( false === $ip_long || false === $subnet_long ) {
            return false;
        }

        if ( $prefix_len === 0 ) {
            return true;
        }

        if ( $prefix_len > 32 ) {
            return false;
        }

        $mask = ~( ( 1 << ( 32 - $prefix_len ) ) - 1 );

        return ( $ip_long & $mask ) === ( $subnet_long & $mask );
    }

    /**
     * 取得安全的 referer URL。
     *
     * @param string $fallback fallback URL。
     * @return string
     */
    public static function safe_referer($fallback = '') {
        $fallback = $fallback ? esc_url_raw($fallback) : admin_url();

        $referer = wp_get_referer();

        if (!$referer) {
            return $fallback;
        }

        return esc_url_raw($referer);
    }

    /**
     * 判斷是否為安全的布林值。
     *
     * @param mixed $value 原始值。
     * @return bool
     */
    public static function truthy($value) {
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