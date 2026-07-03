<?php
/**
 * UR AI Assistant Formatter
 *
 * 外掛共用文字格式化工具類別。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Formatter
 */
class UR_AI_Formatter {

    /**
     * 將回答文字轉成安全 HTML。
     *
     * @param string $answer 回答文字。
     * @return string
     */
    public static function answer_html($answer) {
        $answer = (string) $answer;
        $answer = trim($answer);

        if ('' === $answer) {
            return '';
        }

        $answer = self::normalize_line_breaks($answer);
        $answer = self::escape_markdown_html($answer);
        $answer = self::format_markdown_like_text($answer);

        return wp_kses_post($answer);
    }

    /**
     * 相容舊版呼叫：format_answer_html().
     *
     * @param string $answer 回答文字。
     * @return string
     */
    public static function format_answer_html($answer) {
        return self::answer_html($answer);
    }

    /**
     * 將回答文字轉為純文字。
     *
     * 相容舊版 Response Formatter 呼叫。
     *
     * @param string $answer 回答文字。
     * @return string
     */
    public static function format_answer_text($answer) {
        return self::plain_text($answer);
    }

    /**
     * 後台摘要文字。
     *
     * @param string $text 文字。
     * @param int    $length 長度。
     * @return string
     */
    public static function admin_excerpt($text, $length = 80) {
        return self::excerpt($text, $length);
    }

    /**
     * 前台摘要文字。
     *
     * @param string $text 文字。
     * @param int    $length 長度。
     * @return string
     */
    public static function public_excerpt($text, $length = 120) {
        return self::excerpt($text, $length);
    }

    /**
     * 通用摘要文字。
     *
     * @param string $text 文字。
     * @param int    $length 長度。
     * @param string $suffix 後綴。
     * @return string
     */
    public static function excerpt($text, $length = 80, $suffix = '...') {
        $text   = self::plain_text($text);
        $length = absint($length);

        if ($length <= 0) {
            $length = 80;
        }

        if (self::strlen($text) <= $length) {
            return $text;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
        }

        return substr($text, 0, $length) . $suffix;
    }

    /**
     * 相關頁面說明摘要。
     *
     * 給 Related Page Service 使用。
     *
     * @param string $description 說明文字。
     * @param int    $length 長度。
     * @return string
     */
    public static function related_page_description($description, $length = 90) {
        return self::excerpt($description, $length);
    }

    /**
     * URL 顯示標籤。
     *
     * 將完整 URL 簡化為較適合後台或前台顯示的文字。
     *
     * @param string $url URL。
     * @return string
     */
    public static function url_label($url) {
        $url = esc_url_raw((string) $url);

        if ('' === $url) {
            return '';
        }

        $parts = wp_parse_url($url);

        if (empty($parts['host'])) {
            return $url;
        }

        $label = $parts['host'];

        if (!empty($parts['path']) && '/' !== $parts['path']) {
            $path = trim($parts['path'], '/');

            if ('' !== $path) {
                $label .= '/' . self::excerpt($path, 40, '...');
            }
        }

        return $label;
    }

