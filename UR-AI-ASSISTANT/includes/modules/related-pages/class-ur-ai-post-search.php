<?php
/**
 * UR AI Assistant Post Search
 *
 * WordPress 文章 / 頁面搜尋工具。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Post_Search
 */
class UR_AI_Post_Search {

    /**
     * 可搜尋的文章類型。
     *
     * @var array
     */
    private $post_types = array('post', 'page');

    /**
     * 搜尋文章 / 頁面。
     *
     * @param string $keyword 搜尋關鍵字。
     * @param int    $limit 筆數。
     * @return array
     */
    public function search($keyword = '', $limit = 20) {
        $keyword = is_string($keyword) ? sanitize_text_field($keyword) : '';
        $limit   = absint($limit);

        if ($limit <= 0) {
            $limit = 20;
        }

        $args = array(
            'post_type'      => $this->post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'suppress_filters' => false,
        );

        if ('' !== trim($keyword)) {
            $args['s'] = $keyword;
        }

        $query = new WP_Query($args);

        $items = array();

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $items[] = $this->format_post($post);
            }
        }

        wp_reset_postdata();

        return $items;
    }

    /**
     * 依文章 ID 取得文章資料。
     *
     * @param int $post_id 文章 ID。
     * @return array
     */
    public function get_post($post_id) {
        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return array();
        }

        $post = get_post($post_id);

        if (!$post || !in_array($post->post_type, $this->post_types, true)) {
            return array();
        }

        if ('publish' !== $post->post_status) {
            return array();
        }

        return $this->format_post($post);
    }

    /**
     * 批次取得文章資料。
     *
     * @param array $post_ids 文章 ID 陣列。
     * @return array
     */
    public function get_posts_by_ids($post_ids) {
        if (!is_array($post_ids)) {
            return array();
        }

        $post_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $post_ids)
                )
            )
        );

        if (empty($post_ids)) {
            return array();
        }

        $query = new WP_Query(
            array(
                'post_type'      => $this->post_types,
                'post_status'    => 'publish',
                'post__in'       => $post_ids,
                'posts_per_page' => count($post_ids),
                'orderby'        => 'post__in',
                'suppress_filters' => false,
            )
        );

        $items = array();

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $items[] = $this->format_post($post);
            }
        }

        wp_reset_postdata();

        return $items;
    }

    /**
     * 格式化文章資料。
     *
     * @param WP_Post $post 文章物件。
     * @return array
     */
    public function format_post($post) {
        if (!$post instanceof WP_Post) {
            return array();
        }

        $title       = get_the_title($post);
        $url         = get_permalink($post);
        $description = $this->get_description($post);
        $category    = $this->guess_category($post);
        $keywords    = $this->guess_keywords($post, $category);

        return array(
            'post_id'     => absint($post->ID),
            'post_type'   => sanitize_key($post->post_type),
            'source'      => $this->get_source_from_post_type($post->post_type),
            'title'       => $title,
            'url'         => esc_url_raw($url),
            'description' => $description,
            'category'    => $category,
            'keywords'    => $keywords,
            'date'        => get_the_date('Y-m-d H:i:s', $post),
        );
    }

    /**
     * 將文章資料轉為推薦頁面資料。
     *
     * @param array $post_data format_post 回傳資料。
     * @return array
     */
    public function to_related_page_data($post_data) {
        if (!is_array($post_data) || empty($post_data)) {
            return array();
        }

        return array(
            'category'       => isset($post_data['category']) ? $post_data['category'] : '待分類',
            'title'          => isset($post_data['title']) ? $post_data['title'] : '',
            'url'            => isset($post_data['url']) ? $post_data['url'] : '',
            'description'    => isset($post_data['description']) ? $post_data['description'] : '',
            'keywords'       => isset($post_data['keywords']) ? $post_data['keywords'] : '',
            'status'         => 'inactive',
            'source'         => isset($post_data['source']) ? $post_data['source'] : 'post',
            'source_post_id' => isset($post_data['post_id']) ? absint($post_data['post_id']) : 0,
            'sort_order'     => 100,
            'admin_note'     => __('由 WordPress 文章 / 頁面匯入，請檢查分類、摘要與關鍵字後再啟用。', 'ur-ai-assistant'),
        );
    }

    /**
     * 取得文章摘要。
     *
     * @param WP_Post $post 文章物件。
     * @return string
     */
    private function get_description($post) {
        $excerpt = '';

        if (!empty($post->post_excerpt)) {
            $excerpt = $post->post_excerpt;
        } else {
            $excerpt = wp_strip_all_tags($post->post_content);
        }

        $excerpt = trim(preg_replace('/\s+/u', ' ', $excerpt));

        if (class_exists('UR_AI_Helper')) {
            return UR_AI_Helper::truncate($excerpt, 90);
        }

        if (function_exists('mb_substr')) {
            return mb_substr($excerpt, 0, 90, 'UTF-8');
        }

        return substr($excerpt, 0, 90);
    }

    /**
     * 推測分類。
     *
     * @param WP_Post $post 文章物件。
     * @return string
     */
    private function guess_category($post) {
        $text = get_the_title($post) . ' ' . wp_strip_all_tags($post->post_content);

        if (class_exists('UR_AI_FAQ_Category_Helper')) {
            $helper = new UR_AI_FAQ_Category_Helper();
            $category = $helper->suggest_category($text);

            if ('' !== trim($category)) {
                return $category;
            }
        }

        $rules = array(
            '權利變換' => array('權利變換', '分配', '估價', '共同負擔', '找補'),
            '協議合建' => array('協議合建', '合建', '建商', '合建契約'),
            '更新會'   => array('更新會', '會員大會', '理事會', '監事'),
            '自主更新' => array('自主更新', '地主發起', '自辦都更'),
            '危老重建' => array('危老', '危老重建', '耐震', '老屋'),
            '都市更新' => array('都市更新', '都更', '事業概要', '事業計畫'),
            '行政救濟' => array('訴願', '行政訴訟', '救濟', '停止執行'),
        );

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (false !== mb_stripos($text, $keyword, 0, 'UTF-8')) {
                    return $category;
                }
            }
        }

        return '待分類';
    }

    /**
     * 推測關鍵字。
     *
     * @param WP_Post $post 文章物件。
     * @param string  $category 分類。
     * @return string
     */
    private function guess_keywords($post, $category) {
        $title   = get_the_title($post);
        $content = wp_strip_all_tags($post->post_content);
        $text    = $title . ' ' . $content;

        if (class_exists('UR_AI_FAQ_Category_Helper')) {
            $helper = new UR_AI_FAQ_Category_Helper();
            return $helper->suggest_keywords($category, $title, $content);
        }

        $keywords = array();

        $candidate_keywords = array(
            '都市更新',
            '都更',
            '危老重建',
            '危老',
            '更新會',
            '自主更新',
            '權利變換',
            '協議合建',
            '共同負擔',
            '估價',
            '分配',
            '同意書',
            '同意比例',
            '信託',
            '履約保證',
            '行政訴訟',
            '訴願',
        );

        foreach ($candidate_keywords as $keyword) {
            if (false !== mb_stripos($text, $keyword, 0, 'UTF-8')) {
                $keywords[] = $keyword;
            }
        }

        if (empty($keywords) && '' !== trim($category)) {
            $keywords[] = $category;
        }

        $keywords = array_values(array_unique($keywords));

        return implode(', ', array_slice($keywords, 0, 12));
    }

    /**
     * 依文章類型取得來源代碼。
     *
     * @param string $post_type 文章類型。
     * @return string
     */
    private function get_source_from_post_type($post_type) {
        $post_type = sanitize_key($post_type);

        if ('page' === $post_type) {
            return 'page';
        }

        return 'post';
    }

    /**
     * 設定可搜尋文章類型。
     *
     * @param array $post_types 文章類型。
     * @return void
     */
    public function set_post_types($post_types) {
        if (!is_array($post_types)) {
            return;
        }

        $clean = array();

        foreach ($post_types as $post_type) {
            $post_type = sanitize_key($post_type);

            if (post_type_exists($post_type)) {
                $clean[] = $post_type;
            }
        }

        if (!empty($clean)) {
            $this->post_types = array_values(array_unique($clean));
        }
    }

    /**
     * 取得可搜尋文章類型。
     *
     * @return array
     */
    public function get_post_types() {
        return $this->post_types;
    }
}