<?php
/**
 * UR AI Gateway Rate Limiter
 *
 * 端點層的「每 IP 每分鐘」請求節流，刻意放在授權碼查詢「之前」執行：
 * 目的是擋掉「大量未帶有效授權碼的洪水請求」——那類請求若每次都走到
 * 資料庫查授權碼，會平白消耗 DB 與主機資源。先用一個極輕量的計數（WP
 * transient，有物件快取時走快取、否則走 wp_options）把同一 IP 的每分鐘
 * 請求數壓在上限內，超過就直接回 429，不進入後續任何 DB／OpenAI 流程。
 *
 * 設計取捨：
 * - 節流只要「夠近似」即可（擋濫用），不像每日計費額度需要精確，因此
 *   這裡用簡單的讀→加→寫 transient，不追求併發下的絕對精確。
 * - 預設上限刻意寬鬆（見設定），且每組授權碼本來就有各自的每日額度，
 *   正常客戶幾乎不可能撞到這個每分鐘上限；它主要是對付匿名洪水。
 * - 預設以 REMOTE_ADDR 判斷來源 IP。若服務站在反向代理／CDN 之後，
 *   可於 wp-config.php 定義 UR_AI_GATEWAY_CLIENT_IP_HEADER（例如
 *   'X-Forwarded-For' 或 'CF-Connecting-IP'）指定要信任的標頭；未定義
 *   時不讀任何可被偽造的標頭。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Rate_Limiter
 */
class UR_AI_Gateway_Rate_Limiter {

    /**
     * transient 鍵前綴。
     *
     * @var string
     */
    const PREFIX = 'ur_ai_gw_rl_';

    /**
     * 檢查某 IP 本分鐘是否還在上限內，並累加一次。
     *
     * @param string $ip      來源 IP。
     * @param int    $per_min 每分鐘上限（<=0 代表停用節流）。
     * @return bool true＝允許（已累加一次）；false＝已超過上限。
     */
    public static function check($ip, $per_min) {
        $per_min = (int) $per_min;

        // 0（或負）＝停用節流。
        if ($per_min <= 0) {
            return true;
        }

        $ip = (string) $ip;

        // 無法辨識來源 IP 時不阻擋（避免誤殺），交由後續授權碼驗證把關。
        if ('' === $ip) {
            return true;
        }

        $bucket = (int) floor(time() / 60);
        $key    = self::PREFIX . md5($ip) . '_' . $bucket;

        $count = (int) get_transient($key);

        if ($count >= $per_min) {
            return false;
        }

        // 存活 120 秒即可（時間桶本身只有 60 秒，多留一點容錯）。
        set_transient($key, $count + 1, 120);

        return true;
    }

    /**
     * 取得來源 IP（預設 REMOTE_ADDR；可由 wp-config 常數指定信任標頭）。
     *
     * @return string 合法 IP 字串，取不到則空字串。
     */
    public static function get_client_ip() {
        if (defined('UR_AI_GATEWAY_CLIENT_IP_HEADER') && '' !== trim((string) UR_AI_GATEWAY_CLIENT_IP_HEADER)) {
            $header     = trim((string) UR_AI_GATEWAY_CLIENT_IP_HEADER);
            $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $header));

            if (!empty($_SERVER[$server_key])) {
                // X-Forwarded-For 可能是「client, proxy1, proxy2」清單，取第一個。
                $parts = explode(',', (string) wp_unslash($_SERVER[$server_key]));
                $ip    = trim($parts[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string) wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
    }
}
