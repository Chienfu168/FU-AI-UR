<?php
/**
 * UR AI Assistant Quiz Draft Service
 *
 * 依現有 FAQ 內容，透過 AI 產生知識大考驗題目草稿。
 *
 * 沿用 FAQ 模組「AI 起草、人工審核後才能上線」的既有慣例：
 * 新產生的題目一律為 status=inactive、review_status=draft，
 * 必須經後台人工審核通過並改為 approved／active 後，才會被前台抽題抽到。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Quiz_Draft_Service
 */
class UR_AI_Quiz_Draft_Service {

    /**
     * Quiz Service.
     *
     * @var UR_AI_Quiz_Service|null
     */
    private $quiz_service = null;

    /**
     * FAQ Service.
     *
     * @var UR_AI_FAQ_Service|null
     */
    private $faq_service = null;

    /**
     * OpenAI Client.
     *
     * @var UR_AI_OpenAI_Client|null
     */
    private $openai_client = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->quiz_service = class_exists('UR_AI_Quiz_Service') ? new UR_AI_Quiz_Service() : null;
        $this->faq_service   = class_exists('UR_AI_FAQ_Service') ? new UR_AI_FAQ_Service() : null;
        $this->openai_client = class_exists('UR_AI_OpenAI_Client') ? new UR_AI_OpenAI_Client() : null;
    }

    /**
     * 依單一 FAQ 產生一則題目草稿。
     *
     * @param int $faq_id FAQ ID。
     * @return array array('success'=>bool, 'question_id'=>int, 'message'=>string)。
     */
    public function generate_from_faq($faq_id) {
        $faq_id = absint($faq_id);

        if ($faq_id <= 0) {
            return array('success' => false, 'question_id' => 0, 'message' => __('無效的 FAQ ID。', 'ur-ai-assistant'));
        }

        if (!$this->quiz_service instanceof UR_AI_Quiz_Service || !$this->faq_service instanceof UR_AI_FAQ_Service) {
            return array('success' => false, 'question_id' => 0, 'message' => __('題庫或 FAQ 服務尚未正確載入。', 'ur-ai-assistant'));
        }

        if (!$this->openai_client instanceof UR_AI_OpenAI_Client) {
            return array('success' => false, 'question_id' => 0, 'message' => __('AI 服務尚未正確載入。', 'ur-ai-assistant'));
        }

        $faq = $this->faq_service->find($faq_id);

        if (!$faq) {
            return array('success' => false, 'question_id' => 0, 'message' => __('找不到指定的 FAQ。', 'ur-ai-assistant'));
        }

        $question = (string) $this->get_value($faq, 'question', '');
        $answer   = (string) $this->get_value($faq, 'answer', '');
        $category = (string) $this->get_value($faq, 'category', '');

        $result = $this->openai_client->generate_quiz_question($question, $answer);

        if (empty($result['success'])) {
            $message = isset($result['message']) ? $result['message'] : __('AI 出題失敗。', 'ur-ai-assistant');

            return array('success' => false, 'question_id' => 0, 'message' => $message);
        }

        $question_id = $this->quiz_service->create_question(
            array(
                'question'       => $result['question'],
                'option_a'       => $result['option_a'],
                'option_b'       => $result['option_b'],
                'option_c'       => $result['option_c'],
                'option_d'       => $result['option_d'],
                'correct_option' => $result['correct_option'],
                'explanation'    => $result['explanation'],
                'difficulty'     => $result['difficulty'],
                'category'       => $category,
                'source_faq_id'  => $faq_id,
                'status'         => 'inactive',
                'review_status'  => 'draft',
                'source'         => 'ai_faq',
                'admin_note'     => sprintf(
                    /* translators: %d: FAQ ID */
                    __('由 FAQ #%d 使用 AI 自動出題。請人工檢查題目、選項與正確答案是否正確後，再核准並啟用。', 'ur-ai-assistant'),
                    $faq_id
                ),
            )
        );

        if ($question_id <= 0) {
            return array('success' => false, 'question_id' => 0, 'message' => __('題目已產生但寫入資料庫失敗。', 'ur-ai-assistant'));
        }

        return array('success' => true, 'question_id' => $question_id, 'message' => __('已產生題目草稿，請至題庫審核。', 'ur-ai-assistant'));
    }

    /**
     * 依一篇「FAQ 產生的文章」（已發布）產生一則題目草稿。
     *
     * 文章內容通常比原始 FAQ 固定回答更完整（擴寫過的說明、情境舉例
     * 等），適合用來出更豐富的題目；但只接受由本外掛「產生文章草稿」
     * 功能建立、且已經管理者審核發布的文章（透過 `_ur_ai_source_faq_id`
     * meta 判斷），不接受任意 WordPress 文章，避免出題內容失去「已經
     * 人工審核過」的品質保證。
     *
     * 題目的 source_faq_id 仍指向原始 FAQ（而不是另外新增文章欄位），
     * 這樣既有的「答錯時顯示相關 FAQ／文章連結」邏輯不需要任何修改，
     * 自動就能沿用。
     *
     * @param int $post_id WordPress 文章 ID。
     * @return array array('success'=>bool, 'question_id'=>int, 'message'=>string)。
     */
    public function generate_from_article($post_id) {
        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return array('success' => false, 'question_id' => 0, 'message' => __('無效的文章 ID。', 'ur-ai-assistant'));
        }

        if (!$this->quiz_service instanceof UR_AI_Quiz_Service || !$this->faq_service instanceof UR_AI_FAQ_Service) {
            return array('success' => false, 'question_id' => 0, 'message' => __('題庫或 FAQ 服務尚未正確載入。', 'ur-ai-assistant'));
        }

        if (!$this->openai_client instanceof UR_AI_OpenAI_Client) {
            return array('success' => false, 'question_id' => 0, 'message' => __('AI 服務尚未正確載入。', 'ur-ai-assistant'));
        }

        $post = get_post($post_id);

        if (!$post || 'publish' !== $post->post_status) {
            return array('success' => false, 'question_id' => 0, 'message' => __('找不到指定的文章，或該文章尚未發布。', 'ur-ai-assistant'));
        }

        $faq_id = absint(get_post_meta($post_id, '_ur_ai_source_faq_id', true));

        if ($faq_id <= 0) {
            return array('success' => false, 'question_id' => 0, 'message' => __('這篇文章不是由本外掛的「產生文章草稿」功能建立，無法依此出題。', 'ur-ai-assistant'));
        }

        $faq = $this->faq_service->find($faq_id);

        if (!$faq) {
            return array('success' => false, 'question_id' => 0, 'message' => __('這篇文章的來源 FAQ 已被刪除，無法依此出題。', 'ur-ai-assistant'));
        }

        $category = (string) $this->get_value($faq, 'category', '');
        $title    = (string) $post->post_title;
        $content  = wp_strip_all_tags((string) $post->post_content);

        $result = $this->openai_client->generate_quiz_question($title, $content);

        if (empty($result['success'])) {
            $message = isset($result['message']) ? $result['message'] : __('AI 出題失敗。', 'ur-ai-assistant');

            return array('success' => false, 'question_id' => 0, 'message' => $message);
        }

        $question_id = $this->quiz_service->create_question(
            array(
                'question'       => $result['question'],
                'option_a'       => $result['option_a'],
                'option_b'       => $result['option_b'],
                'option_c'       => $result['option_c'],
                'option_d'       => $result['option_d'],
                'correct_option' => $result['correct_option'],
                'explanation'    => $result['explanation'],
                'difficulty'     => $result['difficulty'],
                'category'       => $category,
                'source_faq_id'  => $faq_id,
                'status'         => 'inactive',
                'review_status'  => 'draft',
                'source'         => 'ai_article',
                'admin_note'     => sprintf(
                    /* translators: 1: 文章 ID 2: 來源 FAQ ID */
                    __('由文章 #%1$d（來源 FAQ #%2$d）使用 AI 自動出題。請人工檢查題目、選項與正確答案是否正確後，再核准並啟用。', 'ur-ai-assistant'),
                    $post_id,
                    $faq_id
                ),
            )
        );

        if ($question_id <= 0) {
            return array('success' => false, 'question_id' => 0, 'message' => __('題目已產生但寫入資料庫失敗。', 'ur-ai-assistant'));
        }

        return array('success' => true, 'question_id' => $question_id, 'message' => __('已產生題目草稿，請至題庫審核。', 'ur-ai-assistant'));
    }

    /**
     * 批次依多篇文章產生題目草稿。
     *
     * @param array $post_ids 文章 ID 陣列。
     * @return array array('created'=>int, 'failed'=>int, 'messages'=>array)。
     */
    public function generate_batch_from_articles($post_ids) {
        $post_ids = is_array($post_ids) ? array_map('absint', $post_ids) : array();
        $post_ids = array_values(array_unique(array_filter($post_ids)));

        $created  = 0;
        $failed   = 0;
        $messages = array();

        foreach ($post_ids as $post_id) {
            $result = $this->generate_from_article($post_id);

            if (!empty($result['success'])) {
                $created++;
            } else {
                $failed++;
                $messages[] = sprintf('文章 #%d：%s', $post_id, $result['message']);
            }
        }

        return array(
            'created'  => $created,
            'failed'   => $failed,
            'messages' => $messages,
        );
    }

    /**
     * 批次依多個 FAQ 產生題目草稿。
     *
     * @param array $faq_ids FAQ ID 陣列。
     * @return array array('created'=>int, 'failed'=>int, 'messages'=>array)。
     */
    public function generate_batch($faq_ids) {
        $faq_ids = is_array($faq_ids) ? array_map('absint', $faq_ids) : array();
        $faq_ids = array_values(array_unique(array_filter($faq_ids)));

        $created  = 0;
        $failed   = 0;
        $messages = array();

        foreach ($faq_ids as $faq_id) {
            $result = $this->generate_from_faq($faq_id);

            if (!empty($result['success'])) {
                $created++;
            } else {
                $failed++;
                $messages[] = sprintf('FAQ #%d：%s', $faq_id, $result['message']);
            }
        }

        return array(
            'created'  => $created,
            'failed'   => $failed,
            'messages' => $messages,
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
