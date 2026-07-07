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

## v1.6.0

更新日期
2026-07-03

版本定位

新增前台「知識庫瀏覽」功能。既有的「AI 問答」流程中，FAQ 只有在使用者
提問文字剛好被比對演算法命中（分數 ≥ 45）時才會顯示，命中率受限於使用者
措辭，效益不高；使用者也無從得知知識庫裡實際收錄哪些問題。本版新增獨立的
搜尋／分類瀏覽入口，直接呈現問答內容，不經過比對演算法、也不呼叫 AI。

一、主要新增功能

1. 前台知識庫瀏覽區塊（預設關閉，需後台啟用）
  於 AI 助理 widget 內新增「瀏覽全部常見問題」區塊：關鍵字搜尋（送出才查，
  非即時搜尋）＋分類篩選下拉＋可展開的手風琴式問答列表＋上一頁／下一頁。
  頁面載入時會自動載入第一頁（無篩選條件），之後的搜尋／換頁才各觸發 1 次
  AJAX 請求。

2. 新 AJAX 端點 ur_ai_faq_browse
  獨立於既有的 ur_ai_ask：直接查詢 status=active 的 FAQ 並回傳問答內容，
  完全不經過 UR_AI_FAQ_Matcher 的比對演算法，也不會在無命中時退回呼叫 AI。
  沿用既有的前台 public nonce 驗證。

3. 後台設定
  「功能設定」新增「知識庫瀏覽」卡片：啟用開關（預設關閉，避免既有網站
  更新後未經確認就多出新 UI）與每頁筆數（預設 10，可調 1~50）。

4. Shortcode 新參數
  `show_kb_browse`（預設 1，需搭配後台啟用開關）、`kb_browse_limit`
  （留空則採後台設定值）。

二、後端變更（不影響既有 FAQ 比對／AI 問答流程）

includes/modules/faq/class-ur-ai-faq-repository.php
  - 新增 get_active_categories()：取得目前有 active FAQ 使用的分類清單
    （去重、排序），供前台分類篩選使用；區別於既有的
    UR_AI_Schema_FAQs::get_default_categories() 建議清單。

includes/modules/faq/class-ur-ai-faq-service.php
  - 新增 get_active_categories()：transient 快取包裝，快取失效時機與
    get_active_faqs() 共用同一套清除邏輯（内容寫入時清除）。
  - 新增 browse()：知識庫瀏覽的查詢包裝，強制 status=active，支援關鍵字
    ＋分類篩選＋分頁，回傳 items／total／per_page／paged／total_pages。
    查詢結果本身不快取（篩選組合多變，快取效益低）。

includes/modules/ajax/class-ur-ai-ajax-module.php
  - 新增 handle_faq_browse()：驗證 nonce、檢查 kb_browse_enabled 設定，
    呼叫 UR_AI_FAQ_Service::browse()，並將每筆答案轉為安全 HTML
    （沿用既有的 format_answer_html()）後回傳。

includes/shared/class-ur-ai-settings.php
  - 新增 kb_browse_enabled（預設 0）、kb_browse_per_page（預設 10）
    設定與對應的 is_kb_browse_enabled() / get_kb_browse_per_page()。

includes/modules/public/class-ur-ai-shortcode.php
  - build_view_args() 新增知識庫瀏覽相關參數解析（含分類清單查詢）。

三、前台變更

public/views/assistant-view.php
  - 新增 .ur-ai-kb-browse 區塊（搜尋表單＋結果列表＋分頁），受
    kb_browse_enabled 控制。

public/assets/js/public.js
  - 新增 fetchKbList()／renderKbItem()／renderKbPagination()／
    handleKbSearchSubmit()／handleKbItemToggle()／handleKbPageLinkClick()／
    initKbBrowse()，頁面載入時自動載入第一頁。

public/assets/css/public.css
  - 新增 .ur-ai-kb-* 樣式，沿用既有配色與圓角視覺語言。

admin/pages/settings-page.php
  - 新增「知識庫瀏覽」設定卡片（啟用開關＋每頁筆數）。

ur-ai-assistant.php
  - 版本號 1.5.1 → 1.6.0。

四、資料表變更

無。kb_browse_enabled／kb_browse_per_page 為既有 ur_ai_assistant_settings
option 內新增的鍵，非新資料表／欄位。

