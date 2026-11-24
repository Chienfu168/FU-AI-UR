<?php
/**
 * UR AI Assistant LINE Article Search
 *
 * LINE 客服在「FAQ 未命中、呼叫 AI 之前」用來搜尋站上**已發佈的文章／
 * 頁面**，命中就回「摘要＋連結」，把網站既有內容變成客服的即時答案來源，
 * 也能把流量導回網站。沿用 UR_AI_Post_Search（WP_Query 搜尋）。
 *
 * 命中門檻刻意偏嚴：只有在「搜尋詞確實出現在文章標題或摘要」時才回文章，
 * 避免回到不相關的文章（寧可讓 AI 接手，也不要給錯文章）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Article_Search
 */
class UR_AI_Line_Article_Search {

    /**
     * 依使用者問題找出最相關的站內文章。
     *
     * 策略（為了讓「台北的海砂屋獎勵」這類多概念問句也能命中）：
     * 1. 先從問題中抽出「都更危老領域關鍵詞」（如 海砂屋、容積獎勵、危老…），
     *    以這些關鍵詞分別搜尋並合併，再要求命中文章的標題或摘要「確實含有
     *    其中一個關鍵詞」才回覆（長詞優先、標題優先），避免亂命中。
     * 2. 問題若完全不含領域關鍵詞，退回舊版「整句（去疑問詞）嚴格比對」。
     *
     * @param string $question 使用者問題。
     * @return array|null array{title, url, text} 命中；否則 null。
     */
    public function find($question) {
        if (!class_exists('UR_AI_Post_Search')) {
            return null;
        }

        $keywords = $this->extract_keywords($question);

        if (!empty($keywords)) {
            // 有領域關鍵詞：用關鍵詞搜尋＋比對。找不到就交給 AI（不硬湊）。
            return $this->find_by_keywords($keywords);
        }

        // 後援：無領域關鍵詞時，用整句（去疑問詞）嚴格比對。
        return $this->find_by_phrase($question);
    }

    /**
     * 以領域關鍵詞搜尋並挑出最相關文章。
     *
     * @param array $keywords 關鍵詞（由長到短）。
     * @return array|null
     */
    private function find_by_keywords($keywords) {
        $search = new UR_AI_Post_Search();

        $post_types = $this->get_post_types();
        if (!empty($post_types)) {
            $search->set_post_types($post_types);
        }

        // 以最具代表性的前幾個關鍵詞各搜一次，合併去重（長詞在前，較精準）。
        $items = array();
        $seen  = array();

        foreach (array_slice($keywords, 0, 3) as $kw) {
            $found = $search->search($kw, 6);

            if (empty($found) || !is_array($found)) {
                continue;
            }

            foreach ($found as $it) {
                $url = $this->get($it, 'url');
                $key = '' !== $url ? $url : md5(wp_json_encode($it));

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $items[]    = $it;
            }

            if (count($items) >= 12) {
                break;
            }
        }

        if (empty($items)) {
            return null;
        }

        $best = $this->pick_best_by_keywords($items, $keywords);

        if (null === $best) {
            return null;
        }

        return array(
            'title' => $this->get($best, 'title'),
            'url'   => $this->get($best, 'url'),
            'text'  => $this->format_reply($best),
        );
    }

    /**
     * 後援：整句（去疑問詞）嚴格比對（舊版行為）。
     *
     * @param string $question 使用者問題。
     * @return array|null
     */
    private function find_by_phrase($question) {
        $term = $this->derive_search_term($question);

        // 太短／太籠統就不搜（交給 AI），避免亂命中。
        if ($this->strlen($term) < 2) {
            return null;
        }

        $search = new UR_AI_Post_Search();

        $post_types = $this->get_post_types();
        if (!empty($post_types)) {
            $search->set_post_types($post_types);
        }

        $items = $search->search($term, 6);

        if (empty($items) || !is_array($items)) {
            return null;
        }

        $best = $this->pick_best($items, $term);

        if (null === $best) {
            return null;
        }

        return array(
            'title' => $this->get($best, 'title'),
            'url'   => $this->get($best, 'url'),
            'text'  => $this->format_reply($best),
        );
    }

