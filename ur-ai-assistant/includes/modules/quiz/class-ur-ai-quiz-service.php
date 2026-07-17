<?php
/**
 * UR AI Assistant Quiz Service
 *
 * 知識大考驗業務邏輯層：抽題、伺服器端計分、排行榜、節流。
 *
 * 安全設計重點：
 * - 正確答案永遠只存在伺服器端的暫存憑證（transient）中，前台 JS 拿到的
 *   題目資料絕對不含正確答案，避免瀏覽器開發者工具就能看到答案。
 * - 每次開始作答會產生一組一次性 token，送出作答後立即刪除該 token，
 *   防止同一個 token 被重複送出洗分。
 * - 選項順序每次隨機打亂，避免使用者只記選項位置。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Quiz_Service
 */
class UR_AI_Quiz_Service {

    /**
     * 暫存憑證 transient key 前綴。
     *
     * @var string
     */
    const ATTEMPT_TRANSIENT_PREFIX = 'ur_ai_quiz_attempt_';

    /**
     * 暫存憑證有效期（秒）。作答不限時，但仍設合理上限避免 transient 永久堆積。
     *
     * @var int
     */
    const ATTEMPT_TTL = 1800;

    /**
     * Repository.
     *
     * @var UR_AI_Quiz_Repository|null
     */
    private $repository;

    /**
     * FAQ Service，用於作答結果串接相關 FAQ 供複習。
     *
     * @var UR_AI_FAQ_Service|null
     */
    private $faq_service;

