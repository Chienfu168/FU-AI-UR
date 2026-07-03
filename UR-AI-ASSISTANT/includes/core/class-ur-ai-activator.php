<?php
/**
 * UR AI Assistant Activator
 *
 * 外掛啟用時執行的初始化流程。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Activator
 */
class UR_AI_Activator {

    /**
     * 外掛啟用。
     *
     * @return void
     */
    public static function activate() {
        self::check_requirements();

        self::install_database();

        self::install_default_settings();

        self::flush_rewrite_rules();
    }

    /**
     * 檢查基本環境需求。
     *
     * @return void
     */
    private static function check_requirements() {
        global $wp_version;

        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(UR_AI_ASSISTANT_PLUGIN_FILE));

            wp_die(
                esc_html__('UR AI Assistant 需要 PHP 7.4 以上版本。', 'ur-ai-assistant'),
                esc_html__('外掛啟用失敗', 'ur-ai-assistant'),
                array(
                    'response' => 500,
                    'back_link' => true,
                )
            );
        }

        if (isset($wp_version) && version_compare($wp_version, '6.0', '<')) {
            deactivate_plugins(plugin_basename(UR_AI_ASSISTANT_PLUGIN_FILE));

            wp_die(
                esc_html__('UR AI Assistant 需要 WordPress 6.0 以上版本。', 'ur-ai-assistant'),
                esc_html__('外掛啟用失敗', 'ur-ai-assistant'),
                array(
                    'response' => 500,
                    'back_link' => true,
                )
            );
        }
    }

    /**
     * 建立或更新資料表。
     *
     * @return void
     */
    private static function install_database() {
        if (!class_exists('UR_AI_Schema_Manager')) {
            return;
        }

        /*
         * 注意：
         * 目前最後版 UR_AI_Schema_Manager 提供的方法是 install()，
         * 不是 install_or_update()。
         */
        UR_AI_Schema_Manager::install();
    }

    /**
     * 建立預設設定。
     *
     * @return void
     */
    private static function install_default_settings() {
        if (class_exists('UR_AI_Settings')) {
            UR_AI_Settings::maybe_install_defaults();
        }
    }

    /**
     * 重新整理 rewrite rules。
     *
     * 目前外掛尚未註冊自訂 rewrite rule，
     * 但保留此流程，方便未來加入 REST / 自訂端點時使用。
     *
     * @return void
     */
    private static function flush_rewrite_rules() {
        flush_rewrite_rules();
    }
}