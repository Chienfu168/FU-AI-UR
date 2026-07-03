<?php
/**
 * UR AI Assistant Related Page Importer
 *
 * 相關頁面推薦匯入服務。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Related_Page_Importer
 */
class UR_AI_Related_Page_Importer {

    /**
     * Related Page Service.
     *
     * @var UR_AI_Related_Page_Service|null
     */
    private $service = null;

    /**
     * Post Search.
     *
     * @var UR_AI_Post_Search|null
     */
    private $post_search = null;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->service = class_exists('UR_AI_Related_Page_Service')
            ? new UR_AI_Related_Page_Service()
            : null;

        $this->post_search = class_exists('UR_AI_Post_Search')
            ? new UR_AI_Post_Search()
            : null;
    }

    /**
     * 從單一 WordPress 文章 / 頁面匯入推薦頁面。
     *
     * @param int  $post_id WordPress 文章 ID。
     * @param bool $allow_duplicate 是否允許重複匯入。
     * @return array
     */
    public function import_from_post($post_id, $allow_duplicate = false) {
        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return $this->result(false, 0, __('文章 ID 不正確。', 'ur-ai-assistant'), 'invalid_post_id');
        }

        if (!$this->service instanceof UR_AI_Related_Page_Service) {
            return $this->result(false, 0, __('推薦頁面服務尚未載入。', 'ur-ai-assistant'), 'missing_service');
        }

        if (!$this->post_search instanceof UR_AI_Post_Search) {
            return $this->result(false, 0, __('文章搜尋工具尚未載入。', 'ur-ai-assistant'), 'missing_post_search');
        }

        $post_data = $this->post_search->get_post($post_id);

        // 單篇匯入：$existing_by_*_map 傳 null，內部改用即時查詢（見 import_post_data()）。
        return $this->import_post_data($post_id, $post_data, $allow_duplicate, null, null);
    }

    /**
     * 匯入單篇文章的核心邏輯。
     *
     * 抽出此方法讓 bulk_import_from_posts() 可以傳入預先批次查好的重複檢查
     * 對照表（$existing_by_post_map / $existing_by_url_map），避免對
     * related_pages 資料表逐篇文章各自查詢一次（N+1）。單篇匯入
     * （import_from_post()）則傳 null，維持原本的即時單筆查詢行為。
     *
     * @param int        $post_id 文章 ID。
     * @param array|null $post_data 文章資料。
     * @param bool       $allow_duplicate 是否允許重複匯入。
     * @param array|null $existing_by_post_map source_post_id => object；null 表示改用即時查詢。
     * @param array|null $existing_by_url_map  url => object；null 表示改用即時查詢。
     * @return array
     */
    private function import_post_data($post_id, $post_data, $allow_duplicate, $existing_by_post_map, $existing_by_url_map) {
        if (empty($post_data)) {
            return $this->result(false, 0, __('找不到可匯入的已發布文章或頁面。', 'ur-ai-assistant'), 'post_not_found');
        }

        $url = isset($post_data['url']) ? $post_data['url'] : '';

        if (!$allow_duplicate) {
            $existing_by_post = is_array($existing_by_post_map)
                ? (isset($existing_by_post_map[$post_id]) ? $existing_by_post_map[$post_id] : null)
                : $this->service->find_by_source_post_id($post_id);

            if ($existing_by_post) {
                return $this->result(
                    false,
                    absint($this->get_value($existing_by_post, 'id', 0)),
                    __('此文章已匯入過推薦頁面。', 'ur-ai-assistant'),
                    'already_imported'
                );
            }

            $existing_by_url = is_array($existing_by_url_map)
                ? (isset($existing_by_url_map[$url]) ? $existing_by_url_map[$url] : null)
                : $this->service->find_by_url($url);

            if ($existing_by_url) {
                return $this->result(
                    false,
                    absint($this->get_value($existing_by_url, 'id', 0)),
                    __('此網址已存在於推薦頁面中。', 'ur-ai-assistant'),
                    'url_exists'
                );
            }
        }

        $data = $this->post_search->to_related_page_data($post_data);

        if (empty($data)) {
            return $this->result(false, 0, __('文章資料轉換失敗。', 'ur-ai-assistant'), 'convert_failed');
        }

        $related_page_id = $this->service->create($data);

        if ($related_page_id <= 0) {
            return $this->result(false, 0, __('推薦頁面建立失敗。', 'ur-ai-assistant'), 'create_failed');
        }

        return $this->result(
            true,
            $related_page_id,
            __('已成功匯入推薦頁面，預設為停用，請檢查後再啟用。', 'ur-ai-assistant'),
            'imported'
        );
    }

    /**
     * 批次從 WordPress 文章 / 頁面匯入。
     *
     * @param array $post_ids WordPress 文章 ID 陣列。
     * @param bool  $allow_duplicate 是否允許重複匯入。
     * @return array
     */
    public function bulk_import_from_posts($post_ids, $allow_duplicate = false) {
        if (!is_array($post_ids)) {
            return $this->empty_bulk_result();
        }

        $post_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $post_ids)
                )
            )
        );

        if (empty($post_ids)) {
            return $this->empty_bulk_result();
        }

        if (!$this->service instanceof UR_AI_Related_Page_Service || !$this->post_search instanceof UR_AI_Post_Search) {
            // 缺少必要依賴時，交由 import_from_post() 逐一回報明確的錯誤代碼。
            return $this->import_posts_one_by_one($post_ids, $allow_duplicate);
        }

        // 批次預先取得每篇文章資料與重複檢查對照表（各 1 次查詢），
        // 取代原本「每篇文章各自查詢 related_pages 資料表 2 次」的 N+1 寫法。
        $post_data_map = array();
        $urls          = array();

        foreach ($post_ids as $post_id) {
            $post_data               = $this->post_search->get_post($post_id);
            $post_data_map[$post_id] = $post_data;

            if (!empty($post_data['url'])) {
                $urls[] = $post_data['url'];
            }
        }

        $existing_by_post_map = $allow_duplicate ? array() : $this->service->find_existing_by_source_post_ids($post_ids);
        $existing_by_url_map  = $allow_duplicate ? array() : $this->service->find_existing_by_urls($urls);

        $results       = array();
        $success_count = 0;
        $failed_count  = 0;
        $skipped_count = 0;

        foreach ($post_ids as $post_id) {
            $result = $this->import_post_data(
                $post_id,
                $post_data_map[$post_id],
                $allow_duplicate,
                $existing_by_post_map,
                $existing_by_url_map
            );

            $results[] = array_merge(
                array(
                    'post_id' => $post_id,
                ),
                $result
            );

            if (!empty($result['success'])) {
                $success_count++;
                continue;
            }

            if (in_array($result['code'], array('already_imported', 'url_exists'), true)) {
                $skipped_count++;
                continue;
            }

            $failed_count++;
        }

        return array(
            'success_count' => $success_count,
            'failed_count'  => $failed_count,
            'skipped_count' => $skipped_count,
            'results'       => $results,
        );
    }

    /**
     * 逐篇呼叫 import_from_post()（僅在必要依賴缺失、無法批次查詢時使用）。
     *
     * @param array $post_ids 文章 ID 陣列。
     * @param bool  $allow_duplicate 是否允許重複匯入。
     * @return array
     */
    private function import_posts_one_by_one($post_ids, $allow_duplicate) {
        $results       = array();
        $success_count = 0;
        $failed_count  = 0;
        $skipped_count = 0;

        foreach ($post_ids as $post_id) {
            $result = $this->import_from_post($post_id, $allow_duplicate);

            $results[] = array_merge(array('post_id' => $post_id), $result);

            if (!empty($result['success'])) {
                $success_count++;
                continue;
            }

            if (in_array($result['code'], array('already_imported', 'url_exists'), true)) {
                $skipped_count++;
                continue;
            }

            $failed_count++;
        }

        return array(
            'success_count' => $success_count,
            'failed_count'  => $failed_count,
            'skipped_count' => $skipped_count,
            'results'       => $results,
        );
    }

    /**
     * 空批次結果。
     *
     * @return array
     */
    private function empty_bulk_result() {
        return array(
            'success_count' => 0,
            'failed_count'  => 0,
            'skipped_count' => 0,
            'results'       => array(),
        );
    }

    /**
     * 搜尋可匯入文章。
     *
     * @param string $keyword 搜尋字。
     * @param int    $limit 筆數。
     * @return array
     */
    public function search_importable_posts($keyword = '', $limit = 20) {
        if (!$this->post_search instanceof UR_AI_Post_Search) {
            return array();
        }

        $posts = $this->post_search->search($keyword, $limit);

        if (empty($posts)) {
            return array();
        }

        // 批次預先查好所有結果的重複檢查對照表（各 1 次查詢），
        // 取代原本每篇搜尋結果各自查詢 related_pages 資料表 2 次的寫法。
        $existing_by_post_map = array();
        $existing_by_url_map  = array();

        if ($this->service instanceof UR_AI_Related_Page_Service) {
            $post_ids = array();
            $urls     = array();

            foreach ($posts as $post_data) {
                if (!empty($post_data['post_id'])) {
                    $post_ids[] = absint($post_data['post_id']);
                }
                if (!empty($post_data['url'])) {
                    $urls[] = (string) $post_data['url'];
                }
            }

            $existing_by_post_map = $this->service->find_existing_by_source_post_ids($post_ids);
            $existing_by_url_map  = $this->service->find_existing_by_urls($urls);
        }

        $items = array();

        foreach ($posts as $post_data) {
            $post_id = isset($post_data['post_id']) ? absint($post_data['post_id']) : 0;
            $url     = isset($post_data['url']) ? (string) $post_data['url'] : '';

            $already_imported = false;
            $existing_id      = 0;

            if (isset($existing_by_post_map[$post_id])) {
                $already_imported = true;
                $existing_id      = absint($this->get_value($existing_by_post_map[$post_id], 'id', 0));
            } elseif ('' !== $url && isset($existing_by_url_map[$url])) {
                $already_imported = true;
                $existing_id      = absint($this->get_value($existing_by_url_map[$url], 'id', 0));
            }

            $post_data['already_imported']       = $already_imported;
            $post_data['existing_related_page_id'] = $existing_id;

            $items[] = $post_data;
        }

        return $items;
    }

    /**
     * 從文章資料預覽推薦頁面資料。
     *
     * @param int $post_id WordPress 文章 ID。
     * @return array
     */
    public function preview_from_post($post_id) {
        $post_id = absint($post_id);

        if ($post_id <= 0 || !$this->post_search instanceof UR_AI_Post_Search) {
            return array();
        }

        $post_data = $this->post_search->get_post($post_id);

        if (empty($post_data)) {
            return array();
        }

        return $this->post_search->to_related_page_data($post_data);
    }

    /**
     * 建立結果格式。
     *
     * @param bool   $success 是否成功。
     * @param int    $related_page_id 推薦頁面 ID。
     * @param string $message 訊息。
     * @param string $code 代碼。
     * @return array
     */
    private function result($success, $related_page_id, $message, $code = '') {
        return array(
            'success'         => (bool) $success,
            'related_page_id' => absint($related_page_id),
            'message'         => is_string($message) ? $message : '',
            'code'            => is_string($code) ? sanitize_key($code) : '',
        );
    }

    /**
     * 安全取得資料值。
     *
     * @param mixed  $data 資料。
     * @param string $key 鍵名。
     * @param mixed  $default 預設值。
     * @return mixed
     */
    private function get_value($data, $key, $default = null) {
        if (class_exists('UR_AI_Helper')) {
            return UR_AI_Helper::data_get($data, $key, $default);
        }

        if (is_array($data) && array_key_exists($key, $data)) {
            return $data[$key];
        }

        if (is_object($data) && isset($data->{$key})) {
            return $data->{$key};
        }

        return $default;
    }
}