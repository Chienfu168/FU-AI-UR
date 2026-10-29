<?php
/**
 * UR AI Assistant LINE Event Router
 *
 * 解析 LINE webhook 的 events 陣列，分派到對話服務。
 *
 * P1 範圍：1:1 的文字訊息與加好友事件。群組 @、圖片／語音等非文字
 * 訊息屬 P2，這裡直接略過（不回覆）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Event_Router
 */
class UR_AI_Line_Event_Router {

    /**
     * 對話服務。
     *
     * @var object
     */
    private $conversation;

    /**
     * 建構子。
     *
     * @param object|null $conversation 對話服務（可注入，測試用）。
     */
    public function __construct($conversation = null) {
        $this->conversation = $conversation
            ?: (class_exists('UR_AI_Line_Conversation_Service') ? new UR_AI_Line_Conversation_Service() : null);
    }

    /**
     * 分派多個事件。
     *
     * @param array $events events 陣列。
     * @return void
     */
    public function dispatch($events) {
        if (!is_array($events) || !$this->conversation) {
            return;
        }

        foreach ($events as $event) {
            $this->dispatch_one($event);
        }
    }

    /**
     * 分派單一事件。
     *
     * @param array $event 單一事件。
     * @return void
     */
    public function dispatch_one($event) {
        if (!is_array($event)) {
            return;
        }

        $type        = isset($event['type']) ? (string) $event['type'] : '';
        $reply_token = isset($event['replyToken']) ? (string) $event['replyToken'] : '';
        $source      = isset($event['source']) && is_array($event['source']) ? $event['source'] : array();
        $source_type = isset($source['type']) ? (string) $source['type'] : '';
        $user_id     = isset($source['userId']) ? (string) $source['userId'] : '';

        if ('follow' === $type) {
            $this->conversation->handle_follow($reply_token);
            return;
        }

        if ('message' !== $type) {
            return;
        }

        $message = isset($event['message']) && is_array($event['message']) ? $event['message'] : array();

        // P1：只處理 1:1 的文字訊息。
        if (('text' !== (isset($message['type']) ? $message['type'] : '')) || 'user' !== $source_type || '' === $user_id) {
            return;
        }

        $text = isset($message['text']) ? (string) $message['text'] : '';

        $this->conversation->handle_text($user_id, $user_id, $reply_token, $text);
    }
}
