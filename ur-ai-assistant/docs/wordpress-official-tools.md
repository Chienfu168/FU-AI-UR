# WordPress 官方工具紀錄（可利用 / 可調用）

本文件記錄 WordPress 官方 GitHub 組織（https://github.com/WordPress）底下，
對本外掛（UR AI Assistant）**有實際用途**的工具、模組或函式庫，包含用途、
安裝／使用方式與目前採用狀態，方便日後需要時直接調用。

> 篩選原則：只列出「能用在這個 PHP 外掛開發／品質／發布流程」的項目；
> 核心鏡像、佈景主題、Gutenberg 積木範例等與本外掛無關者不納入。

---

## A. 已導入（開發／品質工具）

### 1. WordPress Coding Standards（WPCS）＋ PHP_CodeSniffer ✅ 已設定

- **來源**：`WordPress/WordPress-Coding-Standards`（PHPCS 規則集）。
- **用途**：自動檢查 PHP 的安全性（輸出逸出、nonce、輸入淨化、`$wpdb->prepare`）、
  i18n、命名前綴、PHP 版本相容性等。
- **本專案設定檔**：
  - `composer.json`：開發相依套件（`squizlabs/php_codesniffer`、
    `wp-coding-standards/wpcs`、`phpcompatibility/phpcompatibility-wp`）。
  - `phpcs.xml.dist`：規則集。**刻意排除純排版格式類 sniff**（縮排、
    括號空格、陣列對齊…），因為本專案一致採用 4 空格縮排（非 WP 核心
    的 Tab），讓檢查聚焦在安全性／i18n／相容性等真正有價值的項目。
  - 安裝目錄 `.phpcs-vendor/`（已 gitignore，不隨外掛發布）。
- **調用方式**：
  ```bash
  cd ur-ai-assistant
  composer install          # 安裝開發工具到 .phpcs-vendor/
  composer lint             # 完整掃描（依 phpcs.xml.dist）
  composer lint:security    # 只跑安全性／i18n sniff
  composer lint:compat      # 只檢查 PHP 7.4+ 相容性
  composer lint:fix         # 自動修正可修復的風格問題
  ```
- **基準結果（v1.39.1，首次掃描）**：完整標準約 66,000 筆多為「4 空格 vs
  Tab」等可自動修正的排版差異（本專案風格選擇，非錯誤）。排除排版後，
  剩餘項目經逐一檢視為**框架未辨識的誤報**，無真正資安漏洞：
  - `NonceVerification.Missing`：本外掛以 `UR_AI_Security::ajax_verify_public_nonce_or_die()`
    等封裝函式驗證 nonce，WPCS 只認得 `check_ajax_referer` 等原生函式，故誤報。
  - `ValidatedSanitizedInput`：數值輸入以 `(float)`／`(int)` 轉型即為淨化，
    WPCS 未辨識，誤報。
  - `PreparedSQL`：內插的僅為資料表名（來自 `$wpdb->prefix`，非使用者輸入，
    無法用 `prepare` 參數化），數值比較均已使用 `%s`／`%d` 佔位符，安全。
  - `EscapeOutput`：後台 `sprintf` 中的整數統計值（count），非 XSS 向量。
  - `error_log`：CF7 整合中的除錯記錄，屬刻意行為。

### 2. WordPress/Requests（HTTP 函式庫）✅ 核心已內建，無須另裝

- 本外掛使用 `wp_remote_get`／`wp_remote_post`，底層即 WordPress 核心隨附的
  Requests 函式庫。不需額外安裝，已受惠。

---

## B. 建議在真實環境使用（發布前品質把關）

### 3. WordPress/plugin-check（Plugin Check，PCP）⭐ 建議上架前跑

- **用途**：官方外掛檢查器，跑的是 WordPress.org 審查外掛時**同一套自動
  檢查**（逸出／淨化、i18n、`readme.txt` 格式、禁用函式、檔案結構…）。
- **為何未在本環境導入**：需要「執行中的 WordPress」，本開發沙盒無法架站。
- **調用方式（於真實站台或 Playground）**：
  1. 後台「外掛 → 安裝外掛」搜尋「Plugin Check」安裝啟用（或從
     `WordPress/plugin-check` 下載）。
  2. 後台「工具 → Plugin Check」選擇本外掛執行。
  3. 亦可用 WP-CLI：`wp plugin check ur-ai-assistant`。
- **建議時機**：若未來要把外掛送上 WordPress.org 外掛目錄，這是必跑項目。

### 4. WordPress/wordpress-playground（瀏覽器內 WordPress）⭐ 測試／展示

- **用途**：用 WebAssembly 在瀏覽器或 Node CLI 直接跑一個真的 WordPress，
  不需架站。可用來實測外掛、跑 Plugin Check、截圖、做 demo。
- **為何有用**：本專案先前多次遇到「沙盒無法連 wordpress.org、無法架 WP」
  的限制，Playground 可在能連外網的環境補上「真實 WP 實測」這一塊。
- **調用方式**：
  - 線上：https://playground.wordpress.net/ （可用 Blueprint 預先安裝本外掛）。
  - CLI：`npx @wp-playground/cli server --mount ./ur-ai-assistant:/wordpress/wp-content/plugins/ur-ai-assistant`

---

## C. AI 相關，值得評估（較新，尚未導入）

### 5. WordPress/php-ai-client（跨供應商 PHP AI SDK）🔎 觀察中

- **用途**：官方「provider-agnostic」PHP AI SDK，用同一套 API 接
  OpenAI／Anthropic／Google 等多家生成式 AI。
- **與本外掛的關係**：目前本外掛以自寫的 `UR_AI_OpenAI_Client` 直連 OpenAI
  （另有 v1.31.0 起的「代管服務端點」可切換）。此 SDK 之後可讓外掛以單一
  介面支援多家 AI 供應商。
- **狀態**：2025 年才推出、仍在發展，現階段導入偏早；列為長期觀察。

### 6. WordPress/ai、WordPress/mcp-adapter 🔎 實驗性

- 官方 AI 功能框架（Abilities API）與 MCP 橋接。屬實驗性質，長期方向可留意，
  現階段不建議接入產品。

### 7. WordPress/agent-skills 🔎 供 AI 編碼助理參考

- 「給 AI 編碼助理用的 WordPress 專家知識」。這是給開發用的 AI 助理參考資料，
  不是外掛的相依套件，可於後續開發時參考。

---

## D. 網站層級可選（與本外掛功能無關）

- **WordPress/two-factor**：官方兩步驟驗證外掛，網站想加強後台安全可裝。
- **WordPress/performance**（Performance Lab）：網站層級效能模組集合。
- 兩者皆為獨立外掛，與 UR AI Assistant 功能不相干，僅供站方視需要安裝。

---

## E. 僅在改為「區塊編輯器」時才需要（功能方向，非現成套件）

- **WordPress/gutenberg** 及其 `@wordpress/*` npm 套件（`@wordpress/components`、
  `@wordpress/block-editor` 等）：只有在決定把現有的 shortcode 改成／增加
  Gutenberg 積木（例如「共同負擔試算」積木）時才會用到。屬功能決策，非可
  直接安裝的相依套件。

---

## 更新紀錄

- 2026-07-22：初版。導入 WPCS（phpcs）開發工具並完成首次掃描（結論：無真正
  資安漏洞，主要為排版風格差異與框架誤報）；記錄 Plugin Check、Playground、
  php-ai-client 等待用工具與調用方式。