五、是否建議上正式站

建議先於測試站確認：搜尋／分類篩選／分頁／答案展開皆正常，且確認在
「功能設定」手動啟用後才會出現此區塊（預設關閉，不影響既有網站的
既有外觀）。

---

## v1.6.1

更新日期
2026-07-03

版本定位

正式站回報：知識庫瀏覽的分頁按鈕（上一頁／下一頁）文字顯示不出來（疑似
主題全域按鈕樣式覆蓋了文字顏色），且選擇分類後需要再按一次「搜尋」才會
套用篩選，體感上像是「選分類沒有用」。本版修正這兩點。

一、修正內容

1. 分頁按鈕文字顯示
  .ur-ai-kb-page-link 先前沒有明確指定文字顏色，若佈景主題對 <button>
  有全域樣式覆蓋（例如漸層文字效果），會導致按鈕文字不可見。改為明確
  指定 color 與 -webkit-text-fill-color，不再依賴繼承。

2. 選擇分類立即套用篩選
  新增 change 事件：使用者一改變分類下拉選單，立即以該分類重新查詢
  （回到第 1 頁），不需要再額外按「搜尋」鍵。關鍵字搜尋維持原本的
  送出才查（避免每次選字都打資料庫）。

二、修改檔案

public/assets/css/public.css
  - .ur-ai-kb-page-link 新增明確文字顏色。

public/assets/js/public.js
  - 新增 handleKbCategoryChange()，綁定於分類下拉選單的 change 事件。

ur-ai-assistant.php
  - 版本號 1.6.0 → 1.6.1。

三、資料表變更

無。

四、是否建議上正式站

建議。純前端樣式與互動微調，不影響後端查詢邏輯。

---

## v1.7.0

更新日期
2026-07-03

版本定位

v1.6.0 的「知識庫瀏覽」是純 AJAX 動態載入，塞在 AI 助理 widget 裡，對 SEO
幫助有限：內容不在頁面原始 HTML 裡、沒有獨立網址、也沒有結構化資料。
本版新增一個完全獨立、伺服器端渲染的「FAQ 知識庫查詢頁」shortcode，
專門給想額外做 SEO 的網站使用，可放在自建的獨立頁面上。

一、主要新增功能

1. 新 shortcode [ur_ai_faq_kb_page]
  與 [ur_ai_assistant] 完全獨立，建議放在自己新建的頁面（例如「常見問題」，
  網址可自訂為 /faq/）。伺服器端直接輸出問答內容（不需 JavaScript），
  搜尋、分類篩選、換頁皆透過網址參數（?kb_q=、?kb_cat=、?kb_page=）
  以標準 GET 表單與 <a href> 連結運作，任何搜尋引擎爬蟲或停用 JS 的
  瀏覽器都能正常瀏覽與被索引。

2. FAQPage 結構化資料（JSON-LD）
  每次渲染時，依「目前頁面實際顯示的問答」輸出 Google 支援的 FAQPage
  schema.org 標記，讓網站有機會在 Google 搜尋結果中直接顯示常見問題摘要
  （FAQ rich result）。

3. 問答內容以 <details>/<summary> 呈現
  原生 HTML 手風琴元件，不需要 JavaScript 就能展開／收合，且收合狀態下
  文字仍存在於 DOM 中，對搜尋引擎索引更友善。

二、新增檔案

includes/modules/public/class-ur-ai-faq-kb-page-shortcode.php
  - 新 shortcode 控制器：解析 title／per_page 屬性與 $_GET 的
    kb_q／kb_cat／kb_page，呼叫既有的 UR_AI_FAQ_Service::browse()／
    get_active_categories()（v1.6.0 已建置，未新增查詢邏輯），
    格式化答案 HTML 後交給 view 渲染。

public/views/faq-kb-page-view.php
  - 頁面樣板：搜尋表單（GET）、分類下拉、問答手風琴列表、上一頁／下一頁
    連結、FAQPage JSON-LD。CSS class 刻意採用 .ur-ai-faq-kb-page-* 前綴，
    與 widget 內知識庫瀏覽區塊的 .ur-ai-kb-* 前綴區隔，避免同頁共存時
    樣式衝突。

三、修改檔案

