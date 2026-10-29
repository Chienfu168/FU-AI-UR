<?php
/**
 * UR AI Assistant LINE Conversation Service
 *
 * LINE 客服的核心狀態機：決定一則訊息該由 AI 回、該轉專人、還是在專人
 * 模式中靜默記錄；並處理歡迎訊息、關鍵字轉人、AI 判斷轉人、12 小時
 * 自動恢復、「#恢復」管理指令、每位使用者每小時限流與轉人通知。
 *
 * 可測試性：對外依賴（客戶／訊息 repository、回答服務、LINE client）皆可
 * 由建構子注入，單元測試時傳入假物件即可，不需真正連 LINE 或資料庫。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Conversation_Service
 */
class UR_AI_Line_Conversation_Service {

    /**
     * 客戶 repository。
     *
     * @var object
     */
    private $customers;

    /**
     * 訊息 repository。
     *
     * @var object
     */
    private $messages;

    /**
     * 回答服務。
     *
     * @var object
     */
    private $answer_service;

    /**
     * LINE client。
     *
     * @var object
     */
    private $client;

    /**
     * 建構子（依賴可注入，預設自行建立）。
     *
     * @param object|null $customers      客戶 repository。
     * @param object|null $messages       訊息 repository。
     * @param object|null $answer_service 回答服務。
     * @param object|null $client         LINE client。
     */
    public function __construct($customers = null, $messages = null, $answer_service = null, $client = null) {
        $this->customers      = $customers ?: (class_exists('UR_AI_Line_Customer_Repository') ? new UR_AI_Line_Customer_Repository() : null);
        $this->messages       = $messages ?: (class_exists('UR_AI_Line_Message_Repository') ? new UR_AI_Line_Message_Repository() : null);
        $this->answer_service = $answer_service ?: (class_exists('UR_AI_Line_Answer_Service') ? new UR_AI_Line_Answer_Service() : null);

        if ($client) {
            $this->client = $client;
        } else {
            $token        = class_exists('UR_AI_Line_Settings') ? UR_AI_Line_Settings::get_channel_access_token() : '';
            $this->client = class_exists('UR_AI_Line_Client') ? new UR_AI_Line_Client($token) : null;
        }
    }

    /**
     * 處理「加好友」事件：回覆歡迎詞。
     *
     * @param string $reply_token replyToken。
     * @return void
     */
    public function handle_follow($reply_token) {
        $welcome = class_exists('UR_AI_Line_Settings')
            ? (string) UR_AI_Line_Settings::get('welcome_message', '')
            : '';

        if ('' !== trim($welcome) && $this->client) {
            $this->client->reply_text($reply_token, $welcome);
        }
    }

