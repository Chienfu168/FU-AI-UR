<?php
/**
 * UR AI Assistant Partners Module
 *
 * 相關產業名錄模組主類別（前台名錄 shortcode + 後台名錄管理）。
 * 移植自 UR_SYS 的合作夥伴／相關產業名錄功能。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UR_AI_Partners_Module
 */
class UR_AI_Partners_Module {

    const SHORTCODE            = 'ur_ai_partners';
    const STYLE_HANDLE         = 'ur-ai-partners';
    const SCRIPT_HANDLE        = 'ur-ai-partners';
    const ADMIN_MENU_SLUG      = 'ur-ai-assistant-partners';
    const PARENT_SLUG          = 'ur-ai-assistant';
    const SAVE_ACTION          = 'ur_ai_partner_save';
    const DELETE_ACTION        = 'ur_ai_partner_delete';
    const BATCH_ACTION         = 'ur_ai_partner_batch';
    const IMPORT_ACTION        = 'ur_ai_partner_import';
    const SETTINGS_SAVE_ACTION = 'ur_ai_partners_settings_save';

    /**
     * 名錄資料存取。
     *
     * @var UR_AI_Partner_Repository|null
     */
    private $repository;

    /**
     * 建構子。
     */
    public function __construct() {
        $this->repository = class_exists('UR_AI_Partner_Repository') ? new UR_AI_Partner_Repository() : null;
    }

