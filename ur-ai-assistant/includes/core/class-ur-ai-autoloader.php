<?php
/**
 * UR AI Assistant Autoloader
 *
 * 外掛類別自動載入器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Autoloader
 */
class UR_AI_Autoloader {

    /**
     * Class map.
     *
     * @var array
     */
    private static $class_map = array();

    /**
     * 是否已註冊。
     *
     * @var bool
     */
    private static $registered = false;

    /**
     * 註冊 autoloader。
     *
     * @return void
     */
    public static function register() {
        if (self::$registered) {
            return;
        }

        self::$class_map = self::get_class_map();

        spl_autoload_register(array(__CLASS__, 'autoload'));

        self::$registered = true;
    }

    /**
     * 自動載入 class。
     *
     * @param string $class_name Class name.
     * @return void
     */
    public static function autoload($class_name) {
        if (strpos($class_name, 'UR_AI_') !== 0) {
            return;
        }

        $file = self::get_file_for_class($class_name);

        if ($file && file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * 依 class name 取得檔案完整路徑。
     *
     * @param string $class_name Class name.
     * @return string
     */
    private static function get_file_for_class($class_name) {
        if (isset(self::$class_map[$class_name])) {
            return UR_AI_ASSISTANT_PLUGIN_DIR . self::$class_map[$class_name];
        }

        return self::guess_file_path($class_name);
    }

    /**
     * Class map.
     *
     * 只放目前最後架構中確定存在或主要會使用的類別。
     * 若未來新增類別，建議明確加入這裡，降低路徑猜測失誤。
     *
     * @return array
     */
    private static function get_class_map() {
        return array(
            /*
             * Core
             */
            'UR_AI_Bootstrap'      => 'includes/core/class-ur-ai-bootstrap.php',
            'UR_AI_Autoloader'     => 'includes/core/class-ur-ai-autoloader.php',
            'UR_AI_Activator'      => 'includes/core/class-ur-ai-activator.php',
            'UR_AI_Deactivator'    => 'includes/core/class-ur-ai-deactivator.php',
            'UR_AI_Module_Manager' => 'includes/core/class-ur-ai-module-manager.php',

            /*
             * Shared
             */
            'UR_AI_Settings'    => 'includes/shared/class-ur-ai-settings.php',
            'UR_AI_Security'    => 'includes/shared/class-ur-ai-security.php',
            'UR_AI_Permissions' => 'includes/shared/class-ur-ai-permissions.php',
            'UR_AI_Helper'      => 'includes/shared/class-ur-ai-helper.php',
            'UR_AI_Formatter'   => 'includes/shared/class-ur-ai-formatter.php',
            'UR_AI_Exporter'    => 'includes/shared/class-ur-ai-exporter.php',

            /*
             * Database
             */
            'UR_AI_Schema_Manager'           => 'includes/database/class-ur-ai-schema-manager.php',
            'UR_AI_Schema_FAQs'              => 'includes/database/schemas/class-ur-ai-schema-faqs.php',
            'UR_AI_Schema_Logs'              => 'includes/database/schemas/class-ur-ai-schema-logs.php',
            'UR_AI_Schema_Related_Pages'     => 'includes/database/schemas/class-ur-ai-schema-related-pages.php',
            'UR_AI_Schema_Popular_Questions' => 'includes/database/schemas/class-ur-ai-schema-popular-questions.php',
            'UR_AI_Schema_Calculator_Leads'  => 'includes/database/schemas/class-ur-ai-schema-calculator-leads.php',
            'UR_AI_Schema_Market_Prices'     => 'includes/database/schemas/class-ur-ai-schema-market-prices.php',

            /*
             * Admin Module
             */
            'UR_AI_Admin_Module' => 'includes/modules/admin/class-ur-ai-admin-module.php',
            'UR_AI_Admin_Menu'   => 'includes/modules/admin/class-ur-ai-admin-menu.php',
            'UR_AI_Admin_Assets' => 'includes/modules/admin/class-ur-ai-admin-assets.php',

            /*
             * Public Module
             */
            'UR_AI_Public_Module'         => 'includes/modules/public/class-ur-ai-public-module.php',
            'UR_AI_Public_Assets'         => 'includes/modules/public/class-ur-ai-public-assets.php',
            'UR_AI_Shortcode'             => 'includes/modules/public/class-ur-ai-shortcode.php',
            'UR_AI_FAQ_KB_Page_Shortcode' => 'includes/modules/public/class-ur-ai-faq-kb-page-shortcode.php',

            /*
             * Calculator Module（都更分回試算，v1.1.0 新增）
             */
            'UR_AI_Calculator_Module'          => 'includes/modules/calculator/class-ur-ai-calculator-module.php',
            'UR_AI_Calculator_Service'         => 'includes/modules/calculator/class-ur-ai-calculator-service.php',
            'UR_AI_Calculator_Settings'        => 'includes/modules/calculator/class-ur-ai-calculator-settings.php',
            'UR_AI_Calculator_Lead_Repository' => 'includes/modules/calculator/class-ur-ai-calculator-lead-repository.php',
            'UR_AI_Calculator_Ajax'            => 'includes/modules/calculator/class-ur-ai-calculator-ajax.php',
            'UR_AI_Calculator_CF7'             => 'includes/modules/calculator/class-ur-ai-calculator-cf7.php',

            /*
             * Market Price Module（行情參考，v1.8.0 新增）
             */
            'UR_AI_Market_Price_Module'          => 'includes/modules/market-price/class-ur-ai-market-price-module.php',
            'UR_AI_Market_Price_Settings'         => 'includes/modules/market-price/class-ur-ai-market-price-settings.php',
            'UR_AI_Market_Price_Repository'       => 'includes/modules/market-price/class-ur-ai-market-price-repository.php',
            'UR_AI_Market_Price_Service'          => 'includes/modules/market-price/class-ur-ai-market-price-service.php',
            'UR_AI_Market_Price_Import_Service'   => 'includes/modules/market-price/class-ur-ai-market-price-import-service.php',
            'UR_AI_Market_Price_Zone_Normalizer'  => 'includes/modules/market-price/class-ur-ai-market-price-zone-normalizer.php',
            'UR_AI_Market_Price_Ajax'             => 'includes/modules/market-price/class-ur-ai-market-price-ajax.php',
            'UR_AI_Market_Price_Admin'            => 'includes/modules/market-price/class-ur-ai-market-price-admin.php',
            'UR_AI_Market_Price_Ranking_Shortcode' => 'includes/modules/market-price/class-ur-ai-market-price-ranking-shortcode.php',

            /*
             * AJAX Module
             */
            'UR_AI_Ajax_Module' => 'includes/modules/ajax/class-ur-ai-ajax-module.php',

            /*
             * Assistant Module
             */
            'UR_AI_Assistant_Module' => 'includes/modules/assistant/class-ur-ai-assistant-module.php',
            'UR_AI_Answer_Service'   => 'includes/modules/assistant/class-ur-ai-answer-service.php',

            /*
             * OpenAI Integration
             */
            'UR_AI_OpenAI_Client' => 'includes/integrations/openai/class-ur-ai-openai-client.php',

            /*
             * FAQ Module
             */
            'UR_AI_FAQ_Module'          => 'includes/modules/faq/class-ur-ai-faq-module.php',
            'UR_AI_FAQ_Repository'      => 'includes/modules/faq/class-ur-ai-faq-repository.php',
            'UR_AI_FAQ_Service'         => 'includes/modules/faq/class-ur-ai-faq-service.php',
            'UR_AI_FAQ_Matcher'         => 'includes/modules/faq/class-ur-ai-faq-matcher.php',
            'UR_AI_FAQ_Admin'           => 'includes/modules/faq/class-ur-ai-faq-admin.php',
            'UR_AI_FAQ_Draft_Service'   => 'includes/modules/faq/class-ur-ai-faq-draft-service.php',
            'UR_AI_FAQ_Category_Helper' => 'includes/modules/faq/class-ur-ai-faq-category-helper.php',

            /*
             * Logs Module
             */
            'UR_AI_Logs_Module'    => 'includes/modules/logs/class-ur-ai-logs-module.php',
            'UR_AI_Log_Repository' => 'includes/modules/logs/class-ur-ai-log-repository.php',
            'UR_AI_Log_Service'    => 'includes/modules/logs/class-ur-ai-log-service.php',
            'UR_AI_Log_Admin'      => 'includes/modules/logs/class-ur-ai-log-admin.php',

            /*
             * Related Pages Module
             */
            'UR_AI_Related_Pages_Module'    => 'includes/modules/related-pages/class-ur-ai-related-pages-module.php',
            'UR_AI_Related_Page_Repository' => 'includes/modules/related-pages/class-ur-ai-related-page-repository.php',
            'UR_AI_Related_Page_Service'    => 'includes/modules/related-pages/class-ur-ai-related-page-service.php',
            'UR_AI_Related_Page_Admin'      => 'includes/modules/related-pages/class-ur-ai-related-page-admin.php',
            'UR_AI_Post_Search'             => 'includes/modules/related-pages/class-ur-ai-post-search.php',
            'UR_AI_Related_Page_Importer'   => 'includes/modules/related-pages/class-ur-ai-related-page-importer.php',

            /*
             * Popular Questions Module
             */
            'UR_AI_Popular_Questions_Module'    => 'includes/modules/popular-questions/class-ur-ai-popular-questions-module.php',
            'UR_AI_Popular_Question_Repository' => 'includes/modules/popular-questions/class-ur-ai-popular-question-repository.php',
            'UR_AI_Popular_Question_Service'    => 'includes/modules/popular-questions/class-ur-ai-popular-question-service.php',
            'UR_AI_Popular_Question_Admin'      => 'includes/modules/popular-questions/class-ur-ai-popular-question-admin.php',

            /*
             * Feedback Module
             */
            'UR_AI_Feedback_Module'  => 'includes/modules/feedback/class-ur-ai-feedback-module.php',
            'UR_AI_Feedback_Service' => 'includes/modules/feedback/class-ur-ai-feedback-service.php',
        );
    }

    /**
     * 猜測 class 檔案路徑。
     *
     * 這是備援機制。正式核心類別仍建議放入 class map。
     *
     * @param string $class_name Class name.
     * @return string
     */
    private static function guess_file_path($class_name) {
        $file_name = self::class_name_to_file_name($class_name);

        $possible_paths = array(
            'includes/core/' . $file_name,

            'includes/database/' . $file_name,
            'includes/database/schemas/' . $file_name,

            'includes/shared/' . $file_name,

            'includes/integrations/' . $file_name,
            'includes/integrations/openai/' . $file_name,

            'includes/modules/admin/' . $file_name,
            'includes/modules/public/' . $file_name,
            'includes/modules/ajax/' . $file_name,
            'includes/modules/assistant/' . $file_name,
            'includes/modules/faq/' . $file_name,
            'includes/modules/logs/' . $file_name,
            'includes/modules/related-pages/' . $file_name,
            'includes/modules/popular-questions/' . $file_name,
            'includes/modules/feedback/' . $file_name,
        );

        foreach ($possible_paths as $relative_path) {
            $full_path = UR_AI_ASSISTANT_PLUGIN_DIR . $relative_path;

            if (file_exists($full_path)) {
                return $full_path;
            }
        }

        return '';
    }

    /**
     * 將 class name 轉成檔名。
     *
     * 例如：
     * UR_AI_FAQ_Service => class-ur-ai-faq-service.php
     *
     * @param string $class_name Class name.
     * @return string
     */
    private static function class_name_to_file_name($class_name) {
        $class_name = str_replace('_', '-', $class_name);
        $class_name = strtolower($class_name);

        return 'class-' . $class_name . '.php';
    }

    /**
     * 手動載入指定 class。
     *
     * @param string $class_name Class name.
     * @return bool
     */
    public static function load($class_name) {
        if (class_exists($class_name)) {
            return true;
        }

        self::autoload($class_name);

        return class_exists($class_name);
    }

    /**
     * 檢查 class map 中的檔案是否存在。
     *
     * 可供除錯或後台健康檢查使用。
     *
     * @return array
     */
    public static function check_class_map_files() {
        $results = array();

        foreach (self::get_class_map() as $class_name => $relative_path) {
            $full_path = UR_AI_ASSISTANT_PLUGIN_DIR . $relative_path;

            $results[$class_name] = array(
                'relative_path' => $relative_path,
                'full_path'     => $full_path,
                'exists'        => file_exists($full_path),
            );
        }

        return $results;
    }

    /**
     * 取得 class map。
     *
     * @return array
     */
    public static function get_registered_class_map() {
        if (empty(self::$class_map)) {
            self::$class_map = self::get_class_map();
        }

        return self::$class_map;
    }
}