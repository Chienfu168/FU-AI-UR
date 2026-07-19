<?php
/**
 * UR AI Assistant OpenAI Client
 *
 * OpenAI API 串接客戶端。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_OpenAI_Client
 */
class UR_AI_OpenAI_Client {

    /**
     * OpenAI Chat Completions API endpoint.
     *
     * @var string
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * 呼叫 OpenAI Chat API。
     *
     * @param string $question 使用者問題。
     * @return array
     */
    public function chat($question) {
        $question = $this->sanitize_question($question);

        if ('' === trim($question)) {
            return $this->error_result(
                __('問題內容不可空白。', 'ur-ai-assistant'),
                'empty_question'
            );
        }

        $api_key = $this->get_api_key();

        if ('' === $api_key) {
            return $this->error_result(
                __('尚未設定 OpenAI API Key。', 'ur-ai-assistant'),
                'api_key_missing'
            );
        }

        $payload = $this->build_payload($question);

        $response = wp_remote_post(
            $this->get_api_endpoint(),
            array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode($payload),
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result(
                $response->get_error_message(),
                'wp_remote_error'
            );
        }

        $status_code = absint(wp_remote_retrieve_response_code($response));
        $body        = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            return $this->handle_api_error($body, $status_code);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return $this->error_result(
                __('OpenAI 回傳格式無法解析。', 'ur-ai-assistant'),
                'invalid_json'
            );
        }

        $answer = $this->extract_answer($decoded);

        if ('' === trim($answer)) {
            return $this->error_result(
                __('OpenAI 回傳內容為空。', 'ur-ai-assistant'),
                'empty_response'
            );
        }