    /**
     * 註冊掛鉤。
     *
     * @return void
     */
    public function register() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));

        add_action('admin_menu', array($this, 'register_admin_page'), 20);
        add_action('admin_post_' . self::SAVE_ACTION, array($this, 'handle_save'));
        add_action('admin_post_' . self::DELETE_ACTION, array($this, 'handle_delete'));
        add_action('admin_post_' . self::BATCH_ACTION, array($this, 'handle_batch'));
        add_action('admin_post_' . self::IMPORT_ACTION, array($this, 'handle_import'));
        add_action('admin_post_' . self::SETTINGS_SAVE_ACTION, array($this, 'handle_settings_save'));
    }

    /**
     * 啟動。
     *
     * @return void
     */
    public function boot() {
        if (class_exists('UR_AI_Partner_Settings')) {
            UR_AI_Partner_Settings::maybe_install_defaults();
        }
    }

    /* ---------------------------------------------------------------------
     * 前台
     * ------------------------------------------------------------------- */

    /**
     * 註冊前台資產。
     *
     * @return void
     */
    public function register_assets() {
        wp_register_style(self::STYLE_HANDLE, UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/css/partners.css', array(), UR_AI_ASSISTANT_VERSION);
        wp_register_script(self::SCRIPT_HANDLE, UR_AI_ASSISTANT_PLUGIN_URL . 'public/assets/js/partners.js', array(), UR_AI_ASSISTANT_VERSION, true);
    }

    /**
     * shortcode 輸出。
     *
     * @param array|string $atts 屬性。
     * @return string
     */
    public function render_shortcode($atts = array()) {
        if (!class_exists('UR_AI_Partner_Settings') || !UR_AI_Partner_Settings::is_enabled()) {
            return $this->admin_only_notice(__('相關產業名錄功能目前已停用。管理員可至後台「相關產業名錄」設定重新啟用。', 'ur-ai-assistant'));
        }
        if (!$this->repository instanceof UR_AI_Partner_Repository) {
            return $this->admin_only_notice(__('相關產業名錄尚未正確載入，請確認外掛檔案是否完整。', 'ur-ai-assistant'));
        }

        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);

        $partners    = $this->repository->get_active_directory();
        $cat_counts  = $this->repository->category_counts();
        $title       = UR_AI_Partner_Settings::get('title', '');
        $intro       = UR_AI_Partner_Settings::get('intro', '');
        $disclaimer  = UR_AI_Partner_Settings::get('disclaimer', '');

        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'public/views/partners-view.php';
        if (!file_exists($view)) {
            return '';
        }

        ob_start();
        include $view;

        return ob_get_clean();
    }

    /**
     * 僅管理員可見的提示。
     *
     * @param string $msg 訊息。
     * @return string
     */
    private function admin_only_notice($msg) {
        if (!current_user_can('manage_options')) {
            return '';
        }
        return '<div class="ur-ai-partners"><div class="ur-ai-error">' . esc_html($msg) . '</div></div>';
    }

    /* ---------------------------------------------------------------------
     * 後台
     * ------------------------------------------------------------------- */

    /**
     * 解析所需權限。
     *
     * @return string
     */
    private function capability() {
        if (class_exists('UR_AI_Permissions') && method_exists('UR_AI_Permissions', 'get_capability')) {
            $maybe = UR_AI_Permissions::get_capability('dashboard');
            if (is_string($maybe) && '' !== $maybe) {
                return $maybe;
            }
        }
        return 'manage_options';
    }

    /**
     * 註冊後台名錄管理頁。
     *
     * @return void
     */
    public function register_admin_page() {
        add_submenu_page(
            self::PARENT_SLUG,
            __('相關產業名錄', 'ur-ai-assistant'),
            __('相關產業名錄', 'ur-ai-assistant'),
            $this->capability(),
            self::ADMIN_MENU_SLUG,
            array($this, 'render_admin_page')
        );
    }

    /**
     * 渲染後台頁。
     *
     * @return void
     */
    public function render_admin_page() {
        if (!current_user_can($this->capability())) {
            wp_die(esc_html__('權限不足。', 'ur-ai-assistant'));
        }

        // CSV 匯出（需 nonce）。
        if (isset($_GET['export']) && 'csv' === $_GET['export']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce 於下行檢查。
            check_admin_referer('ur_ai_partner_export');
            $this->export_csv();
        }

        $repository = $this->repository;
        $editing    = null;

        if (isset($_GET['edit'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 唯讀顯示。
            $edit_id = (int) $_GET['edit']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $editing = $repository ? $repository->get($edit_id) : null;
        }

        $view = UR_AI_ASSISTANT_PLUGIN_DIR . 'admin/pages/partners-page.php';
        if (file_exists($view)) {
            include $view;
        }
    }

    /**
     * 新增／更新一筆名錄。
     *
     * @return void
     */
    public function handle_save() {
        $this->guard(self::SAVE_ACTION);

        $data = $this->collect_partner_input();
        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($this->repository) {
            if ($id > 0) {
                $this->repository->update($id, $data);
                $notice = 'updated';
            } else {
                $this->repository->insert($data);
                $notice = 'added';
            }
        } else {
            $notice = 'error';
        }

        $this->redirect_back(array('ur_notice' => $notice));
    }

    /**
     * 刪除一筆。
     *
     * @return void
     */
    public function handle_delete() {
        $this->guard(self::DELETE_ACTION);

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0 && $this->repository) {
            $this->repository->delete($id);
        }

        $this->redirect_back(array('ur_notice' => 'deleted'));
    }

    /**
     * 批次操作。
     *
     * @return void
     */
    public function handle_batch() {
        $this->guard(self::BATCH_ACTION);

        $ids    = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', wp_unslash($_POST['ids'])) : array();
        $action = isset($_POST['batch_action']) ? sanitize_key(wp_unslash($_POST['batch_action'])) : '';
        $value  = isset($_POST['batch_value']) ? sanitize_text_field(wp_unslash($_POST['batch_value'])) : null;

        $count = ($this->repository && $ids && $action) ? $this->repository->batch($ids, $action, $value) : 0;

        $this->redirect_back(array('ur_notice' => 'batch', 'ur_count' => $count));
    }

    /**
     * CSV 匯入。
     *
     * 欄位順序：名稱, 分類, 地區, 標語, 簡介, 聯絡人, 電話, Email, 官方網站,
     * 等級(standard/featured), 權重, 狀態(active/inactive), 已核對(0/1),
     * 核對日, 資料來源, 來源網址。第一列若為標題會自動略過。
     *
     * @return void
     */
    public function handle_import() {
        $this->guard(self::IMPORT_ACTION);

        $result = array('inserted' => 0, 'failed' => 0);

        if (!empty($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $rows = $this->parse_csv($_FILES['csv_file']['tmp_name']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- 檔案路徑，內容於 parse_csv 逐欄清理。
            if ($this->repository && $rows) {
                $result = $this->repository->import_rows($rows);
            }
        }

        $this->redirect_back(array('ur_notice' => 'imported', 'ur_ins' => $result['inserted'], 'ur_fail' => $result['failed']));
    }

    /**
     * 儲存設定。
     *
     * @return void
     */
    public function handle_settings_save() {
        $this->guard(self::SETTINGS_SAVE_ACTION);

        UR_AI_Partner_Settings::update(array(
            'enabled'    => !empty($_POST['enabled']) ? 1 : 0,
            'title'      => isset($_POST['title']) ? wp_unslash($_POST['title']) : '',
            'intro'      => isset($_POST['intro']) ? wp_unslash($_POST['intro']) : '',
            'disclaimer' => isset($_POST['disclaimer']) ? wp_unslash($_POST['disclaimer']) : '',
        ));

        $this->redirect_back(array('ur_notice' => 'settings'));
    }

    /**
     * 匯出全部名錄為 CSV（欄位順序與匯入相容）。
     *
     * @return void
     */
    private function export_csv() {
        if (!$this->repository) {
            return;
        }

        $all = $this->repository->find_all(array('per_page' => 100000, 'page' => 1));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ur_ai_partners_' . gmdate('Ymd') . '.csv"');

        $out = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions
        // UTF-8 BOM，讓 Excel 正確辨識中文。
        fwrite($out, "\xEF\xBB\xBF"); // phpcs:ignore WordPress.WP.AlternativeFunctions
        fputcsv($out, array('名稱', '分類', '地區', '標語', '簡介', '聯絡人', '電話', 'Email', '官方網站', '等級', '權重', '狀態', '已核對', '核對日', '資料來源', '來源網址'), ',', '"', '');

        foreach ($all['items'] as $p) {
            fputcsv($out, array(
                $p['name'], $p['category'], $p['region'], $p['tagline'], $p['description'],
                $p['contact_person'], $p['phone'], $p['email'], $p['website_url'],
                $p['tier'], $p['weight'], $p['status'], $p['is_verified'], $p['verified_at'],
                $p['source_type'], $p['source_url'],
            ), ',', '"', '');
        }
        fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }

    /* ---------------------------------------------------------------------
     * 內部
     * ------------------------------------------------------------------- */

    /**
     * 權限 + nonce 檢查。
     *
     * @param string $action action。
     * @return void
     */
    private function guard($action) {
        if (!current_user_can($this->capability())) {
            wp_die(esc_html__('權限不足。', 'ur-ai-assistant'));
        }
        check_admin_referer($action);
    }

    /**
     * 導回名錄管理頁。
     *
     * @param array $args query args。
     * @return void
     */
    private function redirect_back(array $args) {
        $args['page'] = self::ADMIN_MENU_SLUG;
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * 從 POST 收集並清理名錄欄位。
     *
     * @return array
     */
    private function collect_partner_input() {
        $g = function ($k) {
            return isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- nonce 於 guard() 檢查。
        };

        $tier   = ('featured' === $g('tier')) ? 'featured' : 'standard';
        $status = ('inactive' === $g('status')) ? 'inactive' : 'active';

        return array(
            'name'           => $g('name'),
            'category'       => UR_AI_Partner_Service::normalize_category($g('category')),
            'region'         => $g('region'),
            'tagline'        => $g('tagline'),
            'description'    => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '', // phpcs:ignore WordPress.Security.NonceVerification
            'contact_person' => $g('contact_person'),
            'phone'          => $g('phone'),
            'email'          => sanitize_email($g('email')),
            'website_url'    => esc_url_raw($g('website_url')),
            'logo_url'       => esc_url_raw($g('logo_url')),
            'tier'           => $tier,
            'weight'         => max(1, (int) ($_POST['weight'] ?? 1)), // phpcs:ignore WordPress.Security.NonceVerification
            'status'         => $status,
            'sort_order'     => (int) ($_POST['sort_order'] ?? 0), // phpcs:ignore WordPress.Security.NonceVerification
            'source_type'    => $g('source_type'),
            'source_url'     => esc_url_raw($g('source_url')),
            'verified_at'    => $g('verified_at'),
            'is_verified'    => !empty($_POST['is_verified']) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification
            'featured_until' => $g('featured_until'),
        );
    }

    /**
     * 解析 CSV 檔為欄位陣列（逐欄清理）。
     *
     * @param string $path 上傳暫存檔路徑。
     * @return array
     */
    private function parse_csv($path) {
        $rows   = array();
        $handle = fopen($path, 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions -- 讀取上傳暫存檔。
        if (false === $handle) {
            return $rows;
        }

        $map = array(
            0 => 'name', 1 => 'category', 2 => 'region', 3 => 'tagline', 4 => 'description',
            5 => 'contact_person', 6 => 'phone', 7 => 'email', 8 => 'website_url',
            9 => 'tier', 10 => 'weight', 11 => 'status', 12 => 'is_verified',
            13 => 'verified_at', 14 => 'source_type', 15 => 'source_url',
        );

        $first = true;
        while (false !== ($cols = fgetcsv($handle, 0, ',', '"', ''))) {
            if ($first) {
                $first = false;
                // 第一列若含「名稱」等標題字樣則略過。
                $joined = implode('', array_map('strval', $cols));
                if (false !== strpos($joined, '名稱') || false !== strpos(strtolower($joined), 'name')) {
                    continue;
                }
            }
            if (empty(array_filter($cols, function ($c) { return '' !== trim((string) $c); }))) {
                continue;
            }

            $data = array();
            foreach ($map as $i => $field) {
                $raw = isset($cols[$i]) ? trim((string) $cols[$i]) : '';
                switch ($field) {
                    case 'category':
                        $data[$field] = UR_AI_Partner_Service::normalize_category($raw);
                        break;
                    case 'email':
                        $data[$field] = sanitize_email($raw);
                        break;
                    case 'website_url':
                    case 'source_url':
                        $data[$field] = esc_url_raw($raw);
                        break;
                    case 'description':
                        $data[$field] = sanitize_textarea_field($raw);
                        break;
                    case 'tier':
                        $data[$field] = ('featured' === $raw) ? 'featured' : 'standard';
                        break;
                    case 'status':
                        $data[$field] = ('inactive' === $raw) ? 'inactive' : 'active';
                        break;
                    case 'weight':
                        $data[$field] = max(1, (int) $raw);
                        break;
                    case 'is_verified':
                        $data[$field] = ('' !== $raw && '0' !== $raw) ? 1 : 0;
                        break;
                    default:
                        $data[$field] = sanitize_text_field($raw);
                }
            }
            $rows[] = $data;
        }

        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions

        return $rows;
    }
}
