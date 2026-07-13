<?php
/**
 * UR AI Assistant Industry Profiles
 *
 * 多產業擴充架構 Phase 1：把「產業別」相關的預設值（AI 系統提示詞、
 * 前台標題／副標題、各模組是否為該產業的核心工具）從寫死在各模組裡的
 * 字串，抽成集中管理的設定資料。
 *
 * 設計原則：
 * - 目前僅有 'urban_renewal'（都更危老）一個產業別，做為所有現有安裝
 *   的預設值與唯一選項，這一步對既有網站的行為完全零影響——純粹是把
 *   內容從程式碼裡的字串搬進這個登錄檔的資料結構。
 * - 各模組（Settings／Calculator Settings 等）讀取的仍然只是「預設值」；
 *   管理者原本就能在後台自行覆寫的設定（系統提示詞、模組啟用開關）不受
 *   任何影響，覆寫優先權完全不變。
 * - 新增產業別時，只需要在 profiles() 裡新增一筆資料，不需要更動任何
 *   讀取端的邏輯。
 *
 * 詳細規劃見 docs/industry-expansion-architecture.md。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Industry_Profiles
 */
class UR_AI_Industry_Profiles {

    /**
     * 預設／目前唯一支援的產業別 key。
     *
     * @var string
     */
    const DEFAULT_INDUSTRY = 'urban_renewal';

    /**
     * 取得可選產業別清單（key => 顯示名稱），供後台下拉選單使用。
     *
     * @return array
     */
    public static function get_all() {
        $labels = array();

        foreach (self::profiles() as $key => $profile) {
            $labels[$key] = $profile['label'];
        }

        return $labels;
    }

    /**
     * 取得單一產業別的完整設定內容。
     *
     * @param string $industry 產業別 key。
     * @return array|null 不存在時回傳 null。
     */
    public static function get($industry) {
        $profiles = self::profiles();
        $industry = sanitize_key((string) $industry);

        return isset($profiles[$industry]) ? $profiles[$industry] : null;
    }

    /**
     * 判斷產業別 key 是否為已註冊的有效選項。
     *
     * @param string $industry 產業別 key。
     * @return bool
     */
    public static function is_valid($industry) {
        return null !== self::get($industry);
    }

    /**
     * 取得目前啟用中的產業別設定。
     *
     * 注意：這裡刻意直接以 get_option() 讀取原始設定值，不透過
     * UR_AI_Settings::get_all()／defaults()，避免 UR_AI_Settings::defaults()
     * 反過來呼叫 get_active() 造成無窮遞迴（defaults() 本身會用到這裡的
     * 回傳值來決定系統提示詞等預設文案）。
     *
     * @return array
     */
    public static function get_active() {
        $profile = self::get(self::get_stored_industry());

        if (null !== $profile) {
            return $profile;
        }

        return self::profiles()[self::DEFAULT_INDUSTRY];
    }

    /**
     * 直接讀取儲存在主設定 option 裡的產業別原始值。
     *
     * @return string
     */
    private static function get_stored_industry() {
        $option_name = class_exists('UR_AI_Settings') ? UR_AI_Settings::OPTION_NAME : 'ur_ai_assistant_settings';
        $settings    = get_option($option_name, array());

        if (is_array($settings) && !empty($settings['industry'])) {
            return sanitize_key((string) $settings['industry']);
        }

        return self::DEFAULT_INDUSTRY;
    }

    /**
     * 全部已註冊的產業別設定資料。
     *
     * @return array
     */
    private static function profiles() {
        return array(
            'urban_renewal' => array(
                'key'       => 'urban_renewal',
                'label'     => __('都更危老（建設公司／規劃公司／都更顧問）', 'ur-ai-assistant'),
                'assistant' => array(
                    'system_prompt'     => self::urban_renewal_system_prompt(),
                    'frontend_title'    => __('都更危老 AI 助理', 'ur-ai-assistant'),
                    'frontend_subtitle' => __('用白話方式，快速了解都市更新、危老重建、更新會、自主更新、權利變換與協議合建等基礎問題。', 'ur-ai-assistant'),
                ),
                /*
                 * 標示各模組是否為此產業別的核心工具（供未來新增產業別時，
                 * 決定該模組的預設啟用狀態；管理者仍可在各模組自己的設定
                 * 頁手動開關，這裡只影響「第一次安裝時」的預設值）。
                 */
                'modules'   => array(
                    'calculator'   => true,
                    'market_price' => true,
                    'quiz'         => true,
                ),
            ),
        );
    }

    /**
     * 都更危老產業別的 AI 系統提示詞（沿用外掛既有的都更危老人設文案）。
     *
     * @return string
     */
    private static function urban_renewal_system_prompt() {
        return implode(
            "\n",
            array(
                '你是「都更危老 AI 助理」，專門協助台灣民眾理解台灣都市更新、危老重建、更新會、自主更新、權利變換、協議合建、都市更新程序與相關基礎知識。',
                '請使用繁體中文回答，語氣應客觀、中立、清楚、白話，適合一般民眾閱讀。',
                '回答應以一般性說明、概念整理、流程介紹與風險提醒為主。',
                '不可假裝已審閱使用者的個案文件、契約、權利變換計畫、估價報告、建築圖說、土地建物謄本或會議紀錄。',
                '不可直接替使用者作成法律、估價、建築、稅務、登記、權利分配、訴訟勝敗或個案是否合理之判斷。',
                '若問題涉及個案權益、契約內容、財產分配、訴訟或專業判斷，請提醒使用者應洽詢律師、建築師、估價師、地政士或都市更新專業人士。',
                '若問題明顯超出都市更新、危老重建與不動產重建基礎知識範圍，請禮貌說明本工具主要回答都更危老相關問題。',
            )
        );
    }
}