        return array(
            'success'     => true,
            'answer'      => $answer,
            'model'       => isset($decoded['model']) ? sanitize_text_field($decoded['model']) : $this->get_model(),
            'tokens_used' => $this->extract_tokens_used($decoded),
            'raw'         => $decoded,
        );
    }

    /**
     * 後台「AI 對話」：多輪對話，供管理者與 AI 助理腦力激盪知識庫內容
     * 方向。與 chat() 的差異在於這裡帶入完整對話歷史（多輪），且使用
     * 專屬的「對管理者說話」系統提示詞，而非對一般訪客的系統提示詞。
     *
     * @param array $messages 對話紀錄，格式為
     *                        [['role'=>'user'|'assistant','content'=>string]]。
     * @return array
     */
    public function chat_conversation($messages) {
        $messages = $this->sanitize_conversation_messages($messages);

        if (empty($messages)) {
            return $this->error_result(
                __('對話內容不可空白。', 'ur-ai-assistant'),
                'empty_conversation'
            );
        }

        $api_key = $this->get_api_key();

        if ('' === $api_key) {
            return $this->error_result(
                __('尚未設定 OpenAI API Key。', 'ur-ai-assistant'),
                'api_key_missing'
            );
        }

        $payload = array(
            'model'       => $this->get_model(),
            'messages'    => array_merge(
                array(
                    array(
                        'role'    => 'system',
                        'content' => $this->admin_chat_system_prompt(),
                    ),
                ),
                $messages
            ),
            'temperature' => $this->get_temperature(),
            'max_tokens'  => $this->get_max_answer_tokens(),
        );

        /**
         * Filter 後台 AI 對話用的 OpenAI payload。
         *
         * @param array $payload  OpenAI payload。
         * @param array $messages 對話紀錄。
         */
        $payload = apply_filters('ur_ai_admin_chat_openai_payload', $payload, $messages);

        $response = wp_remote_post(
            $this->get_api_endpoint(),
            array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode($payload),
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result($response->get_error_message(), 'wp_remote_error');
        }

        $status_code = absint(wp_remote_retrieve_response_code($response));
        $body        = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            return $this->handle_api_error($body, $status_code);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return $this->error_result(
                __('OpenAI 回傳格式無法解析。', 'ur-ai-assistant'),
                'invalid_json'
            );
        }

        $answer = $this->extract_answer($decoded);

        if ('' === trim($answer)) {
            return $this->error_result(
                __('OpenAI 回傳內容為空。', 'ur-ai-assistant'),
                'empty_response'
            );
        }

        return array(
            'success'     => true,
            'answer'      => $answer,
            'model'       => isset($decoded['model']) ? sanitize_text_field($decoded['model']) : $this->get_model(),
            'tokens_used' => $this->extract_tokens_used($decoded),
        );
    }

    /**
     * 後台「AI 對話」：把整段對話紀錄整理成適合收錄進 FAQ 知識庫的
     * 「標準問題」／「固定回答」草稿建議（最多 5 則）。
     *
     * @param array $messages 對話紀錄，格式同 chat_conversation()。
     * @return array 成功時包含 drafts（[['question'=>string,'answer'=>string]]），
     *               失敗時包含 success=false 與 message。
     */
    public function summarize_conversation_to_faq_drafts($messages) {
        $messages = $this->sanitize_conversation_messages($messages);

        if (empty($messages)) {
            return $this->error_result(
                __('對話內容不可空白，無法整理草稿。', 'ur-ai-assistant'),
                'empty_conversation'
            );
        }

        $api_key = $this->get_api_key();

        if ('' === $api_key) {
            return $this->error_result(
                __('尚未設定 OpenAI API Key。', 'ur-ai-assistant'),
                'api_key_missing'
            );
        }

        $payload = array(
            'model'       => $this->get_model(),
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => $this->admin_chat_summarize_system_prompt(),
                ),
                array(
                    'role'    => 'user',
                    'content' => $this->format_conversation_transcript($messages),
                ),
            ),
            'temperature' => 0.3,
            'max_tokens'  => 2400,
        );

        /**
         * Filter 後台 AI 對話「產生總結草稿」用的 OpenAI payload。
         *
         * @param array $payload  OpenAI payload。
         * @param array $messages 對話紀錄。
         */
        $payload = apply_filters('ur_ai_admin_chat_summarize_openai_payload', $payload, $messages);

        $response = wp_remote_post(
            $this->get_api_endpoint(),
            array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode($payload),
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result($response->get_error_message(), 'wp_remote_error');
        }

        $status_code = absint(wp_remote_retrieve_response_code($response));
        $body        = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            return $this->handle_api_error($body, $status_code);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return $this->error_result(
                __('OpenAI 回傳格式無法解析。', 'ur-ai-assistant'),
                'invalid_json'
            );
        }

        $content = $this->extract_answer($decoded);

        if ('' === trim($content)) {
            return $this->error_result(
                __('OpenAI 回傳內容為空。', 'ur-ai-assistant'),
                'empty_response'
            );
        }

        return $this->parse_faq_drafts_json($content);
    }

    /**
     * 依 FAQ 問答內容，請 OpenAI 產生一則選擇題草稿（供知識大考驗題庫使用）。
     *
     * 與 chat() 使用完全獨立的 system prompt 與 payload，不受後台「AI 助理
     * 系統提示詞」設定影響，避免管理者調整 FAQ 問答語氣時意外影響到出題格式。
     *
     * @param string $faq_question 來源 FAQ 標準問題。
     * @param string $faq_answer   來源 FAQ 固定回答。
     * @return array 成功時包含 question/option_a../correct_option/explanation，
     *               失敗時包含 success=false 與 message。
     */
    public function generate_quiz_question($faq_question, $faq_answer) {
        $faq_question = $this->sanitize_question($faq_question);
        $faq_answer   = is_scalar($faq_answer) ? trim((string) $faq_answer) : '';

        if ('' === $faq_question || '' === $faq_answer) {
            return $this->error_result(
                __('來源 FAQ 內容為空，無法出題。', 'ur-ai-assistant'),
                'empty_source'
            );
        }

        $api_key = $this->get_api_key();

        if ('' === $api_key) {
            return $this->error_result(
                __('尚未設定 OpenAI API Key。', 'ur-ai-assistant'),
                'api_key_missing'
            );
        }

        $payload = array(
            'model'       => $this->get_model(),
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => $this->quiz_system_prompt(),
                ),
                array(
                    'role'    => 'user',
                    'content' => "FAQ 標準問題：{$faq_question}\n\nFAQ 固定回答：{$faq_answer}",
                ),
            ),
            'temperature' => 0.4,
            'max_tokens'  => 700,
        );

        /**
         * Filter 出題用 OpenAI payload。
         *
         * @param array  $payload      OpenAI payload。
         * @param string $faq_question 來源 FAQ 問題。
         * @param string $faq_answer   來源 FAQ 回答。
         */
        $payload = apply_filters('ur_ai_quiz_openai_payload', $payload, $faq_question, $faq_answer);

        $response = wp_remote_post(
            $this->get_api_endpoint(),
            array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode($payload),
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result($response->get_error_message(), 'wp_remote_error');
        }

        $status_code = absint(wp_remote_retrieve_response_code($response));
        $body        = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            return $this->handle_api_error($body, $status_code);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return $this->error_result(__('OpenAI 回傳格式無法解析。', 'ur-ai-assistant'), 'invalid_json');
        }

        $content = $this->extract_answer($decoded);

        if ('' === trim($content)) {
            return $this->error_result(__('OpenAI 回傳內容為空。', 'ur-ai-assistant'), 'empty_response');
        }

        return $this->parse_quiz_json($content);
    }

    /**
     * 依 FAQ 問答內容，請 OpenAI 把一則問答擴寫成一篇適合放在網站部落格／
     * 專欄的完整文章草稿（標題＋內文），供後台「FAQ 知識庫」頁的
     * 「產生文章草稿」功能使用。
     *
     * 與 chat()／generate_quiz_question() 相同，使用完全獨立的 system
     * prompt 與 payload，不受後台「AI 助理系統提示詞」設定影響。
     *
     * @param string $question 來源 FAQ 標準問題。
     * @param string $answer   來源 FAQ 固定回答。
     * @return array 成功時包含 title／content，失敗時包含 success=false 與 message。
     */
    public function generate_article_from_faq($question, $answer) {
        $question = $this->sanitize_question($question);
        $answer   = is_scalar($answer) ? trim((string) $answer) : '';

        if ('' === $question || '' === $answer) {
            return $this->error_result(
                __('來源 FAQ 內容為空，無法產生文章。', 'ur-ai-assistant'),
                'empty_source'
            );
        }

        $api_key = $this->get_api_key();

        if ('' === $api_key) {
            return $this->error_result(
                __('尚未設定 OpenAI API Key。', 'ur-ai-assistant'),
                'api_key_missing'
            );
        }

        $min_length = class_exists('UR_AI_Settings') ? UR_AI_Settings::get_article_min_length() : 300;
        list(, $target_max) = $this->article_length_target($min_length);

        $payload = array(
            'model'       => $this->get_model(),
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => $this->article_system_prompt($min_length),
                ),
                array(
                    'role'    => 'user',
                    'content' => "FAQ 標準問題：{$question}\n\nFAQ 固定回答：{$answer}",
                ),
            ),
            'temperature' => 0.4,
            'max_tokens'  => min(8000, max(2200, $target_max * 2 + 300)),
        );

        /**
         * Filter 「產生文章草稿」用的 OpenAI payload。
         *
         * @param array  $payload  OpenAI payload。
         * @param string $question 來源 FAQ 問題。
         * @param string $answer   來源 FAQ 回答。
         */
        $payload = apply_filters('ur_ai_article_openai_payload', $payload, $question, $answer);

        $response = wp_remote_post(
            $this->get_api_endpoint(),
            array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode($payload),
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result($response->get_error_message(), 'wp_remote_error');
        }

        $status_code = absint(wp_remote_retrieve_response_code($response));
        $body        = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            return $this->handle_api_error($body, $status_code);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return $this->error_result(
                __('OpenAI 回傳格式無法解析。', 'ur-ai-assistant'),
                'invalid_json'
            );
        }

        $content = $this->extract_answer($decoded);

        if ('' === trim($content)) {
            return $this->error_result(
                __('OpenAI 回傳內容為空。', 'ur-ai-assistant'),
                'empty_response'
            );
        }

        return $this->parse_article_json($content);
    }

    /**
     * 依後台設定的「文章最低字數」門檻，計算要求 AI 瞄準的字數區間
     * （下限／上限）。
     *
     * 下限維持不低於 500 字（即使門檻調得比 500 低，也不縮小 AI 原本
     * 就被要求寫出的份量）；上限則是下限再加 400 字的緩衝，避免 AI
     * 剛好卡在門檻邊緣、稍有落差就被品質檢查擋下。門檻維持預設值
     * 300 時，區間即為原本的 500～900 字，行為完全不變。
     *
     * @param int $min_length 後台設定的文章最低字數門檻。
     * @return array{0:int,1:int} array($target_min, $target_max)。
     */
    private function article_length_target($min_length) {
        $min_length = absint($min_length);
        $target_min = max(500, $min_length);
        $target_max = max(900, $min_length + 400);

        return array($target_min, $target_max);
    }

    /**
     * 「產生文章草稿」用的 system prompt：要求輸出嚴格 JSON 格式的
     * 標題＋內文，且明確限制只能依提供的 FAQ 內容擴寫，不可自行捏造。
     *
     * @param int $min_length 後台設定的文章最低字數門檻，用來動態調整
     *                        要求 AI 瞄準的字數區間。
     * @return string
     */
    private function article_system_prompt($min_length = 300) {
        $brand_name = class_exists('UR_AI_Industry_Profiles')
            ? UR_AI_Industry_Profiles::get_active_brand_name()
            : __('AI 助理', 'ur-ai-assistant');

        list($target_min, $target_max) = $this->article_length_target($min_length);

        return implode(
            "\n",
            array(
                sprintf(
                    /* translators: %s: 目前產業別的品牌名稱 */
                    __('你是「%s」網站的內容編輯助手，任務是把提供的一則 FAQ 問答，擴寫成一篇適合放在網站部落格／專欄的完整文章。', 'ur-ai-assistant'),
                    $brand_name
                ),
                '',
                __('擴寫原則：', 'ur-ai-assistant'),
                __('1. 文章內容只能根據提供的 FAQ 問答內容擴充說明（例如補充背景、常見情境、實務注意事項），不可以自行捏造 FAQ 沒有提到的具體法規名稱、稅率、金額或期限。', 'ur-ai-assistant'),
                __('2. 若有需要進一步說明、但 FAQ 沒有提供依據的地方，請用提醒讀者「應洽詢專業人士確認」的方式帶過，不要編造答案。', 'ur-ai-assistant'),
                sprintf(
                    /* translators: 1: 目標字數下限 2: 目標字數上限 */
                    __('3. 文章長度約 %1$d～%2$d 字，段落分明，可視內容需要適度使用小標題。', 'ur-ai-assistant'),
                    $target_min,
                    $target_max
                ),
                __('4. 標題不要直接照抄 FAQ 問題文字，應該是更適合文章閱讀的標題寫法。', 'ur-ai-assistant'),
                __('5. content 欄位請用簡單的 HTML 段落標籤（例如 <p>、<h2>），不要使用 Markdown 語法。', 'ur-ai-assistant'),
                '',
                __('請務必只回傳一個 JSON 物件，不要包含任何 JSON 以外的文字、Markdown 標記或程式碼區塊符號，格式如下：', 'ur-ai-assistant'),
                '{"title":"文章標題","content":"<p>文章內文，使用 HTML 段落標籤</p>"}',
            )
        );
    }

    /**
     * 解析「產生文章草稿」的 JSON 回應，並做基本結構驗證。
     *
     * @param string $content OpenAI 回傳的文字內容。
     * @return array
     */
    private function parse_article_json($content) {
        $content = trim($content);

        // 部分模型仍會包住 ```json ... ``` 區塊，先行剝除。
        $content = preg_replace('/^```(?:json)?/i', '', $content);
        $content = preg_replace('/```$/', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);

        if (!is_array($data) || empty($data['title']) || empty($data['content']) || !is_string($data['title']) || !is_string($data['content'])) {
            return $this->error_result(
                __('AI 回傳內容不是有效的 JSON 格式，無法自動建立文章草稿。', 'ur-ai-assistant'),
                'invalid_article_json'
            );
        }

        return array(
            'success' => true,
            'title'   => sanitize_text_field($data['title']),
            'content' => wp_kses_post($data['content']),
        );
    }

    /**
     * 出題用 system prompt：要求輸出嚴格 JSON 格式的單選題。
     *
     * @return string
     */
    private function quiz_system_prompt() {
        return implode(
            "\n",
            array(
                '你是「都更危老 AI 助理」知識庫的出題助手，任務是依據提供的 FAQ 問答內容，',
                '出一題「四選一單選題」，用來測試民眾對都市更新／危老重建基礎知識的理解。',
                '',
                '出題原則：',
                '1. 題目與正確答案必須完全依據提供的 FAQ 內容，不可自行添加 FAQ 沒有提到的資訊或數字。',
                '2. 四個選項長度應盡量接近，避免用「選項特別長」或「選項特別完整」暗示正確答案。',
                '3. 錯誤選項（誘答）應該是「看起來合理、但確實錯誤」的內容，不可以是明顯荒謬或離題的選項。',
                '4. 不要在題目或選項中直接引用「根據 FAQ」「依本知識庫」等字眼，題目應該像是獨立的知識問答。',
                '5. explanation 欄位請用 1-2 句話說明為什麼正確答案是對的，供作答後顯示。',
                '',
                '請務必只回傳一個 JSON 物件，不要包含任何 JSON 以外的文字、Markdown 標記或程式碼區塊符號，格式如下：',
                '{"question":"題目文字","option_a":"選項A","option_b":"選項B","option_c":"選項C","option_d":"選項D","correct_option":"a","explanation":"簡短說明","difficulty":"medium"}',
                'difficulty 請填 easy、medium 或 hard 三者之一，依內容複雜度自行判斷。',
            )
        );
    }

    /**
     * 解析出題 JSON 回應，並做基本結構驗證。
     *
     * @param string $content OpenAI 回傳的文字內容。
     * @return array
     */
    private function parse_quiz_json($content) {
        $content = trim($content);

        // 部分模型仍會包住 ```json ... ``` 區塊，先行剝除。
        $content = preg_replace('/^```(?:json)?/i', '', $content);
        $content = preg_replace('/```$/', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return $this->error_result(
                __('AI 回傳內容不是有效的 JSON 格式，無法自動建立題目。', 'ur-ai-assistant'),
                'invalid_quiz_json'
            );
        }

        $required = array('question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option');

        foreach ($required as $field) {
            if (empty($data[$field]) || !is_string($data[$field])) {
                return $this->error_result(
                    __('AI 回傳的題目缺少必要欄位，無法自動建立題目。', 'ur-ai-assistant'),
                    'incomplete_quiz_json'
                );
            }
        }

        $correct_option = strtolower(trim((string) $data['correct_option']));

        if (!in_array($correct_option, array('a', 'b', 'c', 'd'), true)) {
            return $this->error_result(
                __('AI 回傳的正確答案格式無法辨識。', 'ur-ai-assistant'),
                'invalid_correct_option'
            );
        }

        $difficulty = isset($data['difficulty']) ? strtolower(trim((string) $data['difficulty'])) : 'medium';

        if (!in_array($difficulty, array('easy', 'medium', 'hard'), true)) {
            $difficulty = 'medium';
        }

        return array(
            'success'        => true,
            'question'       => sanitize_textarea_field($data['question']),
            'option_a'       => sanitize_textarea_field($data['option_a']),
            'option_b'       => sanitize_textarea_field($data['option_b']),
            'option_c'       => sanitize_textarea_field($data['option_c']),
            'option_d'       => sanitize_textarea_field($data['option_d']),
            'correct_option' => $correct_option,
            'explanation'    => isset($data['explanation']) ? sanitize_textarea_field((string) $data['explanation']) : '',
            'difficulty'     => $difficulty,
        );
    }

    /**
     * 清理後台「AI 對話」的對話紀錄。
     *
     * 只保留 role 為 user／assistant、content 非空白的訊息；並限制帶入
     * 的輪數上限，避免對話越聊越長時，每次呼叫都把完整歷史紀錄送給
     * OpenAI，造成 token 用量與費用不成比例增加——超過上限時只保留
     * 最近的訊息，捨棄最舊的內容。
     *
     * @param mixed $messages 前端送來的原始對話紀錄。
     * @return array
     */
    private function sanitize_conversation_messages($messages) {
        if (!is_array($messages)) {
            return array();
        }

        $sanitized = array();

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $role = isset($message['role']) ? sanitize_key($message['role']) : '';

            if (!in_array($role, array('user', 'assistant'), true)) {
                continue;
            }

            $content = isset($message['content']) ? $this->sanitize_question($message['content']) : '';

            if ('' === $content) {
                continue;
            }

            $sanitized[] = array(
                'role'    => $role,
                'content' => $content,
            );
        }

        $max_messages = 20;

        if (count($sanitized) > $max_messages) {
            $sanitized = array_slice($sanitized, -$max_messages);
        }

        return $sanitized;
    }

    /**
     * 後台「AI 對話」的系統提示詞。
     *
     * 沿用目前啟用中產業別的人設與知識範圍（get_system_prompt()），
     * 再疊加「對話對象是管理者、目的是腦力激盪知識庫內容」的補充
     * 說明，確保不同產業別安裝時，這個後台對話功能討論的範圍會跟著
     * 目前的產業別走，而不是固定寫死某一種產業的內容。
     *
     * @return string
     */
    private function admin_chat_system_prompt() {
        $brand_name = class_exists('UR_AI_Industry_Profiles')
            ? UR_AI_Industry_Profiles::get_active_brand_name()
            : __('AI 助理', 'ur-ai-assistant');

        return implode(
            "\n",
            array(
                $this->get_system_prompt(),
                '',
                sprintf(
                    /* translators: %s: 目前產業別的品牌名稱 */
                    __('現在的對話對象是網站管理者，不是一般訪客，目的是協助管理者腦力激盪、討論「%s」知識庫（FAQ）還需要補充哪些內容。', 'ur-ai-assistant'),
                    $brand_name
                ),
                __('請根據上述知識範圍，與管理者討論可能的常見問題、協助釐清正確的說明方式、或指出目前知識庫可能的缺口，可以主動提出建議的問題方向。', 'ur-ai-assistant'),
                __('不確定的具體法規名稱、稅率、金額或期限數字，請明確告知管理者需要自行查證，不要為了讓回答看起來完整而自行捏造。', 'ur-ai-assistant'),
                __('這是內部工作對話，不需要顧慮一般訪客的閱讀體驗，可以更直接、更有條理地列點討論。', 'ur-ai-assistant'),
            )
        );
    }

    /**
     * 後台「AI 對話」的「產生總結草稿」系統提示詞，要求輸出嚴格 JSON
     * 格式的 FAQ 草稿清單。
     *
     * @return string
     */
    private function admin_chat_summarize_system_prompt() {
        $min_length = $this->get_min_faq_draft_answer_length();

        return implode(
            "\n",
            array(
                '你是知識庫內容整理助手。請閱讀提供的完整對話紀錄（管理者與 AI 助理討論知識庫內容方向的對話），',
                '從中整理出適合直接收錄進 FAQ 知識庫的「標準問題」與「固定回答」草稿。',
                '',
                '整理原則：',
                '1. 每一則草稿的「標準問題」應該是訪客可能會問的自然提問方式，不要直接複製對話裡管理者的口語提問或指示句。',
                '2. 「固定回答」內容只能根據對話中實際討論過、有共識或有明確依據的內容整理，不可以自行延伸對話中沒有提到的具體數字、法規名稱或期限。',
                sprintf(
                    /* translators: %d: 固定回答最低字數門檻 */
                    __('3. 「固定回答」必須是完整、具體的一段說明，至少 %d 字以上（建議涵蓋 2～3 句完整內容，例如：結論＋原因或條件＋注意事項），讓訪客不需要再追問就能理解重點；不可以只用一句話簡短帶過（例如「需要準備相關文件即可」這種沒有具體內容的空泛回答）。', 'ur-ai-assistant'),
                    $min_length
                ),
                '4. 如果對話中沒有討論出足夠明確、足夠完整的內容可以整理成一則完整的問答，就不要勉強生成，寧可少於預期則數，也不要生成內容空洞或過於簡略的草稿。',
                '5. 最多整理 5 則，依討論的完整度與重要性排序。',
                '',
                '請務必只回傳一個 JSON 物件，不要包含任何 JSON 以外的文字、Markdown 標記或程式碼區塊符號，格式如下：',
                '{"drafts":[{"question":"標準問題文字","answer":"固定回答文字"}]}',
                '若對話內容完全不足以整理出任何一則草稿，請回傳 {"drafts":[]}。',
            )
        );
    }

    /**
     * 把對話紀錄轉成給「產生總結草稿」用的純文字逐字稿。
     *
     * @param array $messages 已清理過的對話紀錄。
     * @return string
     */
    private function format_conversation_transcript($messages) {
        $lines = array();

        foreach ($messages as $message) {
            $speaker = 'assistant' === $message['role']
                ? __('AI 助理', 'ur-ai-assistant')
                : __('管理者', 'ur-ai-assistant');

            $lines[] = $speaker . '：' . $message['content'];
        }

        return implode("\n\n", $lines);
    }

    /**
     * 解析「產生總結草稿」的 JSON 回應，並做基本結構驗證。
     *
     * @param string $content OpenAI 回傳的文字內容。
     * @return array
     */
    private function parse_faq_drafts_json($content) {
        $content = trim($content);

        // 部分模型仍會包住 ```json ... ``` 區塊，先行剝除。
        $content = preg_replace('/^```(?:json)?/i', '', $content);
        $content = preg_replace('/```$/', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['drafts']) || !is_array($data['drafts'])) {
            return $this->error_result(
                __('AI 回傳內容不是有效的 JSON 格式，無法自動整理草稿。', 'ur-ai-assistant'),
                'invalid_summary_json'
            );
        }

        $drafts = array();

        foreach ($data['drafts'] as $draft) {
            if (!is_array($draft)) {
                continue;
            }

            $question = isset($draft['question']) && is_string($draft['question']) ? sanitize_textarea_field($draft['question']) : '';
            $answer   = isset($draft['answer']) && is_string($draft['answer']) ? sanitize_textarea_field($draft['answer']) : '';

            if ('' === trim($question) || '' === trim($answer)) {
                continue;
            }

            if ($this->text_length($answer) < $this->get_min_faq_draft_answer_length()) {
                continue;
            }

            $drafts[] = array(
                'question' => $question,
                'answer'   => $answer,
            );
        }

        // 最多保留 5 則，避免單次整理出過多草稿造成後台審核負擔過重。
        $drafts = array_slice($drafts, 0, 5);

        return array(
            'success' => true,
            'drafts'  => $drafts,
        );
    }

    /**
     * 計算文字字數（先去除 HTML 標籤，避免標籤本身灌水字數）。
     *
     * @param string $text 文字。
     * @return int
     */
    private function text_length($text) {
        $text = wp_strip_all_tags((string) $text);

        return function_exists('mb_strlen') ? mb_strlen(trim($text)) : strlen(trim($text));
    }

    /**
     * 建立 OpenAI payload.
     *
     * @param string $question 使用者問題。
     * @return array
     */
    private function build_payload($question) {
        $payload = array(
            'model'       => $this->get_model(),
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => $this->get_system_prompt(),
                ),
                array(
                    'role'    => 'user',
                    'content' => $question,
                ),
            ),
            'temperature' => $this->get_temperature(),
            'max_tokens'  => $this->get_max_answer_tokens(),
        );

        /**
         * Filter OpenAI payload.
         *
         * @param array  $payload OpenAI payload.
         * @param string $question User question.
         */
        return apply_filters('ur_ai_openai_payload', $payload, $question);
    }

    /**
     * 處理 API 錯誤。
     *
     * @param string $body API response body.
     * @param int    $status_code HTTP status code.
     * @return array
     */
    private function handle_api_error($body, $status_code) {
        $decoded = json_decode($body, true);

        $message = __('OpenAI API 呼叫失敗。', 'ur-ai-assistant');
        $code    = 'api_error';

        if (is_array($decoded) && isset($decoded['error'])) {
            if (!empty($decoded['error']['message'])) {
                $message = sanitize_textarea_field($decoded['error']['message']);
            }

            if (!empty($decoded['error']['code'])) {
                $code = sanitize_key($decoded['error']['code']);
            } elseif (!empty($decoded['error']['type'])) {
                $code = sanitize_key($decoded['error']['type']);
            }
        }

        return array(
            'success'     => false,
            'answer'      => '',
            'message'     => $message,
            'error_code'  => $code,
            'status_code' => absint($status_code),
        );
    }

    /**
     * 從 API 回傳中取出回答。
     *
     * @param array $decoded API decoded body.
     * @return string
     */
    private function extract_answer($decoded) {
        if (
            isset($decoded['choices'][0]['message']['content']) &&
            is_string($decoded['choices'][0]['message']['content'])
        ) {
            return trim($decoded['choices'][0]['message']['content']);
        }

        return '';
    }

    /**
     * 取得 token 使用量。
     *
     * @param array $decoded API decoded body.
     * @return int
     */
    private function extract_tokens_used($decoded) {
        if (isset($decoded['usage']['total_tokens'])) {
            return absint($decoded['usage']['total_tokens']);
        }

        return 0;
    }

    /**
     * 取得呼叫 AI 服務用的憑證。
     *
     * 預設（未啟用代管服務）沿用既有行為：直接使用管理者自行填寫的
     * OpenAI API Key。若啟用「使用代管服務」，改回傳代管服務的授權碼
     * ——外層呼叫端（chat()／chat_conversation() 等）完全不需要知道
     * 目前是哪一種模式，一律當作「Bearer 憑證」放進 Authorization
     * header 即可，行為與升級前完全相同。
     *
     * @return string
     */
    private function get_api_key() {
        if (!class_exists('UR_AI_Settings')) {
            return '';
        }

        if (UR_AI_Settings::is_hosted_ai_service_enabled()) {
            return trim((string) UR_AI_Settings::get_hosted_service_token());
        }

        return trim((string) UR_AI_Settings::get_api_key());
    }

    /**
     * 取得呼叫 AI 服務用的 API 端點。
     *
     * 預設（未啟用代管服務）沿用既有行為：直接呼叫 OpenAI 官方端點
     * （self::API_ENDPOINT）。若啟用「使用代管服務」且已填寫代管服務
     * 端點網址，則改呼叫該端點；端點網址留空時，即使切換到代管模式
     * 也安全退回 OpenAI 官方端點，不會因為設定不完整而呼叫到空網址。
     *
     * @return string
     */
    private function get_api_endpoint() {
        if (class_exists('UR_AI_Settings') && UR_AI_Settings::is_hosted_ai_service_enabled()) {
            $endpoint = UR_AI_Settings::get_hosted_service_endpoint();

            if ('' !== $endpoint) {
                return $endpoint;
            }
        }

        return self::API_ENDPOINT;
    }

    /**
     * 取得模型。
     *
     * @return string
     */
    private function get_model() {
        if (class_exists('UR_AI_Settings')) {
            $model = trim((string) UR_AI_Settings::get_model());

            if ('' !== $model) {
                return $model;
            }
        }

        return 'gpt-4o-mini';
    }

    /**
     * 「AI 對話」產生總結草稿時，每則草稿「固定回答」的最低字數門檻。
     *
     * 開放後台調整（「功能設定」頁，鍵名 admin_chat_min_draft_answer_
     * length），不同網站經營者對「多短算太精簡」的標準不一定相同。
     * 預設 60 字沿用這個防護機制剛上線時依實際 FAQ 內容包長度估算的
     * 水準。
     *
     * @return int
     */
    private function get_min_faq_draft_answer_length() {
        if (class_exists('UR_AI_Settings')) {
            return UR_AI_Settings::get_admin_chat_min_draft_answer_length();
        }

        return 60;
    }

    /**
     * 取得 temperature.
     *
     * @return float
     */
    private function get_temperature() {
        if (class_exists('UR_AI_Settings')) {
            return (float) UR_AI_Settings::get_temperature();
        }

        return 0.3;
    }

    /**
     * 取得最大回答 tokens.
     *
     * @return int
     */
    private function get_max_answer_tokens() {
        if (class_exists('UR_AI_Settings')) {
            $tokens = absint(UR_AI_Settings::get_max_answer_tokens());

            if ($tokens > 0) {
                return $tokens;
            }
        }

        return 1200;
    }

    /**
     * 取得 system prompt.
     *
     * @return string
     */
    private function get_system_prompt() {
        if (class_exists('UR_AI_Settings')) {
            $prompt = trim((string) UR_AI_Settings::get_system_prompt());

            if ('' !== $prompt) {
                return $prompt;
            }
        }

        return $this->default_system_prompt();
    }

    /**
     * 預設 system prompt.
     *
     * @return string
     */
    private function default_system_prompt() {
        return implode(
            "\n",
            array(
                '你是「都更危老 AI 助理」，專門協助台灣民眾理解台灣都市更新、危老重建、更新會、自主更新、權利變換、協議合建、都市更新程序與相關基礎知識。',
                '請使用繁體中文回答，語氣客觀、中立、清楚、白話，適合一般民眾閱讀。',
                '回答應以一般性說明、概念整理、流程介紹與風險提醒為主。',
                '不可假裝已審閱使用者的個案文件、契約、權利變換計畫、估價報告、建築圖說、土地建物謄本或會議紀錄。',
                '不可直接替使用者作成法律、估價、建築、稅務、登記、權利分配、訴訟勝敗或個案是否合理之判斷。',
                '若問題涉及個案權益、契約內容、財產分配、訴訟或專業判斷，請提醒使用者應洽詢律師、建築師、估價師、地政士或都市更新專業人士。',
                '若問題明顯超出都市更新、危老重建與不動產重建基礎知識範圍，請禮貌說明本工具主要回答都更危老相關問題。',
            )
        );
    }

    /**
     * 清理問題。
     *
     * @param mixed $question 原始問題。
     * @return string
     */
    private function sanitize_question($question) {
        if (class_exists('UR_AI_Security')) {
            return UR_AI_Security::sanitize_question($question);
        }

        $question = is_scalar($question) ? (string) $question : '';
        $question = wp_strip_all_tags($question);
        $question = sanitize_textarea_field($question);
        $question = preg_replace('/\s+/u', ' ', $question);

        return trim($question);
    }

    /**
     * 錯誤結果。
     *
     * @param string $message 錯誤訊息。
     * @param string $code 錯誤代碼。
     * @return array
     */
    private function error_result($message, $code = 'error') {
        return array(
            'success'    => false,
            'answer'     => '',
            'message'    => $message,
            'error_code' => sanitize_key($code),
        );
    }
}