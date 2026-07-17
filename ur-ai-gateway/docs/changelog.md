# UR AI Gateway 更新紀錄

本文件記錄 UR AI Gateway 外掛的版本演進、功能新增、架構調整與測試重點。

---

## v1.0.0

更新日期
2026-07-17

版本定位

延續「UR AI Assistant」外掛 v1.31.0 新增的「AI 服務來源」設定（可切換
為「使用代管服務」），這一版建立實際提供代管服務所需要的另一個獨立
外掛——**這個外掛安裝在服務提供者自己的網站（例如 ur-promoter.com），
不安裝在客戶端網站上**。

背景討論：使用者確認未來的免費／付費方向走「服務加值」而非「程式碼
鎖定」（外掛本身維持完全免費開源），並確認由 WooCommerce ＋
WooCommerce Subscriptions ＋ 綠界金流負責訂單與月租收款，這個外掛
負責「訂閱狀態 → 授權碼」與「授權碼 → 實際代理呼叫 OpenAI」這兩段
串接。

一、主要新增

1. **授權碼資料表與服務層**（`UR_AI_Gateway_License_Repository`／
   `UR_AI_Gateway_License_Service`）：每組授權碼記錄客戶 Email、方案、
   來源訂單／訂閱 ID、狀態（啟用中／暫停／已終止／已過期）、每日與
   累計呼叫次數。授權碼字串由本外掛隨機產生（`urg_` 前綴＋40 碼亂數），
   客戶端把它當成 Bearer 憑證使用。
2. **WooCommerce Subscriptions 整合**（`UR_AI_Gateway_WC_Integration`）：
   訂閱進入 `active` 狀態時自動建立授權碼（同一筆訂閱不會重複建立）
   並寄送通知信；訂閱進入其他狀態（`on-hold`／`cancelled`／`expired`
   等）時，依對應表同步更新授權狀態。只在偵測到 WooCommerce
   Subscriptions 已啟用時才掛勾，未安裝時不會出現無意義的錯誤。
3. **REST API 代理端點**（`UR_AI_Gateway_REST_Controller`，路由
   `POST /wp-json/ur-ai-gateway/v1/chat`）：驗證 Bearer 授權碼是否
   存在、狀態是否有效、當日用量是否超過上限，通過後才用**服務提供者
   自己的** OpenAI API Key 真正呼叫 OpenAI，並把 OpenAI 的回應原封
   不動傳回去。刻意維持與 OpenAI 官方 API 完全相同的成功／錯誤 JSON
   格式（錯誤一律是 `{"error":{"message":...,"code":...}}`），讓
   客戶端「UR AI Assistant」外掛既有的 `UR_AI_OpenAI_Client` 解析
   邏輯（`extract_answer()`／`handle_api_error()`）完全不需要修改
   就能相容代管模式。
4. **每日用量上限**：每組授權碼有各自的每日呼叫次數上限（新授權碼
   預設值可在「設定」頁調整），換日時自動重設用量計數，超過上限時
   回傳 429 錯誤。
5. **後台管理頁**：「授權碼管理」頁列出所有授權碼、可手動建立（測試
   或特殊個案用）、可手動調整狀態；「設定」頁填入服務提供者自己的
   OpenAI API Key 與預設每日上限，並顯示完整的 REST 端點網址供複製
   給客戶。

二、設計原則

- 這個外掛完全不處理「收費」本身——訂單、扣款排程、發票都交給
  WooCommerce ＋ WooCommerce Subscriptions ＋ 金流外掛處理；這裡只
  在訂閱狀態變化時做「授權要不要開通」的判斷，職責單純、風險集中
  在單一小範圍。
- REST 端點刻意做成「透明代理」而不是自訂 JSON 契約，讓兩個獨立
  開發的外掛（`ur-ai-assistant` 與 `ur-ai-gateway`）能在完全不修改
  客戶端既有程式碼的情況下互通。
- 規模小、模組少，不採用 `ur-ai-assistant` 那種完整的 Module
  Manager／Bootstrap 架構，直接在主檔案裡依序啟動各服務即可，避免
  為了少數幾個模組另外做一層不必要的抽象。

三、測試方式

1. PHP 整合測試（使用真實 SQLite 資料庫＋真實 `UR_AI_Gateway_
   License_Repository`／`UR_AI_Gateway_License_Service`，非假物件）：
   `create_from_subscription()` 對同一筆訂閱 ID 具備冪等性（不會
   重複建立）；WooCommerce 訂閱狀態正確對應到授權狀態；`is_valid()`
   正確判斷非啟用狀態與已過期兩種情境；每日用量上限正確擋下超額
   呼叫、換日後正確重設；`create_manual()` 可獨立於任何訂閱建立
   授權碼。
2. PHP 整合測試（真實 `UR_AI_Gateway_REST_Controller`，攔截
   `wp_remote_post()`）：缺少／不存在／暫停中授權碼皆正確以
   OpenAI 錯誤格式與正確狀態碼（401）拒絕；正常請求正確用服務
   提供者自己的 Key 轉呼叫 OpenAI 並原封不動回傳結果；OpenAI 回傳
   的錯誤與連線層失敗（`WP_Error`）都正確轉換成乾淨的錯誤回應
   （不會產生未攔截的例外）；每日用量上限在 REST 層同樣正確生效。
3. **跨外掛整合測試**（同時載入 `ur-ai-assistant` 真實的
   `UR_AI_OpenAI_Client`／`UR_AI_Settings` 與 `ur-ai-gateway` 真實的
   `UR_AI_Gateway_REST_Controller`／授權服務，用攔截 `wp_remote_post()`
   模擬 HTTP 往返，把客戶端的請求直接導進代管服務端的 controller，
   再導進模擬的 OpenAI 回應）：確認客戶端切換為「使用代管服務」
   並填入代管服務發放的真實授權碼後，`chat()` 呼叫能完整跑通「客戶端
   → 代管服務 → OpenAI」整條鏈路並取得正確回答；代管服務端把授權碼
   設為「已終止」後，同一個客戶端呼叫會乾淨地失敗並顯示代管服務的
   錯誤訊息，不會發生未攔截的例外。這是這兩個獨立開發的外掛第一次
   真正互相呼叫，也是最重要的一項驗證。

全部情境（授權服務 11 項＋REST 端點 9 項＋跨外掛整合 3 項，共 23 項
檢查點）通過，`php -l` 確認所有新增檔案語法正確。

四、是否建議上正式站

這是全新外掛的第一個版本，建議先在測試環境／測試訂閱方案完整走過
一次「訂閱付款 → 收到授權碼 → 客戶端切換代管模式 → 實際問答」的
完整流程後，再正式對外開放付費訂閱。
