<?php
/**
 * Plugin Name: UR AI Gateway
 * Plugin URI: https://www.ur-promoter.com/
 * Description: 「UR AI Assistant」外掛的代管 AI 服務授權與代理外掛。安裝在服務提供站（例如 ur-promoter.com），依 WooCommerce／WooCommerce Subscriptions 訂單狀態發放與管理授權碼，並提供一個 REST API 端點代理呼叫 OpenAI，讓客戶端網站不需要自行申請 OpenAI API Key。
 * Version: 1.0.0
 * Author: UR Promoter
 * Author URI: https://www.ur-promoter.com/
 * Text Domain: ur-ai-gateway
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 外掛版本。
 */
define('UR_AI_GATEWAY_VERSION', '1.0.0');

/**
 * 外掛基本路徑常數。
 */
define('UR_AI_GATEWAY_PLUGIN_FILE', __FILE__);
define('UR_AI_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UR_AI_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UR_AI_GATEWAY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * 最低 PHP 版本需求。
 */
define('UR_AI_GATEWAY_MIN_PHP_VERSION', '7.4');

/**
 * 外掛主要 option key。
 */
define('UR_AI_GATEWAY_OPTION_SETTINGS', 'ur_ai_gateway_settings');
define('UR_AI_GATEWAY_OPTION_DB_VERSION', 'ur_ai_gateway_db_version');

/**
 * 資料庫版本。
 */
define('UR_AI_GATEWAY_DB_VERSION', '1.0.0');

/**
 * 檢查 PHP 版本是否符合需求。
 *
 * @return bool
 */
function ur_ai_gateway_check_php_version() {
    return version_compare(PHP_VERSION, UR_AI_GATEWAY_MIN_PHP_VERSION, '>=');
}

/**
 * PHP 版本不足時顯示後台提示。
 *
 * @return void
 */
function ur_ai_gateway_php_version_notice() {
    if (ur_ai_gateway_check_php_version()) {
        return;
    }

    if (!current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-error"><p>';
    echo esc_html(
        sprintf(
            /* translators: 1: minimum PHP version, 2: current PHP version */
            __('UR AI Gateway 需要 PHP %1$s 以上版本才能執行。您目前的 PHP 版本是 %2$s。', 'ur-ai-gateway'),
            UR_AI_GATEWAY_MIN_PHP_VERSION,
            PHP_VERSION
        )
    );
    echo '</p></div>';
}
add_action('admin_notices', 'ur_ai_gateway_php_version_notice');

/**
 * 載入語系檔。
 *
 * @return void
 */
function ur_ai_gateway_load_textdomain() {
    load_plugin_textdomain(
        'ur-ai-gateway',
        false,
        dirname(UR_AI_GATEWAY_PLUGIN_BASENAME) . '/languages'
    );
}
add_action('plugins_loaded', 'ur_ai_gateway_load_textdomain');

/**
 * 載入 Autoloader。
 *
 * @return bool
 */
function ur_ai_gateway_load_autoloader() {
    $autoload_file = UR_AI_GATEWAY_PLUGIN_DIR . 'includes/core/class-ur-ai-gateway-autoloader.php';

    if (!file_exists($autoload_file)) {
        return false;
    }

    require_once $autoload_file;

    if (!class_exists('UR_AI_Gateway_Autoloader')) {
        return false;
    }

    UR_AI_Gateway_Autoloader::register();

    return true;
}

/**
 * 外掛啟用處理：建立資料表。
 *
 * @return void
 */
function ur_ai_gateway_activate() {
    if (!ur_ai_gateway_check_php_version()) {
        deactivate_plugins(UR_AI_GATEWAY_PLUGIN_BASENAME);

        wp_die(
            esc_html(
                sprintf(
                    /* translators: 1: minimum PHP version, 2: current PHP version */
                    __('UR AI Gateway 需要 PHP %1$s 以上版本才能啟用。您目前的 PHP 版本是 %2$s。', 'ur-ai-gateway'),
                    UR_AI_GATEWAY_MIN_PHP_VERSION,
                    PHP_VERSION
                )
            ),
            esc_html__('外掛啟用失敗', 'ur-ai-gateway'),
            array('back_link' => true)
        );
    }

    if (!ur_ai_gateway_load_autoloader()) {
        deactivate_plugins(UR_AI_GATEWAY_PLUGIN_BASENAME);

        wp_die(
            esc_html__('UR AI Gateway 啟用失敗：必要檔案載入失敗，請確認外掛檔案完整。', 'ur-ai-gateway'),
            esc_html__('外掛啟用失敗', 'ur-ai-gateway'),
            array('back_link' => true)
        );
    }

    if (class_exists('UR_AI_Gateway_Schema_Manager')) {
        UR_AI_Gateway_Schema_Manager::maybe_upgrade();
    }
}
register_activation_hook(__FILE__, 'ur_ai_gateway_activate');

/**
 * 啟動外掛主程式。
 *
 * 這個外掛規模小、模組少，不採用 ur-ai-assistant 那種完整的
 * Module Manager 架構，直接在這裡逐一啟動各個服務即可，避免為了
 * 少數幾個模組另外做一層不必要的抽象。
 *
 * @return void
 */
function ur_ai_gateway_run() {
    if (!ur_ai_gateway_check_php_version()) {
        return;
    }

    if (!ur_ai_gateway_load_autoloader()) {
        return;
    }

    // 資料庫版本檢查：外掛更新後，即使沒有重新啟用也要能補建/升級資料表。
    if (class_exists('UR_AI_Gateway_Schema_Manager')) {
        UR_AI_Gateway_Schema_Manager::maybe_upgrade();
    }

    if (is_admin() && class_exists('UR_AI_Gateway_Admin_Menu')) {
        $admin_menu = new UR_AI_Gateway_Admin_Menu();
        $admin_menu->register();
    }

    if (class_exists('UR_AI_Gateway_WC_Integration')) {
        $wc_integration = new UR_AI_Gateway_WC_Integration();
        $wc_integration->register();
    }

    if (class_exists('UR_AI_Gateway_REST_Controller')) {
        $rest_controller = new UR_AI_Gateway_REST_Controller();
        add_action('rest_api_init', array($rest_controller, 'register_routes'));
    }
}
add_action('plugins_loaded', 'ur_ai_gateway_run', 20);
