<?php
/**
 * UR AI Assistant FAQ Article Service
 *
 * 把既有 FAQ 問答擴寫成一篇完整文章草稿（WordPress 文章，狀態為草稿），
 * 供後台「FAQ 知識庫」頁的「產生文章草稿」功能使用。
 *
 * 設計原則：
 * - 只從已經人工審核過的 FAQ 內容延伸，不接受管理者自行輸入主題／大綱
 *   憑空生成——降低內容失真的風險，也剛好能替內容量還不多的產業別
 *   （例如新試點的地政士）快速把既有 FAQ 轉成較完整的文章內容。
 * - 產生的文章一律以「草稿」狀態寫入，絕對不會自動發布，需要管理者
 *   在 WordPress 文章編輯畫面人工審核、編輯後才會上線，與外掛既有的
 *   「AI 產生的內容一律需人工審核」原則一致。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_FAQ_Article_Service
 */
class UR_AI_FAQ_Article_Service {

    /**
     * 來源 FAQ 問題＋回答的最低總字數門檻。
     *
     * 低於這個字數就直接擋下、不呼叫 AI：內容太薄的話，AI 擴寫時容易
     * 為了湊出文章長度而自行「加油添醋」，增加內容失真的風險。門檻值
     * 是依實際 FAQ 內容包（data/industry-packs/land_agent/faq.csv，
     * 問題＋回答最短一筆 133 字、中位數 159 字）估算，刻意訂在明顯低於
     * 中位數、但足以濾掉近乎空白／過度簡略內容的水準。
     *
     * @var int
     */
    const MIN_SOURCE_LENGTH = 120;

    /**
     * AI 產生的文章內文最低字數門檻。
     *
     * 系統提示詞已要求 AI 產生約 500～900 字的內文，但 AI 偶爾仍可能
     * 回傳明顯不足、近乎沒有展開的內容；與其把這種內容也建立成草稿
     * 讓管理者事後才發現品質不佳，不如在建立文章前就擋下、要求重新
     * 產生，門檻訂在明顯低於系統提示詞要求下限（500 字）的水準，只
     * 用來濾掉明顯不合格的輸出，不是用來要求「剛好達標」。
     *
     * @var int
     */
    const MIN_ARTICLE_LENGTH = 300;

    /**
     * 一篇文章最多可以同時掛幾個分類。
     *
     * 使用者反映希望文章分類不要只能有一個，改成依比對分數由高到低
     * 保留多個分類；設上限是為了避免關鍵字比對太寬鬆時，一次掛上一堆
     * 關聯度其實不高的分類，讓分類清單失去篩選內容的意義。
     *
     * @var int
     */
    const MAX_CATEGORIES = 5;

    /**
     * FAQ Service。
     *
     * @var UR_AI_FAQ_Service|null
     */
    private $faq_service;

    /**
     * OpenAI Client。
     *
     * @var UR_AI_OpenAI_Client|null
     */
    private $openai_client;

