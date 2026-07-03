<?php
/**
 * UR AI Assistant FAQ Category Helper
 *
 * FAQ 分類與關鍵字建議工具。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_Category_Helper
 */
class UR_AI_FAQ_Category_Helper {

    /**
     * 分類規則。
     *
     * @var array
     */
    private $category_rules = array();

    /**
     * 建構子。
     */
    public function __construct() {
        $this->category_rules = $this->get_default_category_rules();
    }

    /**
     * 建議分類。
     *
     * @param string $question 問題。
     * @param string $answer 回答。
     * @return string
     */
    public function suggest_category($question, $answer = '') {
        $text = $this->normalize_text($question . ' ' . $answer);

        if ('' === $text) {
            return '待分類';
        }

        $best_category = '待分類';
        $best_score    = 0;

        foreach ($this->category_rules as $category => $keywords) {
            $score = 0;

            foreach ($keywords as $keyword) {
                $keyword = $this->normalize_text($keyword);

                if ('' === $keyword) {
                    continue;
                }

                if ($this->contains($text, $keyword)) {
                    $score += $this->keyword_weight($keyword);
                }
            }

            if ($score > $best_score) {
                $best_score    = $score;
                $best_category = $category;
            }
        }

        if ($best_score <= 0) {
            return '待分類';
        }

        return $best_category;
    }

    /**
     * 建議關鍵字。
     *
     * @param string $question 問題。
     * @param string $answer 回答。
     * @return string
     */
    public function suggest_keywords($question, $answer = '') {
        $text = $this->normalize_text_with_space($question . ' ' . $answer);

        if ('' === trim($text)) {
            return '';
        }

        $matched = array();

        foreach ($this->get_keyword_candidates() as $keyword) {
            if ($this->contains($text, $keyword)) {
                $matched[] = $keyword;
            }
        }

        $category = $this->suggest_category($question, $answer);

        if ('待分類' !== $category && '其他' !== $category) {
            array_unshift($matched, $category);
        }

        $matched = array_values(array_unique(array_filter($matched)));

        /**
         * 避免關鍵字太多，最多保留 10 個。
         */
        $matched = array_slice($matched, 0, 10);

        return implode(', ', $matched);
    }

    /**
     * 建議熱門問題分類。
     *
     * @param string $question 問題。
     * @param string $description 說明。
     * @return string
     */
    public function suggest_popular_question_category($question, $description = '') {
        return $this->suggest_category($question, $description);
    }

    /**
     * 建議相關頁面分類。
     *
     * @param string $title 標題。
     * @param string $description 說明。
     * @param string $keywords 關鍵字。
     * @return string
     */
    public function suggest_related_page_category($title, $description = '', $keywords = '') {
        return $this->suggest_category($title . ' ' . $keywords, $description);
    }

    /**
     * 取得預設分類。
     *
     * @return array
     */
    public function get_categories() {
        return array_keys($this->category_rules);
    }

    /**
     * 取得預設分類規則。
     *
     * @return array
     */
    private function get_default_category_rules() {
        $rules = array(
            '都市更新' => array(
                '都市更新',
                '都更',
                '都市更新條例',
                '事業概要',
                '事業計畫',
                '更新地區',
                '都市計畫',
                '審議',
                '核定',
                '公告實施',
                '實施者',
            ),

            '危老重建' => array(
                '危老',
                '危老重建',
                '危險老舊建築',
                '老屋重建',
                '耐震',
                '結構安全',
                '危險建築',
                '重建計畫',
                '建築容積獎勵',
                '危老條例',
            ),

            '更新會' => array(
                '更新會',
                '都市更新會',
                '會員大會',
                '理事會',
                '監事',
                '章程',
                '會員',
                '會務',
                '自主實施',
                '更新會成立',
                '更新會解散',
            ),

            '自主更新' => array(
                '自主更新',
                '地主自辦',
                '所有權人自辦',
                '代理實施者',
                '遴選建商',
                '地主發起',
                '社區共識',
                '自力更新',
                '自辦都更',
            ),

            '權利變換' => array(
                '權利變換',
                '權變',
                '權利價值',
                '分配',
                '估價',
                '共同負擔',
                '找補',
                '選配',
                '權利變換計畫',
                '土地權利價值',
                '建物權利價值',
                '分回坪數',
            ),

            '協議合建' => array(
                '協議合建',
                '合建',
                '地主合建',
                '建商合建',
                '合建契約',
                '分屋',
                '分售',
                '合建條件',
                '契約',
                '地主權益',
                '建商承諾',
            ),

            '同意與程序' => array(
                '同意書',
                '同意比例',
                '撤回同意',
                '反悔',
                '程序',
                '門檻',
                '所有權人同意',
                '公聽會',
                '聽證',
                '通知',
                '送達',
                '審議程序',
            ),

            '估價與分配' => array(
                '估價',
                '鑑價',
                '分配',
                '價值',
                '坪數',
                '單價',
                '房地價值',
                '選屋',
                '車位',
                '共同負擔比',
                '更新後價值',
                '更新前價值',
            ),

            '共同負擔' => array(
                '共同負擔',
                '工程費',
                '管理費',
                '貸款利息',
                '稅捐',
                '拆遷補償',
                '成本',
                '負擔比例',
                '費用',
                '成本控管',
            ),

            '信託與資金控管' => array(
                '信託',
                '不動產信託',
                '資金信託',
                '履約保證',
                '專款專用',
                '工程款',
                '資金控管',
                '續建',
                '風險控管',
                '付款',
            ),

            '行政救濟' => array(
                '訴願',
                '行政訴訟',
                '救濟',
                '撤銷',
                '停止執行',
                '異議',
                '爭議',
                '主管機關',
                '法院',
                '審議結果',
            ),

            '其他' => array(
                '其他',
                '一般問題',
                '常見問題',
                '說明',
                '介紹',
            ),
        );

        /**
         * Filter FAQ category rules.
         *
         * @param array $rules Category rules.
         */
        return apply_filters('ur_ai_faq_category_rules', $rules);
    }