    /**
     * 處理文字訊息事件（主流程）。
     *
     * @param string $line_user_id LINE userId。
     * @param string $chat_id      對話 id（1:1 為 userId）。
     * @param string $reply_token  replyToken。
     * @param string $text         訊息文字。
     * @return string 處理結果代碼（供測試斷言）：
     *                welcome_admin_resume｜keyword_transfer｜human_silent｜
     *                auto_resumed_answer｜rate_limited｜answer｜ai_transfer｜
     *                error_transfer｜ignored_too_long｜no_customer
     */
    public function handle_text($line_user_id, $chat_id, $reply_token, $text) {
        if (!$this->customers) {
            return 'no_customer';
        }

        $line_user_id = trim((string) $line_user_id);
        $chat_id      = '' !== trim((string) $chat_id) ? trim((string) $chat_id) : $line_user_id;
        $text         = (string) $text;
        $text_trim    = trim($text);

        if ('' === $line_user_id || '' === $text_trim) {
            return 'no_customer';
        }

        // 單則字數上限（防洗訊息／爆 token）。
        $max_chars = class_exists('UR_AI_Line_Settings') ? UR_AI_Line_Settings::get_max_message_chars() : 1000;

        if ($this->strlen($text_trim) > $max_chars) {
            $this->reply($reply_token, '您的訊息較長，請精簡後再傳一次，或輸入「我要找真人」由專人協助。');
            return 'ignored_too_long';
        }

        // 管理員指令：#恢復 <userId>（僅限設定的管理員 LINE id）。
        $admin_result = $this->maybe_handle_admin_command($line_user_id, $reply_token, $text_trim);
        if (null !== $admin_result) {
            return $admin_result;
        }

        $customer = $this->customers->get_or_create($line_user_id, $chat_id);
        if (method_exists($this->customers, 'touch')) {
            $this->customers->touch($line_user_id);
        }

        // 關鍵字轉人（最優先，使用者明確要求）。
        if ($this->matches_transfer_keyword($text_trim)) {
            $this->record($chat_id, $line_user_id, 'user', $text);
            $this->customers->set_mode($line_user_id, 'human');
            $this->notify_admin($line_user_id, $text_trim);
            $msg = '好的，已為您轉接專人，稍後將由真人回覆。';
            $this->record($chat_id, $line_user_id, 'assistant', $msg, 'system');
            $this->reply($reply_token, $msg);
            return 'keyword_transfer';
        }

        // 專人模式中（含 12 小時自動恢復判斷）。
        $mode = $this->resolve_mode($customer, $line_user_id);

        if ('human' === $mode) {
            // AI 不回覆，只記錄，讓專人看得到對話。
            $this->record($chat_id, $line_user_id, 'user', $text);
            return 'human_silent';
        }

        // bot 模式：每位使用者每小時限流。
        if (!$this->within_hourly_limit($line_user_id)) {
            $this->reply($reply_token, '您詢問得較頻繁，請稍後再試；若需要專人協助，請輸入「我要找真人」。');
            return 'rate_limited';
        }

        // 先取脈絡（不含本次），再記錄本次問題。
        $context = $this->messages ? $this->messages->recent_for_chat($chat_id, $this->context_limit()) : array();
        $this->record($chat_id, $line_user_id, 'user', $text);

        $result = $this->answer_service ? $this->answer_service->answer($text_trim, $context) : array('type' => 'error');
        $type   = isset($result['type']) ? $result['type'] : 'error';

        if ('answer' === $type && '' !== trim((string) $result['text'])) {
            $this->increment_hourly($line_user_id);
            $source = isset($result['source']) ? (string) $result['source'] : 'ai';
            // 紀錄存原始答案（乾淨，利於日後轉 FAQ）；回覆才附上推廣署名。
            $this->record($chat_id, $line_user_id, 'assistant', $result['text'], $source);
            $this->reply($reply_token, $this->append_signature($result['text']));

            $resumed = isset($result['resumed']) && $result['resumed'];
            return $resumed ? 'auto_resumed_answer' : 'answer';
        }

        if ('transfer' === $type) {
            $this->customers->set_mode($line_user_id, 'human');
            $this->notify_admin($line_user_id, $text_trim);
            $msg = '這部分需要專人進一步協助，已為您轉接專人，稍後將由真人回覆。';
            $this->record($chat_id, $line_user_id, 'assistant', $msg, 'system');
            $this->reply($reply_token, $msg);
            return 'ai_transfer';
        }

        // error（含額度用罄）→ 轉專人並告知忙碌。
        $this->customers->set_mode($line_user_id, 'human');
        $this->notify_admin($line_user_id, $text_trim);
        $msg = '系統目前較忙碌，已為您轉接專人，稍後將由真人回覆，謝謝您的耐心。';
        $this->record($chat_id, $line_user_id, 'assistant', $msg, 'system');
        $this->reply($reply_token, $msg);
        return 'error_transfer';
    }

    /**
     * 處理「#恢復 <userId>」管理指令。
     *
     * @param string $sender_id   送出者 userId。
     * @param string $reply_token replyToken。
     * @param string $text        訊息文字（已 trim）。
     * @return string|null 有處理回結果代碼，否則 null。
     */
    private function maybe_handle_admin_command($sender_id, $reply_token, $text) {
        if (0 !== strpos($text, '#恢復')) {
            return null;
        }

        $admin_ids = class_exists('UR_AI_Line_Settings') ? UR_AI_Line_Settings::get_admin_line_ids() : array();

        // 未設定管理員或送出者非管理員 → 不理會（安全預設）。
        if (empty($admin_ids) || !in_array($sender_id, $admin_ids, true)) {
            return null;
        }

        $target = trim(preg_replace('/^#恢復\s*/u', '', $text));

        if ('' === $target) {
            $this->reply($reply_token, '用法：#恢復 <使用者ID>');
            return 'welcome_admin_resume';
        }

        $this->customers->set_mode($target, 'bot');
        $this->reply($reply_token, '已恢復該使用者的自動回覆。');

        return 'welcome_admin_resume';
    }

    /**
     * 取得生效模式，並套用 12 小時自動恢復。
     *
     * @param object|null $customer     客戶資料列。
     * @param string      $line_user_id LINE userId。
     * @return string bot｜human
     */
    private function resolve_mode($customer, $line_user_id) {
        $mode = is_object($customer) && isset($customer->mode) ? (string) $customer->mode : 'bot';

        if ('human' !== $mode) {
            return 'bot';
        }

        $human_since = is_object($customer) && !empty($customer->human_since) ? (string) $customer->human_since : '';
        $timeout_h   = class_exists('UR_AI_Line_Settings') ? UR_AI_Line_Settings::get_human_timeout_hours() : 12;

        if ('' !== $human_since) {
            $since_ts = strtotime($human_since);
            $now_ts   = function_exists('current_time') ? (int) current_time('timestamp') : time();

            if ($since_ts && ($now_ts - $since_ts) >= $timeout_h * HOUR_IN_SECONDS) {
                $this->customers->set_mode($line_user_id, 'bot');
                return 'bot';
            }
        }

        return 'human';
    }

