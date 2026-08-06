<?php
/**
 * UR AI Assistant Partner Service
 *
 * 相關產業名錄的分類定義、地區解析、排序與卡片渲染邏輯。
 * 移植自 UR_SYS `inc/partners.php`，改用 WordPress 逸出函式與外掛自有樣式。
 *
 * 名錄僅供資訊查詢與自行洽詢參考，不代表本平台之認證或背書；卡片只標示
 * 「資料已核對／公開資料待補充」與資料來源、最後核對日，不使用「推薦」字樣。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Partner_Service
 */
class UR_AI_Partner_Service {

    /**
     * 內建分類：key => 顯示名稱。自訂分類則以名稱本身為 key。
     *
     * @return array
     */
    public static function builtin_categories() {
        return array(
            'appraiser'   => __('估價師', 'ur-ai-assistant'),
            'land_agent'  => __('地政士', 'ur-ai-assistant'),
            'developer'   => __('建商', 'ur-ai-assistant'),
            'implementer' => __('實施者', 'ur-ai-assistant'),
            'architect'   => __('建築師', 'ur-ai-assistant'),
            'constructor' => __('營造廠', 'ur-ai-assistant'),
            'lawyer'      => __('律師', 'ur-ai-assistant'),
            'finance'     => __('金融／融資', 'ur-ai-assistant'),
            'consultant'  => __('更新顧問', 'ur-ai-assistant'),
            'other'       => __('其他專業服務', 'ur-ai-assistant'),
        );
    }

    /**
     * 顯示名稱：內建 key 轉中文；自訂分類本身即顯示名稱。
     *
     * @param string $stored 儲存值。
     * @return string
     */
    public static function category_label($stored) {
        $builtin = self::builtin_categories();
        if (isset($builtin[$stored])) {
            return $builtin[$stored];
        }
        return '' !== $stored ? $stored : __('其他專業服務', 'ur-ai-assistant');
    }

    /**
     * 把使用者輸入（內建 key／內建中文名稱／全新自訂名稱）正規化為儲存值。
     *
     * @param string $input 輸入。
     * @return string
     */
    public static function normalize_category($input) {
        $input = trim((string) $input);
        if ('' === $input) {
            return 'other';
        }
        $builtin = self::builtin_categories();
        if (isset($builtin[$input])) {
            return $input;
        }
        $label_to_key = array_flip($builtin);
        if (isset($label_to_key[$input])) {
            return $label_to_key[$input];
        }
        return function_exists('mb_substr') ? mb_substr($input, 0, 30) : substr($input, 0, 30);
    }

    /**
     * 分類對應的圖示 emoji（無 logo 的名錄用來識別）。
     *
     * @param string $key 分類 key。
     * @return string
     */
    public static function category_icon($key) {
        $map = array(
            'appraiser'   => '🧮',
            'land_agent'  => '📑',
            'developer'   => '🏢',
            'implementer' => '🔨',
            'architect'   => '📐',
            'constructor' => '🧱',
            'lawyer'      => '⚖️',
            'finance'     => '💰',
            'consultant'  => '👥',
            'other'       => '💼',
        );
        return isset($map[$key]) ? $map[$key] : '🏛️';
    }

    /**
     * 名錄等級。
     *
     * @return array
     */
    public static function tiers() {
        return array(
            'standard' => __('一般（隨機輪播）', 'ur-ai-assistant'),
            'featured' => __('置頂曝光', 'ur-ai-assistant'),
        );
    }

    /**
     * 資料來源類型。
     *
     * @return array
     */
    public static function source_types() {
        return array(
            '政府名冊' => __('政府名冊', 'ur-ai-assistant'),
            '公會'     => __('專業公會', 'ur-ai-assistant'),
            '官方網站' => __('單位官方網站', 'ur-ai-assistant'),
            '業者提供' => __('業者提供', 'ur-ai-assistant'),
        );
    }