includes/modules/public/class-ur-ai-public-module.php
  - 註冊新 shortcode ur_ai_faq_kb_page；新增 render_faq_kb_page_shortcode()。

includes/modules/public/class-ur-ai-public-assets.php
  - 新增 enqueue_style_only()：此頁面不需要 public.js（無 AJAX 依賴），
    只載入 CSS，減少頁面重量。

includes/core/class-ur-ai-autoloader.php
  - 新增 UR_AI_FAQ_KB_Page_Shortcode 類別對照。

public/assets/css/public.css
  - 新增 .ur-ai-faq-kb-page-* 樣式。

ur-ai-assistant.php
  - 版本號 1.6.1 → 1.7.0。

四、資料表變更

無。沿用 v1.6.0 已建置的 UR_AI_FAQ_Service::browse()／get_active_categories()。

五、測試方式

由於本機沙箱環境無法安裝完整 WordPress（網路政策擋掉 wordpress.org），
改以最小化 WordPress 函式模擬層，讓 faq-kb-page-view.php 實際渲染模擬資料，
驗證：非空狀態下不顯示「找不到」訊息、問答筆數正確、FAQPage JSON-LD 為
合法 JSON 且結構正確、非知識庫相關的既有網址參數（如 utm_source）會被保留、
kb_page 不會被誤存為隱藏欄位（否則會與換頁邏輯衝突）、分頁連結 class 與
widget 版本無命名衝突。

六、是否建議上正式站

建議先建立一個新頁面（例如「常見問題」），加入 [ur_ai_faq_kb_page]，
確認搜尋／分類／換頁皆可用網址直接操作，且 Google 結構化資料測試工具
能正確解析 FAQPage 標記後，再對外公開該頁面連結。

---

## v1.7.1

更新日期
2026-07-03

版本定位

readme.txt／docs 雖然都記錄了 Shortcode 用法，但站方實際安裝時真正會看的
是後台總覽頁，而總覽頁原本的「前台使用方式」卡片只簡短提到 2 組 Shortcode
（AI 助理、FAQ 知識庫查詢頁），完全沒提到試算器的 [ur_ai_calculator]，
也沒有列出各 Shortcode 的完整參數。日後把這套外掛安裝到不同網站時，
需要有一個後台就能查到的完整參考。本版把 3 組 Shortcode 的完整用法
整理成後台總覽頁的一個獨立收合區塊。

一、主要調整

1. 後台總覽頁新增「Shortcode 使用說明」收合區塊
  位置：安裝後設定指南下方，摘要卡片上方。內容依序為：
    1. AI 助理問答 [ur_ai_assistant]（含 title／subtitle／placeholder／
       show_popular／popular_limit／show_groups／group_limit／
       show_kb_browse／kb_browse_limit 全部 9 個參數）
    2. FAQ 知識庫查詢頁 [ur_ai_faq_kb_page]（title／per_page）
    3. 都更分回效益試算器 [ur_ai_calculator]（mode=owner／pro）
  每組皆附完整 Shortcode 代碼＋複製按鈕＋參數說明＋範例。

2. 簡化「前台使用方式」卡片
  移除與新區塊重複的完整參數說明，改為保留最基本的 [ur_ai_assistant]
  複製功能，並附連結指向新的「Shortcode 使用說明」區塊，避免同一份
  資訊維護兩份、日後容易改一邊漏一邊。

二、修改檔案

admin/pages/dashboard-page.php
  - 新增「Shortcode 使用說明」<details> 區塊（id="ur-ai-shortcode-guide"，
    可用網址錨點 #ur-ai-shortcode-guide 直接連結並自動展開）。
  - 簡化原本「前台使用方式」卡片內容。

admin/assets/css/admin.css
  - 新增 .ur-ai-shortcode-params 清單樣式。

ur-ai-assistant.php
  - 版本號 1.7.0 → 1.7.1。

三、資料表變更

無。純後台說明文字與版面調整。

四、是否建議上正式站

建議。純後台文件性質調整，不影響前台任何功能與既有頁面。

---

## v1.8.0

更新日期
2026-07-07

版本定位