    /**
     * FAQ 分類／關鍵字建議工具（用來替產生的文章建議分類與標籤）。
     *
     * @var UR_AI_FAQ_Category_Helper|null
     */
    private $category_helper;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->faq_service     = class_exists('UR_AI_FAQ_Service') ? new UR_AI_FAQ_Service() : null;
        $this->openai_client   = class_exists('UR_AI_OpenAI_Client') ? new UR_AI_OpenAI_Client() : null;
        $this->category_helper = class_exists('UR_AI_FAQ_Category_Helper') ? new UR_AI_FAQ_Category_Helper() : null;
    }

    /**
     * 依來源 FAQ ID，反查是否有一篇「已發布」的文章是由這則 FAQ 產生的。
     *
     * 只承認已發布（`publish`）的文章：由「產生文章草稿」建立的文章
     * 一律先是草稿，只有管理者親自審核、編輯並發布後，才代表這篇內容
     * 已經確認可以公開呈現給訪客——與外掛既有「AI 產生的內容一律需
     * 人工審核」原則一致，不會讓還沒審過的草稿內容意外被前台其他
     * 功能（例如知識大考驗答錯複習連結）引用出去。
     *
     * @param int $faq_id 來源 FAQ ID。
     * @return string 已發布文章的網址；找不到時回傳空字串。
     */
    public function find_published_article_url($faq_id) {
        $faq_id = absint($faq_id);

        if ($faq_id <= 0 || !class_exists('WP_Query')) {
            return '';
        }

        $query = new WP_Query(
            array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'meta_key'       => '_ur_ai_source_faq_id',
                'meta_value'     => $faq_id,
                'posts_per_page' => 1,
                'no_found_rows'  => true,
                'fields'         => 'ids',
            )
        );

        if (empty($query->posts)) {
            return '';
        }

        $url = get_permalink($query->posts[0]);

        return is_string($url) ? $url : '';
    }

    /**
     * 依指定 FAQ 建立一篇文章草稿。
     *
     * @param int $faq_id FAQ ID。
     * @return array{ success: bool, post_id?: int, edit_url?: string, message?: string }
     */
    public function create_from_faq($faq_id) {
        $faq_id = absint($faq_id);

        if ($faq_id <= 0) {
            return $this->error(__('FAQ ID 不正確。', 'ur-ai-assistant'));
        }

        if (!$this->faq_service instanceof UR_AI_FAQ_Service) {
            return $this->error(__('FAQ 服務尚未正確載入，請確認外掛檔案是否完整。', 'ur-ai-assistant'));
        }

        if (!$this->openai_client instanceof UR_AI_OpenAI_Client) {
            return $this->error(__('AI 服務尚未正確載入，請確認外掛檔案是否完整。', 'ur-ai-assistant'));
        }

        $faq = $this->faq_service->find($faq_id);

        if (!$faq) {
            return $this->error(__('找不到指定的 FAQ，可能已被刪除。', 'ur-ai-assistant'));
        }

        $question = (string) $this->get_value($faq, 'question', '');
        $answer   = (string) $this->get_value($faq, 'answer', '');

        if ('' === trim($question) || '' === trim($answer)) {
            return $this->error(__('這則 FAQ 的問題或回答內容為空，無法產生文章。', 'ur-ai-assistant'));
        }

        if ($this->source_length($question, $answer) < self::MIN_SOURCE_LENGTH) {
            return $this->error(
                sprintf(
                    /* translators: %d: 最低字數門檻 */
                    __('這則 FAQ 的問題與回答內容加總不足 %d 字，內容可能過於簡略，AI 擴寫時容易自行添加未經查證的內容。建議先補充這則 FAQ 的回答內容，再嘗試產生文章。', 'ur-ai-assistant'),
                    self::MIN_SOURCE_LENGTH
                )
            );
        }

        $result = $this->openai_client->generate_article_from_faq($question, $answer);

        if (empty($result['success'])) {
            return $this->error(
                !empty($result['message']) ? $result['message'] : __('產生文章草稿失敗，請稍後再試。', 'ur-ai-assistant')
            );
        }

        if ($this->article_length($result['content']) < self::MIN_ARTICLE_LENGTH) {
            return $this->error(
                sprintf(
                    /* translators: %d: 最低字數門檻 */
                    __('AI 產生的文章內容不足 %d 字，可能沒有確實展開，品質不佳，請重新嘗試產生。', 'ur-ai-assistant'),
                    self::MIN_ARTICLE_LENGTH
                )
            );
        }

        $categories = $this->suggest_categories($question, $answer);
        $keywords   = $this->suggest_keywords($question, $answer);
        $tags       = array_filter(array_map('trim', explode(',', $keywords)));

        $content = $result['content'] . $this->disclaimer_paragraph($faq_id);

        $postarr = array(
            'post_title'   => $result['title'],
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'post',
            'tags_input'   => $tags,
            'meta_input'   => array(
                '_ur_ai_source_faq_id' => $faq_id,
                '_ur_ai_ai_generated'  => 1,
            ),
        );

        $category_ids = $this->resolve_category_ids($categories);

        if (!empty($category_ids)) {
            $postarr['post_category'] = $category_ids;
        }

        $post_id = wp_insert_post($postarr, true);

        if (is_wp_error($post_id) || !$post_id) {
            return $this->error(__('建立文章草稿失敗，請確認網站的文章功能是否正常。', 'ur-ai-assistant'));
        }

        $post_id = absint($post_id);

        return array(
            'success'    => true,
            'post_id'    => $post_id,
            'edit_url'   => (string) get_edit_post_link($post_id, 'raw'),
            'categories' => $categories,
            'keywords'   => $keywords,
        );
    }

    /**
     * 計算問題＋回答的總字數（先去除 HTML 標籤，避免標籤本身灌水字數）。
     *
     * @param string $question 問題。
     * @param string $answer 回答。
     * @return int
     */
    private function source_length($question, $answer) {
        $text = wp_strip_all_tags($question) . wp_strip_all_tags($answer);

        return function_exists('mb_strlen') ? mb_strlen(trim($text)) : strlen(trim($text));
    }

    /**
     * 計算文章內文字數（先去除 HTML 標籤，避免標籤本身灌水字數）。
     *
     * @param string $content 文章內文（HTML）。
     * @return int
     */
    private function article_length($content) {
        $text = wp_strip_all_tags((string) $content);

        return function_exists('mb_strlen') ? mb_strlen(trim($text)) : strlen(trim($text));
    }

    /**
     * 依來源 FAQ 問答，建議這篇文章的分類（可能有多個，無法載入分類
     * 工具時退回單一的「待分類」，不中斷整個流程）。
     *
     * @param string $question 問題。
     * @param string $answer 回答。
     * @return array 分類名稱陣列。
     */
    private function suggest_categories($question, $answer) {
        if ($this->category_helper instanceof UR_AI_FAQ_Category_Helper) {
            return $this->category_helper->suggest_categories($question, $answer, self::MAX_CATEGORIES);
        }

        return array('待分類');
    }

    /**
     * 依來源 FAQ 問答，建議這篇文章的標籤（無法載入分類工具時回傳空
     * 字串，不中斷整個流程）。
     *
     * @param string $question 問題。
     * @param string $answer 回答。
     * @return string
     */
    private function suggest_keywords($question, $answer) {
        if ($this->category_helper instanceof UR_AI_FAQ_Category_Helper) {
            return $this->category_helper->suggest_keywords($question, $answer);
        }

        return '';
    }

    /**
     * 把多個分類名稱依序轉換成 WordPress 分類 term_id，過濾掉無法解析
     * （例如「待分類」）的項目。
     *
     * @param array $categories 分類名稱陣列。
     * @return array term_id 陣列（已去除重複與 0）。
     */
    private function resolve_category_ids($categories) {
        $ids = array();

        foreach ((array) $categories as $category) {
            $id = $this->resolve_category_id($category);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 依分類名稱找出（或建立）對應的 WordPress 分類 term，回傳 term_id。
     *
     * 「待分類」是 FAQ 分類工具找不到適合分類時的預設值，不需要在文章
     * 分類法裡另外建一個叫「待分類」的分類——直接不指定分類，讓文章
     * 沿用 WordPress 預設分類（通常是「未分類」）即可。
     *
     * @param string $category 分類名稱。
     * @return int term_id，0 表示不指定分類。
     */
    private function resolve_category_id($category) {
        $category = trim((string) $category);

        if ('' === $category || '待分類' === $category) {
            return 0;
        }

        if (!function_exists('term_exists') || !function_exists('wp_insert_term')) {
            return 0;
        }

        $existing = term_exists($category, 'category');

        if (is_array($existing) && !empty($existing['term_id'])) {
            return (int) $existing['term_id'];
        }

        if (is_numeric($existing) && (int) $existing > 0) {
            return (int) $existing;
        }

        $inserted = wp_insert_term($category, 'category');

        if (is_wp_error($inserted) || empty($inserted['term_id'])) {
            return 0;
        }

        return (int) $inserted['term_id'];
    }

    /**
     * 附加在文章末尾的固定提醒段落，明確標示這是 AI 草稿、需人工審核。
     *
     * @param int $faq_id 來源 FAQ ID。
     * @return string
     */
    private function disclaimer_paragraph($faq_id) {
        return "\n\n<p>" . sprintf(
            /* translators: %d: 來源 FAQ ID */
            esc_html__('（本文由 AI 依 FAQ #%d 內容草擬產生，發布前請務必核對事實正確性並自行編輯完善。）', 'ur-ai-assistant'),
            $faq_id
        ) . '</p>';
    }

    /**
     * 組成失敗回應。
     *
     * @param string $message 錯誤訊息。
     * @return array
     */
    private function error($message) {
        return array(
            'success' => false,
            'message' => $message,
        );
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
