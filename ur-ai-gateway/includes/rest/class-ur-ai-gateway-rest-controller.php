<?php
/**
 * UR AI Gateway REST Controller
 *
 * 代管服務的核心：一個相容 OpenAI Chat Completions API 的代理端點。
 *
 * 客戶端網站（安裝了 UR AI Assistant 外掛、並在「功能設定」選擇「使用
 * 代管服務」）會把原本要送給 OpenAI 的請求，改成送到這個端點，並用
 * 授權碼取代 OpenAI API Key 當作 Bearer 憑證。這個端點驗證授權碼、
 * 檢查每日用量上限後，改用「服務提供者自己的」OpenAI API Key 真正
 * 呼叫 OpenAI，並把 OpenAI 的回應原封不動傳回去。
 *
 * 刻意做成「透明代理」而不是自訂的 JSON 格式，是因為 UR AI Assistant
 * 外掛的 OpenAI Client（extract_answer()／handle_api_error() 等）本來
 * 就是依照 OpenAI 官方回應格式撰寫的解析邏輯——讓這個端點的成功／
 * 錯誤回應都維持 OpenAI 原生格式，代管模式與自行提供 API Key 模式
 * 才能共用同一套呼叫端解析程式碼，完全不用改客戶端外掛。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_REST_Controller
 */
class UR_AI_Gateway_REST_Controller {

    /**
     * OpenAI Chat Completions API 端點。
     *
     * @var string
     */
    const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * License Service.
     *
     * @var UR_AI_Gateway_License_Service|null
     */
    private $license_service;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->license_service = class_exists('UR_AI_Gateway_License_Service') ? new UR_AI_Gateway_License_Service() : null;
    }

    /**
     * 註冊 REST 路由。
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            'ur-ai-gateway/v1',
            '/chat',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_chat'),
                // 呼叫端是外部網站、不是登入中的 WP 使用者，權限判斷改在
                // handle_chat() 內部依授權碼處理，這裡一律放行進入 callback。
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * 處理代理請求。
     *
     * @param WP_REST_Request $request 請求物件。
     * @return WP_REST_Response
     */
    public function handle_chat($request) {
        if (!$this->license_service instanceof UR_AI_Gateway_License_Service) {
            return $this->error_response(500, '代管服務尚未正確載入，請聯絡服務提供者。', 'gateway_not_loaded');
        }

        $token = $this->extract_bearer_token($request);

        if ('' === $token) {
            return $this->error_response(401, '缺少授權碼。', 'invalid_api_key');
        }

        $license = $this->license_service->find_by_key($token);

        if (!$license) {
            return $this->error_response(401, '授權碼不存在或不正確。', 'invalid_api_key');
        }

        if (!$this->license_service->is_valid($license)) {
            return $this->error_response(
                401,
                sprintf('這組授權碼目前狀態為「%s」，暫時無法使用代管服務，請確認訂閱狀態。', $license->status),
                'license_inactive'
            );
        }

        if (!$this->license_service->check_and_consume_daily_limit($license)) {
            return $this->error_response(429, '已達每日呼叫次數上限，請明日再試，或聯絡服務提供者調整方案。', 'rate_limit_exceeded');
        }

        $api_key = class_exists('UR_AI_Gateway_Settings') ? UR_AI_Gateway_Settings::get_openai_api_key() : '';

        if ('' === $api_key) {
            return $this->error_response(500, '代管服務尚未設定 OpenAI API Key，請聯絡服務提供者。', 'gateway_not_configured');
        }

        $payload = $request->get_body();

        if (!is_string($payload) || '' === trim($payload)) {
            return $this->error_response(400, '請求內容為空。', 'empty_payload');
        }

        $response = wp_remote_post(
            self::OPENAI_ENDPOINT,
            array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => $payload,
            )
        );

        if (is_wp_error($response)) {
            return $this->error_response(502, $response->get_error_message(), 'upstream_error');
        }

        $status_code = absint(wp_remote_retrieve_response_code($response));
        $body        = wp_remote_retrieve_body($response);
        $decoded     = json_decode($body, true);

        if (!is_array($decoded)) {
            return $this->error_response(502, 'OpenAI 回傳格式無法解析。', 'invalid_upstream_response');
        }

        return new WP_REST_Response($decoded, $status_code > 0 ? $status_code : 200);
    }

    /**
     * 從請求的 Authorization header 取出 Bearer Token。
     *
     * @param WP_REST_Request $request 請求物件。
     * @return string
     */
    private function extract_bearer_token($request) {
        $header = $request->get_header('authorization');

        if (!is_string($header) || '' === $header) {
            return '';
        }

        if (0 !== stripos($header, 'Bearer ')) {
            return '';
        }

        return trim(substr($header, 7));
    }

    /**
     * 組成與 OpenAI 官方錯誤回應相同格式的錯誤結果，讓客戶端既有的
     * 錯誤解析邏輯（UR_AI_OpenAI_Client::handle_api_error()）可以
     * 直接沿用，不需要為了代管模式另外寫一套錯誤處理。
     *
     * @param int    $status_code HTTP 狀態碼。
     * @param string $message 錯誤訊息。
     * @param string $code 錯誤代碼。
     * @return WP_REST_Response
     */
    private function error_response($status_code, $message, $code) {
        return new WP_REST_Response(
            array(
                'error' => array(
                    'message' => $message,
                    'code'    => $code,
                ),
            ),
            $status_code
        );
    }
}