都更危老重建案的前期評估，除了試算分回效益外，屋主與投資方也常需要知道
「附近的成屋行情大概多少」，並且需要區分「更新前的老舊房屋」與
「更新後的新成屋」兩種參考基準。考量「估價」一詞在法規上僅限領有證照的
不動產估價師使用，本模組定位為「歷史成交行情參考」，資料來源為內政部
不動產交易實價查詢服務的公開資料，不做任何預測或估算，僅呈現歷史統計
（中位數為主），並排除特殊關係交易、設有最低樣本數門檻。範圍先限定
雙北（台北市／新北市），資料以後台手動上傳 CSV 的方式匯入與持續累積。

核心目標

* 提供「簡易查詢行情參考」的獨立 shortcode，不與既有 AI 助理／試算器混用。
* 以獨立模組建置（schema／repository／service／import service／
  settings／ajax／admin／module 各層分離），service 層方法設計成
  未來其他模組（例如試算器）可直接 new 呼叫取得行情資料，不需要額外的
  hook 或 REST 包裝。
* 同一批資料依「建物年齡」動態分成「老屋現況」與「新成屋」兩組統計，
  門檻皆可於後台調整，不需要維護兩份資料。
* 資料正確性與統計效度優先於功能豐富度：特殊關係交易一律排除、
  最低樣本數不足時只顯示筆數不顯示金額、以中位數而非平均數為主要指標
  （避免極端交易拉高/拉低參考值）。

一、主要新增功能

1. 新 shortcode [ur_ai_market_price]
  使用者選擇縣市（台北市／新北市）與行政區後，可查詢該行政區「老屋現況」
  （預設屋齡 30 年以上）與「新成屋」（預設屋齡 5 年內）兩組歷史成交行情
  統計：中位數、平均、最低、最高單價（元/坪）與平均屋齡。任一組樣本數
  低於後台設定的最低門檻（預設 5 筆）時，只顯示筆數並標示「樣本不足」，
  不顯示金額，避免統計失真誤導使用者。

2. 後台「行情參考」管理頁
  - CSV 匯入：上傳內政部實價登錄公開資料 CSV（依政府「編號」欄位去重，
    可重複上傳含重疊區間的資料而不會產生重複紀錄，方便日後持續累加）。
  - 功能開關與門檻設定：老屋／新成屋屋齡門檻、最低樣本數門檻、
    免責聲明文字皆可調整。
  - 各行政區樣本數健檢總覽：一次檢視雙北各行政區的老屋／新成屋樣本數，
    低於門檻的儲存格會標示提醒色，方便判斷哪些行政區還需要補充資料。
  - 資料過舊提醒：最後匯入時間超過 90 天會於管理頁顯示提醒。

3. 資料清理與正規化（匯入時自動處理）
  - 民國年日期換算為西元年月日（相容 6～7 碼、含驗證，格式錯誤直接略過
    該筆而非整批匯入失敗）。
  - 都市土地使用分區原始文字（如「都市：其他:第三種住宅區。」）正規化為
    簡短分類（如「住三」），與既有試算器的分區分類方式一致。
  - 自動偵測備註欄含「特殊關係」字樣的交易並標記排除，不納入任何統計。
  - 單價換算：以（總價－車位總價）÷（建物移轉總面積 ÷ 3.305785）計算
    元/坪單價，扣除車位總價影響、避免車位交易拉高住宅單價參考值。

二、新增檔案

includes/database/schemas/class-ur-ai-schema-market-prices.php
  - 新資料表 schema：ur_ai_market_prices（含 source_record_id 唯一索引
    作為去重依據，及 city/district/zone/building_age_years/
    transaction_date 等查詢索引）。

includes/modules/market-price/class-ur-ai-market-price-settings.php
  - 專屬設定類別（獨立 option，仿試算器模組慣例）：啟用開關、老屋／
    新成屋屋齡門檻、最低樣本數門檻、免責聲明文字，皆含 sanitize 邊界檢查。

includes/modules/market-price/class-ur-ai-market-price-zone-normalizer.php
  - 都市土地使用分區文字正規化工具（住宅區／商業區各種別，無法辨識時
    歸類為「其他」）。

includes/modules/market-price/class-ur-ai-market-price-repository.php
  - 資料存取層：去重寫入、依條件（縣市／行政區／分區／建物型態／
    屋齡區間）查詢統計（中位數／平均／最低／最高於 PHP 端計算，
    避免依賴特定 MySQL/MariaDB 版本的統計函式）、各行政區樣本數健檢查詢。

