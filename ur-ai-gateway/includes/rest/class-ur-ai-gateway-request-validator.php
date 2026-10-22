<?php
/**
 * UR AI Gateway Request Validator
 *
 * 代管服務轉發 OpenAI 請求前的 payload 檢查。
 *
 * 設計原則：gateway 刻意是「透明代理」，回應維持 OpenAI 原生格式，
 * 因此這裡只做「成本防護」必要的最小驗證，不改寫請求語意：
 * - 拒絕 stream（gateway 無法處理 SSE，若轉發會造成已計費卻解析失敗）。
 * - 強制 max_tokens 上限（不信任客戶端送的值，避免單次請求產生巨量輸出）。
 * - 限制 messages 筆數與總字元數（避免 context 灌爆放大成本）。
 * - 可選的 model 白名單：預設為空＝不限制（避免誤擋付費客戶實際使用的
 *   model）；服務提供者確認客戶使用的 model 後，可於設定頁啟用白名單。
 *
 * 額度（次數／token）不在這裡處理，由授權碼服務負責。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Request_Validator
 */
class UR_AI_Gateway_Request_Validator {

    /**
     * 驗證並正規化請求 payload。
     *
     * @param string $raw    原始請求本體（JSON 字串）。
     * @param array  $limits {
     *     @type array $allowed_models   允許的 model 清單；空陣列＝不限制。
     *     @type int   $max_output_tokens max_tokens 上限（<=0 代表不強制）。
     *     @type int   $max_messages      messages 筆數上限。
     *     @type int   $max_input_chars   所有 message 內容合計字元上限。
     * }
     * @return array 成功：['ok'=>true, 'payload'=>string, 'model'=>string]；
     *               失敗：['ok'=>false, 'status'=>int, 'code'=>string, 'message'=>string]。
     */
    public static function validate($raw, array $limits) {
        $limits = self::merge_limits($limits);

        if (!is_string($raw) || '' === trim($raw)) {
            return self::fail(400, 'empty_payload', '請求內容為空。');
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return self::fail(400, 'invalid_json', '請求內容不是有效的 JSON。');
        }

        // 拒絕 stream（見類別說明）。
        if (!empty($data['stream'])) {
            return self::fail(400, 'stream_not_supported', '代管服務不支援串流（stream）回應，請改用一般回應。');
        }
        unset($data['stream']);

        // model 白名單（預設不限制）。
        $model = isset($data['model']) ? (string) $data['model'] : '';
        if (!empty($limits['allowed_models']) && !in_array($model, $limits['allowed_models'], true)) {
            return self::fail(400, 'model_not_allowed', sprintf('代管服務未開放使用的模型：%s。', '' !== $model ? $model : '(未指定)'));
        }

        // messages 必須存在且為陣列。
        if (!isset($data['messages']) || !is_array($data['messages'])) {
            return self::fail(400, 'invalid_messages', '請求缺少有效的 messages。');
        }
        if (count($data['messages']) > $limits['max_messages']) {
            return self::fail(400, 'too_many_messages', sprintf('messages 筆數超過上限（上限 %d 筆）。', $limits['max_messages']));
        }

        // messages 內容總字元數。
        $chars = self::count_message_chars($data['messages']);
        if ($chars > $limits['max_input_chars']) {
            return self::fail(400, 'input_too_large', sprintf('輸入內容過長（上限 %d 字元）。', $limits['max_input_chars']));
        }

        // 強制 max_tokens 上限：取 min(客戶端值, 上限)；未給或非正整數則設為上限。
        if ($limits['max_output_tokens'] > 0) {
            $requested = isset($data['max_tokens']) ? (int) $data['max_tokens'] : 0;
            $data['max_tokens'] = ($requested > 0)
                ? min($requested, $limits['max_output_tokens'])
                : $limits['max_output_tokens'];
        }

        $encoded = function_exists('wp_json_encode') ? wp_json_encode($data) : json_encode($data);

        if (!is_string($encoded)) {
            return self::fail(400, 'encode_failed', '請求內容重新編碼失敗。');
        }

        return array('ok' => true, 'payload' => $encoded, 'model' => $model);
    }

    /**
     * 合併預設上限。
     *
     * @param array $limits 傳入上限。
     * @return array
     */
    private static function merge_limits(array $limits) {
        return array(
            'allowed_models'    => isset($limits['allowed_models']) && is_array($limits['allowed_models']) ? array_values(array_filter(array_map('strval', $limits['allowed_models']))) : array(),
            'max_output_tokens' => isset($limits['max_output_tokens']) ? (int) $limits['max_output_tokens'] : 16384,
            'max_messages'      => isset($limits['max_messages']) ? max(1, (int) $limits['max_messages']) : 100,
            'max_input_chars'   => isset($limits['max_input_chars']) ? max(1, (int) $limits['max_input_chars']) : 200000,
        );
    }

    /**
     * 計算 messages 內容總字元數（content 可能是字串或陣列）。
     *
     * @param array $messages messages。
     * @return int
     */
    private static function count_message_chars(array $messages) {
        $total   = 0;
        $strlen  = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

        foreach ($messages as $m) {
            if (!is_array($m) || !isset($m['content'])) {
                continue;
            }
            $content = $m['content'];
            if (is_string($content)) {
                $total += $strlen($content);
            } elseif (is_array($content)) {
                // 多段式內容（如 vision）：累加各段文字。
                foreach ($content as $part) {
                    if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                        $total += $strlen($part['text']);
                    } elseif (is_string($part)) {
                        $total += $strlen($part);
                    }
                }
            }
        }

        return $total;
    }

    /**
     * 組成失敗結果。
     *
     * @param int    $status  HTTP 狀態碼。
     * @param string $code    錯誤代碼。
     * @param string $message 錯誤訊息。
     * @return array
     */
    private static function fail($status, $code, $message) {
        return array('ok' => false, 'status' => $status, 'code' => $code, 'message' => $message);
    }
}
