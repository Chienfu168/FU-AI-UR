<?php
/**
 * UR AI Assistant LINE Client
 *
 * 呼叫 LINE Messaging API 回覆訊息：reply（用 replyToken 回覆，不計入
 * 主動推播額度）與 push（主動推播，用於轉真人通知等）。所有回覆都是
 * 純文字（LINE text message），符合客服回覆原則（不使用 Markdown）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Client
 */
class UR_AI_Line_Client {

    /**
     * reply API 端點。
     */
    const REPLY_ENDPOINT = 'https://api.line.me/v2/bot/message/reply';

    /**
     * push API 端點。
     */
    const PUSH_ENDPOINT = 'https://api.line.me/v2/bot/message/push';

    /**
     * Channel access token.
     *
     * @var string
     */
    private $access_token;

    /**
     * 建構子。
     *
     * @param string $access_token Channel access token。
     */
    public function __construct($access_token = '') {
        $this->access_token = (string) $access_token;
    }

    /**
     * 以 replyToken 回覆文字訊息。
     *
     * @param string $reply_token replyToken。
     * @param string $text        回覆文字。
     * @return bool
     */
    public function reply_text($reply_token, $text) {
        $reply_token = trim((string) $reply_token);
        $text        = $this->normalize_text($text);

        if ('' === $reply_token || '' === $text) {
            return false;
        }

        return $this->post(
            self::REPLY_ENDPOINT,
            array(
                'replyToken' => $reply_token,
                'messages'   => array(
                    array('type' => 'text', 'text' => $text),
                ),
            )
        );
    }

    /**
     * 主動推播文字訊息（轉真人通知等）。
     *
     * @param string $to   目標 userId／groupId。
     * @param string $text 推播文字。
     * @return bool
     */
    public function push_text($to, $text) {
        $to   = trim((string) $to);
        $text = $this->normalize_text($text);

        if ('' === $to || '' === $text) {
            return false;
        }

        return $this->post(
            self::PUSH_ENDPOINT,
            array(
                'to'       => $to,
                'messages' => array(
                    array('type' => 'text', 'text' => $text),
                ),
            )
        );
    }

    /**
     * 送出請求到 LINE API。
     *
     * @param string $endpoint 端點。
     * @param array  $payload  payload。
     * @return bool 是否 2xx 成功。
     */
    private function post($endpoint, array $payload) {
        if ('' === trim($this->access_token)) {
            return false;
        }

        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode($payload),
            )
        );

        if (is_wp_error($response)) {
            return false;
        }

        $code = absint(wp_remote_retrieve_response_code($response));

        return $code >= 200 && $code < 300;
    }

    /**
     * 正規化回覆文字：LINE 單則 text 上限 5000 字，超過截斷。
     *
     * @param string $text 原始文字。
     * @return string
     */
    private function normalize_text($text) {
        $text = trim((string) $text);

        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            if (mb_strlen($text, 'UTF-8') > 5000) {
                $text = mb_substr($text, 0, 4997, 'UTF-8') . '...';
            }

            return $text;
        }

        if (strlen($text) > 5000) {
            $text = substr($text, 0, 4997) . '...';
        }

        return $text;
    }
}
