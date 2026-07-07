<?php
/**
 * UR AI Assistant Schema Manager
 *
 * 資料庫結構管理器。
 *
 * 負責在外掛啟用或版本升級時建立 / 更新資料表。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Schema_Manager
 */
class UR_AI_Schema_Manager {

    /**
     * 資料庫版本 option name.
     *
     * @var string
     */
    const DB_VERSION_OPTION = 'ur_ai_assistant_db_version';

    /**
     * 目前資料庫版本。
     *
     * @var string
     */
    const DB_VERSION = '1.2.0';

    /**
     * 安裝或更新資料表。
     *
     * @return void
     */
    public static function install() {
        self::maybe_load_upgrade_file();

        self::create_tables();

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);

        if (class_exists('UR_AI_Settings') && method_exists('UR_AI_Settings', 'maybe_install_defaults')) {
            UR_AI_Settings::maybe_install_defaults();
        }
    }

    /**
     * 相容舊版呼叫：install_or_update().
     *
     * 有些舊檔案可能仍呼叫 install_or_update()，
     * 這裡保留相容方法，避免發生 Call to undefined method。
     *
     * @return void
     */
    public static function install_or_update() {
        self::install();
    }

    /**
     * 檢查是否需要升級。
     *
     * @return void
     */
    public static function maybe_upgrade() {
        $installed_version = get_option(self::DB_VERSION_OPTION, '');

        if ($installed_version === self::DB_VERSION) {
            return;
        }

        self::install();
    }

    /**
     * 建立全部資料表。
     *
     * 支援兩種 schema 寫法：
     *
     * 1. Schema class 自己提供 create_table()
     * 2. Schema class 提供 get_sql()，由本 Manager 呼叫 dbDelta()
     *
     * @return void
     */
    public static function create_tables() {
        self::maybe_load_upgrade_file();

        $schemas = self::get_schema_classes();

        foreach ($schemas as $schema_class) {
            if (!class_exists($schema_class)) {
                continue;
            }

            /*
             * 優先支援 schema class 自己建立資料表。
             */
            if (method_exists($schema_class, 'create_table')) {
                call_user_func(array($schema_class, 'create_table'));
                continue;
            }

            /*
             * 支援目前檔案包中使用的 get_sql() 架構。
             */
            if (method_exists($schema_class, 'get_sql')) {
                $sql = call_user_func(array($schema_class, 'get_sql'));

                if (is_string($sql) && '' !== trim($sql)) {
                    dbDelta($sql);
                }
            }
        }
    }

    /**
     * 刪除全部資料表。
     *
     * 注意：只建議 uninstall.php 在使用者明確選擇刪除資料時呼叫。
     *
     * @return void
     */
    public static function drop_tables() {
        global $wpdb;

        $schemas = self::get_schema_classes();

        foreach ($schemas as $schema_class) {
            if (!class_exists($schema_class)) {
                continue;
            }

            if (!method_exists($schema_class, 'get_table_name')) {
                continue;
            }

            $table_name = call_user_func(array($schema_class, 'get_table_name'));

            if ('' === trim((string) $table_name)) {
                continue;
            }

            /*
             * table name 由 WordPress prefix 與固定字串組成。
             * 加上反引號可避免特殊前綴造成 SQL 問題。
             */
            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
        }

        delete_option(self::DB_VERSION_OPTION);
    }

    /**
     * 取得資料表狀態。
     *
     * @return array
     */
    public static function get_table_statuses() {
        global $wpdb;

        $statuses = array();
        $schemas  = self::get_schema_classes();

        foreach ($schemas as $schema_class) {
            if (!class_exists($schema_class) || !method_exists($schema_class, 'get_table_name')) {
                continue;
            }

            $table_name = call_user_func(array($schema_class, 'get_table_name'));

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    'SHOW TABLES LIKE %s',
                    $table_name
                )
            );

            $statuses[$schema_class] = array(
                'table_name' => $table_name,
                'exists'     => $exists === $table_name,
            );
        }

        return $statuses;
    }

    /**
     * 取得所有 schema class。
     *
     * @return array
     */
    public static function get_schema_classes() {
        return array(
            'UR_AI_Schema_FAQs',
            'UR_AI_Schema_Logs',
            'UR_AI_Schema_Related_Pages',
            'UR_AI_Schema_Popular_Questions',
            // 都更分回試算名單表（v1.1.0 新增）。
            'UR_AI_Schema_Calculator_Leads',
            // 行情參考表（v1.8.0 新增）。
            'UR_AI_Schema_Market_Prices',
        );
    }

    /**
     * 載入 WordPress dbDelta。
     *
     * @return void
     */
    private static function maybe_load_upgrade_file() {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
    }

    /**
     * 取得資料庫 charset collate。
     *
     * @return string
     */
    public static function get_charset_collate() {
        global $wpdb;

        return $wpdb->get_charset_collate();
    }

    /**
     * 取得目前資料庫版本。
     *
     * @return string
     */
    public static function get_db_version() {
        return self::DB_VERSION;
    }

    /**
     * 取得已安裝資料庫版本。
     *
     * @return string
     */
    public static function get_installed_db_version() {
        return (string) get_option(self::DB_VERSION_OPTION, '');
    }

    /**
     * 判斷資料表是否存在。
     *
     * @param string $table_name 資料表名稱。
     * @return bool
     */
    public static function table_exists($table_name) {
        global $wpdb;

        $table_name = sanitize_text_field((string) $table_name);

        if ('' === $table_name) {
            return false;
        }

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        return $exists === $table_name;
    }

    /**
     * 取得資料表名稱清單。
     *
     * @return array
     */
    public static function get_table_names() {
        $tables  = array();
        $schemas = self::get_schema_classes();

        foreach ($schemas as $schema_class) {
            if (!class_exists($schema_class) || !method_exists($schema_class, 'get_table_name')) {
                continue;
            }

            $tables[$schema_class] = call_user_func(array($schema_class, 'get_table_name'));
        }

        return $tables;
    }

    /**
     * 檢查所有必要資料表是否存在。
     *
     * @return bool
     */
    public static function all_tables_exist() {
        $statuses = self::get_table_statuses();

        if (empty($statuses)) {
            return false;
        }

        foreach ($statuses as $status) {
            if (empty($status['exists'])) {
                return false;
            }
        }

        return true;
    }
}