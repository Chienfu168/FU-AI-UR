<?php
/**
 * UR AI Assistant Calculator CF7 Bridge
 *
 * 都更分回試算與 Contact Form 7 的橋接（方案甲）。
 *
 * 當指定 CF7 表單送出時：
 * - 讀取前台夾帶的 ur_ai_calc_token。
 * - 還原試算情境（transient）。
 * - 連同 CF7 聯絡欄位寫入 leads 表。
 *
 * 不需在 CF7 加 hidden mail-tag，也不需額外外掛；僅讀取原始 POST。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Calculator_CF7
 */
class UR_AI_Calculator_CF7 {

    /**
     * 名單 repository。
     *
     * @var UR_AI_Calculator_Lead_Repository
     */
    private $repository;

    /**
     * 建構。
     *
     * @param UR_AI_Calculator_Lead_Repository $repository 名單 repository。
     */
    public function __construct(UR_AI_Calculator_Lead_Repository $repository) {
        $this->repository = $repository;
    }

    /**
     * 註冊掛鉤。
     *
     * @return void
     */
    public function register() {
        // 驗證通過、寄信前觸發，確保即使 SMTP 失敗仍能留名單。
        add_action('wpcf7_before_send_mail', array($this, 'capture_lead'), 10, 1);
    }

    /**
     * 擷取名單。
     *
     * @param mixed $contact_form WPCF7_ContactForm。
     * @return void
     */
    public function capture_lead($contact_form) {
        if (!is_object($contact_form) || !method_exists($contact_form, 'id')) {
            return;
        }

        $target_id = UR_AI_Calculator_Settings::get_cf7_form_id();

        if ($target_id <= 0 || (int) $contact_form->id() !== $target_id) {
            return; // 不是試算名單表單，略過。
        }

        if (!class_exists('WPCF7_Submission')) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();

        if (!$submission) {
            return;
        }

        $posted = $submission->get_posted_data();

        if (!is_array($posted)) {
            $posted = array();
        }

        // 還原試算情境（一次性）。
        $token   = isset($posted['ur_ai_calc_token']) ? $posted['ur_ai_calc_token'] : '';
        if (is_array($token)) {
            $token = reset($token);
        }
        $context = UR_AI_Calculator_Ajax::pull_context($token);

        $data = $this->build_lead_data($posted, $context, $target_id);

        if (false === $this->repository->insert($data)) {
            error_log('UR AI Assistant: failed to insert calculator lead for CF7 form ID ' . $target_id);
        }
    }

    /**
     * 組裝 leads 資料列。
     *
     * @param array      $posted  CF7 posted data。
     * @param array|null $context 試算情境。
     * @param int        $form_id 表單 ID。
     * @return array
     */
    private function build_lead_data($posted, $context, $form_id) {
        $name    = $this->field($posted, 'your-name');
        $tel     = $this->field($posted, 'tel');
        $email   = $this->field($posted, 'your-email');
        $message = $this->field($posted, 'your-message');

        // 同意勾選框：CF7 checkbox 回傳陣列，非空即視為同意。
        $consent_raw = isset($posted['consent']) ? $posted['consent'] : '';
        $consent     = (is_array($consent_raw) ? !empty($consent_raw) : ('' !== trim((string) $consent_raw))) ? 1 : 0;

        $data = array(
            'name'        => sanitize_text_field($name),
            'tel'         => sanitize_text_field($tel),
            'email'       => sanitize_email($email),
            'message'     => sanitize_textarea_field($message),
            'consent'     => $consent,
            'cf7_form_id' => $form_id,
            'status'      => 'new',
            'city'        => '',
            'track'       => '',
            'result_summary' => '',
            'context_json'   => '',
            'source_url'     => '',
            'ip_hash'        => '',
        );

        if (is_array($context)) {
            $data['city']           = isset($context['city']) ? sanitize_key($context['city']) : '';
            $data['track']          = isset($context['track']) ? sanitize_key($context['track']) : '';
            $data['result_summary'] = isset($context['summary']) ? sanitize_text_field($context['summary']) : '';
            $data['source_url']     = isset($context['source_url']) ? esc_url_raw($context['source_url']) : '';
            $data['ip_hash']        = isset($context['ip_hash']) ? sanitize_text_field($context['ip_hash']) : '';
            $data['context_json']   = wp_json_encode($context);
        }

        return $data;
    }

    /**
     * 安全取 CF7 欄位值（可能為陣列）。
     *
     * @param array  $posted CF7 posted data。
     * @param string $key    欄位名。
     * @return string
     */
    private function field($posted, $key) {
        if (!isset($posted[$key])) {
            return '';
        }

        $value = $posted[$key];

        if (is_array($value)) {
            $value = reset($value);
        }

        return (string) $value;
    }
}