    /**
     * 是否命中轉人關鍵字。
     *
     * @param string $text 文字。
     * @return bool
     */
    private function matches_transfer_keyword($text) {
        $keywords = class_exists('UR_AI_Line_Settings') ? UR_AI_Line_Settings::get_transfer_keywords() : array();

        foreach ($keywords as $kw) {
            if ('' !== $kw && false !== mb_strpos($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 每位使用者每小時限流檢查（唯讀，不遞增）。
     *
     * @param string $line_user_id LINE userId。
     * @return bool true＝仍在額度內。
     */
    private function within_hourly_limit($line_user_id) {
        $limit = class_exists('UR_AI_Line_Settings') ? UR_AI_Line_Settings::get_hourly_limit() : 30;

        if ($limit <= 0) {
            return true;
        }

        $count = (int) get_transient($this->hourly_key($line_user_id));

        return $count < $limit;
    }

    /**
     * 遞增每小時計數。
     *
     * @param string $line_user_id LINE userId。
     * @return void
     */
    private function increment_hourly($line_user_id) {
        $limit = class_exists('UR_AI_Line_Settings') ? UR_AI_Line_Settings::get_hourly_limit() : 30;

        if ($limit <= 0) {
            return;
        }

        $key   = $this->hourly_key($line_user_id);
        $count = (int) get_transient($key);

        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }

    /**
     * 每小時限流 transient key。
     *
     * @param string $line_user_id LINE userId。
     * @return string
     */
    private function hourly_key($line_user_id) {
        $hour = function_exists('current_time') ? current_time('YmdH') : gmdate('YmdH');

        return 'ur_ai_line_rl_' . md5((string) $line_user_id) . '_' . $hour;
    }

    /**
     * 帶給 AI 的脈絡筆數。
     *
     * @return int
     */
    private function context_limit() {
        return class_exists('UR_AI_Line_Settings') ? UR_AI_Line_Settings::get_context_messages() : 10;
    }

    /**
     * 記錄一則訊息。
     *
     * @param string $chat_id      對話 id。
     * @param string $line_user_id LINE userId。
     * @param string $role         user｜assistant。
     * @param string $content      內容。
     * @return void
     */
    private function record($chat_id, $line_user_id, $role, $content, $source = '') {
        if ($this->messages && method_exists($this->messages, 'add')) {
            $this->messages->add($chat_id, $line_user_id, $role, $content, $source);
        }
    }

    /**
     * 在答覆文字後附上推廣署名（若有設定）。只用於實質回答，不用於
     * 轉接／忙碌等系統訊息。
     *
     * @param string $text 答覆文字。
     * @return string
     */
    private function append_signature($text) {
        $signature = class_exists('UR_AI_Line_Settings')
            ? trim((string) UR_AI_Line_Settings::get('reply_signature', ''))
            : '';

        if ('' === $signature) {
            return $text;
        }

        return rtrim((string) $text) . "\n\n" . $signature;
    }

    /**
     * 回覆文字。
     *
     * @param string $reply_token replyToken。
     * @param string $text        文字。
     * @return void
     */
    private function reply($reply_token, $text) {
        if ($this->client && method_exists($this->client, 'reply_text')) {
            $this->client->reply_text($reply_token, $text);
        }
    }

    /**
     * 轉真人時通知管理者（P1：Email）。
     *
     * @param string $line_user_id LINE userId。
     * @param string $last_message 最後一則訊息。
     * @return void
     */
    private function notify_admin($line_user_id, $last_message) {
        $email = class_exists('UR_AI_Line_Settings') ? trim((string) UR_AI_Line_Settings::get('admin_notify_email', '')) : '';

        if ('' === $email || !function_exists('wp_mail')) {
            return;
        }

        $subject = 'LINE 客服轉真人通知';
        $body    = sprintf(
            "有一位 LINE 使用者已轉為專人處理，請至官方帳號後台回覆。\n\n使用者 ID：%s\n最後訊息：%s\n",
            $line_user_id,
            $last_message
        );

        wp_mail($email, $subject, $body);
    }

    /**
     * 計算字串長度。
     *
     * @param string $text 文字。
     * @return int
     */
    private function strlen($text) {
        return function_exists('mb_strlen') ? mb_strlen((string) $text, 'UTF-8') : strlen((string) $text);
    }
}
