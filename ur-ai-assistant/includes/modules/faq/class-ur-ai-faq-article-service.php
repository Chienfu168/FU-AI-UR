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
     * 建構子。
     */
    public function __construct() {
        $this->faq_service   = class_exists('UR_AI_FAQ_Service') ? new UR_AI_FAQ_Service() : null;
        $this->openai_client = class_exists('UR_AI_OpenAI_Client') ? new UR_AI_OpenAI_Client() : null;
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

        $content = $result['content'] . $this->disclaimer_paragraph($faq_id);

        $post_id = wp_insert_post(
            array(
                'post_title'   => $result['title'],
                'post_content' => $content,
                'post_status'  => 'draft',
                'post_type'    => 'post',
                'meta_input'   => array(
                    '_ur_ai_source_faq_id' => $faq_id,
                    '_ur_ai_ai_generated'  => 1,
                ),
            ),
            true
        );

        if (is_wp_error($post_id) || !$post_id) {
            return $this->error(__('建立文章草稿失敗，請確認網站的文章功能是否正常。', 'ur-ai-assistant'));
        }

        $post_id = absint($post_id);

        return array(
            'success'  => true,
            'post_id'  => $post_id,
            'edit_url' => (string) get_edit_post_link($post_id, 'raw'),
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