    /**
     * 純文字。
     *
     * @param string $text 文字。
     * @return string
     */
    public static function plain_text($text) {
        $text = (string) $text;
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * textarea 安全輸出用文字。
     *
     * @param string $text 文字。
     * @return string
     */
    public static function textarea_text($text) {
        $text = (string) $text;
        $text = self::normalize_line_breaks($text);

        return trim($text);
    }

    /**
     * CSV 欄位文字。
     *
     * @param mixed $value 欄位值。
     * @return string
     */
    public static function csv_cell($value) {
        if (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $value = (string) $value;
        $value = self::normalize_line_breaks($value);
        $value = str_replace(array("\r", "\n"), ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * 將陣列轉成逗號字串。
     *
     * @param array|string $items 項目。
     * @return string
     */
    public static function comma_list($items) {
        if (is_string($items)) {
            return trim($items);
        }

        if (!is_array($items)) {
            return '';
        }

        $clean = array();

        foreach ($items as $item) {
            $item = sanitize_text_field((string) $item);

            if ('' !== trim($item)) {
                $clean[] = $item;
            }
        }

        return implode(', ', array_values(array_unique($clean)));
    }

    /**
     * 格式化日期時間。
     *
     * @param string $datetime 日期時間。
     * @return string
     */
    public static function datetime($datetime) {
        if (class_exists('UR_AI_Helper')) {
            return UR_AI_Helper::datetime($datetime);
        }

        $timestamp = strtotime((string) $datetime);

        if (!$timestamp) {
            return (string) $datetime;
        }

        return date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
    }

    /**
     * 將換行統一為 \n。
     *
     * @param string $text 文字。
     * @return string
     */
    public static function normalize_line_breaks($text) {
        $text = (string) $text;
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        return $text;
    }

    /**
     * 字串長度。
     *
     * @param string $text 文字。
     * @return int
     */
    public static function strlen($text) {
        $text = (string) $text;

        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }

    /**
     * 轉義原始 HTML，避免 AI 回答注入。
     *
     * @param string $text 文字。
     * @return string
     */
    private static function escape_markdown_html($text) {
        return esc_html($text);
    }

    /**
     * 簡易 Markdown-like 格式轉 HTML。
     *
     * 支援：
     * - 段落
     * - 無序清單
     * - 有序清單
     * - **粗體**
     *
     * @param string $text 已 escape 的文字。
     * @return string
     */
    private static function format_markdown_like_text($text) {
        $text = self::normalize_line_breaks($text);

        $lines  = explode("\n", $text);
        $html   = '';
        $buffer = array();
        $in_ul  = false;
        $in_ol  = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ('' === $line) {
                if (!empty($buffer)) {
                    $html .= self::paragraph($buffer);
                    $buffer = array();
                }

                if ($in_ul) {
                    $html .= '</ul>';
                    $in_ul = false;
                }

                if ($in_ol) {
                    $html .= '</ol>';
                    $in_ol = false;
                }

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/u', $line, $matches)) {
                if (!empty($buffer)) {
                    $html .= self::paragraph($buffer);
                    $buffer = array();
                }

                if ($in_ol) {
                    $html .= '</ol>';
                    $in_ol = false;
                }

                if (!$in_ul) {
                    $html .= '<ul>';
                    $in_ul = true;
                }

                $html .= '<li>' . self::inline_format($matches[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\d+[\.、]\s*(.+)$/u', $line, $matches)) {
                if (!empty($buffer)) {
                    $html .= self::paragraph($buffer);
                    $buffer = array();
                }

                if ($in_ul) {
                    $html .= '</ul>';
                    $in_ul = false;
                }

                if (!$in_ol) {
                    $html .= '<ol>';
                    $in_ol = true;
                }

                $html .= '<li>' . self::inline_format($matches[1]) . '</li>';
                continue;
            }

            if ($in_ul) {
                $html .= '</ul>';
                $in_ul = false;
            }

            if ($in_ol) {
                $html .= '</ol>';
                $in_ol = false;
            }

            $buffer[] = $line;
        }

        if (!empty($buffer)) {
            $html .= self::paragraph($buffer);
        }

        if ($in_ul) {
            $html .= '</ul>';
        }

        if ($in_ol) {
            $html .= '</ol>';
        }

        return $html;
    }

    /**
     * 建立段落。
     *
     * @param array $lines 段落行。
     * @return string
     */
    private static function paragraph($lines) {
        $text = implode('<br>', array_map(array(__CLASS__, 'inline_format'), $lines));

        return '<p>' . $text . '</p>';
    }

    /**
     * 行內格式。
     *
     * @param string $text 文字。
     * @return string
     */
    private static function inline_format($text) {
        $text = (string) $text;

        /*
         * 因為文字已經 esc_html，所以這裡只轉 **文字** 為 strong。
         */
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);

        return $text;
    }
}