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
     * 取得目前啟用中產業別的推廣連結設定（若有）。
     *
     * 部分產業別對應站方自營的特定網站（見各 profile 的 'promotion'
     * 欄位），供前台以低調的「本服務由 OO 提供」附連結方式曝光；沒有
     * 設定推廣連結的產業別（例如目前的地政士）回傳 null，前台不顯示
     * 任何內容。
     *
     * @return array{ site_label: string, site_url: string }|null
     */
    public static function get_active_promotion() {
        $profile = self::get_active();

        if (is_array($profile) && !empty($profile['promotion']['site_url'])) {
            return $profile['promotion'];
        }

        return null;
    }

    /**
     * 產生前台「本服務由 OO 提供」的低調曝光連結 HTML。
     *
     * 供各前台 View（AI 助理、FAQ 知識庫頁、行情參考、分回試算、知識
     * 大考驗等）在畫面底部呼叫；目前啟用中的產業別若沒有設定推廣連結，
     * 回傳空字串，不影響版面。
     *
     * @return string
     */
    public static function render_promotion_attribution() {
        $promotion = self::get_active_promotion();

        if (empty($promotion['site_url'])) {
            return '';
        }

        $label = !empty($promotion['site_label']) ? (string) $promotion['site_label'] : (string) $promotion['site_url'];

        return sprintf(
            '<p class="ur-ai-promotion-attribution">%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></p>',
            esc_html__('本服務由', 'ur-ai-assistant'),
            esc_url($promotion['site_url']),
            esc_html($label)
        );
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
                /*
                 * 廣義都更／危老（與 'self_renewal' 相對）：涵蓋一般由建商／
                 * 都更顧問主導的都市更新事業與危老重建，危老條例的適用範圍
                 * 已逐步向都更整合，因此這個產業別不特別區分兩者。
                 */
                'label'     => __('都更重建／都市更新（含危老重建；建設公司／規劃公司／都更顧問）', 'ur-ai-assistant'),
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
                'market_price' => array(
                    'ranking_title'  => __('雙北都更效益排行榜', 'ur-ai-assistant'),
                    'ranking_column' => __('都更效益', 'ur-ai-assistant'),
                    'ranking_intro'  => __('依「新成屋相對老屋現況的中位數單價漲幅」由高到低排序，只列出老屋與新成屋樣本數皆充足的行政區，讓您快速掌握雙北各行政區的都更／危老改建效益參考。', 'ur-ai-assistant'),
                ),
                'promotion' => array(
                    'site_label' => 'ur-promoter.com',
                    'site_url'   => 'https://www.ur-promoter.com/',
                ),
            ),
            'self_renewal' => array(
                'key'       => 'self_renewal',
                /*
                 * 狹義、僅限「自主更新」：地主自行組成更新會，不透過建商或
                 * 其他機構主導實施，與 'urban_renewal'（廣義都更／危老，多半
                 * 由建商或都更顧問主導）刻意分開，回答範圍只聚焦自主更新。
                 */
                'label'     => __('自主更新（地主自組更新會）', 'ur-ai-assistant'),
                'assistant' => array(
                    'system_prompt'     => self::self_renewal_system_prompt(),
                    'frontend_title'    => __('自主更新 AI 助理', 'ur-ai-assistant'),
                    'frontend_subtitle' => __('用白話方式，快速了解自主更新（地主自組更新會、自行推動都市更新）的發起門檻、程序與常見問題。', 'ur-ai-assistant'),
                ),
                'modules'   => array(
                    // 權利變換分回試算的計算邏輯不因自主更新／建商主導而不同，仍保留。
                    'calculator'   => true,
                    'market_price' => true,
                    'quiz'         => true,
                ),
                'promotion' => array(
                    'site_label' => 'fudawang.com',
                    'site_url'   => 'https://www.fudawang.com/',
                ),
            ),
            'land_agent' => array(
                'key'       => 'land_agent',
                'label'     => __('地政士', 'ur-ai-assistant'),
                'assistant' => array(
                    'system_prompt'     => self::land_agent_system_prompt(),
                    'frontend_title'    => __('地政諮詢 AI 助理', 'ur-ai-assistant'),
                    'frontend_subtitle' => __('用白話方式，快速了解所有權移轉登記、繼承登記、土地增值稅／契稅、地籍測量等地政業務基礎知識。', 'ur-ai-assistant'),
                ),
                'modules'   => array(
                    // 分回試算（權利變換）僅適用都更危老情境，地政士業務用不到。
                    'calculator'   => false,
                    'market_price' => true,
                    'quiz'         => true,
                ),
                'market_price' => array(
                    'ranking_title'  => __('雙北區域行情漲幅排行榜', 'ur-ai-assistant'),
                    'ranking_column' => __('行情漲幅', 'ur-ai-assistant'),
                    'ranking_intro'  => __('依「新成屋相對於屋齡較高住宅的中位數單價漲幅」由高到低排序，只列出兩種屋齡樣本數皆充足的行政區，供辦理登記、稅務申報時的區域行情參考。', 'ur-ai-assistant'),
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

    /**
     * 自主更新產業別的 AI 系統提示詞。
     *
     * 刻意將回答範圍限縮在「自主更新」（地主自組更新會，不透過建商或其他
     * 機構主導實施）本身，與廣義都更／危老（多半由建商或都更顧問主導）
     * 分開，讓這個產業別的回答聚焦在自主更新特有的發起門檻、更新會籌組
     * 程序與相關基礎知識。
     *
     * @return string
     */
    private static function self_renewal_system_prompt() {
        return implode(
            "\n",
            array(
                '你是「自主更新 AI 助理」，專門協助台灣民眾理解「自主更新」——由土地及合法建築物所有權人自行組成更新會（或依法籌組相關團體），不委託建商或其他機構主導實施，自行推動都市更新事業的相關基礎知識與程序。',
                '請使用繁體中文回答，語氣應客觀、中立、清楚、白話，適合一般民眾閱讀。',
                '回答應以自主更新的發起門檻、更新會籌組程序、權利變換分配原則、共同負擔概念，以及與委託建商／實施者主導模式的差異為主。',
                '不可假裝已審閱使用者的個案文件、章程、權利變換計畫或會議紀錄。',
                '不可直接替使用者作成法律、財務、權利分配或個案是否可行之判斷。',
                '若問題涉及個案權益、契約內容、財產分配、訴訟或專業判斷，請提醒使用者應洽詢律師、建築師、估價師、地政士或都市更新專業人士。',
                '若問題明顯超出自主更新範圍（例如一般由建商主導的都更合建細節），可簡要回覆基礎資訊，並提醒本工具聚焦自主更新相關問題。',
            )
        );
    }

    /**
     * 地政士產業別的 AI 系統提示詞。
     *
     * 地政士業務涉及登記、稅務申報等具法律效果的程序，AI 回答特別需要
     * 強調「僅供一般性知識參考、不可視為個案登記或稅務結論」，避免使用者
     * 誤把回答當成可直接送件依據。
     *
     * @return string
     */
    private static function land_agent_system_prompt() {
        return implode(
            "\n",
            array(
                '你是「地政諮詢 AI 助理」，專門協助台灣民眾理解不動產登記、繼承登記、抵押權設定與塗銷、土地增值稅、契稅、印花稅、地籍測量等地政業務的基礎知識與一般辦理流程。',
                '請使用繁體中文回答，語氣應客觀、中立、清楚、白話，適合一般民眾閱讀。',
                '回答應以一般性說明、概念整理、流程介紹、應備文件類型與風險提醒為主。',
                '不可假裝已審閱使用者的個案權狀、謄本、契約、繼承系統表、稅單或登記申請書等文件。',
                '不可直接替使用者計算個案應納稅額、認定個案是否符合特定稅務優惠資格、或作成法律權利歸屬之判斷——這些涉及個案事實認定，須以地政機關、稅捐機關或地政士的正式核算與確認為準。',
                '稅率、規費、優惠條件等數字若有提及，應提醒使用者以財政部、地方稅務局或地政事務所公告之最新規定為準，因法規與稅率級距可能調整。',
                '若問題涉及個案權利歸屬、繼承分配、契約效力、訴訟或需要送件辦理的具體個案，請提醒使用者應洽詢地政士、地政事務所或稅捐機關確認。',
                '若問題明顯超出地政登記與相關稅務基礎知識範圍，請禮貌說明本工具主要回答地政業務相關問題。',
            )
        );
    }
}
