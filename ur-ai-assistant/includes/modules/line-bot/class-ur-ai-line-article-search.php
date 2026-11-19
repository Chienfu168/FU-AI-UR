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
     * @param string $question 使用者問題。
     * @return array|null array{title, url, text} 命中；否則 null。
     */
    public function find($question) {
        if (!class_exists('UR_AI_Post_Search')) {
            return null;
        }

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
            'title' => $best['title'],
            'url'   => $best['url'],
            'text'  => $this->format_reply($best),
        );
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
