<?php
/**
 * Plugin Name: UR AI Assistant
 * Plugin URI: https://www.ur-promoter.com/
 * Description: 都更危老 AI 助理，提供都市更新、危老重建、更新會、自主更新、權利變換、協議合建等知識問答、FAQ 知識庫、相關文章推薦、熱門問題導覽與後台分析功能。
 * Version: 1.19.0
 * Author: UR Promoter
 * Author URI: https://www.ur-promoter.com/
 * Text Domain: ur-ai-assistant
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/Chienfu168/FU-AI-UR/
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 外掛版本
 *
 * 從零重寫版，作為長期穩定架構起點。
 */
define('UR_AI_ASSISTANT_VERSION', '1.19.0');

/**
 * 外掛基本路徑常數
 */
define('UR_AI_ASSISTANT_PLUGIN_FILE', __FILE__);
define('UR_AI_ASSISTANT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UR_AI_ASSISTANT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UR_AI_ASSISTANT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * 最低 PHP 版本需求
 */
define('UR_AI_ASSISTANT_MIN_PHP_VERSION', '7.4');

/**
 * 外掛主要 option key
 */
define('UR_AI_ASSISTANT_OPTION_SETTINGS', 'ur_ai_assistant_settings');
define('UR_AI_ASSISTANT_OPTION_DB_VERSION', 'ur_ai_assistant_db_version');

/**
 * 外掛資料庫版本
 *
 * 之後若資料表 schema 有變更，可獨立調整這個版本。
 */
define('UR_AI_ASSISTANT_DB_VERSION', '1.1.0');

/**
 * 檢查 PHP 版本是否符合需求。
 *
 * @return bool
 */
function ur_ai_assistant_check_php_version() {
    return version_compare(PHP_VERSION, UR_AI_ASSISTANT_MIN_PHP_VERSION, '>=');
}

/**
 * PHP 版本不足時顯示後台提示。
 *
 * @return void
 */
function ur_ai_assistant_php_version_notice() {
    if (ur_ai_assistant_check_php_version()) {
        return;
    }

    if (!current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-error"><p>';
    echo esc_html(
        sprintf(
            /* translators: 1: plugin name, 2: minimum PHP version, 3: current PHP version */
            __('%1$s 需要 PHP %2$s 以上版本才能執行。您目前的 PHP 版本是 %3$s。', 'ur-ai-assistant'),
            'UR AI Assistant',
            UR_AI_ASSISTANT_MIN_PHP_VERSION,
            PHP_VERSION
        )
    );
    echo '</p></div>';
}
add_action('admin_notices', 'ur_ai_assistant_php_version_notice');

/**
 * 載入語系檔。
 *
 * @return void
 */
function ur_ai_assistant_load_textdomain() {
    load_plugin_textdomain(
        'ur-ai-assistant',
        false,
        dirname(UR_AI_ASSISTANT_PLUGIN_BASENAME) . '/languages'
    );
}
add_action('plugins_loaded', 'ur_ai_assistant_load_textdomain');

/**
 * 載入 Autoloader。
 *
 * @return bool
 */
function ur_ai_assistant_load_autoloader() {
    $autoload_file = UR_AI_ASSISTANT_PLUGIN_DIR . 'includes/core/class-ur-ai-autoloader.php';

    if (!file_exists($autoload_file)) {
        add_action(
            'admin_notices',
            function () {
                if (!current_user_can('activate_plugins')) {
                    return;
                }

                echo '<div class="notice notice-error"><p>';
                echo esc_html__('UR AI Assistant 載入失敗：找不到 Autoloader 檔案。請確認外掛檔案是否完整上傳。', 'ur-ai-assistant');
                echo '</p></div>';
            }
        );

        return false;
    }

    require_once $autoload_file;

    if (!class_exists('UR_AI_Autoloader')) {
        add_action(
            'admin_notices',
            function () {
                if (!current_user_can('activate_plugins')) {
                    return;
                }

                echo '<div class="notice notice-error"><p>';
                echo esc_html__('UR AI Assistant 載入失敗：Autoloader 類別不存在。請確認檔案內容是否正確。', 'ur-ai-assistant');
                echo '</p></div>';
            }
        );

        return false;
    }

    UR_AI_Autoloader::register();

    return true;
}

/**
 * 外掛啟用處理。
 *
 * @return void
 */
function ur_ai_assistant_activate() {
    if (!ur_ai_assistant_check_php_version()) {
        deactivate_plugins(UR_AI_ASSISTANT_PLUGIN_BASENAME);

        wp_die(
            esc_html(
                sprintf(
                    /* translators: 1: minimum PHP version, 2: current PHP version */
                    __('UR AI Assistant 需要 PHP %1$s 以上版本才能啟用。您目前的 PHP 版本是 %2$s。', 'ur-ai-assistant'),
                    UR_AI_ASSISTANT_MIN_PHP_VERSION,
                    PHP_VERSION
                )
            ),
            esc_html__('外掛啟用失敗', 'ur-ai-assistant'),
            array(
                'back_link' => true,
            )
        );
    }

    if (!ur_ai_assistant_load_autoloader()) {
        deactivate_plugins(UR_AI_ASSISTANT_PLUGIN_BASENAME);

        wp_die(
            esc_html__('UR AI Assistant 啟用失敗：必要檔案載入失敗。請確認外掛檔案完整。', 'ur-ai-assistant'),
            esc_html__('外掛啟用失敗', 'ur-ai-assistant'),
            array(
                'back_link' => true,
            )
        );
    }

    if (class_exists('UR_AI_Activator')) {
        UR_AI_Activator::activate();
    }
}
register_activation_hook(__FILE__, 'ur_ai_assistant_activate');

/**
 * 外掛停用處理。
 *
 * @return void
 */
function ur_ai_assistant_deactivate() {
    if (!ur_ai_assistant_load_autoloader()) {
        return;
    }

    if (class_exists('UR_AI_Deactivator')) {
        UR_AI_Deactivator::deactivate();
    }
}
register_deactivation_hook(__FILE__, 'ur_ai_assistant_deactivate');

/**
 * 啟動外掛主程式。
 *
 * @return void
 */
function ur_ai_assistant_run() {
    if (!ur_ai_assistant_check_php_version()) {
        return;
    }

    if (!ur_ai_assistant_load_autoloader()) {
        return;
    }

    if (!class_exists('UR_AI_Bootstrap')) {
        add_action(
            'admin_notices',
            function () {
                if (!current_user_can('activate_plugins')) {
                    return;
                }

                echo '<div class="notice notice-error"><p>';
                echo esc_html__('UR AI Assistant 啟動失敗：找不到 Bootstrap 類別。請確認外掛檔案是否完整。', 'ur-ai-assistant');
                echo '</p></div>';
            }
        );

        return;
    }

    $bootstrap = new UR_AI_Bootstrap();
    $bootstrap->run();
}
add_action('plugins_loaded', 'ur_ai_assistant_run', 20);