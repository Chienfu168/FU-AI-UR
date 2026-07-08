<?php
/**
 * UR AI Assistant FAQ Matcher
 *
 * FAQ 知識庫比對器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_Matcher
 */
class UR_AI_FAQ_Matcher {

    /**
     * FAQ Service.
     *
     * @var UR_AI_FAQ_Service|null
     */
    private $service = null;

    /**
     * 最低命中分數。
     *
     * @var int
     */
    private $min_score = 45;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->service = class_exists('UR_AI_FAQ_Service')
            ? new UR_AI_FAQ_Service()
            : null;

        /**
         * Filter FAQ minimum match score.
         *
         * @param int $score Minimum score.
         */
        $this->min_score = absint(apply_filters('ur_ai_faq_min_match_score', $this->min_score));

        if ($this->min_score <= 0) {
            $this->min_score = 45;
        }
    }

    /**
     * 比對 FAQ。
     *
     * @param string $question 使用者問題。
     * @return array
     */
    public function match($question) {
        $question = $this->normalize_text($question);

        if ('' === $question) {
            return array();
        }

        if (!$this->service instanceof UR_AI_FAQ_Service) {
            return array();
        }

        $faqs = $this->service->get_active_faqs(1000);

        if (empty($faqs)) {
            return array();
        }

        $best = array(
            'faq'              => null,
            'score'            => 0,
            'matched_keywords' => array(),
            'reason'           => '',
        );

        foreach ($faqs as $faq) {
            $result = $this->score_faq($question, $faq);

            if ($result['score'] > $best['score']) {
                $best = array(
                    'faq'              => $faq,
                    'score'            => $result['score'],
                    'matched_keywords' => $result['matched_keywords'],
                    'reason'           => $result['reason'],
                );
            }
        }

        if (empty($best['faq']) || $best['score'] < $this->min_score) {
            return array();
        }

        return $best;
    }

    /**
     * 增加 FAQ 命中次數。
     *
     * @param int $faq_id FAQ ID。
     * @return bool
     */
    public function increase_hit_count($faq_id) {
        if (!$this->service instanceof UR_AI_FAQ_Service) {
            return false;
        }

        return $this->service->increase_hit_count($faq_id);
    }

    /**
     * 計算單筆 FAQ 分數。
     *
     * @param string       $question 使用者問題。
     * @param object|array $faq FAQ 資料。
     * @return array
     */
    private function score_faq($question, $faq) {
        $faq_question = $this->normalize_text($this->get_value($faq, 'question', ''));
        $faq_answer   = $this->normalize_text($this->get_value($faq, 'answer', ''));
        $category     = $this->normalize_text($this->get_value($faq, 'category', ''));
        $keywords_raw = (string) $this->get_value($faq, 'keywords', '');

        $keywords = $this->parse_keywords($keywords_raw);

        $score            = 0;
        $matched_keywords = array();
        $reasons          = array();

        if ('' === $faq_question) {
            return array(
                'score'            => 0,
                'matched_keywords' => array(),
                'reason'           => '',
            );
        }

        if ($question === $faq_question) {
            $score += 100;
            $reasons[] = 'exact_question';
        }

        if ($this->contains($question, $faq_question) || $this->contains($faq_question, $question)) {
            $score += 60;
            $reasons[] = 'question_contains';
        }

        $similarity = $this->similarity_percent($question, $faq_question);

        if ($similarity >= 80) {
            $score += 45;
            $reasons[] = 'high_similarity';
        } elseif ($similarity >= 60) {
            $score += 28;
            $reasons[] = 'medium_similarity';
        } elseif ($similarity >= 45) {
            $score += 15;
            $reasons[] = 'low_similarity';
        }

        foreach ($keywords as $keyword) {
            if ('' === $keyword) {
                continue;
            }

            if ($this->contains($question, $keyword)) {
                $score += $this->keyword_weight($keyword);
                $matched_keywords[] = $keyword;
            }
        }

        if ('' !== $category && $this->contains($question, $category)) {
            $score += 12;
            $matched_keywords[] = $category;
            $reasons[] = 'category_match';
        }

        $important_terms = $this->extract_important_terms($faq_question . ' ' . $keywords_raw);

        foreach ($important_terms as $term) {
            if ($this->contains($question, $term)) {
                $score += 5;
                $matched_keywords[] = $term;
            }
        }

        /**
         * 若問題太短，只命中單一泛用詞，降低誤判。
         */
        if ($this->strlen($question) <= 6 && count(array_unique($matched_keywords)) <= 1 && $score < 75) {
            $score = min($score, 35);
            $reasons[] = 'short_question_penalty';
        }

        /**
         * 如果只命中「都更、都市更新、危老」這類泛用詞，降低分數。
         */
        if ($this->is_only_generic_keywords($matched_keywords)) {
            $score = min($score, 40);
            $reasons[] = 'generic_keyword_penalty';
        }

        return array(
            'score'            => min(100, absint($score)),
            'matched_keywords' => array_values(array_unique($matched_keywords)),
            'reason'           => implode(',', array_values(array_unique($reasons))),
        );
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
            return 22;
        }

        if ($length >= 4) {
            return 16;
        }

        return 10;
    }

    /**
     * 判斷是否只命中泛用關鍵字。
     *
     * @param array $matched_keywords 命中關鍵字。
     * @return bool
     */
    private function is_only_generic_keywords($matched_keywords) {
        if (empty($matched_keywords) || !is_array($matched_keywords)) {
            return false;
        }

        $generic = array(
            '都更',
            '都市更新',
            '危老',
            '危老重建',
            '重建',
            '房子',
            '房屋',
            '老屋',
        );

        $matched_keywords = array_values(array_unique(array_filter(array_map(array($this, 'normalize_text'), $matched_keywords))));

        if (empty($matched_keywords)) {
            return false;
        }

        foreach ($matched_keywords as $keyword) {
            if (!in_array($keyword, $generic, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 擷取重要詞。
     *
     * @param string $text 文字。
     * @return array
     */
    private function extract_important_terms($text) {
        $text = $this->normalize_text($text);

        if ('' === $text) {
            return array();
        }

        $candidates = array(
            '權利變換',
            '協議合建',
            '更新會',
            '自主更新',
            '危老重建',
            '共同負擔',
            '估價',
            '分配',
            '同意書',
            '同意比例',
            '會員大會',
            '理事會',
            '信託',
            '履約保證',
            '行政訴訟',
            '訴願',
            '事業概要',
            '事業計畫',
            '權利變換計畫',
            '建商',
            '實施者',
            '地主',
            '所有權人',
        );

        $terms = array();

        foreach ($candidates as $candidate) {
            if ($this->contains($text, $candidate)) {
                $terms[] = $candidate;
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * 解析關鍵字。
     *
     * @param string $keywords_raw 原始關鍵字。
     * @return array
     */
    private function parse_keywords($keywords_raw) {
        $keywords_raw = (string) $keywords_raw;

        if ('' === trim($keywords_raw)) {
            return array();
        }

        $keywords_raw = str_replace(array('，', '、', ';', '；', '|'), ',', $keywords_raw);
        $parts        = explode(',', $keywords_raw);

        $keywords = array();

        foreach ($parts as $part) {
            $part = $this->normalize_text($part);

            if ('' !== $part) {
                $keywords[] = $part;
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * 文字是否包含關鍵字。
     *
     * @param string $text 文字。
     * @param string $needle 關鍵字。
     * @return bool
     */
    private function contains($text, $needle) {
        $text   = $this->normalize_text($text);
        $needle = $this->normalize_text($needle);

        if ('' === $text || '' === $needle) {
            return false;
        }

        if (function_exists('mb_stripos')) {
            return false !== mb_stripos($text, $needle, 0, 'UTF-8');
        }

        return false !== stripos($text, $needle);
    }

    /**
     * 相似度百分比。
     *
     * @param string $a 文字 A。
     * @param string $b 文字 B。
     * @return float
     */
    private function similarity_percent($a, $b) {
        $a = $this->normalize_text($a);
        $b = $this->normalize_text($b);

        if ('' === $a || '' === $b) {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return (float) $percent;
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

    /**
     * 安全取得資料值。
     *
     * @param mixed  $data 資料。
     * @param string $key 鍵名。
     * @param mixed  $default 預設值。
     * @return mixed
     */
    private function get_value($data, $key, $default = null) {
        if (class_exists('UR_AI_Helper')) {
            return UR_AI_Helper::data_get($data, $key, $default);
        }

        if (is_array($data) && array_key_exists($key, $data)) {
            return $data[$key];
        }

        if (is_object($data) && isset($data->{$key})) {
            return $data->{$key};
        }

        return $default;
    }
}