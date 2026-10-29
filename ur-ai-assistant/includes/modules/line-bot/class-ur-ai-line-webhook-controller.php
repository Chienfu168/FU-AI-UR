<?php
/**
 * UR AI Assistant LINE Webhook Controller
 *
 * 對外 REST 端點 POST /wp-json/ur-ai/v1/line/webhook：
 * 1. 未啟用時仍回 200（讓 LINE 後台 Verify 能通過，但不做事）。
 * 2. 驗證 X-Line-Signature，不符回 400。
 * 3. 解析 events、交由 Event Router 處理，最後回 200。
 *
 * P1 為同步處理（低流量足夠）；未來量大可改為「先回 200 再背景處理」
 * （fastcgi_finish_request）以降低 LINE 逾時重送風險——見 docs 規劃。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Line_Webhook_Controller
 */
class UR_AI_Line_Webhook_Controller {

    /**
     * 註冊 REST 路由。
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            'ur-ai/v1',
            '/line/webhook',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle'),
                // 呼叫端是 LINE 平台、非登入使用者；驗證改用簽章（見 handle()）。
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * 處理 webhook。
     *
     * @param WP_REST_Request $request 請求。
     * @return WP_REST_Response
     */
    public function handle($request) {
        if (!class_exists('UR_AI_Line_Settings') || !UR_AI_Line_Settings::is_enabled()) {
            // 未啟用：回 200 讓 Verify 能過，但不處理任何訊息。
            return new WP_REST_Response(null, 200);
        }

        $raw_body  = $request->get_body();
        $signature = (string) $request->get_header('x-line-signature');
        $secret    = UR_AI_Line_Settings::get_channel_secret();

        if (!class_exists('UR_AI_Line_Signature') || !UR_AI_Line_Signature::is_valid($raw_body, $signature, $secret)) {
            return new WP_REST_Response(array('message' => 'invalid signature'), 400);
        }

        $data   = json_decode((string) $raw_body, true);
        $events = (is_array($data) && isset($data['events']) && is_array($data['events'])) ? $data['events'] : array();

        if (!empty($events) && class_exists('UR_AI_Line_Event_Router')) {
            $router = new UR_AI_Line_Event_Router();
            $router->dispatch($events);
        }

        return new WP_REST_Response(null, 200);
    }
}