includes/modules/market-price/class-ur-ai-market-price-import-service.php
  - CSV 匯入服務：欄位對應、民國年換算、特殊關係偵測、分區正規化、
    單價計算，並統計本次匯入的新增／重複／略過筆數。

includes/modules/market-price/class-ur-ai-market-price-service.php
  - 對外服務層（含 transient 快取）：get_comparison() 回傳老屋／新成屋
    兩組統計、get_sample_health()、get_last_imported_at() 等。刻意維持
    一組穩定、有清楚文件註解的公開方法，方便未來其他模組（例如試算器）
    需要參考行情資料時直接 new 一個本類別呼叫。

includes/modules/market-price/class-ur-ai-market-price-ajax.php
  - 前台查詢 AJAX handler（沿用共用 nonce ur_ai_assistant_public_nonce）。

includes/modules/market-price/class-ur-ai-market-price-module.php
  - 模組進入點：註冊 shortcode、專屬 CSS/JS（不共用 public.css/js）、
    後台選單、admin-post 匯入與設定儲存 handler。

includes/modules/market-price/class-ur-ai-market-price-admin.php
  - 後台匯入與設定儲存邏輯（capability 檢查、nonce 驗證、匯入結果訊息）。

admin/pages/market-price-import-page.php
  - 後台「行情參考」管理頁樣板。

public/views/market-price-view.php
  - 前台查詢表單與結果容器樣板（僅提供縣市／行政區篩選，符合「簡易查詢」
    需求；分區／建物型態篩選已在 service／repository／ajax 層備妥，
    未來如需更細緻篩選可直接擴充前台表單）。

public/assets/js/market-price.js
  - 前台互動邏輯：依縣市篩選行政區選項、送出 AJAX 查詢、渲染老屋／
    新成屋比較卡片。

public/assets/css/market-price.css
  - 專屬樣式（.ur-ai-market-price-* 前綴，與既有模組樣式命名空間區隔）。

三、修改檔案

includes/database/class-ur-ai-schema-manager.php
  - DB_VERSION 1.1.0 → 1.2.0；註冊 UR_AI_Schema_Market_Prices。

includes/core/class-ur-ai-autoloader.php
  - 新增行情參考模組 8 個類別與新 schema 類別的路徑對照。

includes/core/class-ur-ai-module-manager.php
  - 註冊 market_price 模組（UR_AI_Market_Price_Module）。

admin/pages/dashboard-page.php
  - 資料過舊（≥90 天未匯入）提醒訊息。
  - 「Shortcode 使用說明」新增第 4 組：雙北成屋行情參考 [ur_ai_market_price]。
  - 「3 組 Shortcode」文字更新為「4 組 Shortcode」。

uninstall.php
  - 解除安裝清單新增 ur_ai_market_prices 資料表。

readme.txt／docs/installation.md
  - 主要功能列表、Shortcode 說明、FAQ（解除安裝保留資料表清單）同步更新。

ur-ai-assistant.php
  - 版本號 1.7.1 → 1.8.0。

四、資料表變更

新增 wp_ur_ai_market_prices 資料表（詳見上方 schema 檔案說明）。
DB_VERSION 由 1.1.0 → 1.2.0，沿用既有 dbDelta 自動建表機制，
啟用外掛或外掛更新時會自動建立，不需手動執行 SQL。

五、測試方式

由於本機沙箱環境無法安裝完整 WordPress 且無法連線政府開放資料網站，
改用使用者實際提供的內政部實價登錄雙北成屋樣本資料（.xls 原始檔，
以 Python xlrd 轉出等同 Excel「另存為 CSV」格式，含 UTF-8 BOM 與
英文欄名列，模擬管理員實際會上傳的檔案）搭配 PHP + SQLite 驗證工具，
直接載入本模組 5 個真實類別檔案（zone-normalizer／repository／
import-service／settings／service）對真實資料執行完整流程，驗證：

* 台北市樣本（212 筆）與新北市樣本（1058 筆）皆正確匯入，總筆數
  與資料庫實際筆數一致。
* 重複上傳同一份檔案時，212 筆全部正確判定為重複、0 筆新增
  （驗證 source_record_id 去重機制）。
