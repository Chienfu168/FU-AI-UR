完整內容如下：

# UR AI Assistant 更新紀錄

本文件記錄 UR AI Assistant 外掛的版本演進、功能新增、架構調整與測試重點。

---

## 版本紀錄原則

每次更新建議記錄：

```text
版本號
更新日期
更新目的
新增檔案
修改檔案
資料表變更
測試結果
是否建議上正式站
v1.0.0
更新日期
2026-05-16
版本定位

v1.0.0 為 UR AI Assistant 模組化架構整理版。

本版本以「FAQ 知識庫優先、AI 回答補位」為核心，建立較完整的 WordPress 外掛架構，方便後續長期維護與擴充。

核心目標
建立穩定的外掛基礎架構
將前台、後台、AJAX、FAQ、Logs、資料庫 Schema 分層
降低未來修改造成白畫面的風險
建立 FAQ 優先命中與 AI 補位流程
建立問答紀錄與回饋分析基礎
建立相關頁面推薦與熱門問題管理
一、主要新增功能
1. 前台 AI 助理

新增前台 shortcode：

[ur_ai_assistant]

功能包含：

前台提問輸入框
熱門問題按鈕
分類熱門問題
AI 回答顯示
回答來源標籤
相關頁面推薦
使用者回饋
手機版響應式排版
2. FAQ 知識庫管理

新增 FAQ 管理功能：

新增 FAQ
編輯 FAQ
刪除 FAQ
批次啟用
批次停用
批次刪除
FAQ 搜尋
FAQ 分類篩選
FAQ 狀態篩選
FAQ CSV 匯出
FAQ 命中次數統計
3. FAQ 優先命中回答

新增 FAQ Matcher：

使用者提問
↓
比對 FAQ question / keywords / category
↓
計算命中分數
↓
達門檻則回傳 FAQ 固定回答
↓
FAQ 未命中才呼叫 AI

保護機制：

短問題懲罰
泛用關鍵字懲罰
最低命中分數門檻
4. OpenAI API 串接

新增 OpenAI Client：

讀取 API Key
讀取模型設定
組合 system prompt
呼叫 Chat Completions API
解析回答
解析 token 使用量
標準化錯誤回傳

預設模型：

gpt-4o-mini
5. 問答紀錄 Logs

新增問答紀錄功能：

紀錄使用者問題
紀錄回答內容
紀錄回答來源
紀錄 FAQ 命中分數
紀錄 FAQ 命中關鍵字
紀錄相關頁面 ID
紀錄 token 使用量
紀錄錯誤訊息
紀錄使用者回饋
支援轉 FAQ 草稿
支援 CSV 匯出
6. 回饋分析

新增回饋分析頁：

總回饋數
有幫助比例
沒幫助比例
FAQ / AI 回答比較
沒幫助原因統計
需要優先改善的問答
7. 相關頁面推薦

新增相關頁面推薦功能：

新增推薦頁面
編輯推薦頁面
刪除推薦頁面
批次啟用 / 停用 / 刪除
從 WordPress 文章匯入
從 WordPress 頁面匯入
曝光次數統計
點擊次數統計
CTR 計算
CSV 匯出
8. 熱門問題管理

新增熱門問題管理功能：

新增熱門問題
編輯熱門問題
刪除熱門問題
批次啟用 / 停用 / 刪除
FAQ 匯入熱門問題
熱門問題轉 FAQ 草稿
點擊次數統計
FAQ 對應狀態
CSV 匯出
9. 每日提問限制

新增每日提問限制：

訪客每日限制
會員每日限制
管理員每日限制

預設：

訪客：20 次
會員：50 次
管理員：不限
10. CSV 匯出工具

新增共用 CSV 匯出工具：

FAQ 匯出
Logs 匯出
Related Pages 匯出
Popular Questions 匯出

支援 UTF-8 BOM，降低 Excel 開啟中文亂碼問題。

二、主要架構調整
1. 模組化資料夾

建立模組化結構：

includes/modules/

包含：

admin
ajax
assistant
faq
logs
public
2. 共用工具集中

新增：

includes/shared/

包含：

class-ur-ai-settings.php
class-ur-ai-security.php
class-ur-ai-permissions.php
class-ur-ai-helper.php
class-ur-ai-formatter.php
class-ur-ai-exporter.php
3. 資料庫 Schema 獨立

新增：

includes/database/
includes/database/schemas/

包含：

class-ur-ai-schema-manager.php
class-ur-ai-schema-faqs.php
class-ur-ai-schema-logs.php
class-ur-ai-schema-related-pages.php
class-ur-ai-schema-popular-questions.php
4. OpenAI 串接獨立

新增：

includes/integrations/openai/class-ur-ai-openai-client.php

讓 AI 供應商串接與核心問答流程分離。

三、新增資料表

本版本新增或重整以下資料表：

wp_ur_ai_faqs
wp_ur_ai_logs
wp_ur_ai_related_pages
wp_ur_ai_popular_questions

若 WordPress 資料表前綴不是 wp_，實際資料表名稱會依網站前綴調整。

四、主要新增檔案
Admin Pages
admin/pages/dashboard-page.php
admin/pages/settings-page.php
admin/pages/faq-page.php
admin/pages/logs-page.php
admin/pages/related-pages-page.php
admin/pages/popular-questions-page.php
admin/pages/feedback-page.php
Admin Assets
admin/assets/css/admin.css
admin/assets/js/admin.js
Public
public/assets/css/public.css
public/assets/js/public.js
public/views/assistant-view.php
Public Module
includes/modules/public/class-ur-ai-public-module.php
includes/modules/public/class-ur-ai-public-assets.php
includes/modules/public/class-ur-ai-shortcode.php
AJAX Module
includes/modules/ajax/class-ur-ai-ajax-module.php
Assistant Module
includes/modules/assistant/class-ur-ai-assistant-module.php
includes/modules/assistant/class-ur-ai-answer-service.php
OpenAI Integration
includes/integrations/openai/class-ur-ai-openai-client.php
FAQ Module
includes/modules/faq/class-ur-ai-faq-module.php
includes/modules/faq/class-ur-ai-faq-repository.php
includes/modules/faq/class-ur-ai-faq-service.php
includes/modules/faq/class-ur-ai-faq-matcher.php
includes/modules/faq/class-ur-ai-faq-admin.php
includes/modules/faq/class-ur-ai-faq-draft-service.php
includes/modules/faq/class-ur-ai-faq-category-helper.php
Logs Module
includes/modules/logs/class-ur-ai-logs-module.php
includes/modules/logs/class-ur-ai-log-repository.php
includes/modules/logs/class-ur-ai-log-service.php
includes/modules/logs/class-ur-ai-log-admin.php
Shared
includes/shared/class-ur-ai-settings.php
includes/shared/class-ur-ai-security.php
includes/shared/class-ur-ai-permissions.php
includes/shared/class-ur-ai-helper.php
includes/shared/class-ur-ai-formatter.php
includes/shared/class-ur-ai-exporter.php
Database
includes/database/class-ur-ai-schema-manager.php
includes/database/schemas/class-ur-ai-schema-faqs.php
includes/database/schemas/class-ur-ai-schema-logs.php
includes/database/schemas/class-ur-ai-schema-related-pages.php
includes/database/schemas/class-ur-ai-schema-popular-questions.php
Docs / Others
templates/index.php
languages/ur-ai-assistant.pot
uninstall.php
readme.txt
docs/installation.md
docs/testing-checklist.md
docs/developer-notes.md
docs/changelog.md
五、測試重點

本版本上傳測試站後，至少需確認：

外掛可正常啟用
網站前台不白畫面
網站後台不白畫面
資料表可正常建立
設定頁可正常儲存
FAQ 可新增 / 編輯 / 刪除
FAQ 可正常命中
FAQ 未命中時可呼叫 AI
問答紀錄可正常寫入
使用者回饋可送出
相關頁面推薦可顯示
熱門問題可點擊送出
CSV 可正常匯出
手機版前台可正常使用
六、正式站上線建議

v1.0.0 屬於架構整理版，不建議未經測試直接覆蓋正式網站。

正式站更新前建議：

完整備份外掛資料夾
完整備份資料庫
先於測試站安裝
確認資料表建立正常
確認 shortcode 正常
確認 FAQ / AI / Logs / Feedback 正常
確認沒有 PHP Fatal Error
再上傳正式站
七、已知注意事項
1. 新增檔案多，需確認 autoload / require

本版本新增大量 class 檔案，若主檔或 autoloader 未載入，可能發生：

Class not found

正式使用前必須檢查所有 class 是否已被載入。

2. AJAX action 必須完整註冊

前台 JS 會呼叫：

ur_ai_ask
ur_ai_feedback
ur_ai_related_page_click
ur_ai_popular_question_click

若對應 PHP handler 未註冊，前台可能無法正常運作。

3. Related Pages / Popular Questions 相關 Service 需確認完整

本版本已建立頁面、資料表與部分流程。
若正式站要完整啟用，需確認相關 Repository / Service / Admin Controller 是否皆已載入並完整。

4. languages/ur-ai-assistant.pot 目前為基礎版

正式版可在全部檔案完成後，用 WP-CLI 重新產生：

wp i18n make-pot . languages/ur-ai-assistant.pot
八、後續版本建議
v1.0.1 建議方向
完整檢查 autoloader / require 清單
補齊 Related Pages Service / Repository / Admin
補齊 Popular Questions Service / Repository / Admin
補齊 Feedback Service
補齊 Settings Admin 儲存流程
補齊 AJAX feedback / click tracking handlers
進行 PHP 語法檢查
建立測試站安裝包
v1.0.2 建議方向
優化 FAQ 命中分數
增加 FAQ CSV 匯入
增加 Related Pages CSV 匯入
增加 Popular Questions CSV 匯入
增加前台複製回答功能
增加更多後台統計圖表
v1.1.0 建議方向
REST API
多 AI 供應商
FAQ 版本管理
完整審核流程
成本估算報表
前台連續追問
九、版本結論

v1.0.0 是 UR AI Assistant 從功能累積走向模組化維護的重要版本。

本版本的核心價值：

架構清楚
功能分層
FAQ 優先
AI 補位
紀錄完整
可逐步擴充

但也因為新增檔案多，正式站使用前務必完整測試，尤其要確認：

class 載入
資料表建立
AJAX 註冊
前台 shortcode
後台頁面

完成測試後，才建議作為下一階段穩定版基礎。


---

## v1.1.x（里程碑補記）— 安全與效能強化、試算器模組建置

> 補記說明
> 本區塊為事後依開發脈絡回溯整理，涵蓋 1.0.0 之後、進階評估之前的一系列更新。
> 當時採連續小版（1.1.0 ~ 1.1.7）逐步交付，各項功能的精確落點版號未完整留存，
> 故以「功能里程碑」方式記錄；日後新功能請恢復逐版精確記錄。

更新日期
約 2026-05 下旬 ~ 06 月

版本定位

在穩定架構上，先補齊安全與效能，再掛入「都更分回效益試算器」新模組。

一、安全與效能強化

1. 安全修補（H1 / H2 / H3）
  - H1 IP 偽造防護：get_user_ip() 加入可信代理白名單與私有 IP 過濾，避免訪客偽造 HTTP_X_FORWARDED_FOR 等標頭繞過每日提問限制。
  - H2 每日計數原子化：改用 INSERT ... ON DUPLICATE KEY UPDATE 原子操作，並保留「僅成功才計數」語意與 transient 自動過期，避免高並發超額與資料表膨脹。
  - H3 隱藏內部錯誤碼：前台失敗回應不再曝露 api_key_missing 等內部 error_code。

2. FAQ 快取（M1）
  - get_active_faqs() 加入 Transient 快取，避免每次提問全量查詢資料庫；FAQ 更新時主動清快取。
  - 修正 limit 邊界行為（limit=0 誤產生 1000 筆，改回內部預設 50）。

3. OpenAI 重試機制（M2）
  - API 呼叫失敗（網路逾時、429、5xx）加入重試；明確排除不可重試錯誤（401/400/404、成功但空回應）。
  - 加入總時間預算（50 秒）與重試時逐次縮短逾時（30 秒），避免觸發 PHP max_execution_time 造成 504。
  - 新增 ur_ai_openai_max_retries 過濾器作為即時停用開關。

二、都更分回效益試算器模組（新增）

1. 核心公式
  分回 = 土地持分坪數 × 容積率 × (1+有效獎勵) × 實設係數 × 分回比例
  每個計算步驟於前台透明呈現。

2. 友善列印
  採 JS iframe 列印（非 CSS 隱藏），將結果複製到獨立乾淨文件再列印，
  徹底解決佈景主題頁首頁尾造成的空白頁與雜訊，並加上品牌頁尾。
  （此為試算器列印；AI 助理問答列印於 v1.3.3 另行實作。）

3. 後台試算器設定頁（約 1.1.5）
  所有浮動數據皆後台可調、不必改程式：啟用開關、CF7 表單 ID、一般獎勵預設、
  實設係數、地主分回比例區間、留資料鉤子文案、坪數提醒、免責聲明。
  確立核心原則：凡屬浮動數據（因縣市、分區、案件、政策修法而變動者）一律後台可調。
  此頁同時為日後 SaaS 化（多租戶各自調參）鋪好地基。

資料表變更
新增試算器相關設定（併入既有 ur_ai_assistant_settings option，未新增資料表）。

是否建議上正式站
已上線並經確認。


---

## v1.2.x（里程碑補記）— 進階評估：三案擇優

更新日期
約 2026-06 月

版本定位

依台灣都市更新容積獎勵法規，為試算器加入「進階評估：三種獎勵擇優」，讓原本蓋得比法定容積還滿的老屋也能算準。

一、三軌獎勵路徑（擇優取最有利）

每條先算出「更新後容積樓地板」，再取最大值：
  A. 法定容積 × 1.5（一般獎勵路徑）
  B. 原建築容積 + 法定容積 × 30%（原容積保障，適合原容 > 法容的老屋）
  C. 原建築容積 × 1.2（危險建築／海砂屋為 1.3）

取三者最大值後，套用總量硬上限（總加成 ≤ +100%，即 2 倍基準容積），
再乘實設係數與地主分回比例。所有係數（1.5 / 0.3 / 1.2 / 1.3 / 2.0）皆後台可調。

二、1.2.1 補強

  加入上限標註：當某路徑超過總量上限時顯示（例：「59.2 坪（超過上限，採計 58.2 坪）★最有利」）。
  加入說明：三軌數字代表「更新後容積樓地板」，非最終分回坪數。

資料表變更
無（新增係數併入既有設定 option）。

是否建議上正式站
已上線並經確認。


---

## v1.3.0 / v1.3.1（里程碑補記）— 試算結果可分享連結

更新日期
約 2026-06 月

版本定位

讓地主把試算結果透過 LINE、FB、複製連結分享給家人或鄰居，在地主社群自然擴散。

一、v1.3.0 新增

  試算結果分享連結，含 LINE、FB、複製連結按鈕。
  分享連結以 URL query 參數帶入試算條件（urc=1 及 urc_* 系列），
  對方開啟後前端自動還原條件並重算，不需後端儲存。

二、v1.3.1 修正

  修正分享連結遺失 query string 的 bug：
  原本用 origin + pathname 組連結，會丟掉 ?page_id=316 導致對方被導到首頁。
  改為保留所有既有 query 參數（僅移除舊的 urc_* ），確保連結正確落在試算頁。

三、同期其他調整

  留資料 CTA 文案由「免費評估」改為「想實際推進，需要專業協助？」，
  明確說明詳細分析需專業判斷，維持可信度、不過度促銷。
  鉤子標題與副標於後台設定頁可調；按鈕文字、欄位、同意條款位於 CF7 表單（ID 1157393）。

資料表變更
無。

是否建議上正式站
已上線並經確認。


---

## v1.3.2（里程碑補記）— 收尾與整體驗證

更新日期
約 2026-06 月

版本定位

分回效益試算器一系列開發告一段落，整體驗證與收束。

一、內容

  跨修補的命名空間衝突檢查（確認 ur_ai_active_faqs_、ur_ai_daily_ 與各過濾器名稱互不衝突）。
  交付 ZIP 內容與整合測試檔逐位元組比對、BOM／UTF-8／換行檢查，避免白畫面部署失敗。
  此版為試算器線收尾的穩定基準，之後開發重心轉向 AI 助理問答模組。

資料表變更
無。

是否建議上正式站
已上線並經確認。


---

## v1.3.3

更新日期
2026-07-02

版本定位

前台 AI 助理問答新增「單則問答友善列印」功能。

核心目標
讓訪客能將有用的問答內容乾淨列印或存成 PDF，方便留存、與家人討論
列印版面自動排除互動元素（相關頁面推薦、回饋按鈕、來源標籤）
維持與試算器一致的純前端列印做法，不新增資料表、不觸及訪客個資

一、主要新增功能

1. 單則問答列印按鈕

每則 AI／FAQ 回答的來源標籤列右側，新增「列印」按鈕，僅列印該則問答（問題＋回答）。
採純前端 iframe 列印，內容組裝自答案資料，不從 DOM 反爬，確保列印內容精準乾淨。

2. 列印版面

頁首：網站名稱＋網址（網址取自 home_url，去除通訊協定）。
日期列：列印日期與時間。
內文：問題、回答（保留回答內的段落、清單、標題結構）。
頁尾：免責聲明，沿用後台既有 disclaimer 設定的同一則文字，未設定時採用預設免責文字。

二、修改檔案

includes/modules/public/class-ur-ai-public-assets.php
  - localize 注入 site_name、site_url、disclaimer 供前台列印使用
  - 新增 get_print_site_name／get_print_site_url／get_print_disclaimer 三個 helper
  - i18n 新增列印相關字串（print_button、print_document_title、print_disclaimer_label 等）

public/assets/js/public.js
  - selectors 新增 printButton
  - 答案 meta 區塊插入列印按鈕，攜帶問題與答案資料（encodeURIComponent 編碼）
  - 新增 decodeDataAttr／buildPrintDocument／handlePrintClick 函式
  - bindEvents 綁定列印按鈕點擊事件

public/assets/css/public.css
  - 新增 .ur-ai-print-button 樣式（靠右對齊、圓角外框）
  - @media print 隱藏列印按鈕本身

ur-ai-assistant.php
  - 版本號 1.3.2 → 1.3.3

三、資料表變更

無。

四、測試結果

public.js 通過 node --check 語法檢查。
class-ur-ai-public-assets.php 通過大／中／小括號平衡檢查、新增方法與 i18n 鍵齊備檢查。
BOM 檢查：四個修改檔皆無 BOM。
換行檢查：各檔維持原本換行風格（PHP／JS 為 CRLF、CSS 為 LF）。
改動範圍比對：僅 4 個檔案變動，其餘 122 個檔案與 v1.3.2 逐位元組一致。

五、是否建議上正式站

建議。純前端功能，不影響既有問答、FAQ 命中、資料庫與 API 流程；風險低。
部署後請清一次 LiteSpeed 快取，避免載入舊版 public.js／public.css。


---

## v1.3.4

更新日期
2026-07-02

版本定位

FAQ 知識庫新增「CSV 匯入」功能，補齊搬站與大量新增的最後缺口；並讓控制板「安裝後設定指南」文案與匯入行為一致。

背景

先前版本已具備 FAQ CSV 匯出（export_faqs_csv）與控制板安裝指南，但「匯入」僅在介面文案與連結上提及，後端從未實作。本版將匯入完整補上，使搬站時 FAQ 內容可隨檔案一併帶走、平時也能用 Excel 批次準備後一次匯入。

一、主要新增功能

1. FAQ CSV 匯入（upsert）
  以「標準問題」文字完全相同判斷：
    - 已存在 → 覆蓋更新該筆的分類、回答、關鍵字、狀態、排序。
    - 不存在 → 新增，來源標記為 import。
  匯入完成後顯示統計：新增 X 筆、更新（覆蓋）Y 筆、略過 Z 筆。

2. 欄位與格式
  CSV 表頭沿用匯出的中文欄名（分類、標準問題、固定回答、關鍵字、狀態、排序），
  亦相容英文欄名；因此「匯出 → 編輯 → 匯回」可完整往返。
  必填：標準問題、固定回答。其餘留空時套用預設（分類＝待分類、狀態＝停用、排序＝100）。
  狀態欄接受「啟用／停用」中文或 active／inactive。
  自動去除 Excel 存檔常見的 UTF-8 BOM；忽略 id、命中次數、時間等系統欄位，避免匯入污染。

3. 覆蓋提醒（依需求加強）
  匯入區塊顯示顯著警語：題目相同者會被覆蓋更新、無法復原、建議先匯出備份。
  送出前另有 JavaScript 二次確認。

4. 控制板文案微調
  「建立 FAQ 知識庫」步驟補充說明：匯入時題目相同會覆蓋更新、其餘新增、建議先備份。

二、修改檔案

includes/modules/faq/class-ur-ai-faq-repository.php
  - 新增 find_by_question()：依題目文字精準比對既有 FAQ。

includes/modules/faq/class-ur-ai-faq-service.php
  - 新增 find_by_question() passthrough。
  - 新增 import_rows()：逐列 upsert，回傳新增／更新／略過統計，僅在有變動時清快取。

includes/modules/faq/class-ur-ai-faq-admin.php
  - handle_actions 新增 import_faqs 分派。
  - 新增 handle_import()、parse_faq_csv()、map_faq_csv_header()、normalize_import_status()。
  - get_admin_message 新增匯入相關訊息碼。

admin/pages/faq-page.php
  - 新增「CSV 匯入／匯出」卡片（匯出下載鈕 + 匯入上傳表單 + 覆蓋警語 + 二次確認）。
  - 匯入完成訊息附上新增／更新／略過統計。

admin/pages/dashboard-page.php
  - 安裝指南 FAQ 步驟文案補上覆蓋更新與備份提醒。

admin/assets/css/admin.css
  - 新增匯入／匯出區塊樣式。

ur-ai-assistant.php
  - 版本號 1.3.3 → 1.3.4。

三、資料表變更

無。沿用既有 FAQ 資料表與 sanitize_data 驗證（狀態限 active／inactive、分類空值→待分類、source 支援 import）。

四、測試結果

五個修改的 PHP 檔通過 phply 真實語法解析（PARSE OK）。
所有修改檔通過大／中／小括號平衡與 BOM 檢查（無 BOM）。
CSV 解析邏輯以模擬測試驗證：中文／英文表頭對應、BOM 去除、含逗號與換行的引號欄位、
狀態中文標籤還原、缺必填略過、缺回答欄位判為格式錯誤，皆符合預期。
匯出→匯入往返驗證：匯出的中文狀態標籤（啟用／停用）可被匯入正確還原；
匯出多餘的系統欄位（id、命中次數、時間）於匯入時自動忽略。

五、是否建議上正式站

建議。匯入為後台管理者操作、有 nonce 與權限檢查、有覆蓋警語與二次確認。
首次大量匯入前，建議先匯出一份現有 FAQ 作為備份。


---

## v1.4.0

更新日期
2026-07-02

版本定位

進階評估（三案擇優）新增「樓層／高度概估」，並加入「土地持分輸入方式」雙軌選擇，
讓使用者可用「基地總面積＋持分分子/分母」推算個人持分，取代單純輸入持分坪數，
使量體估算能以真實的整棟基地面積為基礎。

背景

先前進階評估的 site_area 欄位直接視為「個人持分坪數」，與「基地面積 × 建蔽率」
的量體估算所需的「基地總面積」概念不同，兩者無法共用。本版加入雙軌輸入機制，
讓使用者可選擇既有的「持分坪數」直接輸入（預設，維持相容），或改用「基地總面積＋
持分分子/分母」推算，後者會額外算出個人持分回推與樓層／高度概估。

一、主要新增功能

1. 土地持分輸入方式雙軌選擇（僅進階評估）
  預設「我知道持分坪數」：與舊版行為相同，site_area 直接視為個人持分。
  可切換「用基地面積＋持分比例推算」：輸入基地總面積＋持分分子/分母，
  系統換算個人持分坪數，並以整棟基地面積往下計算，最後依比例回推個人分回。

2. 樓層／高度概估（附屬於進階評估「基地面積＋持分比例」模式）
  填入建蔽率（%）後自動顯示：每層樓地板 ＝ 基地總面積 × 建蔽率；
  樓層數 ＝ 總樓地板 ÷ 每層樓地板（無條件進位，保守估算）；
  高度 ＝ 樓層數 × 單層樓高（預設 3.2 米，後台可調，前台可覆寫）。
  一次攤開顯示一句話結論＋完整公式拆解，不收合。
  固定附三則提醒：僅供想像實際以建築設計及都審為準／注意航道與道路寬度限高／
  注意土地使用分區有最大建蔽率限制。

3. 後台可調參數
  新增「單層樓高預設值」與「建蔽率欄位提示文字」，於試算器設定頁維護，無須改程式。

二、修改檔案

includes/modules/calculator/class-ur-ai-calculator-service.php
  - calculate_best_incentive() 新增 own_share 參數：提供時將 site_area 視為
    基地總面積，計算整棟後依 own_share/site_area 比例回推個人分回
    （has_individual／own_share／share_ratio／individual_low／individual_high）。
  - 新增 estimate_massing()：依基地總面積、建蔽率、整棟總樓地板、單層樓高，
    估算樓層數（無條件進位）與高度；輸入不足時回傳 null。

includes/modules/calculator/class-ur-ai-calculator-settings.php
  - defaults() 新增 massing_floor_height（3.2）與 massing_coverage_hint。
  - 新增 get_massing_params()。
  - sanitize() 新增樓層估算欄位清理（樓高夾在 2.4~5.0 米）。

includes/modules/calculator/class-ur-ai-calculator-ajax.php
  - compute_advanced() 重寫：新增 share_mode（pings／ratio）判斷；
    ratio 模式讀取 site_total_area、share_numerator、share_denominator 換算個人持分，
    並在填有 coverage_ratio 時呼叫 estimate_massing() 附加樓層估算結果。
  - summary 依 has_individual 顯示「基地｜持分」或原本的「土地」格式。

includes/modules/calculator/class-ur-ai-calculator-module.php
  - handle_settings_save() 新增 massing_floor_height／massing_coverage_hint 儲存。

admin/pages/calculator-settings-page.php
  - 新增「樓層／高度概估」設定區塊（單層樓高預設值、建蔽率提示文字）。

public/views/calculator-view.php
  - 進階評估面板新增「土地持分輸入方式」切換（持分坪數／基地面積＋持分比例）。
  - 比例模式新增欄位：基地總面積、持分分子/分母、建蔽率、單層樓高。
  - 結果區新增樓層／高度概估區塊（data-calc-massing）。

public/assets/js/calculator.js
  - 新增 share_mode 切換的顯隱邏輯與 currentShareMode／setShareMode 輔助函式。
  - compute() 依 share_mode 收集不同欄位並驗證必填。
  - renderResult() 依 has_individual 顯示個人分回或全體分回。
  - 新增 renderMassing()：顯示樓層／高度概估的一句話結論＋公式拆解＋三則提醒。
  - renderBreakdown() 進階分支新增個人持分回推步驟。
  - buildShareUrl／applySharedParams／setField 新增雙軌輸入與樓層估算參數的分享／還原支援。
  - printStyles() 新增樓層估算區塊的列印樣式。

public/assets/css/calculator.css
  - 新增 .ur-ai-calc__massing、.ur-ai-calc__mode-switch、.ur-ai-calc__ratio-fields、
    .ur-ai-calc__inline-fields 等樣式。
  - @media print 加入 .ur-ai-calc__massing 分頁與色彩列印處理。

ur-ai-assistant.php
  - 版本號 1.3.4 → 1.4.0。

三、資料表變更

無。樓層估算為純計算，不落地儲存；試算情境仍沿用既有 transient 機制。

四、設計取捨（供日後參考）

樓層數採無條件進位、單層樓高採平均值，不逐層試算退縮／斜線限制，避免變因過多失真。
退縮視為已內含於建蔽率概念中，不重複扣減。
地下室層數不影響樓高估算，故本版不納入。
雙軌輸入預設維持「持分坪數」，改動感最小；僅在使用者主動切換「基地面積＋持分比例」
時才需多填欄位、才會顯示樓層估算，不干擾一般地主的既有體驗。
本版僅在「進階評估」加入雙軌輸入與樓層估算；「初步快速試算」與「基地總量回推法」
（compute_site，本就已用基地總面積＋選填 own_share）未變動，維持原行為。

五、是否建議上正式站

建議上線前先在測試環境跑過「基地面積＋持分比例」＋建蔽率的完整流程，
確認樓層數、高度與個人分回的計算結果符合預期，再上正式站。
新舊分享連結（urc_*）皆相容：舊連結無 urc_sm 時預設視為 pings 模式，行為與 v1.3.4 前一致。


---

## v1.4.1

更新日期
2026-07-03

版本定位

列印結果版面壓縮，讓多數試算結果盡量收在 A4 一頁內；三處次要說明文字固定改為精簡版
（畫面與列印稿統一），核心數字與公式拆解維持完整不變。

背景

v1.4.0 上線後，實際列印一份含三案擇優＋樓層估算的完整結果會跨到 2 頁。討論後確認
方向：不做動態量測分頁的複雜邏輯，改用「固定精簡文案＋緊湊排版」的簡化做法，
換取穩定好維護；次要說明文字精簡為固定規則（非視內容多寡才觸發），畫面與列印稿
文字統一，僅版面留白／字級在列印稿額外壓縮。

一、內容調整

1. 三處次要說明文字固定精簡（畫面＋列印稿一致，非僅列印稿）：
  三案擇優下方說明：「※ 上列為容積樓地板，非最終分回坪數；最終分回請見下方試算過程。」
  樓層估算三則提醒合併為一句：「僅供想像，實際以建築設計及都審為準；另須留意限高與
  建蔽率上限規定。」（原本 3 條清單改為單段文字）
  「試算只是起點」6 項因素清單合併為一句，並移除原本的清單樣式，改一般段落文字：
  「本試算為概估，實際分回受營建成本、房價、實施方式、基地整合難度、獎勵審議結果
  及原屋條件等因素影響，請以正式評估為準。」
2. 「重要提醒（免責聲明）」與「坪數提醒」兩個法遵區塊，文字維持完整不變。
3. 試算過程（公式透明拆解）與三案擇優／樓層估算的數字內容不變，僅列印版面壓縮。

二、列印版面壓縮（僅列印稿，畫面顯示不受影響）

@page 邊界由 1.4cm 收為 1cm；內文字級由 13px 收為 11px，行距由 1.65 收為 1.45。
各區塊內距、間距、標題與清單行距同步收緊（約收窄 30~40%）。
不做動態分頁量測：多數案例（如目前的測試案例）應可收在 1 頁；內容特別多時
（例如又有三案擇優、又有樓層估算、又有很長的自訂免責聲明）仍可能落到 2 頁，
此時不犧牲字級可讀性，讓其自然延伸到第 2 頁。

二、修改檔案

public/views/calculator-view.php
  - 「試算只是起點」區塊由標題＋引言＋6 項清單，合併簡化為標題＋單段文字。

public/assets/js/calculator.js
  - renderPaths()：三案擇優下方說明文字改為精簡版。
  - renderMassing()：樓層估算三則提醒由 <ul> 三項改為單段 <p> 文字。
  - printStyles()：全面收緊列印版面（@page 邊界、字級、行距、區塊內距／間距）；
    同步移除 .ur-ai-calc__factors-list 規則（改為段落，不再是清單）。

public/assets/css/calculator.css
  - .ur-ai-calc__factors-text：改為區塊內最後一個元素的間距（移除清單間距）；
    移除 .ur-ai-calc__factors-list／.ur-ai-calc__factors-list li（不再使用）。
  - .ur-ai-calc__massing-notes：移除清單縮排（改為段落樣式）。

ur-ai-assistant.php
  - 版本號 1.4.0 → 1.4.1。

三、資料表變更

無。

四、測試結果

calculator.js 通過 node --check 語法檢查。
calculator-view.php／calculator.css 通過大括號與標籤數量平衡檢查。
與 v1.4.0 逐檔比對，僅上列 4 個檔案變動，其餘 122 個檔案位元組相同。

五、是否建議上正式站

建議。純文案與列印版面調整，不影響計算邏輯與既有分享連結格式，風險低。
部署後請實際列印一次含三案擇優＋樓層估算的完整結果，確認版面與文字符合預期，
並清一次 LiteSpeed 快取避免載入舊版 calculator.js／calculator.css。


---

## v1.5.0

更新日期
2026-07-03

版本定位

「土地面積輸入」整合為單一共用區塊，移至表單最上方，「立即試算」與「進階試算」
共用同一組值（含土地持分輸入方式雙軌切換、建蔽率、單層樓高），不用重複填寫；
樓層／高度概估也隨之開放給「立即試算」使用，不再僅限進階模式。
另外，試算結果的坪數統一改為固定顯示到小數第 2 位。

背景

v1.4.0／v1.4.1 的「土地面積輸入方式」雙軌切換只做在進階評估面板內，與「立即試算」
各自獨立、互不相通，使用者若兩種試算都想試，得填兩次土地面積。討論後決定整合為
一個共用輸入區塊，放在表單最上方，兩個試算按鈕都讀取同一組值；同時比照使用者
需求，把坪數精度從小數 1 位提高到固定小數 2 位。

一、主要調整

1. 土地面積輸入整合為共用區塊
  原本位於「立即試算」面板內的「土地持分坪數（坪）」欄位，與原本僅在「進階評估」
  內的「土地持分輸入方式」雙軌切換（持分坪數／基地面積＋持分比例）、基地總面積、
  持分分子/分母、建蔽率、單層樓高，全部合併為一個共用區塊，移至表單最上方
  （「使用分區」欄位之前）。
  「立即試算」與「進階試算」都讀取同一組值，只需填寫一次。

2. 樓層／高度概估開放給「立即試算」
  先前樓層／高度概估僅在「進階評估」出現；本版起只要選了「基地面積＋持分比例」
  模式並填了建蔽率，「立即試算」與「進階試算」的結果都會顯示樓層／高度概估。

3. 坪數精度：小數 1 位 → 固定小數 2 位
  試算結果內所有坪數（土地面積、可分回坪數、各級距樓地板、樓層估算的每層樓地板／
  總樓地板等）統一改為固定顯示到小數第 2 位（例如 12 → 12.00）。
  百分比、樓層數、樓高（米）等非坪數欄位不受影響，維持原本顯示方式。

二、修改檔案

includes/modules/calculator/class-ur-ai-calculator-service.php
  - round_ping()：四捨五入位數由 1 位提高為 2 位，讓後續固定 2 位小數顯示有意義。

includes/modules/calculator/class-ur-ai-calculator-ajax.php
  - 新增 resolve_land_area_input()：共用的雙軌輸入判斷（原本 compute_site／
    compute_advanced 各自重複的邏輯，抽成同一個私有方法，兩處呼叫）。
  - 新增 resolve_massing()：共用的樓層估算判斷（原本僅 compute_advanced 有，
    抽出後 compute_site 也能呼叫）。
  - compute_site()：改用 resolve_land_area_input()／resolve_massing()，
    新增雙軌輸入與樓層估算支援（先前僅 compute_advanced 有）。
  - compute_advanced()：改用共用方法，行為不變。

public/views/calculator-view.php
  - 新增「土地面積輸入（共用）」區塊，置於表單最上方；內含土地面積輸入方式切換、
    持分坪數欄位、基地面積＋持分比例欄位（含建蔽率、單層樓高）。
  - 移除「立即試算」面板內原本的「土地持分坪數（坪）」欄位（改用共用區塊）。
  - 移除「進階評估」面板內原本的雙軌切換與比例欄位（改用共用區塊）；更新提示文字，
    說明改讀取最上方填入的土地面積。

public/assets/js/calculator.js
  - 新增 applyShareModeVisibility()：統一控制持分坪數欄位／比例欄位的顯隱
    （原本只控制比例欄位，現在也控制持分坪數欄位）。
  - compute()：土地面積相關欄位改為共用一次收集（不再依 track 分開收集），
    立即試算／進階試算都會送出同一組土地面積參數。
  - buildShareUrl()／applySharedParams()：土地面積分享／還原邏輯不再依 track
    分開處理，改統一輸出／還原。
  - renderBreakdown()：「立即試算」分支新增個人持分回推步驟（has_individual），
    與「進階試算」分支邏輯一致。
  - fmt()：改為固定顯示到小數第 2 位（不再省略多餘的 0）。

public/assets/css/calculator.css
  - 新增 .ur-ai-calc__panel--shared：土地面積共用區塊的淺底色視覺區隔樣式。

ur-ai-assistant.php
  - 版本號 1.4.1 → 1.5.0。

三、資料表變更

無。

四、相容性

分享連結（urc_*）格式不變，舊連結（無 urc_sm 參數）仍會被視為 pings 模式正確還原。
舊版直接帶 own_share 參數的呼叫方式（無 share_mode）仍相容（於 resolve_land_area_input()
中作為後備讀取）。

五、測試結果

calculator.js 通過 node --check 語法檢查。
calculator-view.php／calculator-service.php／calculator-ajax.php 通過大括號與標籤
數量平衡檢查，calculator.css 大括號平衡檢查通過。
與 v1.4.1 逐檔比對，僅上列 5 個檔案變動，其餘 121 個檔案位元組相同。

六、是否建議上正式站

建議上線前分別測試「立即試算」與「進階試算」在 pings／ratio 兩種模式下的完整流程，
確認共用欄位切換、樓層估算顯示、坪數小數位數皆符合預期，再上正式站。


---

## v1.5.1

更新日期
2026-07-03

版本定位

程式碼審查（安全性 + 品質）收尾：修正審查中發現的正確性 bug、補上流量限制的
原子操作、讓試算器 CF7 欄位可調，並解決相關頁面查詢與文章匯入的效能問題；
另同步 readme.txt 版本號與異動紀錄，清除舊版前台 CSS／JS 殘留檔。

一、正確性修正

1. uninstall.php 解除安裝清單漏刪 ur_ai_calculator_leads
  勾選「解除安裝時刪除所有資料」時，計算機名單表（含姓名／電話／email 等個資）
  先前不會被刪除；已補上此表。

2. class-ur-ai-calculator-cf7.php：capture_lead() 忽略 insert() 失敗
  CF7 留資料寫入資料庫失敗時原本會被靜默吞掉；現在失敗會記錄 error_log。

3. class-ur-ai-calculator-ajax.php：compute_advanced() 在 pings 模式漏轉發 own_share
  進階（三案擇優）試算在舊版「直接輸入個人持分坪數」模式下，不會回傳個人分回
  區間；已改為與 compute_site() 一致，一律轉發 own_share。

4. class-ur-ai-post-search.php：guess_keywords() 呼叫參數與方法簽章不符
  多帶的第 3 個參數（文章內容）被 PHP 靜默忽略，導致匯入的相關頁面關鍵字
  未反映文章內容；已修正為正確的 2 參數呼叫。

二、穩定性與效能

5. 計算機每小時流量限制的競態條件
  原本的 get_transient()→set_transient() 為非原子操作，高並發下可能被繞過。
  抽出共用的 UR_AI_Helper::atomic_increment_transient()，讓計算機與既有的
  每日提問限制（H2 修法）共用同一套原子遞增邏輯。

6. 相關頁面推薦查詢無快取
  find_related_by_question() 先前每次提問都重新查詢並對最多 500 筆資料重新
  評分；比照 UR_AI_FAQ_Service::get_active_faqs() 加上 transient 快取
  （UR_AI_Related_Page_Service::get_active_pages()），寫入時清除。

7. 文章匯入／搜尋的 N+1 查詢
  bulk_import_from_posts() 與 search_importable_posts() 先前對每篇文章各自
  查詢 related_pages 資料表 2 次；新增批次查詢方法
  find_existing_by_source_post_ids() / find_existing_by_urls()，改為每次
  請求各查詢 1 次。

三、架構調整

8. CF7 欄位名稱改為後台可調
  計算機留資料原本寫死 CF7 欄位名稱（your-name／tel／your-email／
  your-message／consent），表單欄位一改就會靜默失效。新增 cf7_field_map
  設定（試算器設定頁可調），並在送出資料缺少對應欄位時記錄警告。

四、文件

9. readme.txt 版本號與異動紀錄同步
  Stable tag 先前停留在 1.0.0、Changelog 也只到 1.0.0，與外掛實際版本
  1.5.0 脫節；已同步並補上 1.1.0~1.5.0 的精簡異動紀錄。

10. 清除舊版前台資源殘留檔
  public-1.css／public-2.css／public-3.css、public - 1.js／public - 2.js
  皆為未被任何程式碼引用的舊版快照，已移除。

二、修改檔案

uninstall.php
includes/modules/calculator/class-ur-ai-calculator-cf7.php
includes/modules/calculator/class-ur-ai-calculator-ajax.php
includes/modules/calculator/class-ur-ai-calculator-settings.php
includes/modules/calculator/class-ur-ai-calculator-module.php
includes/modules/related-pages/class-ur-ai-post-search.php
includes/modules/related-pages/class-ur-ai-related-page-service.php
includes/modules/related-pages/class-ur-ai-related-page-repository.php
includes/modules/related-pages/class-ur-ai-related-page-importer.php
includes/modules/ajax/class-ur-ai-ajax-module.php
includes/shared/class-ur-ai-helper.php
admin/pages/calculator-settings-page.php
readme.txt
ur-ai-assistant.php
  - 版本號 1.5.0 → 1.5.1。

三、資料表變更

無。cf7_field_map 為既有 ur_ai_calculator_settings option 內新增的鍵，非
新資料表／欄位。

四、是否建議上正式站

建議先於測試站確認：計算機留資料、進階試算個人分回顯示、相關頁面推薦、
文章匯入／搜尋皆正常，再上傳正式網站。

---

## 這個檔案的設計重點

### 1. 留下完整版本脈絡

未來回頭看 v1.0.0，就能知道這一版是「模組化架構整理版」。

### 2. 明確提醒正式站風險

這版檔案很多，最需要注意：

```text
autoload / require
Class not found
AJAX action
資料表建立
3. 保留後續版本方向

後面可以依照：

v1.0.1 補齊完整性
v1.0.2 優化功能
v1.1.0 擴充架構

逐步穩定發展。