    /**
     * 台灣縣市清單。
     *
     * @return array
     */
    public static function city_list() {
        return array(
            '台北市', '新北市', '桃園市', '台中市', '台南市', '高雄市',
            '基隆市', '新竹市', '新竹縣', '苗栗縣', '彰化縣', '南投縣',
            '雲林縣', '嘉義市', '嘉義縣', '屏東縣', '宜蘭縣', '花蓮縣',
            '台東縣', '澎湖縣', '金門縣', '連江縣',
        );
    }

    /**
     * 把 region 拆成 [縣市, 行政區]，支援「台／臺」通用。
     *
     * @param string $region 地區字串。
     * @return array
     */
    public static function split_region($region) {
        $region = trim((string) $region);
        if ('' === $region) {
            return array('', '');
        }
        $strpos  = function_exists('mb_strpos') ? 'mb_strpos' : 'strpos';
        $strlen  = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $substr  = function_exists('mb_substr') ? 'mb_substr' : 'substr';
        $norm    = str_replace('臺', '台', $region);
        foreach (self::city_list() as $city) {
            $cnorm = str_replace('臺', '台', $city);
            if (0 === $strpos($norm, $cnorm)) {
                $rest = trim($substr($region, $strlen($city)));
                return array($city, $rest);
            }
        }
        return array('', '');
    }

    /**
     * 是否為目前有效的置頂曝光。
     *
     * @param array $p 名錄。
     * @return bool
     */
    public static function is_featured_active(array $p) {
        if ('featured' !== ($p['tier'] ?? '')) {
            return false;
        }
        $until = trim((string) ($p['featured_until'] ?? ''));
        if ('' === $until || '0000-00-00' === $until) {
            return true;
        }
        $ts = strtotime($until);
        return false === $ts ? true : ($ts >= strtotime(gmdate('Y-m-d')));
    }

    /**
     * 「資料更新」顯示標籤（優先核對日→修改日→建立日）。
     *
     * @param array $p 名錄。
     * @return string
     */
    public static function updated_label(array $p) {
        $src = trim((string) ($p['verified_at'] ?? '')) ?: trim((string) ($p['updated_at'] ?? '')) ?: trim((string) ($p['created_at'] ?? ''));
        if ('' === $src || false === strtotime($src)) {
            return '';
        }
        return date_i18n('Y年n月', strtotime($src));
    }

    /**
     * 產生卡片外層的 data-* 屬性字串，供前端即時搜尋／篩選使用。
     *
     * @param array $p 名錄。
     * @return string
     */
    public static function data_attrs(array $p) {
        list($city, $district) = self::split_region($p['region'] ?? '');
        $catlabel = self::category_label($p['category'] ?? 'other');
        $website  = trim((string) ($p['website_url'] ?? ''));
        $has_web  = ('' !== $website && preg_match('#^https?://#i', $website)) ? '1' : '0';
        $verified = !empty($p['is_verified']) ? '1' : '0';
        $featured = self::is_featured_active($p) ? '1' : '0';

        $lower = function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';
        $search = $lower(implode(' ', array_filter(array(
            $p['name'] ?? '', $catlabel, $p['region'] ?? '', $p['tagline'] ?? '', $p['description'] ?? '', $p['phone'] ?? '',
        ))));

        return implode(' ', array(
            'data-name="' . esc_attr($p['name'] ?? '') . '"',
            'data-cat="' . esc_attr($p['category'] ?? 'other') . '"',
            'data-catlabel="' . esc_attr($catlabel) . '"',
            'data-city="' . esc_attr($city) . '"',
            'data-district="' . esc_attr($district) . '"',
            'data-search="' . esc_attr($search) . '"',
            'data-website="' . $has_web . '"',
            'data-verified="' . $verified . '"',
            'data-featured="' . $featured . '"',
            'data-weight="' . (int) ($p['weight'] ?? 1) . '"',
        ));
    }

