<?php
/**
 * UR AI Assistant Quiz Admin
 *
 * 知識大考驗後台管理控制器：題庫 CRUD、AI 出題、審核、設定、排行榜管理。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Quiz_Admin
 */
class UR_AI_Quiz_Admin {

    /**
     * Quiz Service.
     *
     * @var UR_AI_Quiz_Service|null
     */
    private $service = null;

    /**
     * Quiz Draft Service.
     *
     * @var UR_AI_Quiz_Draft_Service|null
     */
    private $draft_service = null;

    /**
     * 建構子。
     *
     * @param UR_AI_Quiz_Service|null $service Service。
     */
    public function __construct($service = null) {
        $this->service = $service instanceof UR_AI_Quiz_Service
            ? $service
            : (class_exists('UR_AI_Quiz_Service') ? new UR_AI_Quiz_Service() : null);

        $this->draft_service = class_exists('UR_AI_Quiz_Draft_Service')
            ? new UR_AI_Quiz_Draft_Service()
            : null;
    }

    /**
     * 處理後台操作。
     *
     * @return void
     */
    public function handle_actions() {
        if (!is_admin() || empty($_POST['ur_ai_quiz_action'])) {
            return;
        }

        if (class_exists('UR_AI_Permissions')) {
            UR_AI_Permissions::require_manage_faqs();
        } elseif (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('您沒有權限執行此操作。', 'ur-ai-assistant'),
                esc_html__('權限不足', 'ur-ai-assistant'),
                array('response' => 403)
            );
        }

        if (class_exists('UR_AI_Security')) {
            UR_AI_Security::verify_admin_form_nonce_or_die();
        } else {
            check_admin_referer('ur_ai_assistant_admin_action', 'ur_ai_nonce');
        }

        $action = sanitize_key(wp_unslash($_POST['ur_ai_quiz_action']));