* 士林區老屋現況（18 筆樣本）正確輸出中位數等統計；新成屋（僅 2 筆）
  因低於最低樣本數門檻，正確只顯示筆數、不顯示金額。
* 各行政區樣本數健檢總覽數字與明細資料一致。
* 100 筆特殊關係交易正確被標記且排除於所有統計之外。
* 資料庫內儲存的分區欄位皆為正規化後的簡短分類（住三／商一等），
  原始政府文字（如「都市：其他:第三種住宅區。」）未被原樣存入。

全部 8 項驗證項目皆通過。

六、是否建議上正式站

建議先於後台「行情參考」頁面手動上傳一份完整的雙北實價登錄 CSV，
確認匯入筆數與樣本數健檢總覽符合預期後，再啟用本模組並公開
[ur_ai_market_price] 頁面。啟用前請確認免責聲明文字符合網站需求，
且尚未有自動抓取政府資料的排程機制，需靠人工定期至內政部網站
下載最新 CSV 並於後台重新匯入。

七、上線前程式碼審查修正

正式提交前另外進行一輪多角度程式碼審查（正確性、重複利用、效能、
架構深度），並修正以下發現，因此版本號仍維持 v1.8.0（尚未對外發布）：

* 修正老屋／新成屋屋齡門檻可各自被設為造成區間重疊的數值（例如
  老屋=10、新成屋=15），導致同一筆交易同時被算進兩組矛盾統計；
  現在儲存設定時會強制新成屋門檻小於老屋門檻。
* 修正後台匯入表單「未選擇檔案」時實際上永遠顯示「上傳失敗」而非
  「請選擇檔案」的訊息判斷順序問題。
* CSV 必要欄位檢查補上「編號」欄位，避免該欄位缺漏時整批資料被
  靜默略過卻查不出原因。
* 金額欄位解析前先移除千分位逗號，避免 Excel 另存時保留的
  「15,000,000」格式被誤判為非數字而靜默歸零。
* 「排除特殊關係交易」條件原本在 Repository 兩個查詢方法各自寫一份，
  改為共用同一個方法，避免未來規則異動時兩處不同步。
* 資料過舊天數計算原本在後台總覽頁與行情參考頁各自重複一份，
  改為集中到 Service 層的 get_stale_days()／is_stale()。
* CSV 匯入時，改為匯入前一次查出該縣市已存在的 source_record_id
  集合，逐筆比對是否重複，取代原本每一筆資料都對資料庫下一次
  SELECT 查重複，大幅減少大量匯入時的資料庫查詢次數。
* 「老屋」「新成屋」統計原本分別查詢資料庫兩次，改為一次查詢後於
  PHP 端依屋齡分桶，減少前台查詢的資料庫往返次數。
* 後台權限檢查改為直接呼叫外掛既有的 UR_AI_Permissions::require_capability()
  共用方法，不再重新實作一份。
* get_zones()／get_building_types() 兩個幾乎相同的方法合併為一個
  共用的私有方法。

已重新執行 mp_harness.php（PHP + SQLite，對真實樣本 CSV 資料）驗證，
8 項測試結果與修正前完全一致，確認本輪修正未改變任何對外可見的
統計結果。

---

## v1.8.1

更新日期
2026-07-08

版本定位

v1.8.0 上線後，站方實際上傳一份格式完全正確的實價登錄 CSV 測試，
後台卻回報「共讀取 212 筆、新增 0 筆、略過格式錯誤 212 筆」，且累計
成交紀錄、最後匯入時間都維持在初始值，看起來像是全部資料格式有
問題。追查後確認並非 CSV 格式問題，而是 wp_ur_ai_market_prices
資料表在該站台實際上沒有被成功建立（dbDelta() 執行失敗時不會拋出
例外，因此不會有任何錯誤訊息），導致每一筆資料在寫入階段就失敗，
全部被歸類為「略過」。

一、根本原因

UR_AI_Schema_Manager::install() 原本在呼叫 create_tables() 之後，
不論資料表是否真的建立成功，都會直接把 DB_VERSION 標記為已完成
升級；一旦標記完成，maybe_upgrade() 之後就永遠不會再重試建立資料表，
即使資料表其實還缺漏，也沒有任何提示。這個問題不限於行情參考模組，
任何一次「新增資料表」的外掛升級都可能受影響。

