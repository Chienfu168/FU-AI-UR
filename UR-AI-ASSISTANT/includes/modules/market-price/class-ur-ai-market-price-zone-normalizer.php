<?php
/**
 * UR AI Assistant Market Price Zone Normalizer
 *
 * 把實價登錄原始「都市土地使用分區」自由文字，正規化成跟計算機模組
 * 既有分區分類一致的簡短分類（住二、住三、商一...），方便未來跨模組
 * 使用同一把 key 對照。
 *
 * 原始文字寫法很雜（例如「都市：其他:第三種住宅區。」「住」「商業區」），
 * 無法保證 100% 準確對應，對不上的一律歸入「其他」，不用猜測硬塞分類。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Market_Price_Zone_Normalizer
 */
class UR_AI_Market_Price_Zone_Normalizer {

    /**
     * 未能辨識分類時的保底分類。
     *
     * @var string
     */
    const FALLBACK_ZONE = '其他';

    /**
     * 正規化分區文字。
     *
     * @param string $raw_zone 原始「都市土地使用分區」文字。
     * @return string 正規化後分類（例如「住三」「商一」），無法辨識則回傳「其他」。
     */
    public static function normalize($raw_zone) {
        $text = is_scalar($raw_zone) ? (string) $raw_zone : '';
        $text = trim($text);

        if ('' === $text) {
            return self::FALLBACK_ZONE;
        }

        // 「第X種住宅區」→ 住X。
        if (preg_match('/第([一二三四])種住宅區/u', $text, $matches)) {
            return '住' . $matches[1];
        }

        // 「第X種商業區」→ 商X。
        if (preg_match('/第([一二三四])種商業區/u', $text, $matches)) {
            return '商' . $matches[1];
        }

        // 部分縣市（如新北）直接寫「商業區」，無細分種別。
        if (false !== mb_stripos($text, '商業區', 0, 'UTF-8')) {
            return '商業區';
        }

        // 只寫「住宅區」或單一個「住」字，無法判斷細分種別。
        if (false !== mb_stripos($text, '住宅區', 0, 'UTF-8') || '住' === $text) {
            return '住宅區(未分種別)';
        }

        return self::FALLBACK_ZONE;
    }

    /**
     * 判斷正規化後的分區是否為住宅性質。
     *
     * 供查詢時預設鎖定住宅類分區使用（都更／危老重建關心的多為住宅）。
     *
     * @param string $normalized_zone 正規化後分區。
     * @return bool
     */
    public static function is_residential($normalized_zone) {
        $normalized_zone = (string) $normalized_zone;

        return 0 === mb_strpos($normalized_zone, '住', 0, 'UTF-8');
    }
}
