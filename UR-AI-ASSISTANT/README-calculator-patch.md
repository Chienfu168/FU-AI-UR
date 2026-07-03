# 都更分回試算模組 — 部署說明（v1.1.0 補丁）

本補丁為 **UR AI Assistant 外掛的新模組**，新增「都更分回效益試算」功能：
前台試算（換坪比＋基地總量）、半遮罩留資料鉤子、leads 名單擷取、後台名單頁。

---

## 一、這個補丁包含什麼

**修改的核心檔（4 個，僅小幅增修，未動既有邏輯）**
- `ur-ai-assistant.php`：版本 1.0.0 → 1.1.0（含 DB 版本）。
- `includes/core/class-ur-ai-autoloader.php`：新增計算機類別的 class map。
- `includes/core/class-ur-ai-module-manager.php`：註冊 calculator 模組。
- `includes/database/class-ur-ai-schema-manager.php`：DB 版本 → 1.1.0，新增 leads 表 schema。

**新增檔**
- `includes/modules/calculator/`：模組主體（service／settings／ajax／cf7／lead-repository／module）。
- `includes/database/schemas/class-ur-ai-schema-calculator-leads.php`：名單資料表。
- `public/views/calculator-view.php`、`public/assets/css/calculator.css`、`public/assets/js/calculator.js`：前台。
- `admin/pages/calculator-leads-page.php`：後台名單頁。

---

## 二、部署步驟（不需停用／重裝外掛）

1. **先備份**：備份現有外掛資料夾與資料庫（正式站務必）。
2. **覆蓋上傳**：將本補丁所有檔案，依相同路徑覆蓋／放入外掛資料夾
   （`wp-content/plugins/UR-AI-ASSISTANT/`）。
3. **觸發升級**：登入 WordPress 後台或開啟任一前台頁面一次。
   - 主程式偵測到 DB 版本由 1.0.0 → 1.1.0，會自動建立資料表
     `urf_ur_ai_calculator_leads`。
   - 不需停用／重新啟用外掛。
4. **確認**：後台左側「都更 AI 助理」選單下，應出現「**試算名單**」子頁。

---

## 三、上線前要手動完成的事

1. **放置試算器**：在任一頁面／文章插入短代碼：
   - 地主版（僅換坪比）：`[ur_ai_calculator]`
   - 含整合公司進階：`[ur_ai_calculator mode="pro"]`
2. **CF7 表單**：確認「都更獎勵試算」表單（ID 1157393）已存在且欄位為
   `your-name`／`tel`／`your-email`／`your-message`／`consent`。
   - 後台「都更 AI 助理 → 功能設定」若未提供此欄，表單 ID 預設已內建 1157393；
     如需更改，見下方「參數調整」。
3. **測試一次**：前台填坪數 → 試算 → 出現前後對比與半遮罩 → 填 CF7 送出 →
   後台「試算名單」應出現該筆，且帶有試算情境。

---

## 四、參數調整（全部後台可改，符合「浮動數據後台可調」原則）

目前參數存於 option `ur_ai_calculator_settings`，台北／新北各一組，包含：
換坪倍數、一般獎勵、實設係數、地主分回比例、分區→容積率表、其他獎勵選項、
CF7 表單 ID、免責聲明、公設比警語、鉤子文案。

> 注意：本補丁聚焦「計算＋名單」核心。**後台參數的圖形編輯頁**可作為下一個小補丁
> 補上；在此之前，如需調整可由開發者透過 `UR_AI_Calculator_Settings::update()` 或
> 直接更新該 option 進行。種子預設值已內建且保守可用。

---

## 五、已驗證 / 待你在測試站確認

**已自動驗證（PHP 8.3）**
- 計算核心三條規格驗算（估價師 31.4 坪、保守 25~28 坪、2 倍觸頂 35 坪）全數吻合。
- 2 倍上限 `min()` 截斷、個別分回回推、防呆輸入。
- 模組生命週期、AJAX 兩軌、CF7 名單擷取與情境還原、token 一次性：整合測試 25/25 通過。

**請在測試站實際確認（無法於離線環境模擬的部分）**
- 前台 CF7 表單送出後，token hidden 欄位是否正確隨表單送出（不同佈景主題／快取外掛可能影響）。
- LiteSpeed 快取：試算結果頁建議排除快取，或確認 AJAX 不被快取。
- 後台名單頁樣式與分頁。

部署如遇問題，回報訊息我可再出最小修補。