二、修正內容

1. UR_AI_Schema_Manager::install() 改為呼叫既有但先前未被使用的
   all_tables_exist() 方法，只有在確認全部已註冊資料表都實際存在時，
   才會把 DB_VERSION 標記為升級完成；否則保持未完成狀態，
   maybe_upgrade() 會在下次頁面載入時繼續嘗試重建，具備自我修復能力。
2. 後台「行情參考」頁面新增資料表存在性檢查：若偵測到資料表不存在，
   會直接顯示明確的錯誤提示（而非讓管理者誤以為是 CSV 格式問題），
   並建議「停用外掛」後「重新啟用」以觸發資料表建立，或聯絡主機廠商
   確認資料庫帳號是否具備 CREATE TABLE 權限。

三、修改檔案

includes/database/class-ur-ai-schema-manager.php
  - install() 加入 all_tables_exist() 確認，只有確認建表成功才標記
    DB_VERSION 升級完成。

includes/modules/market-price/class-ur-ai-market-price-import-service.php
  - 新增 detect_city_from_rows()；import_from_csv() 改為匯入前先
    自動偵測縣市並在不符時整批拒絕，取代原本的「警告但照樣匯入」。

includes/modules/market-price/class-ur-ai-market-price-admin.php
  - handle_import() 新增 city_mismatch 分支，導向對應的錯誤訊息頁。

admin/pages/market-price-import-page.php
  - 進入頁面時新增資料表存在性檢查與對應錯誤提示；新增
    import_city_mismatch 訊息呈現；移除已改變行為的舊警告文字。

readme.txt / ur-ai-assistant.php
  - 版本號 1.8.0 → 1.8.1。

四、修正驗證

已重新執行 mp_harness.php（PHP + SQLite，對真實樣本 CSV 資料）驗證，
確認本次修正未影響匯入邏輯本身；此修正屬於「資料表升級機制」層級的
問題，無法在沙箱環境重現真實 MySQL 權限失敗情境，需站方在下次載入
後台頁面（或停用再啟用外掛）時，確認行情參考頁面不再顯示資料表
缺漏警告、且可正常重新匯入 CSV 為準。

五、加碼改進：CSV 匯入自動偵測縣市，選錯直接拒絕匯入

站方詢問「匯入時能否自動辨識縣市、選錯縣市會不會有問題」，追查後
發現原本的處理方式偏寬鬆：若上傳資料的行政區名稱與所選縣市不符，
只會顯示一句警告，但資料仍會被匯入並標記成「使用者選的（錯誤的）
縣市」，事後難以清理，等同污染資料庫。

改進方式：

1. 新增 UR_AI_Market_Price_Import_Service::detect_city_from_rows()，
   利用「台北市 12 區、新北市 29 區行政區名稱完全不重複」這個特性，
   以 CSV 內「鄉鎮市區」欄位多數決自動反推資料實際屬於哪個縣市，
   不需要依賴檔名或使用者輸入。
2. import_from_csv() 改為：匯入前先自動偵測縣市，若完全無法辨識
   （例如上傳非雙北資料），或偵測結果與使用者選擇的縣市不符，
   會「整批拒絕匯入」並回傳明確原因，不再是「警告但照樣匯入」。
3. 後台「行情參考」頁面新增對應的錯誤訊息呈現：會明確告知「偵測到
   這份資料屬於＿＿市，但您選擇的是＿＿市」，方便管理者立即發現
   選錯縣市並重新確認。
4. 移除舊有「偵測到部分資料的行政區名稱不屬於所選縣市，資料仍已
   匯入」這句容易誤導、且行為已改變的警告文字。

新增驗證：於 mp_harness.php 環境追加測試，確認（a）正確選擇縣市時
匯入行為與修正前完全一致；（b）刻意選錯縣市時會被正確拒絕、
detected_city 回報正確、且資料庫筆數不會因此被錯誤寫入的資料污染。
兩項測試皆通過。

六、是否建議上正式站

建議儘快更新。本次修正的兩項內容都是解決「資料可能被錯誤寫入或
完全無法寫入」的正確性問題，修正本身不變更既有查詢或統計邏輯，
風險低。

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