        switch ($action) {
            case 'create_question':
                $this->handle_create();
                break;

            case 'update_question':
                $this->handle_update();
                break;

            case 'delete_question':
                $this->handle_delete();
                break;

            case 'review_question':
                $this->handle_review();
                break;

            case 'generate_ai_draft':
                $this->handle_generate_ai_draft();
                break;

            case 'save_settings':
                $this->handle_save_settings();
                break;

            case 'delete_attempt':
                $this->handle_delete_attempt();
                break;

            case 'import_questions':
                $this->handle_import_questions();
                break;

            case 'bulk_questions':
                $this->handle_bulk_questions();
                break;
        }
    }

    /**
     * 取得題庫列表資料（供後台頁面使用）。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function get_list_data($args = array()) {
        if (!$this->service instanceof UR_AI_Quiz_Service) {
            return array('items' => array(), 'total' => 0, 'pagination' => array());
        }

        $paged    = isset($args['paged']) ? max(1, absint($args['paged'])) : 1;
        $per_page = 20;

        $query_args = array(
            'status'        => isset($args['status']) ? sanitize_key($args['status']) : '',
            'review_status' => isset($args['review_status']) ? sanitize_key($args['review_status']) : '',
            'category'      => isset($args['category']) ? sanitize_text_field($args['category']) : '',
            'search'        => isset($args['search']) ? sanitize_text_field($args['search']) : '',
            'limit'         => $per_page,
            'offset'        => ($paged - 1) * $per_page,
        );

        $items = $this->service->query_questions($query_args);
        $total = $this->service->count_questions($query_args);

        return array(
            'items'      => $items,
            'total'      => $total,
            'pagination' => array(
                'paged'    => $paged,
                'per_page' => $per_page,
                'total'    => $total,
                'pages'    => $per_page > 0 ? (int) ceil($total / $per_page) : 1,
            ),
        );
    }

    /**
     * 新增題目。
     *
     * @return void
     */
    private function handle_create() {
        if (!$this->service instanceof UR_AI_Quiz_Service) {
            $this->redirect_with_message('quiz_service_missing', 'error');
        }

        $id = $this->service->create_question($this->get_form_data());

        $this->redirect_with_message(
            $id > 0 ? 'question_created' : 'question_create_failed',
            $id > 0 ? 'updated' : 'error'
        );
    }

    /**
     * 更新題目。
     *
     * @return void
     */
    private function handle_update() {
        if (!$this->service instanceof UR_AI_Quiz_Service) {
            $this->redirect_with_message('quiz_service_missing', 'error');
        }

        $id = isset($_POST['question_id']) ? absint($_POST['question_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_question_id', 'error');
        }

        $updated = $this->service->update_question($id, $this->get_form_data());

        $this->redirect_with_message(
            $updated ? 'question_updated' : 'question_update_failed',
            $updated ? 'updated' : 'error'
        );
    }

    /**
     * 刪除題目。
     *
     * @return void
     */
    private function handle_delete() {
        if (!$this->service instanceof UR_AI_Quiz_Service) {
            $this->redirect_with_message('quiz_service_missing', 'error');
        }

        $id = isset($_POST['question_id']) ? absint($_POST['question_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_question_id', 'error');
        }

        $deleted = $this->service->delete_question($id);

        $this->redirect_with_message(
            $deleted ? 'question_deleted' : 'question_delete_failed',
            $deleted ? 'updated' : 'error'
        );
    }

    /**
     * 審核題目：核准並上線，或退回。
     *
     * @return void
     */
    private function handle_review() {
        if (!$this->service instanceof UR_AI_Quiz_Service) {
            $this->redirect_with_message('quiz_service_missing', 'error');
        }

        $id = isset($_POST['question_id']) ? absint($_POST['question_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_question_id', 'error');
        }

        $decision = isset($_POST['decision']) ? sanitize_key($_POST['decision']) : '';

        if (!in_array($decision, array('approve', 'reject'), true)) {
            $this->redirect_with_message('invalid_review_decision', 'error');
        }

        $updated = $this->apply_review_decision($id, $decision);

        $this->redirect_with_message(
            $updated ? 'question_reviewed' : 'question_review_failed',
            $updated ? 'updated' : 'error'
        );
    }

    /**
     * 批次操作題目：核准、退回或刪除。
     *
     * 解決單筆審核每次都要回到第一頁、逐題點擊的操作不便問題：可勾選
     * 多筆題目一次核准／退回／刪除，並保留原本所在的頁碼與篩選條件。
     *
     * @return void
     */
    private function handle_bulk_questions() {
        if (!$this->service instanceof UR_AI_Quiz_Service) {
            $this->redirect_with_message('quiz_service_missing', 'error');
        }

        $ids = isset($_POST['question_ids']) ? (array) wp_unslash($_POST['question_ids']) : array();
        $ids = class_exists('UR_AI_Security') ? UR_AI_Security::sanitize_ids($ids) : array_values(array_unique(array_filter(array_map('absint', $ids))));

        if (empty($ids)) {
            $this->redirect_with_message('no_items_selected', 'error');
        }

        $bulk_action = isset($_POST['bulk_action']) ? sanitize_key($_POST['bulk_action']) : '';
        $success     = 0;

        foreach ($ids as $id) {
            if (in_array($bulk_action, array('approve', 'reject'), true)) {
                $ok = $this->apply_review_decision($id, $bulk_action);
            } elseif ('delete' === $bulk_action) {
                $ok = $this->service->delete_question($id);
            } else {
                $this->redirect_with_message('invalid_review_decision', 'error');
                return;
            }

            if ($ok) {
                $success++;
            }
        }

        $message_map = array(
            'approve' => 'questions_bulk_approved',
            'reject'  => 'questions_bulk_rejected',
            'delete'  => 'questions_bulk_deleted',
        );

        $this->redirect_with_message(
            $message_map[$bulk_action],
            $success > 0 ? 'updated' : 'error',
            array('bulk_count' => $success)
        );
    }

    /**
     * 套用審核決定（核准並上線／退回並停用）到單一題目。
     *
     * 供單筆審核與批次審核共用，避免兩處分別維護「先取出既有欄位、
     * 再套用審核狀態」的合併邏輯。
     *
     * @param int    $id 題目 ID。
     * @param string $decision 'approve' 或 'reject'。
     * @return bool
     */
    private function apply_review_decision($id, $decision) {
        if (!$this->service instanceof UR_AI_Quiz_Service) {
            return false;
        }

        $question = $this->service->find_question($id);

        if (!$question) {
            return false;
        }

        $data = array(
            'question'       => $question->question,
            'option_a'       => $question->option_a,
            'option_b'       => $question->option_b,
            'option_c'       => $question->option_c,
            'option_d'       => $question->option_d,
            'correct_option' => $question->correct_option,
            'explanation'    => $question->explanation,
            'difficulty'     => $question->difficulty,
            'category'       => $question->category,
            'admin_note'     => $question->admin_note,
        );

        if ('approve' === $decision) {
            $data['review_status'] = 'approved';
            $data['status']        = 'active';
        } elseif ('reject' === $decision) {
            $data['review_status'] = 'rejected';
            $data['status']        = 'inactive';
        } else {
            return false;
        }

        return $this->service->update_question($id, $data);
    }

    /**
     * 依選定的 FAQ 批次產生 AI 題目草稿。
     *
     * @return void
     */
    private function handle_generate_ai_draft() {
        if (!$this->draft_service instanceof UR_AI_Quiz_Draft_Service) {
            $this->redirect_with_message('quiz_draft_service_missing', 'error');
        }

        $faq_ids = isset($_POST['faq_ids']) ? (array) wp_unslash($_POST['faq_ids']) : array();
        $faq_ids = class_exists('UR_AI_Security') ? UR_AI_Security::sanitize_ids($faq_ids) : array_map('absint', $faq_ids);

        if (empty($faq_ids)) {
            $this->redirect_with_message('no_faqs_selected', 'error');
        }

        $result = $this->draft_service->generate_batch($faq_ids);

        $this->redirect_with_message(
            $result['created'] > 0 ? 'ai_draft_generated' : 'ai_draft_failed',
            $result['created'] > 0 ? 'updated' : 'error',
            array(
                'created' => $result['created'],
                'failed'  => $result['failed'],
            )
        );
    }

    /**
     * 儲存設定。
     *
     * @return void
     */
    private function handle_save_settings() {
        if (!class_exists('UR_AI_Quiz_Settings')) {
            $this->redirect_with_message('quiz_service_missing', 'error');
        }

        UR_AI_Quiz_Settings::update(
            array(
                'enabled'             => isset($_POST['enabled']) ? 1 : 0,
                'question_count'      => isset($_POST['question_count']) ? absint($_POST['question_count']) : 10,
                'rate_limit_per_hour' => isset($_POST['rate_limit_per_hour']) ? absint($_POST['rate_limit_per_hour']) : 3,
                'title'               => isset($_POST['title']) ? wp_unslash($_POST['title']) : '',
            )
        );

        $this->redirect_with_message('settings_saved', 'updated');
    }

    /**
     * 刪除一筆排行榜紀錄（處理不當暱稱等情況）。
     *
     * @return void
     */
    private function handle_delete_attempt() {
        if (!$this->service instanceof UR_AI_Quiz_Service) {
            $this->redirect_with_message('quiz_service_missing', 'error');
        }

        $id = isset($_POST['attempt_id']) ? absint($_POST['attempt_id']) : 0;

        if ($id <= 0) {
            $this->redirect_with_message('invalid_attempt_id', 'error');
        }

        $deleted = $this->service->delete_attempt($id);

        $this->redirect_with_message(
            $deleted ? 'attempt_deleted' : 'attempt_delete_failed',
            $deleted ? 'updated' : 'error'
        );
    }

    /**
     * 處理題庫 CSV 批次匯入。
     *
     * 每一列都會新增為一筆獨立題目（不做覆蓋比對），一律為
     * 「停用／待審核」狀態，需人工審核通過後才會上線，與手動新增、
     * AI 出題草稿的審核要求一致。
     *
     * @return void
     */
    private function handle_import_questions() {
        if (!$this->service instanceof UR_AI_Quiz_Service) {
            $this->redirect_with_message('quiz_service_missing', 'error');
        }

        if (empty($_FILES['ur_ai_quiz_csv']) || !isset($_FILES['ur_ai_quiz_csv']['tmp_name'])) {
            $this->redirect_with_message('quiz_import_no_file', 'error');
        }

        $file = $_FILES['ur_ai_quiz_csv'];

        if (!empty($file['error']) && UPLOAD_ERR_OK !== (int) $file['error']) {
            $this->redirect_with_message('quiz_import_upload_error', 'error');
        }

        $tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';

        if ('' === $tmp_name || !is_uploaded_file($tmp_name)) {
            $this->redirect_with_message('quiz_import_no_file', 'error');
        }

        $filename = isset($file['name']) ? sanitize_file_name($file['name']) : '';
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ('csv' !== $ext && 'txt' !== $ext) {
            $this->redirect_with_message('quiz_import_bad_type', 'error');
        }

        $rows = $this->parse_quiz_csv($tmp_name);

        if (null === $rows) {
            $this->redirect_with_message('quiz_import_parse_error', 'error');
        }

        if (empty($rows)) {
            $this->redirect_with_message('quiz_import_empty', 'error');
        }

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $data = array(
                'question'       => isset($row['question']) ? $row['question'] : '',
                'option_a'       => isset($row['option_a']) ? $row['option_a'] : '',
                'option_b'       => isset($row['option_b']) ? $row['option_b'] : '',
                'option_c'       => isset($row['option_c']) ? $row['option_c'] : '',
                'option_d'       => isset($row['option_d']) ? $row['option_d'] : '',
                'correct_option' => isset($row['correct_option']) ? $row['correct_option'] : 'a',
                'explanation'    => isset($row['explanation']) ? $row['explanation'] : '',
                'difficulty'     => isset($row['difficulty']) ? $row['difficulty'] : 'medium',
                'category'       => isset($row['category']) ? $row['category'] : '',
                'status'         => 'inactive',
                'review_status'  => 'draft',
                'source'         => 'manual',
                'admin_note'     => __('CSV 批次匯入，待人工審核。', 'ur-ai-assistant'),
            );

            $id = $this->service->create_question($data);

            if ($id > 0) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->redirect_with_message(
            'quiz_imported',
            'updated',
            array(
                'imp_created' => $created,
                'imp_skipped' => $skipped,
            )
        );
    }

    /**
     * 解析題庫匯入 CSV，回傳正規化後的資料列。
     *
     * 支援中文欄名（分類、難度、題目、選項A-D、正確答案、解析）與
     * 英文欄名，依表頭自動對應欄位；找不到題目或選項 A／B 欄位時視為
     * 格式錯誤。
     *
     * 常見情境：使用者用 Excel 開啟並另存 CSV 時，若未明確選擇
     * 「CSV UTF-8」，Excel 可能把檔案存成 Big5／ANSI 編碼。WordPress
     * 的 sanitize_text_field()／sanitize_textarea_field() 遇到不合法的
     * UTF-8 位元組序列會直接回傳空字串，導致每一列的題目與選項全部被
     * 清空、在後續的必填檢查中被判定為「格式錯誤」而略過（即使 CSV
     * 表頭與列數其實都正確解析）。因此讀取檔案內容後，會先確認是否為
     * 合法 UTF-8，不是的話嘗試偵測常見中文編碼並轉換，避免整批誤判。
     *
     * @param string $path 上傳暫存檔路徑。
     * @return array|null 成功回傳資料列陣列；讀取失敗回傳 null。
     */
    private function parse_quiz_csv($path) {
        $content = file_get_contents($path);

        if (false === $content) {
            return null;
        }

        if ('' !== $content && function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            $detected = function_exists('mb_detect_encoding')
                ? mb_detect_encoding($content, array('UTF-8', 'BIG5', 'GB2312', 'GBK', 'EUC-TW'), true)
                : false;

            if ($detected && 'UTF-8' !== $detected && function_exists('mb_convert_encoding')) {
                $converted = mb_convert_encoding($content, 'UTF-8', $detected);

                if (false !== $converted) {
                    $content = $converted;
                }
            }
        }

        $handle = fopen('php://temp', 'r+');

        if (false === $handle) {
            return null;
        }

        fwrite($handle, $content);
        rewind($handle);

        $aliases = array(
            'category'       => array('分類', 'category'),
            'difficulty'     => array('難度', 'difficulty'),
            'question'       => array('題目', '問題', 'question'),
            'option_a'       => array('選項a', '選項 a', 'option_a', 'optiona'),
            'option_b'       => array('選項b', '選項 b', 'option_b', 'optionb'),
            'option_c'       => array('選項c', '選項 c', 'option_c', 'optionc'),
            'option_d'       => array('選項d', '選項 d', 'option_d', 'optiond'),
            'correct_option' => array('正確答案', '答案', 'correct_option', 'answer'),
            'explanation'    => array('解析', 'explanation'),
        );

        $rows       = array();
        $header_map = null;

        while (false !== ($cols = fgetcsv($handle, 0, ','))) {
            if (null === $cols || (1 === count($cols) && (null === $cols[0] || '' === trim((string) $cols[0])))) {
                continue;
            }

            if (null === $header_map) {
                if (isset($cols[0])) {
                    $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cols[0]);
                }

                $header_map = array();

                foreach ($cols as $index => $label) {
                    $label = strtolower(trim((string) $label));

                    if ('' === $label) {
                        continue;
                    }

                    foreach ($aliases as $field => $names) {
                        if (isset($header_map[$field])) {
                            continue;
                        }

                        foreach ($names as $name) {
                            if ($label === strtolower($name)) {
                                $header_map[$field] = $index;
                                break 2;
                            }
                        }
                    }
                }

                if (!isset($header_map['question']) || !isset($header_map['option_a']) || !isset($header_map['option_b'])) {
                    fclose($handle);
                    return null;
                }

                continue;
            }

            $row = array();

            foreach ($header_map as $field => $index) {
                $row[$field] = isset($cols[$index]) ? trim((string) $cols[$index]) : '';
            }

            if ('' === (isset($row['question']) ? $row['question'] : '') || '' === (isset($row['option_a']) ? $row['option_a'] : '')) {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * 取得表單資料。
     *
     * @return array
     */
    private function get_form_data() {
        return array(
            'question'       => isset($_POST['question']) ? wp_unslash($_POST['question']) : '',
            'option_a'       => isset($_POST['option_a']) ? wp_unslash($_POST['option_a']) : '',
            'option_b'       => isset($_POST['option_b']) ? wp_unslash($_POST['option_b']) : '',
            'option_c'       => isset($_POST['option_c']) ? wp_unslash($_POST['option_c']) : '',
            'option_d'       => isset($_POST['option_d']) ? wp_unslash($_POST['option_d']) : '',
            'correct_option' => isset($_POST['correct_option']) ? wp_unslash($_POST['correct_option']) : 'a',
            'explanation'    => isset($_POST['explanation']) ? wp_unslash($_POST['explanation']) : '',
            'difficulty'     => isset($_POST['difficulty']) ? wp_unslash($_POST['difficulty']) : 'medium',
            'category'       => isset($_POST['category']) ? wp_unslash($_POST['category']) : '',
            'status'         => isset($_POST['status']) ? wp_unslash($_POST['status']) : 'inactive',
            'review_status'  => isset($_POST['review_status']) ? wp_unslash($_POST['review_status']) : 'draft',
            'source'         => isset($_POST['source']) ? wp_unslash($_POST['source']) : 'manual',
            'admin_note'     => isset($_POST['admin_note']) ? wp_unslash($_POST['admin_note']) : '',
        );
    }

    /**
     * 導向後台頁面並帶上訊息代碼。
     *
     * 會一併帶回送出表單時記錄的頁碼與篩選條件（若表單有附上對應的
     * 隱藏欄位），避免每次審核或刪除單一題目後，畫面都被重置回第一頁、
     * 篩選條件也被清空，造成需要逐頁往後翻找待審題目的操作不便。
     *
     * @param string $message 訊息代碼。
     * @param string $type 訊息類型。
     * @param array  $extra_args 額外查詢字串參數。
     * @return void
     */
    private function redirect_with_message($message, $type = 'updated', $extra_args = array()) {
        $list_state = array();

        foreach (array('paged', 'q_status', 'q_review_status', 'q_category', 'q_s', 'attempts_paged') as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field(wp_unslash($_POST[$field]));

                if ('' !== $value) {
                    $list_state[$field] = $value;
                }
            }
        }

        $args = array_merge(
            $list_state,
            array(
                'page'        => 'ur-ai-assistant-quiz',
                'ur_message'  => sanitize_key($message),
                'ur_msg_type' => sanitize_key($type),
            )
        );

        if (is_array($extra_args) && !empty($extra_args)) {
            foreach ($extra_args as $key => $value) {
                $args[sanitize_key($key)] = sanitize_text_field((string) $value);
            }
        }

        $url = add_query_arg($args, admin_url('admin.php'));

        wp_safe_redirect($url);
        exit;
    }

    /**
     * 後台訊息文字。
     *
     * @param string $code 訊息代碼。
     * @return string
     */
    public function get_admin_message($code) {
        $code = sanitize_key($code);

        $messages = array(
            'question_created'          => __('題目已新增。', 'ur-ai-assistant'),
            'question_create_failed'    => __('題目新增失敗，請確認題目與選項內容。', 'ur-ai-assistant'),
            'question_updated'          => __('題目已更新。', 'ur-ai-assistant'),
            'question_update_failed'    => __('題目更新失敗。', 'ur-ai-assistant'),
            'question_deleted'          => __('題目已刪除。', 'ur-ai-assistant'),
            'question_delete_failed'    => __('題目刪除失敗。', 'ur-ai-assistant'),
            'question_reviewed'         => __('審核結果已更新。', 'ur-ai-assistant'),
            'question_review_failed'    => __('審核操作失敗。', 'ur-ai-assistant'),
            'questions_bulk_approved'   => __('已批次核准所選題目。', 'ur-ai-assistant'),
            'questions_bulk_rejected'   => __('已批次退回所選題目。', 'ur-ai-assistant'),
            'questions_bulk_deleted'    => __('已批次刪除所選題目。', 'ur-ai-assistant'),
            'no_items_selected'         => __('請先選擇要操作的題目。', 'ur-ai-assistant'),
            'invalid_question_id'       => __('題目 ID 不正確。', 'ur-ai-assistant'),
            'invalid_review_decision'   => __('審核結果不正確。', 'ur-ai-assistant'),
            'invalid_attempt_id'        => __('排行榜紀錄 ID 不正確。', 'ur-ai-assistant'),
            'attempt_deleted'           => __('排行榜紀錄已刪除。', 'ur-ai-assistant'),
            'attempt_delete_failed'     => __('排行榜紀錄刪除失敗。', 'ur-ai-assistant'),
            'no_faqs_selected'          => __('請先選擇要出題的 FAQ。', 'ur-ai-assistant'),
            'ai_draft_generated'        => __('AI 出題完成，請至下方題庫審核。', 'ur-ai-assistant'),
            'ai_draft_failed'           => __('AI 出題失敗，請確認已設定 OpenAI API Key 後再試。', 'ur-ai-assistant'),
            'settings_saved'            => __('設定已儲存。', 'ur-ai-assistant'),
            'quiz_service_missing'      => __('知識大考驗服務尚未正確載入。', 'ur-ai-assistant'),
            'quiz_draft_service_missing' => __('AI 出題服務尚未正確載入。', 'ur-ai-assistant'),
            'quiz_import_no_file'       => __('請選擇要上傳的 CSV 檔案。', 'ur-ai-assistant'),
            'quiz_import_bad_type'      => __('檔案格式不符，請上傳 CSV（.csv）檔案。', 'ur-ai-assistant'),
            'quiz_import_upload_error'  => __('檔案上傳失敗，請重新嘗試。', 'ur-ai-assistant'),
            'quiz_import_parse_error'   => __('CSV 格式不正確，請確認包含「題目」「選項A」「選項B」欄位。', 'ur-ai-assistant'),
            'quiz_import_empty'        => __('CSV 內沒有可匯入的題目資料。', 'ur-ai-assistant'),
        );

        return isset($messages[$code]) ? $messages[$code] : '';
    }
}
