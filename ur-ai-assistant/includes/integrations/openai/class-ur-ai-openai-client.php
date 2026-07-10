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
            self::API_ENDPOINT,
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
            self::API_ENDPOINT,
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
     * 取得 API Key。
     *
     * @return string
     */
    private function get_api_key() {
        if (!class_exists('UR_AI_Settings')) {
            return '';
        }

        return trim((string) UR_AI_Settings::get_api_key());
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