    /**
     * FAQ 文章草稿服務，用於反查該 FAQ 是否已有發布的文章可優先連結。
     *
     * @var UR_AI_FAQ_Article_Service|null
     */
    private $article_service;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->repository      = class_exists('UR_AI_Quiz_Repository') ? new UR_AI_Quiz_Repository() : null;
        $this->faq_service      = class_exists('UR_AI_FAQ_Service') ? new UR_AI_FAQ_Service() : null;
        $this->article_service  = class_exists('UR_AI_FAQ_Article_Service') ? new UR_AI_FAQ_Article_Service() : null;
    }

    /* =====================================================================
     * 題庫管理（後台用，直接透傳 repository）
     * ================================================================== */

    /**
     * 新增題目。
     *
     * @param array $data 題目資料。
     * @return int
     */
    public function create_question($data) {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->create_question($data) : 0;
    }

    /**
     * 更新題目。
     *
     * @param int   $id 題目 ID。
     * @param array $data 題目資料。
     * @return bool
     */
    public function update_question($id, $data) {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->update_question($id, $data) : false;
    }

    /**
     * 刪除題目。
     *
     * @param int $id 題目 ID。
     * @return bool
     */
    public function delete_question($id) {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->delete_question($id) : false;
    }

    /**
     * 查詢單一題目。
     *
     * @param int $id 題目 ID。
     * @return object|null
     */
    public function find_question($id) {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->find_question($id) : null;
    }

    /**
     * 查詢題目列表。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query_questions($args = array()) {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->query_questions($args) : array();
    }

    /**
     * 計算題目數量。
     *
     * @param array $args 查詢參數。
     * @return int
     */
    public function count_questions($args = array()) {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->count_questions($args) : 0;
    }

    /**
     * 查詢符合條件的全部題目 ID（不分頁），供「跨頁全選」批次操作使用。
     *
     * @param array $args 查詢參數。
     * @return array
     */
    public function query_question_ids($args = array()) {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->query_question_ids($args) : array();
    }

    /**
     * 已啟用且已審核的題目總數。
     *
     * @return int
     */
    public function count_active_questions() {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->count_active_questions() : 0;
    }

    /* =====================================================================
     * 前台作答流程
     * ================================================================== */

    /**
     * 開始一次挑戰：隨機抽題、打亂選項，回傳「不含正確答案」的題目資料
     * 給前台，並在伺服器端保存一次性憑證供作答送出時計分比對。
     *
     * @return array|WP_Error 成功回傳 array('token'=>string,'questions'=>array)，
     *                        失敗回傳說明錯誤原因的 array('error'=>string)。
     */
    public function start_attempt() {
        if (!$this->repository instanceof UR_AI_Quiz_Repository) {
            return array('error' => __('題庫服務尚未正確載入。', 'ur-ai-assistant'));
        }

        $question_count = class_exists('UR_AI_Quiz_Settings')
            ? UR_AI_Quiz_Settings::get_question_count()
            : 10;

        $available = $this->repository->count_active_questions();

        if ($available < 4) {
            return array('error' => __('目前題庫題目數量不足，暫時無法開始挑戰，請稍後再試。', 'ur-ai-assistant'));
        }

        $draw_count = min($question_count, $available);
        $rows       = $this->repository->get_random_active_questions($draw_count);

        if (empty($rows)) {
            return array('error' => __('抽題失敗，請稍後再試。', 'ur-ai-assistant'));
        }

        $public_questions = array();
        $answer_key       = array();

        foreach ($rows as $index => $row) {
            $options = array(
                'a' => (string) $row->option_a,
                'b' => (string) $row->option_b,
                'c' => (string) $row->option_c,
                'd' => (string) $row->option_d,
            );

            // 將 a/b/c/d 四個原始選項字母，隨機重新指派到 a/b/c/d 四個新位置，
            // 讓每次抽到同一題時，正確答案的位置都不同。
            $letters          = array('a', 'b', 'c', 'd');
            $shuffled_letters = $letters;
            shuffle($shuffled_letters);

            $shuffled_options    = array();
            $correct_position    = 'a';
            $original_correct    = strtolower((string) $row->correct_option);

            foreach ($letters as $slot_index => $new_letter) {
                $original_letter               = $shuffled_letters[$slot_index];
                $shuffled_options[$new_letter] = $options[$original_letter];

                if ($original_letter === $original_correct) {
                    $correct_position = $new_letter;
                }
            }

            $question_uid = 'q' . $index;

            $public_questions[] = array(
                'uid'      => $question_uid,
                'question' => (string) $row->question,
                'options'  => $shuffled_options,
            );

            $answer_key[$question_uid] = array(
                'question_id'   => absint($row->id),
                'correct'       => $correct_position,
                'question'      => (string) $row->question,
                'correct_text'  => isset($options[$original_correct]) ? $options[$original_correct] : '',
                'explanation'   => (string) $row->explanation,
                'source_faq_id' => absint($row->source_faq_id),
            );
        }

        $token = wp_generate_password(32, false, false);

        set_transient(
            self::ATTEMPT_TRANSIENT_PREFIX . $token,
            array(
                'answer_key' => $answer_key,
                'started_at' => time(),
            ),
            self::ATTEMPT_TTL
        );

        return array(
            'token'     => $token,
            'questions' => $public_questions,
        );
    }

    /**
     * 送出作答，伺服器端計分並（視暱稱決定是否）寫入排行榜。
     *
     * @param string $token 開始挑戰時取得的一次性憑證。
     * @param array  $answers 使用者作答，格式 array(question_uid => 選擇的選項字母)。
     * @param string $nickname 暱稱（可空＝匿名）。
     * @param int    $duration_seconds 作答耗時（秒，僅供顯示與同分排序，非計分依據）。
     * @return array 結果，包含 error 或 score 等欄位。
     */
    public function submit_attempt($token, $answers, $nickname, $duration_seconds) {
        $token = sanitize_text_field((string) $token);

        if ('' === $token) {
            return array('error' => __('作答資料無效，請重新開始挑戰。', 'ur-ai-assistant'));
        }

        $transient_key = self::ATTEMPT_TRANSIENT_PREFIX . $token;
        $attempt_data  = get_transient($transient_key);

        if (!is_array($attempt_data) || empty($attempt_data['answer_key'])) {
            return array('error' => __('挑戰已逾時或已送出過，請重新開始一次新的挑戰。', 'ur-ai-assistant'));
        }

        // 立即刪除 token，確保同一次挑戰只能被計分一次，無法重複送出洗分。
        delete_transient($transient_key);

        if (!$this->check_rate_limit()) {
            return array(
                'error' => __('您在短時間內已作答多次，請稍後再試（避免灌水，保護排行榜的公平性）。', 'ur-ai-assistant'),
            );
        }

        $answer_key = $attempt_data['answer_key'];
        $answers    = is_array($answers) ? $answers : array();

        $total   = count($answer_key);
        $correct = 0;
        $review  = array();

        foreach ($answer_key as $question_uid => $expected) {
            $submitted  = isset($answers[$question_uid]) ? strtolower(sanitize_key($answers[$question_uid])) : '';
            $is_correct = ($submitted === $expected['correct']);

            if ($is_correct) {
                $correct++;
            }

            $review_item = array(
                'uid'            => $question_uid,
                'question'       => isset($expected['question']) ? $expected['question'] : '',
                'is_correct'     => $is_correct,
                'your_answer'    => $submitted,
                'correct_answer' => $expected['correct'],
                'correct_text'   => isset($expected['correct_text']) ? $expected['correct_text'] : '',
                'explanation'    => isset($expected['explanation']) ? $expected['explanation'] : '',
            );

            $faq_id = isset($expected['source_faq_id']) ? absint($expected['source_faq_id']) : 0;

            if (!$is_correct && $faq_id > 0 && $this->faq_service instanceof UR_AI_FAQ_Service) {
                $faq = $this->faq_service->find($faq_id);

                if ($faq && 'active' === $faq->status) {
                    $review_item['faq_question'] = (string) $faq->question;
                    $review_item['faq_category'] = (string) $faq->category;

                    /*
                     * 這則 FAQ 若已經被擴寫成一篇文章且已發布，複習時優先
                     * 連結內容更完整的文章；沒有發布過文章時，前端會退回
                     * 顯示既有的純文字 FAQ 提示，行為與升級前完全相同。
                     */
                    if ($this->article_service instanceof UR_AI_FAQ_Article_Service) {
                        $article_url = $this->article_service->find_published_article_url($faq_id);

                        if ('' !== $article_url) {
                            $review_item['article_url'] = $article_url;
                        }
                    }
                }
            }

            $review[] = $review_item;
        }

        $score = $total > 0 ? (int) round(($correct / $total) * 100) : 0;

        $nickname     = class_exists('UR_AI_Security') ? UR_AI_Security::sanitize_text($nickname) : sanitize_text_field((string) $nickname);
        $nickname_key = UR_AI_Quiz_Repository::normalize_nickname_key($nickname);
        $ip_hash      = $this->get_ip_hash();

        $attempt_row = array(
            'nickname'         => $nickname,
            'score'            => $score,
            'total_questions'  => $total,
            'correct_count'    => $correct,
            'duration_seconds' => absint($duration_seconds),
            'ip_hash'          => $ip_hash,
        );

        $is_new_best = true;

        if ($this->repository instanceof UR_AI_Quiz_Repository) {
            if ('' !== $nickname_key) {
                // 有填暱稱：同一暱稱只保留最高分，比對既有紀錄再決定新增或捨棄覆蓋。
                $existing = $this->repository->find_attempt_by_nickname_key($nickname_key);

                if ($existing && absint($existing->score) >= $score) {
                    $is_new_best = false;
                } elseif ($existing) {
                    $this->repository->update_attempt_score($existing->id, $attempt_row);
                } else {
                    $this->repository->create_attempt($attempt_row);
                }
            } else {
                // 匿名：每次都是獨立參與者，不與其他匿名紀錄合併比較。
                $this->repository->create_attempt($attempt_row);
            }
        }

        return array(
            'score'           => $score,
            'correct_count'   => $correct,
            'total_questions' => $total,
            'is_new_best'     => $is_new_best,
            'review'          => $review,
        );
    }

    /**
     * 取得排行榜。
     *
     * @param int $limit 顯示筆數。
     * @return array
     */
    public function get_leaderboard($limit = 20) {
        if (!$this->repository instanceof UR_AI_Quiz_Repository) {
            return array();
        }

        $rows = $this->repository->get_leaderboard($limit);

        return is_array($rows) ? $rows : array();
    }

    /**
     * 刪除一筆排行榜紀錄（後台用，處理不當暱稱）。
     *
     * @param int $id 紀錄 ID。
     * @return bool
     */
    public function delete_attempt($id) {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->delete_attempt($id) : false;
    }

    /**
     * 查詢作答紀錄列表（後台用）。
     *
     * @param int $limit 筆數。
     * @param int $offset 位移。
     * @return array
     */
    public function query_attempts($limit = 50, $offset = 0) {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->query_attempts($limit, $offset) : array();
    }

    /**
     * 計算作答紀錄總數。
     *
     * @return int
     */
    public function count_attempts() {
        return $this->repository instanceof UR_AI_Quiz_Repository ? $this->repository->count_attempts() : 0;
    }

    /**
     * 檢查目前請求是否超過節流上限（同 IP 每小時作答次數）。
     *
     * @return bool true 表示尚未超過上限、可以繼續。
     */
    private function check_rate_limit() {
        if (!$this->repository instanceof UR_AI_Quiz_Repository) {
            return true;
        }

        $ip_hash = $this->get_ip_hash();

        if ('' === $ip_hash) {
            return true;
        }

        $limit = class_exists('UR_AI_Quiz_Settings') ? UR_AI_Quiz_Settings::get_rate_limit_per_hour() : 3;
        $since = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS);

        $count = $this->repository->count_attempts_since($ip_hash, $since);

        return $count < $limit;
    }

    /**
     * 取得目前請求端的雜湊 IP（不儲存原始 IP，僅供節流／防灌水判斷）。
     *
     * @return string
     */
    private function get_ip_hash() {
        $ip = class_exists('UR_AI_Security') ? UR_AI_Security::get_user_ip() : '';

        if ('' === $ip) {
            return '';
        }

        return hash('sha256', $ip . wp_salt('auth'));
    }
}