    /**
     * 取得關鍵字候選清單。
     *
     * @return array
     */
    private function get_keyword_candidates() {
        $keywords = array(
            '都市更新',
            '都更',
            '危老重建',
            '危老',
            '更新會',
            '自主更新',
            '權利變換',
            '協議合建',
            '共同負擔',
            '估價',
            '分配',
            '選配',
            '找補',
            '同意書',
            '同意比例',
            '撤回同意',
            '會員大會',
            '理事會',
            '章程',
            '實施者',
            '代理實施者',
            '建商',
            '地主',
            '所有權人',
            '信託',
            '資金信託',
            '不動產信託',
            '履約保證',
            '續建',
            '公聽會',
            '聽證',
            '事業概要',
            '事業計畫',
            '權利變換計畫',
            '容積獎勵',
            '海砂屋',
            '耐震',
            '拆遷補償',
            '行政救濟',
            '訴願',
            '行政訴訟',
            '主管機關',
            '新北市',
            '台北市',
            '社區共識',
            '風險控管',
            '契約',
            '合建契約',
            '分屋',
            '車位',
            '稅務',
            '登記',
            '謄本',
        );

        /**
         * Filter FAQ keyword candidates.
         *
         * @param array $keywords Keyword candidates.
         */
        return apply_filters('ur_ai_faq_keyword_candidates', $keywords);
    }

    /**
     * 關鍵字權重。
     *
     * @param string $keyword 關鍵字。
     * @return int
     */
    private function keyword_weight($keyword) {
        $length = $this->strlen($keyword);

        if ($length >= 6) {
            return 18;
        }

        if ($length >= 4) {
            return 12;
        }

        return 8;
    }

    /**
     * 文字是否包含關鍵字。
     *
     * @param string $text 文字。
     * @param string $needle 關鍵字。
     * @return bool
     */
    private function contains($text, $needle) {
        $text   = $this->normalize_text_with_space($text);
        $needle = $this->normalize_text_with_space($needle);

        if ('' === trim($text) || '' === trim($needle)) {
            return false;
        }

        if (function_exists('mb_stripos')) {
            return false !== mb_stripos($text, $needle, 0, 'UTF-8');
        }

        return false !== stripos($text, $needle);
    }

    /**
     * 正規化文字。
     *
     * @param mixed $text 原始文字。
     * @return string
     */
    private function normalize_text($text) {
        $text = is_scalar($text) ? (string) $text : '';
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = strtolower($text);
        $text = preg_replace('/\s+/u', '', $text);
        $text = preg_replace('/[，,。！？!？；;：「」『』（）()【】\[\]、]/u', '', $text);

        return trim($text);
    }

    /**
     * 保留空白的文字正規化。
     *
     * @param mixed $text 原始文字。
     * @return string
     */
    private function normalize_text_with_space($text) {
        $text = is_scalar($text) ? (string) $text : '';
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = strtolower($text);
        $text = preg_replace('/[，,。！？!？；;：「」『』（）()【】\[\]、]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * 字串長度。
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