    /**
     * 從問題中抽出「都更危老領域關鍵詞」（依長度由長到短）。
     *
     * 以一份領域關鍵詞字典比對問題字串，命中者即為搜尋關鍵詞。長詞優先，
     * 例如「容積獎勵」優先於「容積」「獎勵」。字典可用
     * `ur_ai_line_article_keywords` 篩選器擴充。
     *
     * @param string $question 問題。
     * @return array 關鍵詞陣列（長→短）；無則空陣列。
     */
    private function extract_keywords($question) {
        $q = trim((string) $question);

        if ('' === $q) {
            return array();
        }

        $found = array();

        foreach ($this->keyword_dictionary() as $kw) {
            if ('' === $kw) {
                continue;
            }

            if ($this->contains($q, $kw)) {
                $found[$kw] = $this->strlen($kw);
            }
        }

        if (empty($found)) {
            return array();
        }

        // 依長度由長到短（長詞較具代表性，優先搜尋與比對）。
        arsort($found);

        return array_keys($found);
    }

    /**
     * 都更危老領域關鍵詞字典（刻意不含地名，避免只因提到縣市就誤命中）。
     *
     * @return array
     */
    private function keyword_dictionary() {
        $default = array(
            '都市更新', '都更', '危老重建', '危老', '老屋重建', '老屋',
            '海砂屋', '海砂', '輻射屋', '氯離子', '危險建築', '列管',
            '容積獎勵', '容積移轉', '容積', '獎勵', '補助款', '補助',
            '重建', '整建維護', '拉皮', '防災都更', '防災',
            '權利變換', '協議合建', '合建', '自主更新', '公辦都更',
            '事業計畫', '更新單元', '更新會', '都更會', '實施者',
            '同意比例', '同意書', '門檻', '迅行劃定', '公展', '聽證', '審議',
            '耐震評估', '耐震', '結構安全', '分回', '坪效', '坪數',
            '稅捐減免', '地價稅', '房屋稅', '契稅', '貸款', '融資', '信託',
            '地主', '建商', '營造', '騎樓', '法定空地',
        );

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('ur_ai_line_article_keywords', $default);

            if (is_array($filtered) && !empty($filtered)) {
                $default = $filtered;
            }
        }

        // 正規化：去空白、去重。
        $out = array();

        foreach ($default as $kw) {
            $kw = trim((string) $kw);

            if ('' !== $kw && !in_array($kw, $out, true)) {
                $out[] = $kw;
            }
        }