    /**
     * 單張名錄卡片 HTML（前台與後台預覽共用）。
     *
     * @param array $p        名錄。
     * @param bool  $featured 是否為置頂曝光樣式。
     * @return string
     */
    public static function render_card(array $p, $featured = false) {
        $name     = (string) ($p['name'] ?? '');
        $cat      = self::category_label($p['category'] ?? 'other');
        $icon     = self::category_icon($p['category'] ?? 'other');
        $region   = trim((string) ($p['region'] ?? ''));
        $tagline  = trim((string) ($p['tagline'] ?? ''));
        $desc     = trim((string) ($p['description'] ?? ''));
        $phone    = trim((string) ($p['phone'] ?? ''));
        $website  = trim((string) ($p['website_url'] ?? ''));
        $logo     = trim((string) ($p['logo_url'] ?? ''));
        $verified = !empty($p['is_verified']);

        $media = ('' !== $logo && preg_match('#^https?://#i', $logo))
            ? '<img class="ur-ai-partners__logo" src="' . esc_url($logo) . '" alt="' . esc_attr($name) . '" loading="lazy" referrerpolicy="no-referrer">'
            : '<span class="ur-ai-partners__icon" aria-hidden="true">' . esc_html($icon) . '</span>';

        $h  = '<div class="ur-ai-partners__card' . ($featured ? ' is-featured' : '') . '">';

        if ($featured) {
            $h .= '<div class="ur-ai-partners__pin">📌 ' . esc_html__('置頂曝光', 'ur-ai-assistant') . '</div>';
        }

        $h .= '<div class="ur-ai-partners__head">' . $media . '<div class="ur-ai-partners__head-text">';
        $h .= '<h4 class="ur-ai-partners__name">' . esc_html($name) . '</h4>';
        $h .= '<div class="ur-ai-partners__meta">';
        $h .= '<span class="ur-ai-partners__cat">' . esc_html($cat) . '</span>';
        if ('' !== $region) {
            $h .= '<span class="ur-ai-partners__region">' . esc_html($region) . '</span>';
        }
        $h .= '</div></div></div>';

        if ('' !== $tagline) {
            $h .= '<p class="ur-ai-partners__tagline">' . esc_html($tagline) . '</p>';
        }
        if ('' !== $desc) {
            $h .= '<p class="ur-ai-partners__desc">' . nl2br(esc_html($desc)) . '</p>';
        }

        $links = array();
        if ('' !== $phone) {
            $tel = preg_replace('/[^0-9+]/', '', $phone);
            $links[] = '<a class="ur-ai-partners__link" href="tel:' . esc_attr($tel) . '">📞 ' . esc_html($phone) . '</a>';
        }
        if ('' !== $website) {
            $safe = filter_var($website, FILTER_VALIDATE_URL) ? $website : '';
            if ('' !== $safe && preg_match('#^https?://#i', $safe)) {
                $links[] = '<a class="ur-ai-partners__link" href="' . esc_url($safe) . '" target="_blank" rel="noopener noreferrer nofollow">🔗 ' . esc_html__('官方網站', 'ur-ai-assistant') . '</a>';
            }
        }
        if ($links) {
            $h .= '<div class="ur-ai-partners__links">' . implode('', $links) . '</div>';
        }

        $h .= '<div class="ur-ai-partners__foot">';
        if ($verified) {
            $h .= '<span class="ur-ai-partners__verified" title="' . esc_attr__('依政府公開名冊、專業公會或單位官方網站整理。', 'ur-ai-assistant') . '">✔ ' . esc_html__('資料已核對', 'ur-ai-assistant') . '</span>';
        } else {
            $h .= '<span class="ur-ai-partners__unverified">ℹ ' . esc_html__('公開資料待補充', 'ur-ai-assistant') . '</span>';
        }
        $upd = self::updated_label($p);
        if ('' !== $upd) {
            $h .= '<span class="ur-ai-partners__updated">' . esc_html__('資料更新：', 'ur-ai-assistant') . esc_html($upd) . '</span>';
        }
        $h .= '</div>';

        $h .= '</div>';

        return $h;
    }
}
