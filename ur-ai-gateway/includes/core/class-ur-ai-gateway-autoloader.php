<?php
/**
 * UR AI Gateway Autoloader
 *
 * 類別對照表 autoloader，沿用 UR AI Assistant 外掛同樣的做法
 * （明確列出類別對應檔案路徑，不做目錄掃描），行為可預期、除錯容易。
 *
 * @package UR_AI_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Gateway_Autoloader
 */
class UR_AI_Gateway_Autoloader {

    /**
     * 註冊 autoloader。
     *
     * @return void
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * 自動載入類別。
     *
     * @param string $class_name 類別名稱。
     * @return void
     */
    public static function autoload($class_name) {
        $map = self::class_map();

        if (!isset($map[$class_name])) {
            return;
        }

        $file = UR_AI_GATEWAY_PLUGIN_DIR . $map[$class_name];

        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * 類別對照表。
     *
     * @return array
     */
    private static function class_map() {
        return array(
            'UR_AI_Gateway_Schema_Manager'        => 'includes/database/class-ur-ai-gateway-schema-manager.php',
            'UR_AI_Gateway_Schema_Licenses'        => 'includes/database/schemas/class-ur-ai-gateway-schema-licenses.php',

            'UR_AI_Gateway_Settings'               => 'includes/shared/class-ur-ai-gateway-settings.php',
            'UR_AI_Gateway_Security'               => 'includes/shared/class-ur-ai-gateway-security.php',

            'UR_AI_Gateway_License_Repository'      => 'includes/licenses/class-ur-ai-gateway-license-repository.php',
            'UR_AI_Gateway_License_Service'         => 'includes/licenses/class-ur-ai-gateway-license-service.php',

            'UR_AI_Gateway_WC_Integration'          => 'includes/woocommerce/class-ur-ai-gateway-wc-integration.php',

            'UR_AI_Gateway_REST_Controller'         => 'includes/rest/class-ur-ai-gateway-rest-controller.php',

            'UR_AI_Gateway_Admin_Menu'              => 'admin/class-ur-ai-gateway-admin-menu.php',
        );
    }
}
