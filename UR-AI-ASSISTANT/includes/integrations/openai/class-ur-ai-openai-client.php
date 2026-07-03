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