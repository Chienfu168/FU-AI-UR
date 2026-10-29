<?php
/**
 * UR AI Assistant Usage Guard
 *
 * 全站 AI 呼叫「總量」上限（每日／每月），與既有「每位訪客每日次數」
 * 互補：後者限制單一使用者，這裡限制整站加總，用來守住整體 OpenAI 帳單。
 *
 * 計數刻意存成單一 wp_option（非 transient）：option 不會被物件快取逐出，
 * 不會因快取清空而「歸零放行」造成成本失控；且以「日期／月份字串」自帶
 * 換日換月判斷，不需額外清理排程，也不會像逐日 key 那樣在 options 累積。
 *
 * 併發下的讀—改—寫可能少算一兩次（輕微低估），對「成本上限」而言可接受；
 * 真正的硬性天花板仍建議在 OpenAI 後台設每月花費上限，兩者互補。
 *
 * 只計「實際成功呼叫 AI」的次數（FAQ 命中不呼叫 AI、不計入）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Usage_Guard
 */
class UR_AI_Usage_Guard {

    /**
     * 計數 option 名稱。
     *
     * @var string
     */
    const OPTION_NAME = 'ur_ai_ai_usage_counter';

    /**
     * 是否還可以呼叫 AI（未達全站每日與每月上限）。
     *
     * @return bool true＝可呼叫；false＝已達上限。
     */
    public static function can_call_ai() {
        $daily   = self::get_daily_cap();
        $monthly = self::get_monthly_cap();

        // 兩者皆為 0（不限制）時直接放行，連 option 都不用讀。
        if ($daily <= 0 && $monthly <= 0) {
            return true;
        }

        $snapshot = self::get_usage_snapshot();

        if ($daily > 0 && $snapshot['day_count'] >= $daily) {
            return false;
        }

        if ($monthly > 0 && $snapshot['month_count'] >= $monthly) {
            return false;
        }

        return true;
    }

    /**
     * 記錄一次成功的 AI 呼叫（在成功取得 AI 回答後呼叫）。
     *
     * @return void
     */
    public static function record_ai_call() {
        $today = self::today();
        $month = self::this_month();

        $data = get_option(self::OPTION_NAME, array());
        if (!is_array($data)) {
            $data = array();
        }

        $day_count   = (isset($data['day']) && $data['day'] === $today) ? (int) $data['day_count'] : 0;
        $month_count = (isset($data['month']) && $data['month'] === $month) ? (int) $data['month_count'] : 0;

        $day_count++;
        $month_count++;

        update_option(
            self::OPTION_NAME,
            array(
                'day'         => $today,
                'day_count'   => $day_count,
                'month'       => $month,
                'month_count' => $month_count,
            ),
            false
        );
    }

    /**
     * 取得目前用量快照（已套用換日／換月歸零判斷，但不寫回）。
     *
     * @return array {today, month, day_count, month_count}
     */
    public static function get_usage_snapshot() {
        $today = self::today();
        $month = self::this_month();

        $data = get_option(self::OPTION_NAME, array());
        if (!is_array($data)) {
            $data = array();
        }

        $day_count   = (isset($data['day']) && $data['day'] === $today) ? (int) $data['day_count'] : 0;
        $month_count = (isset($data['month']) && $data['month'] === $month) ? (int) $data['month_count'] : 0;

        return array(
            'today'       => $today,
            'month'       => $month,
            'day_count'   => $day_count,
            'month_count' => $month_count,
        );
    }

    /**
     * 全站每日 AI 呼叫上限（0＝不限制）。
     *
     * @return int
     */
    private static function get_daily_cap() {
        if (class_exists('UR_AI_Settings') && method_exists('UR_AI_Settings', 'get_site_daily_ai_limit')) {
            return absint(UR_AI_Settings::get_site_daily_ai_limit());
        }

        return 0;
    }

    /**
     * 全站每月 AI 呼叫上限（0＝不限制）。
     *
     * @return int
     */
    private static function get_monthly_cap() {
        if (class_exists('UR_AI_Settings') && method_exists('UR_AI_Settings', 'get_site_monthly_ai_limit')) {
            return absint(UR_AI_Settings::get_site_monthly_ai_limit());
        }

        return 0;
    }

    /**
     * 今日字串（依站台時區）。
     *
     * @return string Ymd
     */
    private static function today() {
        return function_exists('current_time') ? current_time('Ymd') : gmdate('Ymd');
    }

    /**
     * 本月字串（依站台時區）。
     *
     * @return string Ym
     */
    private static function this_month() {
        return function_exists('current_time') ? current_time('Ym') : gmdate('Ym');
    }
}
