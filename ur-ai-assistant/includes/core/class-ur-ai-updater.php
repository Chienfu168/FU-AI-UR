<?php
/**
 * UR AI Assistant Updater
 *
 * 串接 Plugin Update Checker（third-party，MIT 授權，vendor 於
 * vendor/plugin-update-checker/），讓這個沒有上架 wordpress.org 的
 * 外掛也能在後台「外掛」頁看到「有新版本可更新」並一鍵更新，體驗與
 * wordpress.org 上架外掛一致。
 *
 * 版本來源是 GitHub Releases（不是單純的 git tag）：因為外掛實際檔案
 * 放在 repo 的 ur-ai-assistant/ 子目錄下（repo 根目錄還有其他非外掛
 * 內容），無法直接用 GitHub 對 tag 自動產生的原始壓縮檔（那會把整個
 * repo 根目錄的結構包進去）。改用「Release 附加檔案」模式，讀取由
 * .github/workflows/release.yml 在 tag 建立時自動打包、只包含
 * ur-ai-assistant/ 內容且資料夾名稱正確的 zip。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Updater
 */
class UR_AI_Updater {

    /**
     * GitHub repository 網址（放外掛原始碼與 Release 的地方）。
     *
     * @var string
     */
    const REPO_URL = 'https://github.com/Chienfu168/FU-AI-UR/';

    /**
     * 外掛在 Plugin Update Checker 內使用的 slug。
     *
     * @var string
     */
    const SLUG = 'ur-ai-assistant';

    /**
     * 初始化更新檢查器。
     *
     * 依 Plugin Update Checker 官方建議，在 plugins_loaded 掛鉤內（或
     * 不掛任何 hook 直接執行）建立實例，才能讓 WP-CLI、網站健康檢查等
     * 非後台畫面的工具也看得到更新資訊，不只是登入後台的使用者。
     *
     * @return object|null 已建立的 update checker 實例（供測試／除錯內省用），
     *                      未能建立時回傳 null。
     */
    public static function init() {
        $library_file = UR_AI_ASSISTANT_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

        if (!file_exists($library_file)) {
            return null;
        }

        require_once $library_file;

        if (!class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
            return null;
        }

        $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            self::REPO_URL,
            UR_AI_ASSISTANT_PLUGIN_FILE,
            self::SLUG
        );

        if (!is_object($update_checker) || !method_exists($update_checker, 'getVcsApi')) {
            return $update_checker;
        }

        $vcs_api = $update_checker->getVcsApi();

        if (is_object($vcs_api) && method_exists($vcs_api, 'enableReleaseAssets')) {
            // 只採用檔名結尾為 .zip 的 Release 附加檔案，忽略原始碼壓縮檔。
            $vcs_api->enableReleaseAssets('/\.zip($|[?&#])/i');
        }

        return $update_checker;
    }
}
