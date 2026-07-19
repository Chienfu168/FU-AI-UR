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
    const DB_VERSION = '1.4.0';

    /**
     * 安裝或更新資料表。
     *
     * @return void
     */
    public static function install() {
        self::maybe_load_upgrade_file();

        self::create_tables();

        /*
         * dbDelta() 不會拋出例外：資料庫帳號權限不足等原因造成建表失敗時，
         * create_tables() 仍會「執行完畢」。若這裡不做確認就直接把 DB_VERSION
         * 標記為已完成升級，maybe_upgrade() 之後就永遠不會再重試，資料表會
         * 一直缺漏卻沒有任何提示。因此只有在確認全部資料表都存在時才標記版本。
         */
        if (self::all_tables_exist()) {
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }

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
     * 升級重試節流間隔（秒）。
     *
     * @var int
     */
    const RETRY_THROTTLE = 3600;

    /**
     * 升級重試節流鎖的 transient 名稱。
     *
     * @var string
     */
    const RETRY_LOCK_TRANSIENT = 'ur_ai_assistant_db_upgrade_retry_lock';

    /**
     * 檢查是否需要升級。
     *
     * 這個方法掛在 plugins_loaded（見 UR_AI_Bootstrap::register_core_hooks()），
     * 等於「每一次」網站請求（前台與後台皆然）都會被呼叫一次。
     *
     * 若 install() 因故一直無法讓 all_tables_exist() 成立（例如主機的
     * WordPress 核心版本與 PHP 版本組合觸發 dbDelta() 本身解析 KEY／INDEX
     * 子句失敗的相容性問題——這是 WordPress 核心 wp-admin/includes/
     * upgrade.php 的已知舊版本相容性瑕疵，非本外掛 SQL 語法問題），
     * DB_VERSION_OPTION 就永遠不會被標記為已完成升級，若沒有節流，
     * install()／dbDelta() 就會在「每一個」後台頁面（包含與本外掛完全
     * 無關的「新增頁面」）被重新呼叫一次，dbDelta() 本身有一定開銷，
     * 會讓整個後台明顯變慢、甚至像是卡住轉圈圈。
     *
     * 因此改為節流：升級失敗時，最多每小時重試一次，而不是每次請求都重試。
     *
     * @return void
     */
    public static function maybe_upgrade() {
        $installed_version = get_option(self::DB_VERSION_OPTION, '');

        if ($installed_version === self::DB_VERSION) {
            return;
        }

        if (get_transient(self::RETRY_LOCK_TRANSIENT)) {
            return;
        }

        set_transient(self::RETRY_LOCK_TRANSIENT, 1, self::RETRY_THROTTLE);

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
            // 知識大考驗題庫與作答紀錄表（v1.9.0 新增）。
            'UR_AI_Schema_Quiz_Questions',
            'UR_AI_Schema_Quiz_Attempts',
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

    /**
     * 資料庫索引健康檢查。
     *
     * 背景：dbDelta() 在部分主機環境（WordPress 核心版本與 PHP 版本
     * 組合的相容性問題）可能沒辦法正確把 KEY／INDEX 子句加到資料表上
     * ——即使資料表本身已經建立成功（all_tables_exist() 為 true），
     * 缺少索引仍會讓依這些欄位查詢／排序的功能隨資料量增加而越來越慢，
     * 且不會有任何明確的錯誤訊息提醒管理者。這個方法直接用
     * `SHOW INDEX FROM` 讀出資料庫「實際」的索引清單，與各 schema
     * class 的 get_sql() 裡「應該要有」的索引清單比對，找出缺漏。
     *
     * @return array 每個 schema class 一筆：
     *               array{ schema_class, table_name, table_exists,
     *               expected: array, missing: array, healthy: bool }
     */
    public static function get_index_health_report() {
        global $wpdb;

        $report  = array();
        $schemas = self::get_schema_classes();

        foreach ($schemas as $schema_class) {
            if (!class_exists($schema_class) || !method_exists($schema_class, 'get_table_name') || !method_exists($schema_class, 'get_sql')) {
                continue;
            }

            $table_name    = call_user_func(array($schema_class, 'get_table_name'));
            $table_exists  = self::table_exists($table_name);
            $expected_sql  = (string) call_user_func(array($schema_class, 'get_sql'));
            $expected      = self::parse_expected_indexes($expected_sql);
            $actual_names  = $table_exists ? self::get_actual_index_names($table_name) : array();

            $missing = array();
            foreach ($expected as $index) {
                if (!in_array($index['name'], $actual_names, true)) {
                    $missing[] = $index;
                }
            }

            $report[] = array(
                'schema_class'  => $schema_class,
                'table_name'    => $table_name,
                'table_exists'  => $table_exists,
                'expected'      => $expected,
                'missing'       => $missing,
                'healthy'       => $table_exists && empty($missing),
            );
        }

        return $report;
    }

    /**
     * 修復缺少的索引。
     *
     * 刻意不重新呼叫 dbDelta()（那正是導致索引沒有正確建立的來源），
     * 改為針對缺少的索引直接下 `ALTER TABLE ... ADD INDEX`，語法單純、
     * 不經過 dbDelta 那段容易在特定主機環境解析失敗的正規表達式。
     *
     * @param array|null $report 若已經呼叫過 get_index_health_report()，
     *                           可直接傳入其結果避免重複查詢；留空則自行查詢。
     * @return array 每個資料表一筆：array{ table_name, repaired: array, failed: array }
     */
    public static function repair_missing_indexes($report = null) {
        global $wpdb;

        if (!is_array($report)) {
            $report = self::get_index_health_report();
        }

        $results = array();

        foreach ($report as $table_report) {
            if (empty($table_report['table_exists']) || empty($table_report['missing'])) {
                continue;
            }

            $table_name = $table_report['table_name'];
            $repaired   = array();
            $failed     = array();

            foreach ($table_report['missing'] as $index) {
                $index_type = !empty($index['unique']) ? 'UNIQUE INDEX' : 'INDEX';
                $columns    = implode(
                    ', ',
                    array_map(
                        function ($col) {
                            return '`' . str_replace('`', '', $col) . '`';
                        },
                        $index['columns']
                    )
                );
                $index_name = '`' . str_replace('`', '', $index['name']) . '`';

                $sql = "ALTER TABLE `{$table_name}` ADD {$index_type} {$index_name} ({$columns})";

                $ok = $wpdb->query($sql);

                if (false === $ok) {
                    $failed[] = array('name' => $index['name'], 'error' => $wpdb->last_error);
                } else {
                    $repaired[] = $index['name'];
                }
            }

            $results[] = array(
                'table_name' => $table_name,
                'repaired'   => $repaired,
                'failed'     => $failed,
            );
        }

        return $results;
    }

    /**
     * 從 CREATE TABLE SQL 字串解析出「應該要有」的索引清單（不含 PRIMARY KEY）。
     *
     * 只解析本外掛自己 schema 檔案一貫使用的標準寫法：
     * `KEY name (col)`、`UNIQUE KEY name (col)`、`KEY name (col1, col2)`。
     *
     * @param string $sql CREATE TABLE SQL。
     * @return array 每筆 array{ name: string, unique: bool, columns: array }
     */
    private static function parse_expected_indexes($sql) {
        $indexes = array();

        if ('' === trim((string) $sql)) {
            return $indexes;
        }

        if (preg_match_all('/^\s*(UNIQUE\s+)?KEY\s+(\w+)\s*\(([^)]+)\)/im', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $columns = array_map(
                    function ($col) {
                        return trim($col, " `\t\n\r");
                    },
                    explode(',', $match[3])
                );

                $indexes[] = array(
                    'name'    => $match[2],
                    'unique'  => '' !== trim($match[1]),
                    'columns' => array_filter($columns, 'strlen'),
                );
            }
        }

        return $indexes;
    }

    /**
     * 讀出資料表「實際」存在的索引名稱清單（不含 PRIMARY）。
     *
     * @param string $table_name 資料表名稱。
     * @return array
     */
    private static function get_actual_index_names($table_name) {
        global $wpdb;

        $table_name = sanitize_text_field((string) $table_name);

        if ('' === $table_name) {
            return array();
        }

        $rows = $wpdb->get_results("SHOW INDEX FROM `{$table_name}`");

        if (!is_array($rows)) {
            return array();
        }

        $names = array();
        foreach ($rows as $row) {
            if (isset($row->Key_name) && 'PRIMARY' !== $row->Key_name) {
                $names[] = $row->Key_name;
            }
        }

        return array_values(array_unique($names));
    }
}