        return $out;
    }

    /**
     * 從問題萃取搜尋詞：去掉常見的疑問詞綴與標點，留下主題關鍵詞。
     * 例如「海砂屋是什麼」→「海砂屋」、「都更獎勵的規定？」→「都更獎勵」。
     *
     * @param string $question 問題。
     * @return string
     */
    private function derive_search_term($question) {
        $term = trim((string) $question);

        if ('' === $term) {
            return '';
        }

        // 去掉開頭的客套／疑問詞。
        $term = preg_replace('/^(請問|想問|我想問|我想了解|想了解|請教)\s*/u', '', $term);

        // 去掉常見的疑問詞綴（不論出現在哪）。
        $stopwords = array(
            '是什麼意思', '是甚麼意思', '什麼意思', '甚麼意思',
            '是什麼', '是甚麼', '什麼', '甚麼',
            '的意思', '的定義', '的規定', '規定',
            '怎麼辦', '怎麼算', '怎麼做', '如何', '怎麼', '怎樣',
            '有沒有', '有無', '可以嗎', '要注意什麼',
            '介紹', '說明', '解釋', '是不是', '算不算',
        );

        foreach ($stopwords as $word) {
            $term = str_replace($word, '', $term);
        }

        // 去掉標點與空白。
        $term = preg_replace('/[\s，,。！？!?；;：「」『』（）()【】\[\]、~～\-]+/u', '', $term);

        return trim($term);
    }

    /**
     * 以關鍵詞挑出最相關的一篇：依關鍵詞由長到短，優先「標題命中」，
     * 其次「摘要命中」。長詞先、標題先，確保回的是真正相關的文章。
     *
     * @param array $items    搜尋結果。
     * @param array $keywords 關鍵詞（已由長到短）。
     * @return array|null
     */
    private function pick_best_by_keywords($items, $keywords) {
        // 第一輪：標題命中（長詞優先）。
        foreach ($keywords as $kw) {
            foreach ($items as $item) {
                if ($this->contains($this->get($item, 'title'), $kw)) {
                    return $item;
                }
            }
        }

        // 第二輪：摘要命中（長詞優先）。
        foreach ($keywords as $kw) {
            foreach ($items as $item) {
                if ($this->contains($this->get($item, 'description'), $kw)) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * 從搜尋結果挑出最相關的一篇：優先「標題含搜尋詞」，次之「摘要含搜尋詞」。
     *
     * @param array  $items 搜尋結果。
     * @param string $term  搜尋詞。
     * @return array|null
     */
    private function pick_best($items, $term) {
        // 第一輪：標題命中。
        foreach ($items as $item) {
            if ($this->contains($this->get($item, 'title'), $term)) {
                return $item;
            }
        }

        // 第二輪：摘要命中。
        foreach ($items as $item) {
            if ($this->contains($this->get($item, 'description'), $term)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * 組出 LINE 回覆文字。
     *
     * @param array $item 文章資料。
     * @return string
     */
    private function format_reply($item) {
        $title = trim((string) $this->get($item, 'title'));
        $desc  = trim((string) $this->get($item, 'description'));
        $url   = trim((string) $this->get($item, 'url'));

        $lines = array();

        $lines[] = '您可以參考這篇文章：';

        if ('' !== $title) {
            $lines[] = '「' . $title . '」';
        }

        if ('' !== $desc) {
            $lines[] = $desc . '…';
        }

        if ('' !== $url) {
            $lines[] = '👉 ' . $url;
        }

        return implode("\n", $lines);
    }

    /**
     * 可搜尋的文章類型（設定；預設 post 與 page）。
     *
     * @return array
     */
    private function get_post_types() {
        $raw = class_exists('UR_AI_Line_Settings') ? (string) UR_AI_Line_Settings::get('article_post_types', '') : '';

        $types = array();

        foreach (preg_split('/[\s,，]+/u', $raw) as $t) {
            $t = sanitize_key((string) $t);

            if ('' !== $t) {
                $types[] = $t;
            }
        }

        return !empty($types) ? array_values(array_unique($types)) : array('post', 'page');
    }

    /**
     * 字串是否包含關鍵詞（忽略大小寫，支援中文）。
     *
     * @param string $haystack 內容。
     * @param string $needle   關鍵詞。
     * @return bool
     */
    private function contains($haystack, $needle) {
        $haystack = (string) $haystack;
        $needle   = (string) $needle;

        if ('' === $haystack || '' === $needle) {
            return false;
        }

        if (function_exists('mb_stripos')) {
            return false !== mb_stripos($haystack, $needle, 0, 'UTF-8');
        }

        return false !== stripos($haystack, $needle);
    }

    /**
     * 安全取值。
     *
     * @param array  $item 陣列。
     * @param string $key  鍵。
     * @return string
     */
    private function get($item, $key) {
        return is_array($item) && isset($item[$key]) ? (string) $item[$key] : '';
    }

    /**
     * 字串長度。
     *
     * @param string $text 文字。
     * @return int
     */
    private function strlen($text) {
        return function_exists('mb_strlen') ? mb_strlen((string) $text, 'UTF-8') : strlen((string) $text);
    }
}
