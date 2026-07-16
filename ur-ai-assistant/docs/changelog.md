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

## v1.8.2

更新日期
2026-07-08

版本定位

站方實際上線後回報：老屋現況與新成屋兩組行情，「區間」數字落差
非常大（例如 16.6 萬～706.2 萬），擔心這樣的呈現方式會讓使用者
覺得資料失真、參考價值降低。

一、根本原因

原本「區間」顯示的是該篩選條件下所有樣本的最低～最高成交單價
（min／max）。樣本數一多（例如單一行政區累積上千筆歷史交易），
min／max 這兩個統計量對極端值特別敏感——只要有一筆特殊樓層、
瑕疵屋況、法拍尾款或反向的頂樓景觀裝潢戶，就會把區間拉得極寬。
這個現象是真實資料的反映，並非計算錯誤，但呈現方式容易讓人
誤解成資料不可靠。

二、修正內容

1. Repository 新增 percentile() 方法（線性內插法，與常見統計軟體
   預設方法一致），並將 median() 改為呼叫 percentile(0.5)，
   避免中位數計算邏輯重複兩份。
2. get_price_stats_pair() 統計結果新增 range_low／range_high
   （第一、三四分位數，25%～75%），前台「區間」改為顯示這組
   四分位距，取代原本的 min／max。四分位距代表「扣除最極端的
   前後各 25% 之後，中間一半案例落在的範圍」，更貼近一般認知的
   「常見行情」，且不受少數極端案例影響。min／max 兩個欄位仍保留
   在統計結果中（供未來其他用途），只是前台不再拿來當作「區間」
   顯示。
3. 前台區間旁新增一行簡短說明文字：「反映同區域內不同樓層、屋況、
   地點的價格落差，已排除少數極端案例」，讓使用者理解區間本身的
   意義，不會誤以為區間寬是系統錯誤。
4. 順便修正一個先前程式碼審查已發現但尚未修的小缺口：前台 JS 需要
   的 old_title／new_title 文字先前沒有從後台 localize 過去（一直
   是用 JS 內建的預設文字頂著），以及 median_label／average_label
   兩個從未被 JS 讀取的多餘設定，這次一併補上與清理，讓 i18n 設定
   與實際使用的欄位一致。

三、修改檔案

includes/modules/market-price/class-ur-ai-market-price-repository.php
  - summarize_rows() 新增 range_low／range_high；新增 percentile()；
    median() 改為呼叫 percentile(0.5)。

includes/modules/market-price/class-ur-ai-market-price-service.php
  - format_stats()／empty_raw_stats() 新增 range_low／range_high
    欄位傳遞。

includes/modules/market-price/class-ur-ai-market-price-module.php
  - i18n 設定新增 old_title／new_title／range_note，range_label
    文字由「區間」改為「常見區間」，移除未使用的 median_label／
    average_label。

public/assets/js/market-price.js
  - 區間顯示改為 range_low～range_high，新增區間說明文字渲染。

public/assets/css/market-price.css
  - 新增 .ur-ai-market-price-range-note 樣式。

readme.txt / ur-ai-assistant.php
  - 版本號 1.8.1 → 1.8.2。

四、修正驗證

* 獨立撰寫 percentile() 驗證腳本，確認計算結果與標準統計方法
  （R type-7／numpy 預設線性內插）一致，並以刻意設計的偏態資料
  （含一筆極端值）驗證：min／max 為 10～900，但四分位距僅
  71～75，證實新呈現方式能有效排除極端值干擾。
* 重新執行 mp_harness.php（PHP + SQLite，對真實樣本 CSV 資料），
  原本 8 項測試全數通過，確認本次修正未影響既有匯入與統計邏輯，
  僅新增欄位與調整前台呈現方式。

五、是否建議上正式站

建議更新。純統計呈現方式調整（min／max 改為四分位距），未變更
資料庫結構或既有查詢邏輯，風險低；能有效降低使用者對「區間過寬、
資料是否失真」的疑慮，提升參考價值的可信度。案例呈現（例如列出
代表性成交案例）留待下一階段再評估是否納入，需另外考量個資／
去識別化與「非估價」定位的界線問題。

---

## v1.8.3

更新日期
2026-07-08

版本定位

上一版（v1.8.2）把「區間」改成四分位距後，站方進一步詢問：能否
呈現老屋、新成屋各自的代表案例，讓行情參考更有感。討論後確認
「單一代表案例」本身帶有主觀性、且顯示具體門牌等於把某人真實成交
紀錄拿來當行銷素材，容易模糊「統計參考」與「個案估價」的界線；
改為「同一批資料中依單價分散取樣多筆、且僅揭露去識別化特徵」的
做法，並以「路／街／段」取代完整地址（門牌號、樓層、確切交易日期
一律不顯示），在真實感與去識別化之間取得平衡。

一、設計決策

1. 不挑「一個代表案例」，改為依單價由低到高，分散挑選接近第一
   四分位、中位數、第三四分位位置的 3 筆真實交易，呼應 v1.8.2
   剛建立的「四分位距」概念——讓使用者看到「區間內真實存在這樣
   的交易」，而不是只信一個數字或一筆案例。
2. 只揭露「行政區＋路／街／段、屋齡、坪數、建物型態、單價」，
   不顯示門牌號、巷弄、樓層、確切交易日期——避免反查回政府公開
   資料裡的特定一筆交易、指向特定一戶。以正則規則從既有的
   address_raw 欄位擷取路／街／大道＋段（例如「臺北市文山區忠順
   街二段４０號二樓」→「忠順街二段」），實測在會被匯入的「房地」
   交易上可 100% 成功解析（唯一解析失敗的是純土地地號交易，這類
   資料本來就已被匯入邏輯排除）。
3. 樣本數未達最低門檻時，案例跟中位數／平均等其他統計數字一樣
   不顯示——樣本過少時「代表案例」形同直接指向少數幾筆個別交易，
   失去去識別化統計參考的意義。

二、主要修改

1. Repository
   - get_price_stats_pair()／get_price_stats() 的 SELECT 補上
     district、building_type、building_area_sqm、address_raw
     欄位（原本只有 unit_price_per_ping、building_age_years）。
   - summarize_rows() 新增 examples 欄位。
   - 新增 pick_examples()：依單價排序後，取第一四分位／中位數／
     第三四分位位置附近的紀錄（位置重複時自動去重，避免樣本極少
     時重複顯示同一筆）。
   - 新增 format_example()：組出去識別化案例資料（不含門牌／樓層／
     日期）。
   - 新增 extract_road_section()：從 address_raw 擷取路／街／段。
2. Service
   - format_stats()／empty_raw_stats() 新增 examples 欄位傳遞，
     樣本數不足時比照其他統計數字一併隱藏。
3. 前台
   - i18n 新增 examples_label／example_feature／example_price。
   - JS 新增 renderExamples()，於每張比較卡片統計數字下方渲染
     案例清單。
   - CSS 新增 .ur-ai-market-price-examples-label／
     .ur-ai-market-price-examples 樣式。

三、測試方式

* 先用 Python／PHP 分別驗證路段擷取正則規則，對照 5 筆真實地址
  樣本人工核對輸出（例如「臺北市信義區信義路五段１１１號九樓之
  ７」→「信義路五段」），再對完整 CSV 檔案跑過一輪，確認在
  會被實際匯入的 212 筆「房地」交易上比對成功率 100%。
* 重新執行 mp_harness.php（PHP + SQLite，真實樣本 CSV 資料），
  原本 8 項測試全數通過；額外檢視士林區老屋現況（18 筆，樣本
  充足）的 get_comparison() 輸出，確認 examples 正確回傳 3 筆
  分散於區間不同位置的真實案例，且新成屋（僅 2 筆，樣本不足）
  正確不回傳任何案例。

四、是否建議上正式站

建議更新。純新增呈現內容，不影響既有查詢、統計邏輯與已匯入的
資料，風險低。上線後建議實際比對前台呈現的路段名稱是否符合
預期（尤其確認沒有任何案例意外顯示門牌號或樓層資訊）。

---

## v1.8.4

更新日期
2026-07-08

版本定位

v1.8.3 加入「參考案例」清單後，站方反應前台容器寬度偏窄，案例
文字（尤其建物型態說明，例如「住宅大樓(11層含以上有電梯)」）
容易換行過於頻繁，版面顯得侷促。

修改內容

public/assets/css/market-price.css
  - .ur-ai-market-price 容器 max-width 由 720px 調整為 960px，
    讓每張比較卡片有更充裕的寬度容納案例清單文字。手機版
    （480px 以下）版面配置不受影響，仍維持單欄堆疊。

ur-ai-assistant.php / readme.txt
  - 版本號 1.8.3 → 1.8.4。

是否建議上正式站

建議更新。純 CSS 調整，不影響任何查詢邏輯或已匯入資料，風險低。
實際加寬效果仍取決於該頁面所在主題內容區塊本身的寬度上限，
本次調整的是外掛自身容器的「上限」，若主題內容區塊更窄，
仍會以主題寬度為準。

---

## v1.8.5

更新日期
2026-07-08

版本定位

站方實際匯入超過 20 萬筆真實成交資料後，詢問這批規模的資料還能
再運用做什麼——例如「一個區域的新屋成長」這類趨勢資訊。討論後
決定分兩類處理：能直接從既有查詢衍生、互動模式不變的功能（都更
效益百分比、近一年成長率）留在既有 widget 裡；不需要選擇條件、
瀏覽邏輯完全不同的內容（雙北都更效益排行榜）則另外開一個獨立
shortcode 承載，不與「簡易查詢」的既有互動模式混在一起。

一、主要新增功能

1. 都更效益指標（uplift_percent）
   老屋現況與新成屋皆樣本充足時，直接算出「新成屋中位數相對老屋
   中位數」的漲幅百分比，作為一句話結論呈現在兩張比較卡片上方，
   取代原本需要使用者自己心算兩個數字差距。

2. 近一年成長率（trend）
   每組統計（老屋／新成屋各自）新增比較「近一年」與「前一年
   （一～二年前）」中位數單價的年成長率。兩個時間窗都採中位數
   而非平均，且需各自的樣本數都達到最低門檻才會顯示，避免用
   偏少的子樣本算出不具參考意義的成長率。

3. 新 shortcode [ur_ai_market_price_ranking]（雙北都更效益排行榜）
   不需要使用者選擇任何條件，直接列出雙北全部行政區的都更效益
   排行榜，依漲幅由高到低排序，只納入老屋與新成屋樣本數皆充足
   的行政區。伺服器端直接渲染（不需 JavaScript／AJAX），仿照
   [ur_ai_faq_kb_page] 的既有架構模式，適合另外建立獨立頁面
   分享、供搜尋引擎收錄。

二、主要修改

1. Repository
   - get_price_stats_pair()／get_price_stats() 的 SELECT 補上
     transaction_date 欄位。
   - summarize_rows() 新增 trend 欄位；新增 compute_trend()：以
     current_time('timestamp') 為基準，切分「近一年」與「一～
     二年前」兩個時間窗，各自計算中位數後算出漲跌百分比。
   - 新增 get_ranking_data($city, $old_threshold, $new_threshold)：
     一次查詢撈出指定縣市全部紀錄，於 PHP 端依行政區分組、再依
     屋齡門檻分桶計算中位數，避免對 41 個行政區各自下一次查詢。
2. Service
   - get_comparison() 新增頂層 uplift_percent 欄位。
   - format_stats() 新增 trend 欄位傳遞，並比照 examples 的邏輯，
     只有在 recent_count／prior_count 都達最低樣本數門檻時才顯示。
   - 新增 get_ranking($city)：套用最低樣本數門檻篩選、計算漲幅
     百分比、依漲幅排序，並沿用既有 cached() transient 快取機制
     （新增 ranking_{city} 快取 key，clear_cache() 一併清除）。
3. 新增 UR_AI_Market_Price_Ranking_Shortcode 類別與
   public/views/market-price-ranking-view.php：仿照
   UR_AI_FAQ_KB_Page_Shortcode 的既有架構（服務層／檢視層分離、
   缺服務時顯示明確錯誤提示）。
4. class-ur-ai-market-price-module.php 新增
   ur_ai_market_price_ranking shortcode 註冊與對應的 CSS-only
   資產載入（沿用既有 market-price.css，不需要額外的 JS）。
5. 前台 JS／i18n／CSS 新增 uplift、trend 的渲染與樣式；後台總覽頁
   「Shortcode 使用說明」新增第 5 組說明，相關「4 組」文字更新為
   「5 組」。

三、測試方式

* 以真實 CSV 樣本資料驗證都更效益指標：文山區（老屋 12 筆／新
  成屋 5 筆，皆達門檻）算出 uplift_percent，並手動核對公式
  （(新成屋中位數－老屋中位數)／老屋中位數 × 100）結果一致。
* 由於真實樣本資料的交易日期集中在同一~2 個月區間，無法直接
  驗證跨年度的成長率計算，改為在大同區注入 12 筆日期分別落在
  「30 天前」與「400 天前」、單價分別為 70 萬／50 萬的合成資料，
  驗證 compute_trend() 正確算出 +40% 成長率（與手動預期完全相符）。
* 驗證 get_ranking('taipei')：確認排行榜依 uplift_percent 由高到
  低正確排序、且每一筆都符合最低樣本數門檻（3 個台北市行政區
  達標，其餘因樣本不足未列入）。
* 驗證 UR_AI_Market_Price_Ranking_Shortcode 完整渲染流程（含
  view 樣板）：補齊沙箱環境缺少的 WordPress 函式模擬（
  shortcode_atts／current_user_can／esc_html／esc_attr）後，
  確認渲染出的 HTML 含正確標題與排行榜表格內容。
* 重新執行 mp_harness.php，原本 8 項測試全數通過。

四、是否建議上正式站

建議更新。新增內容皆為既有查詢的衍生計算或獨立的新 shortcode，
未變更任何既有欄位的儲存邏輯或既有 shortcode 的既有行為，風險低。
排行榜 shortcode 需另外建立頁面加入才會顯示，不影響既有頁面。

---

## v1.8.6

更新日期
2026-07-08

版本定位

v1.8.5 新增了 [ur_ai_market_price_ranking] 排行榜短碼，並在後台
「總覽」頁的「Shortcode 使用說明」收合區塊補上了第 5 組說明；但
站方回報：實際管理「行情參考」功能時，是直接在後台「行情參考」
管理頁裡的「前台使用方式」卡片找短碼複製，而這張卡片當時只列出
舊的 [ur_ai_market_price]，沒有一併補上新的排行榜短碼，導致誤以為
新短碼沒有被加進後台說明。

修改內容

admin/pages/market-price-import-page.php
  - 「前台使用方式」卡片新增 [ur_ai_market_price_ranking] 短碼
    複製區塊與使用說明，與既有 [ur_ai_market_price] 並列。

ur-ai-assistant.php / readme.txt
  - 版本號 1.8.5 → 1.8.6。

是否建議上正式站

建議更新。純後台文件性質調整，不影響任何功能與資料。

---

## v1.8.7

更新日期
2026-07-08

版本定位

站方回報：資料庫累積超過 20 萬筆真實成交紀錄後，發現「常見區間」
的數字感覺偏保守，跟近期實際成交行情有落差；追查後確認根本原因
是全歷史統計會因為早期（通常較便宜）的成交紀錄佔比隨資料量累積
而越來越重，導致中位數落後於目前市場實際狀況——這正好呼應站方
另外提到「成交日期影響很大」的觀察。同時也請求在查詢結果加上
資料庫總筆數，讓使用者了解樣本母數規模。

一、設計決策

不把「近一年行情」拿來取代原本的全歷史統計，而是兩組數字並列
呈現：全歷史中位數提供長期參考基準，近一年中位數反映當下實際
行情，讓使用者自己判斷兩者落差、市場是否正在變動。這也直接
呼應「資訊呈現更多元化」的需求，比單純把舊數字換成新數字更有
參考價值。

二、主要修改

1. Repository
   - 原本的 compute_trend()（只回傳年成長率百分比）重構為
     compute_recent_window()：完整計算近一年的中位數、四分位距、
     樣本數，即使沒有前一年資料可比較成長率，只要近一年本身樣本
     數足夠，仍會回傳中位數／區間（change_percent 為 null）。
   - summarize_rows() 的 trend 欄位改名為 recent，語意從「只有
     成長率」擴充為「完整的近一年統計」。
2. Service
   - format_stats() 的近一年（recent）顯示邏輯調整為：近一年樣本數
     達最低門檻即可顯示中位數／區間；年成長率則額外需要前一年
     樣本數也達門檻才會顯示，兩者分開判斷，不再綁在一起。
   - get_comparison() 新增頂層 total_records 欄位（呼叫既有的
     count_all()），回傳資料庫累計總筆數（不限查詢條件）。
3. 前台
   - JS 新增 renderRecent()（取代原本的 renderTrend()），呈現
     「近一年行情」中位數、常見區間、樣本數，並在有成長率時附註
     年成長率百分比。
   - 查詢結果 meta 列新增「資料庫累計 N 筆歷史成交紀錄」（新增
     formatNumber() 千分位格式化小工具）。
   - CSS 新增 .ur-ai-market-price-recent-* 樣式（虛線框凸顯區塊）。

三、測試方式

由於真實樣本資料的交易日期集中在窄範圍，改為在南港區注入 12 筆
合成資料驗證：6 筆「30 天前、單價 90 萬」＋ 6 筆「400 天前、單價
60 萬」。驗證結果：近一年中位數（82.3 萬，混合了少量同樣落在近一年
窗口的真實資料）明顯高於全歷史中位數（66.0 萬），年成長率為正值，
正好demonstrate了本次修正想解決的問題——全歷史中位數確實會被
早期較低價的歷史資料拉低。另外驗證 total_records 與
service->count_all() 回傳值一致。重新執行 mp_harness.php，原本
8 項測試全數通過。

四、是否建議上正式站

建議更新。新增內容為既有查詢的衍生計算與新增欄位，未變更任何
既有欄位的儲存邏輯，風險低。

---

## v1.8.8

更新日期
2026-07-08

版本定位

站方回報：透過 LINE 分享連結，在 LINE 內建瀏覽器（in-app browser）
開啟時，行情參考的縣市／行政區下拉選單無法正常點選，需要另外用
「在瀏覽器中開啟」才能使用。這是社群 App 內建瀏覽器（LINE／FB／
IG 等）對原生 HTML 表單元件相容性不佳的已知普遍限制，並非本外掛
特有問題，但仍評估並實作了兩項改善。

一、根本原因與改善方向

1. 行政區下拉選單原本用 JavaScript 動態切換既有 `<option>` 的
   hidden／disabled 屬性來篩選（切換縣市時隱藏不相關的行政區）。
   `hidden` 並非 HTML 規範正式支援用在 `<option>` 上的寫法，部分
   內建瀏覽器對這種動態修改既有選項的方式相容性不佳，可能是點不開
   選單的原因之一。改為切換縣市時直接重建整個選項清單（只留下
   屬於該縣市的真實 `<option>` DOM 節點），相容性更穩定、也是更
   標準的做法。
2. 即使修正選單寫法，社群 App 內建瀏覽器本身的相容性問題仍難以
   完全預期与控制。新增偵測機制：透過 User-Agent 判斷頁面是否在
   LINE／Facebook／Instagram／WeChat 等內建瀏覽器中開啟，若是，
   在查詢區塊上方顯示提示，建議使用者改用外部瀏覽器開啟。

二、主要修改

public/assets/js/market-price.js
  - 移除 filterDistricts()（hidden／disabled 切換寫法），新增
    rebuildDistrictOptions()：預先讀取伺服器端渲染好的完整行政區
    清單（依縣市分組存成 JS 物件），每次切換縣市時清空並重新填入
    <select> 的選項。
  - 新增 detectInAppBrowser()：以 User-Agent 判斷 LINE／Facebook／
    Instagram／WeChat。
  - 新增 maybeShowInAppBrowserNotice()：偵測到內建瀏覽器時，於
    查詢區塊最上方插入提示文字。

includes/modules/market-price/class-ur-ai-market-price-module.php
  - i18n 新增 inapp_notice 提示文字。

public/assets/css/market-price.css
  - 新增 .ur-ai-market-price-inapp-notice 樣式（黃色提示框）。

三、測試方式

由於本機沙箱環境無法連線實際的 LINE／FB 內建瀏覽器，改用 Playwright
啟動真實 Chromium 引擎進行驗證：

* 建立最小化的獨立 HTML 測試頁（不依賴 WordPress），載入實際的
  market-price.js／CSS，模擬縣市／行政區下拉選單的 DOM 結構。
* 一般 Chrome User-Agent：驗證切換縣市後，行政區選單只留下該
  縣市的選項（確認台北市選項被移除、新北市選項出現），且全部
  `<option>` 皆不含 hidden 屬性（確認真的改用重建而非隱藏）；
  同時確認不會顯示內建瀏覽器提示。
* 模擬 LINE（iOS）User-Agent：確認提示文字正確顯示且包含
  「LINE」字樣。
* 模擬 Facebook（FB_IAB）User-Agent：確認提示文字正確顯示且包含
  「Facebook」字樣。
* 全部測試皆在真實 Chromium 引擎中執行並通過。
* 重新執行 mp_harness.php（PHP 端邏輯不受影響），原本 8 項測試
  全數通過。

四、是否建議上正式站

建議更新。純前台 JavaScript／CSS 呈現層調整，未變更任何後端查詢
邏輯或資料庫結構，風險低。

---

## v1.9.0

更新日期
2026-07-10

版本定位

FAQ 知識庫累積到一定規模後，新增「知識大考驗」互動挑戰功能：
從題庫隨機抽題、伺服器端計分、可留暱稱上榜，強化網站的趣味性與
黏著度，也讓使用者以更輕鬆的方式認識都市更新與危老重建常識。

一、設計重點

1. 防作弊優先：正確答案永遠只存在伺服器端的一次性暫存憑證
   （transient）中，前台開始挑戰時取得的題目資料完全不含正確
   答案，避免瀏覽器開發者工具就能看到答案；送出成績後立即刪除
   憑證，同一次挑戰無法重複送出洗分；每次抽到同一題時，四個
   選項的順序都會重新隨機打亂，避免使用者只記選項位置。
2. 內容比照 FAQ 知識庫既有的「AI 草稿必須人工審核」慣例：無論是
   手動新增、CSV 批次匯入或 AI 依 FAQ 出題產生的草稿，一律先落地
   為「停用／待審核」，審核通過後才會出現在前台題庫。
3. 排行榜規則：有留暱稱者，同一（正規化後）暱稱只保留最高分；
   匿名（暱稱留空）者每次都是獨立參與者，不與其他匿名紀錄合併，
   此為刻意設計並已在後台提供刪除功能因應不當暱稱等情況。
4. 節流防灌水：同一 IP 每小時可作答次數上限可由後台設定（僅記錄
   IP 的雜湊值，不儲存原始 IP）。

二、主要新增

1. 資料庫（DB_VERSION 1.2.0 → 1.3.0）
   - wp_ur_ai_quiz_questions：題庫（題目、四個選項、正確答案、
     解析、難度、分類、狀態、審核狀態、來源等）。
   - wp_ur_ai_quiz_attempts：作答紀錄／排行榜（暱稱、正規化暱稱
     鍵值、分數、答對題數、耗時、IP 雜湊等）。
2. 後端模組（includes/modules/quiz/）
   - class-ur-ai-quiz-settings.php：啟用開關、每次題數（預設 10，
     可調 5～30）、每小時作答上限（預設 3，可調 1～20）、標題。
   - class-ur-ai-quiz-repository.php／class-ur-ai-quiz-service.php：
     題庫 CRUD、隨機抽題、伺服器端計分、排行榜查詢與去重邏輯。
   - class-ur-ai-quiz-draft-service.php：依選定的 FAQ 呼叫 AI 產生
     選擇題草稿（沿用 FAQ 草稿服務「一律待審核」慣例）。
   - class-ur-ai-quiz-ajax.php：前台「開始挑戰」「送出作答」兩支
     AJAX action，皆驗證 public nonce。
   - class-ur-ai-quiz-admin.php：後台題庫 CRUD、審核（核准／退回）、
     CSV 批次匯入、AI 出題、設定儲存、排行榜紀錄刪除。
   - class-ur-ai-quiz-leaderboard-shortcode.php／
     class-ur-ai-quiz-module.php：shortcode 註冊、資產管理、
     admin_menu 掛載。
3. OpenAI 整合
   - class-ur-ai-openai-client.php 新增 generate_quiz_question()，
     使用獨立於 FAQ 問答的專屬系統提示詞與 filter hook，避免與
     FAQ 助理的語氣設定產生非預期耦合。
4. 前台
   - shortcode [ur_ai_quiz]：作答挑戰（開始挑戰／作答／留暱稱／
     結算四個狀態畫面）。
   - shortcode [ur_ai_quiz_leaderboard]：伺服器端渲染的排行榜
     （前三名附獎牌 emoji）。
   - public/assets/css/quiz.css／js/quiz.js：進度條、選項卡片、
     結算分數徽章等「挑戰賽」視覺呈現。
5. 後台頁面（admin/pages/quiz-page.php）
   - 統計卡片（題庫總數、可上場題數、待審核數、累計挑戰次數）。
   - 功能設定、AI 依 FAQ 出題（複選 FAQ）、CSV 批次匯入題目、
     新增／編輯題目表單、題庫列表（含審核／刪除）、排行榜管理
     （可刪除不當暱稱紀錄）、以及「使用說明」卡片（兩個短碼含
     複製按鈕，比照行情參考頁的既有慣例）。
6. 初始題庫內容
   - 依現有 FAQ 知識庫逐題核對出題，產出 45 題選擇題（涵蓋都更
     程序爭議救濟、更新會、權利變換估價、基本概念、協議合建、
     共同負擔、拆遷安置、風險控管信託、地主權益、AI 助理使用
     說明、海砂屋專題等分類），透過新增的 CSV 匯入功能一次匯入，
     全數為「停用／待審核」狀態，待站方審核後再逐一啟用。

三、測試方式

撰寫 PHP + SQLite 驗證腳本（quiz_harness.php），要求實際的
UR_AI_Quiz_Admin::parse_quiz_csv()、UR_AI_Quiz_Repository、
UR_AI_Quiz_Service 類別，涵蓋 11 個驗證情境並全數通過：
CSV 正確解析 45 列格式完整的題目；45 題全數以「停用／待審核」
狀態建立、審核前可上場題數為 0；核准 20 題後可上場題數正確變為
20；start_attempt() 依設定題數抽題，且回傳的公開資料只包含
uid／question／options 三個欄位，序列化後的 JSON 內容不含任何
"correct" 字樣；滿分作答情境下伺服器端計分正確；同一 token 重複
送出會被拒絕；暱稱正規化後（去除前後空白、大小寫不敏感）同一人
只保留最高分；暱稱留空的匿名紀錄不互相合併；超過每小時節流上限
會被拒絕；排行榜依分數由高到低排序；後台刪除題目／刪除排行榜
紀錄皆正常運作。

四、是否建議上正式站

建議更新，但啟用前請先完成內容審核：新功能預設關閉
（enabled=0），且本次新增的 45 題初始題庫一律為「停用／待審核」
狀態，不會自動出現在前台，需站方於後台「知識大考驗」頁逐一審核
（或批次核准）後再啟用功能設定與放置短碼。

---

## v1.10.0

更新日期
2026-07-10

版本定位

外掛累積到 12 個模組後，做了一次全面盤點，發現熱門問題、相關頁面
推薦、問答紀錄三個模組其實已經各自算好不少有用的統計資料（例如
「熱門但沒建 FAQ」「推薦頁面點擊率」「AI 常被問但沒 FAQ 的問題」），
只是沒有被整理成後台看得到、用得上的畫面。這個版本把這幾組既有
資料串起來，新增三個純讀取的分析畫面，不新增任何資料表，也不改變
任何既有的計分或比對邏輯。

一、主要新增

1. 「內容缺口」總覽頁（新增後台選單項目，位於回饋分析之後）
   - 整合熱門問題模組既有的 get_high_click_unlinked_questions()
     （點擊 ≥5 次但未連結 FAQ 的問題）。
   - 新增 UR_AI_Log_Repository::get_frequent_ai_questions()：依完全
     相同文字分組，找出「被問 2 次以上、每次都落到 AI 回答、且尚未
     轉過 FAQ 草稿」的問題。
   - 新增 UR_AI_Log_Repository::get_not_helpful_faq_summary()：依
     faq_id 分組統計「沒幫助」回饋次數，找出最需要優先改寫的 FAQ。
   - 三份清單皆附上導向對應既有管理頁（熱門問題／問答紀錄／FAQ
     知識庫）的連結，本頁本身不提供任何新增或修改資料的操作。
2. 相關頁面推薦「健檢」清單（新增於既有「相關頁面推薦」管理頁）
   - 沿用模組既有的 get_low_ctr_pages()（曝光 ≥20 且 CTR < 3%）與
     get_shown_no_click_pages()（有曝光但零點擊），加上實際頁面清單
     與快速編輯連結，取代原本只有數字、看不到明細的「需觀察」統計。
3. API 花費估算（新增於既有「問答紀錄」管理頁）
   - 新增 UR_AI_Log_Repository::get_token_usage_by_model()：依模型
     分組統計 AI 回答的 token 用量（僅計入 answer_source='ai' 的
     紀錄）。
   - 新增「功能設定」欄位「每百萬 Tokens 預估費用（美元）」（預設
     0.5，站方需依實際使用的模型定價自行填寫），供
     UR_AI_Log_Service::get_cost_estimate() 換算成近 30 天與全部
     歷史的估算花費，明確標示為粗估、非 OpenAI 官方帳單金額。

二、設計原則

三項功能都是「把既有服務層方法組合成畫面」，沒有新增資料表、沒有
改變任何既有的比對／計分／匯入邏輯，風險侷限在新增的查詢方法與
頁面本身。

三、測試方式

撰寫 PHP + SQLite 驗證腳本（insights_harness.php），要求實際的
UR_AI_Log_Repository／UR_AI_Log_Service／UR_AI_Settings 類別，涵蓋
5 個情境並全數通過：重複問題比對正確排除「已轉 FAQ」與「未達最低
次數」的項目；沒幫助統計正確依 faq_id 分組且排除「有幫助」的回饋；
近 30 天與全部歷史的 token 統計正確依建立時間篩選（驗證中刻意插入
一筆 40 天前的紀錄，確認被 30 天窗口排除、但仍計入全部歷史）；
依模型分組正確排除非 AI（FAQ 命中）的紀錄；費率設定的上下限（0～
1000）正確生效。

四、是否建議上正式站

建議更新。純新增的讀取型分析頁面與既有服務層的新查詢方法，未修改
任何資料表結構、既有查詢邏輯或前台行為，風險低。

---

## v1.11.0

更新日期
2026-07-12

版本定位

修正使用者回報的「知識庫批次操作，全選後只啟用 1 筆」問題。實際
排查（含 Playwright 重現後台表單真實渲染結果、獨立驗證後端批次
邏輯）確認前後端程式碼皆正確運作：「全選」checkbox 本來就只會勾選
畫面上目前這一頁看得到的項目，這是分頁／篩選情境下的正常行為，
並非程式錯誤。這個版本新增「跨頁全選」功能，讓使用者在符合篩選
條件的資料筆數多於單頁筆數時，可以選擇把批次操作套用到全部符合
條件的資料，而不只是目前頁面看到的項目。

一、主要新增

1. 「跨頁全選」提示列（同步套用於 FAQ 知識庫、熱門問題、相關頁面
   推薦三個有批次操作的清單頁）
   - 勾選「全選」時，若符合目前篩選條件的總筆數多於本頁筆數，會
     顯示提示列，說明本頁筆數與符合條件總筆數，並提供「選取全部」
     ／「僅本頁」兩個按鈕。
   - 點擊「選取全部」後，批次操作（啟用／停用／刪除等）會套用到
     全部符合目前篩選條件的資料，不受限於目前頁面顯示的筆數。
   - 手動取消勾選任何一筆項目時，會自動取消「跨頁全選」狀態，
     避免批次操作誤套用到超出畫面所見的資料。
2. 後端新增 query_ids($args) 方法（FAQ／熱門問題／相關頁面推薦三個
   模組的 Repository／Service 皆新增），沿用既有的 build_where()
   篩選邏輯，回傳全部符合條件、未分頁的 ID 清單。三個模組的
   handle_bulk() 皆新增判斷：當請求帶有 select_all_matching 旗標時，
   改以篩選條件重新查詢全部符合的 ID，取代瀏覽器實際送出的
   （可能只有單頁筆數的）勾選清單。

二、設計原則

「全選只選當頁」维持原本前端行為不變（避免改變使用者熟悉的既有
互動），跨頁全選是額外的、需要使用者主動確認的選項，且完全由
後端依當下的篩選條件重新查詢決定範圍，不信任前端送出的 ID 清單
以外的任何跨頁假設。

三、測試方式

排查階段：以 Playwright 重現「全選→批次啟用」的真實流程，分別對
（a）最小化 3 列重現、（b）以 PHP 樣板渲染引擎輸出真實
faq-page.php 的原始 HTML 並搭配真實 jQuery、真實 admin.js 模擬
使用者操作，兩者皆確認 10 筆全數正確被勾選並會送出；另以獨立的
PHP + SQLite 後端邏輯驗證腳本模擬 10 筆 $_POST，確認
handle_bulk() 正確啟用全部 10 筆。確認前後端皆無錯誤後，判定為
分頁／篩選下的預期行為，並據此設計跨頁全選功能。

新功能驗證：針對 FAQ／熱門問題／相關頁面推薦三個模組，分別撰寫
PHP + SQLite 驗證腳本，情境涵蓋：(1) query_ids() 依篩選條件正確
回傳全部符合條件、不分頁的 ID；(2) 在 25 筆資料（20 筆分類 A、
5 筆分類 B）情境下，模擬使用者只勾選分類 A 頁面 1 的前 10 筆，
但送出 select_all_matching=1，驗證批次啟用正確套用到全部 20 筆
分類 A 的資料，且不影響分類 B；(3) 未帶 select_all_matching 時，
批次操作仍只影響瀏覽器實際送出的 ID，確認原本行為未被破壞。全部
情境皆通過。

四、是否建議上正式站

建議更新。新增功能為選擇性（需使用者主動點擊「選取全部」才會
套用），未改變任何既有批次操作的預設行為，且已針對三個模組個別
驗證跨頁全選的正確性與篩選隔離性。

---

## v1.12.0

更新日期
2026-07-13

版本定位

延續上一版系統盤點的後續建議，這個版本處理其中兩項：把「內容缺口」
頁從純讀取清單，補上可直接執行的「轉 FAQ 草稿」操作；並把「知識
大考驗」既有但從未串接的作答說明（explanation）與題目來源 FAQ
（source_faq_id）欄位，接上前台結算畫面，讓使用者答錯時能立即看到
正確答案、說明文字，以及相關 FAQ 的題目與分類，把測驗和知識庫串成
一個學習迴圈。

一、主要新增

1. 內容缺口頁「一鍵轉 FAQ 草稿」
   - 「熱門但尚未建立 FAQ 的問題」清單：每列新增「轉 FAQ 草稿」
     按鈕，直接呼叫熱門問題模組既有的單筆轉換動作
     （convert_popular_question_to_faq），完成後導向熱門問題頁。
   - 「重複被問、一直靠 AI 回答的問題」清單：新增
     UR_AI_Log_Repository::get_frequent_ai_questions() 回傳欄位
     sample_log_id（該組重複問題中最新一筆的紀錄 ID），每列新增
     「轉 FAQ 草稿」按鈕，呼叫問答紀錄模組既有的單筆轉換動作
     （convert_log_to_faq），完成後導向問答紀錄頁。
   - 兩者皆沿用各自模組既有、原本就用在管理頁本身的轉換邏輯與草稿
     機制，草稿仍需人工審閱後才會啟用，內容缺口頁本身不新增任何
     資料表或直接寫入邏輯。
2. 知識大考驗答題結算新增「作答回顧」
   - 送出成績後，除了原有的總分數，新增逐題回顧：標示每題對／錯、
     顯示正確答案文字、顯示題目本身既有的「詳解」欄位內容
     （explanation，題庫管理頁原本就能填寫，但先前從未顯示給
     使用者）。
   - 答錯且該題有設定來源 FAQ（source_faq_id，AI 依 FAQ 出題時
     會自動記錄）時，額外顯示該 FAQ 的分類與問題文字，提示使用者
     可至「都更AI助理」查詢完整說明；若來源 FAQ 已停用或不存在，
     不會顯示此提示，避免導向失效或不完整的內容。
   - 正確答案文字與詳解等原本只存在伺服器端暫存憑證的資料，僅在
     使用者送出作答、由伺服器完成計分之後才會回傳，作答過程中
     （尚未送出前）看到的題目資料仍不含任何正確答案相關欄位，
     維持原本「防止開發者工具偷看答案」的安全設計不變。

二、設計原則

兩項功能都是「把既有資料/既有動作接上原本沒接的畫面」：內容缺口
頁的轉換按鈕重用模組自己既有的單筆轉換邏輯，沒有新增轉換規則；
知識大考驗的作答回顧只是把資料庫欄位（explanation、
source_faq_id）與既有的「答案只在伺服器端」安全機制結合後回傳，
沒有改變抽題、洗牌選項或計分邏輯本身。

三、測試方式

內容缺口：完整 php -l 語法檢查涵蓋修改的頁面與 Repository。

知識大考驗：撰寫 PHP + SQLite 驗證腳本，情境涵蓋（1）作答開始時
回傳給前端的題目資料不含 correct／explanation／source_faq_id 任何
一個答案相關欄位；（2）送出作答後，答錯且來源 FAQ 為啟用狀態的
題目，正確顯示對應 FAQ 的分類與問題文字；（3）答錯但來源 FAQ 為
停用狀態的題目，正確不顯示任何 FAQ 提示；（4）答對的題目正確標示
為答對，且不附加 FAQ 提示；（5）答錯但題目本身沒有設定來源 FAQ
時，僅顯示詳解，不顯示 FAQ 提示。五個情境全數通過。

四、是否建議上正式站

建議更新。內容缺口的轉換按鈕重用既有模組動作與草稿審核機制；
知識大考驗的作答回顧不影響抽題、計分或正確答案的保密機制，僅在
使用者已送出、伺服器已完成計分後才回傳說明與 FAQ 提示，風險低。

---

## v1.13.0

更新日期
2026-07-13

版本定位

外掛累積到 12 個後台選單項目後，側邊選單變成一長串扁平清單，不容易
一眼看出功能分類。這個版本把彼此相關的子項目合併成同一個選單入口，
內部改用頁面內的分頁籤切換，把可見的選單項目從 12 項減少到 7 項，
不改變任何頁面本身的網址、資料查詢邏輯或既有的表單／轉換動作。

一、主要調整

1. 選單合併為 7 項：
   - 總覽（不變）
   - 功能設定（不變）
   - **知識庫管理**（原本的 FAQ 知識庫／熱門問題／相關頁面推薦／
     內容缺口 4 個選單項目合併於此，分頁籤依序切換）
   - **問答數據分析**（原本的問答紀錄／回饋分析 2 個選單項目合併
     於此）
   - **都更分回試算**（原本的試算名單／試算器設定 2 個選單項目
     合併於此）
   - 行情參考（不變）
   - 知識大考驗（不變）
2. 實作方式：採用 WordPress 內建的 `remove_submenu_page()`，只把
   項目從側邊選單的顯示清單移除，網址、權限檢查與頁面本身的
   render callback 完全不受影響；被合併的 4 個模組頁面最上方加上
   一列分頁籤（真實整頁連結，非 JS 面板切換），可以在同一組內互相
   切換。分頁籤外觀比照既有的 `.ur-ai-admin-tab` 樣式，但改用不同
   的 class 名稱（`.ur-ai-admin-group-tab`），避免被既有專供 JS
   面板切換用的分頁籤點擊監聽攔截、導致連結無法正常跳轉。
3. 所有既有的跨頁連結（例如內容缺口頁連到熱門問題頁、問答紀錄頁的
   轉換動作）都是依原本的頁面 slug 導向，因此完全不需要修改。

二、設計原則

只調整「側邊選單看得到哪些項目」，不觸碰任何一支既有頁面檔案的
查詢、分頁、批次操作或轉換邏輯，也不新增任何資料表。分組完全依照
既有頁面本來就有的關聯性（同一個模組、或同樣圍繞 FAQ 知識庫內容在
維護），不強行湊出分類。

三、測試方式

撰寫 PHP 驗證腳本，模擬 WordPress 的 `add_submenu_page()` /
`remove_submenu_page()` 呼叫，實際執行選單註冊邏輯後確認：原本
12 個選單項目中，剛好有 7 個維持在可見清單，5 個被移出可見清單但
仍完整註冊（代表 `admin.php?page=X` 直接連結與既有跳轉仍會正常
運作）；同時驗證新的分頁籤渲染方法會依目前頁面正確標示啟用中的
項目。`remove_submenu_page()` 只影響側邊選單的渲染陣列、不影響頁面
本身可否透過網址存取，是 WordPress 外掛社群常見且有文件記載的作法。

四、是否建議上正式站

建議更新。純選單顯示層調整，未修改任何資料查詢、表單或權限邏輯，
風險低；但建議上線後實際登入後台走一次每個合併後的分頁籤，確認
外觀與連結符合預期。

---

## v1.13.1

更新日期
2026-07-13

版本定位

修正 v1.13.0 上線後立刻回報的問題：熱門問題、相關頁面推薦、內容
缺口、回饋分析、試算器設定這幾個被合併進分頁籤的頁面，直接用網址
進入時顯示「很抱歉，目前的登入身分沒有存取這個頁面的權限」，即使
是系統管理員帳號也一樣。

原因與修正

v1.13.0 用 `remove_submenu_page()` 把這幾個項目從側邊選單移除，
但這個函式會把項目從 WordPress 內部的 `$submenu` 全域陣列整個
移除。WordPress 自己判斷「目前登入身分能不能存取
`admin.php?page=X`」的內部邏輯（`user_can_access_admin_page()`），
正是靠查詢這個陣列來確認頁面已註冊、以及需要什麼權限；一旦被移除，
WordPress 會判定這個頁面「未登記」而直接擋下存取，顯示權限不足的
訊息——這不是真的權限設定問題，而是選單移除方式用錯了。

修正方式改為：完整保留所有頁面的 `add_submenu_page()` 註冊（讓
WordPress 內部的權限判斷表維持完整），另外用純 CSS（`admin_head`
掛鉤輸出 `display:none`）只在畫面上把這幾個項目從側邊選單藏起來。
這是純顯示層的處理，不會影響 WordPress 判斷頁面是否可存取。

測試方式

撰寫 PHP 驗證腳本，簡化重現 WordPress 核心
`user_can_access_admin_page()` 的查表邏輯，針對這次修正逐一確認：
（1）`remove_submenu_page()` 完全沒有被呼叫；（2）原本 12 個選單
項目對應的 10 個不重複 slug 全數仍完整註冊在 `$submenu` 陣列中；
（3）針對回報問題的 5 個頁面，模擬的權限查表邏輯全數正確授權存取
（重現並確認修正了回報的錯誤情境）；（4）新的 CSS 隱藏方法仍會對
這 5 個頁面輸出對應的隱藏樣式，確認側邊選單畫面上不會因為修正而
重新冒出來。全部情境通過。

是否建議上正式站

建議立即更新，修正 v1.13.0 造成的存取問題。

---

## v1.14.0

更新日期
2026-07-13

版本定位

這個外掛目前沒有上架 wordpress.org，過去每次更新都要手動把檔案
上傳到站台，容易忘記或漏傳檔案。這個版本加入自動更新檢查功能，讓
後台「外掛」頁可以像 wordpress.org 上架的外掛一樣，自動看到「有
新版本可更新」並一鍵更新，不需要再手動上傳。

一、主要新增

1. 內建 Plugin Update Checker（第三方函式庫，MIT 授權，版本 v5.7，
   完整原始碼與授權聲明皆隨外掛附上於 vendor/plugin-update-checker/
   目錄）：這是專門給沒有上架 wordpress.org 的外掛使用的更新檢查
   函式庫，讓後台更新體驗與正式上架的外掛一致。
2. 新增 UR_AI_Updater 類別，於外掛啟動時初始化更新檢查器，指向
   GitHub repo（Chienfu168/FU-AI-UR），並設定只採用 Release 附加
   檔案中副檔名為 .zip 的檔案（避免誤用 GitHub 自動產生、資料夾
   結構不符的原始碼壓縮檔）。
3. 主檔新增 `Update URI` 標頭，避免 WordPress 核心誤以為這個外掛
   有對應的 wordpress.org 上架版本而執行不必要的檢查。
4. 新增 .github/workflows/release.yml：每次 push 一個 vX.Y.Z 格式
   的 git tag，會自動打包 ur-ai-assistant/ 資料夾（保留正確的頂層
   資料夾名稱）成 zip，並自動建立對應的 GitHub Release、附上這個
   zip，供更新檢查器讀取。往後的發布流程只需要在合併版本後多下
   一個 tag（例如 `git tag v1.14.0 && git push origin v1.14.0`），
   不需要在 GitHub 網頁上手動建立 Release。

二、設計原則

外掛實際檔案放在 repo 的 ur-ai-assistant/ 子目錄下（repo 根目錄還有
其他非外掛內容），因此不能直接使用 GitHub 對 tag 自動產生的原始碼
壓縮檔（那會把整個 repo 根目錄結構包進去，資料夾名稱與內容都不符
預期）。改用「Release 附加檔案」模式，由 GitHub Actions 在 tag
建立時自動打包出結構正確的 zip，讓更新檢查器抓到的一定是可以直接
覆蓋安裝的正確內容。

三、測試方式

撰寫 PHP 驗證腳本，直接載入實際 vendor 進來的 Plugin Update Checker
函式庫（非簡化模擬），確認：（1）UR_AI_Updater::init() 可以在
沒有真實 WordPress 環境、只提供必要函式與常數樁的情況下正常執行完
畢，不噴出未預期的錯誤；（2）確實建立了一個對應到本外掛 slug 的
update checker 實例；（3）該實例指向正確的 GitHub repo 網址；
（4）enableReleaseAssets() 確實被呼叫，且篩選條件正確設定為只採用
副檔名 .zip 的附加檔案。全部情境通過。

需要說明的限制：這個環境沒有真實的 WordPress 站台，也無法實際觸發
一次 GitHub Actions tag push 後的完整流程，因此「後台外掛頁實際顯示
有新版本」與「點擊立即更新後確實正確覆蓋安裝」這兩個步驟，仍需要在
真實站台上，於第一次實際發布新版本時親自確認一次。

四、上線前需要你完成的兩件事

1. 把這個 GitHub repo（Chienfu168/FU-AI-UR）從私有改成公開：GitHub
   repo 設定頁 → Danger Zone → Change repository visibility。更新
   檢查器需要能讀取這個 repo 的 Release 資訊。
2. 把外掛更新到 v1.14.0 之後，之後每次發布新版本，除了合併版本更新
   的 PR，還要多下一個對應的 git tag 並 push 到 GitHub（例如
   `git tag v1.14.0 && git push origin v1.14.0`），GitHub Actions
   才會自動建立這一版的 Release 與 zip。

五、是否建議上正式站

建議更新，但上線後第一次實際發布新版本時，請留意後台「外掛」頁是否
正確顯示更新通知，並實際完整走過一次「點擊更新」的流程，確認外掛
檔案正確被覆蓋且功能正常，之後的版本就會是同樣、已驗證過的流程。

---

## v1.14.1

更新日期
2026-07-13

版本定位

小修正：知識大考驗後台「題庫」清單的批次操作，全選後套用「核准
上線」等動作，一樣只會作用在目前這一頁看到的題目，跟先前 FAQ 知識庫
回報過的情況相同——這是分頁下的正常行為，不是程式錯誤，但題庫清單
先前還沒補上跨頁全選功能。這個版本把 v1.11.0 已經套用在 FAQ 知識庫、
熱門問題、相關頁面推薦的「跨頁全選」機制，同步套用到題庫清單。

一、主要新增

1. 題庫清單新增「跨頁全選」提示列：勾選「全選」時，若符合目前篩選
   條件（狀態／審核狀態／分類／搜尋）的題目總數多於本頁筆數，會
   顯示提示列，可選擇將批次操作（核准上線／退回／刪除）套用到
   全部符合條件的題目，而不只是目前頁面看到的項目，與其他清單頁
   的操作體驗一致。
2. 後端新增 UR_AI_Quiz_Repository::query_question_ids($args)／
   UR_AI_Quiz_Service::query_question_ids($args)，沿用既有的
   build_question_where() 篩選邏輯；UR_AI_Quiz_Admin::handle_bulk_
   questions() 新增判斷：帶有 select_all_matching 旗標時，改以
   篩選條件重新查詢全部符合的題目 ID。
3. 頁面模板重用既有的篩選狀態隱藏欄位（q_status／q_review_status／
   q_category／q_s，題庫清單原本就有），只新增全選旗標與提示列
   本身，沒有重複的隱藏欄位。

二、測試方式

撰寫 PHP + SQLite 驗證腳本，情境比照先前三個模組：25 筆待審題目
（20 筆分類 A、5 筆分類 B），模擬只勾選分類 A 頁面 1 的前 10 筆但
送出 select_all_matching=1，驗證批次核准正確套用到全部 20 筆分類 A
的題目且不影響分類 B；未帶 select_all_matching 時，批次操作仍只
影響瀏覽器實際送出的 ID。全部情境通過。

三、是否建議上正式站

建議更新。新增功能為選擇性、不改變既有批次操作預設行為，做法與
已上線驗證過的 FAQ／熱門問題／相關頁面推薦跨頁全選完全一致。

---

## v1.14.2

更新日期
2026-07-13

版本定位

緊急修正 v1.14.0 上線後、實際到正式站更新到 v1.14.1 時才觸發的
問題：後台「外掛」頁點擊「檢查更新」（或背景排程自動檢查更新時）
會出現「網站錯誤」。使用者提供的錯誤紀錄顯示：

```
PHP Fatal error: Uncaught Error: Class "Parsedown" not found
in .../vendor/plugin-update-checker/Puc/v5p7/Vcs/GitHubApi.php:140
```

原因與修正

vendor 進來的 Plugin Update Checker 函式庫，其內部用來把 GitHub
Release 說明文字（Markdown）轉成 HTML 顯示在「查看版本詳情」彈窗
用的 Parsedown 函式庫，是放在函式庫自己的 vendor/ 子目錄底下
（vendor/plugin-update-checker/vendor/Parsedown.php、
ParsedownModern.php、PucReadmeParser.php）。v1.14.0 vendor 進來時
只複製了 Puc/、css/、js/、languages/ 等目錄與少數幾支主要檔案，
遺漏了這個巢狀的 vendor/ 子目錄，導致只要程式碼路徑走到需要顯示
Release 說明文字（例如檢查更新、或背景排程檢查）就會因為找不到
Parsedown 這個類別而整個網站噴出 PHP Fatal error。

已補齊遺漏的三支檔案，並用外掛實際引用的 Autoloader 類別重新比對
完整原始函式庫（fetch 自官方來源）的執行期檔案清單，確認目前 vendor
進來的內容與官方版本完全一致，沒有其他遺漏。

測試方式

撰寫 PHP 驗證腳本，直接重現原始錯誤發生的確切呼叫路徑：載入實際
vendor 進來的 Autoloader，確認 Parsedown／PucReadmeParser 類別現在
都能正常自動載入，並實際呼叫
`Parsedown::instance()->text(...)`（即 GitHubApi.php 第 140 行、
使用者回報錯誤紀錄裡崩潰的那一行）確認能正常把 Markdown 轉成 HTML
而不再噴錯。另外重新執行 v1.14.0 既有的 UR_AI_Updater::init() 驗證
腳本，確認這次修正沒有影響原本已驗證過的初始化邏輯。全部情境通過。

是否建議上正式站

緊急建議立即更新，修正會讓後台「外掛」頁噴出網站錯誤的問題。

---

## v1.15.0

更新日期
2026-07-13

版本定位

行情參考 CSV 匯入原本強制要求先手動選擇縣市，選錯（跟資料內容不符）
就整批拒絕匯入，造成操作上的摩擦。這個版本把縣市選擇改成非必要：
留空時直接採用系統既有的自動偵測結果匯入，不需要再事先選好才能
上傳。

一、主要調整

1. 匯入表單「檔案所屬縣市」新增「自動偵測（依資料內容判斷）」為
   預設選項：留空時，系統會直接採用既有的
   UR_AI_Market_Price_Import_Service::detect_city_from_rows()
   （依「鄉鎮市區」欄位多數決反推縣市）判斷結果匯入，不再要求
   一定要先選對縣市。
2. 若管理者仍手動選擇特定縣市，維持原本的交叉驗證行為：與自動偵測
   結果不符時，仍會整批拒絕匯入並提示，避免手滑選錯縣市却沒發現、
   資料被錯誤標記後污染資料庫。
3. 匯入成功訊息新增顯示「已匯入為『OO市』」，讓使用者能確認自動
   偵測（或手動選擇）的結果正確無誤。

二、設計原則

自動偵測邏輯本身在此之前就已經存在（用來驗證手動選擇是否正確），
這次調整只是把它從「純驗證用途」提升為「留空時的主要判斷依據」，
沒有新增偵測邏輯本身，風險侷限在調整既有邏輯的使用方式。台北市
（12 區）與新北市（29 區）的行政區名稱目前完全不重複，偵測結果
可靠；未來如需擴充支援雙北以外的縣市（尤其是同樣以「區」命名的
直轄市，例如台中、台南、高雄），行政區名稱有機會撞名，屆時需要
額外設計「無法唯一判斷時退回讓使用者手動確認」的機制，不能只靠
擴充對照表就直接沿用目前的自動判斷邏輯。

三、測試方式

撰寫 PHP + SQLite 驗證腳本，直接呼叫實際的
UR_AI_Market_Price_Import_Service::import_from_csv()，涵蓋 5 個
情境：（1）留空縣市、上傳台北市行政區資料，正確自動判斷並匯入為
台北市；（2）留空縣市、上傳新北市行政區資料，正確自動判斷並匯入
為新北市；（3）手動選擇的縣市與自動偵測結果相符時仍正常匯入（維持
向下相容）；（4）手動選擇的縣市與自動偵測結果不符時，仍正確拒絕
匯入且不寫入任何資料列（既有安全機制未被破壞）；（5）行政區名稱
無法對應任何已知縣市時，不論有無手動選擇縣市，皆正確拒絕匯入。
全部情境通過。

四、是否建議上正式站

建議更新。純調整既有匯入邏輯的使用順序（留空時把驗證結果直接當作
匯入依據），未新增資料表、未改變偵測演算法本身，且手動選擇縣市時
的既有安全機制完全保留，風險低。

---

## v1.16.0

更新日期
2026-07-13

版本定位

行情參考前台查詢新增「地址（選填）」欄位，輸入地址後，統計結果裡的
「參考案例」會優先顯示同路段的實際成交案例，讓使用者更容易對照到
跟自己房子相近位置的行情，而不是整個行政區隨機挑出的案例。

背景與限制

內政部實價登錄開放資料本身不含門牌號（法規要求去識別化），本外掛
既有的 extract_road_section()（用於產生去識別化參考案例的地點描述）
也刻意只保留「路／街／大道＋段」層級。因此這個版本**不是**真正的
地理座標最近距離搜尋（沒有經緯度可用，也不打算另外串接地理編碼
服務），而是「同路段比對」：把使用者輸入的地址解析出路名＋段，
優先從資料庫裡地址含有相同路段的紀錄中，依現有的四分位數選例邏輯
挑出參考案例；完全沒有比對到相同路段時，會自動退回原本「整個行政區
隨機挑代表案例」的邏輯，確保有輸入地址時仍然看得到參考案例，不會
因為找不到同路段資料就整組空白。

一、主要新增

1. 新增 UR_AI_Market_Price_Repository::extract_road_hint()（public
   static）：把既有用於格式化參考案例地點描述的路段擷取邏輯抽出來
   共用，同時支援「解析完整原始地址」與「解析使用者輸入地址」兩種
   情境。
2. pick_examples() 新增選填的 $road_hint 參數：有帶路段提示且資料庫
   中有比對到的紀錄時，只從比對到的子集合裡挑選例子；比對不到時
   自動退回原本的全體選例邏輯。經由 summarize_rows()／
   get_price_stats()／get_price_stats_pair() 一路往下傳遞。
3. UR_AI_Market_Price_Service::get_comparison() 新增選填的
   'address' 參數，解析成路段提示後傳入 Repository；未輸入地址時
   行為與之前完全相同。
4. 前台查詢表單新增「地址（選填）」文字輸入欄位，並更新 AJAX 請求
   與後端 handle_query() 一併傳遞。

二、設計原則

只影響「參考案例」要挑選哪幾筆來顯示，完全不影響中位數／平均數／
常見區間／樣本數等既有統計數字本身的計算邏輯與 SQL 查詢條件，風險
侷限在選例邏輯本身；留空地址時的行為與升級前完全一致。

三、測試方式

撰寫 PHP + SQLite 驗證腳本，直接呼叫實際的
UR_AI_Market_Price_Service::get_comparison()，情境涵蓋：（1）不輸入
地址時的基準結果（6 筆資料、樣本數達標、可看到參考案例）；（2）輸入
地址比對到其中 3 筆同路段資料時，統計數字（樣本數、中位數）與基準
完全相同，但回傳的參考案例全數位於該路段；（3）輸入地址但資料庫裡
沒有比對到任何相同路段紀錄時，正確退回基準的選例邏輯，樣本數與
參考案例筆數皆與基準一致，不會因此看不到任何案例。全部情境通過。

四、是否建議上正式站

建議更新。純新增選填欄位與參考案例的選例邏輯調整，未新增資料表、
未改變任何既有統計數字的計算方式，也沒有新增外部服務依賴，風險低。

---

## v1.17.0

更新日期
2026-07-13

版本定位

行情參考的資料更新原本完全仰賴管理者手動操作：到內政部不動產交易
實價查詢服務網站下載、用 Excel 另存成 CSV、再回後台上傳。這一版
新增「自動抓取（內政部開放資料）」，把下載與解壓兩個手動步驟自動化，
仍由管理者在後台按鈕觸發（半自動），不做無人值守的排程，避免長時間
背景下載在共用主機上造成不可預期的資源占用或逾時。

一、主要新增

1. 新增 UR_AI_Market_Price_Remote_Fetch_Service：
   - get_available_seasons()：依目前日期回推最近數個季別（民國年＋
     S1～S4），供後台下拉選單選擇；'current' 對應政府每月 1/11/21
     更新的「本期」靜態資料。
   - fetch_and_import()：以 wp_remote_get() 下載政府 zip、用
     ZipArchive 只解壓雙北兩份主檔（a_lvr_land_a.csv／
     f_lvr_land_a.csv），直接交給既有的
     UR_AI_Market_Price_Import_Service::import_from_csv() 匯入——
     沿用完全相同的欄位解析、特殊關係排除、去重邏輯，不另外重寫一份
     清洗規則。
   - get_fetch_log() / get_log_entry()：記錄每個季別上次抓取的時間與
     新增／略過筆數，供後台顯示「是否已抓取過」。
2. 後台「行情參考」頁新增「自動抓取（內政部開放資料）」卡片：季別
   下拉選單（已抓取過的季別會標示上次抓取時間）、「立即抓取並匯入」
   按鈕，以及抓取紀錄一覽表。
3. UR_AI_Market_Price_Module 新增 FETCH_ACTION，走既有
   admin-post／nonce／capability 檢查模式，與既有的 CSV 上傳、設定
   儲存動作一致。

二、設計原則

- 是否「已抓取過」只作提示，不做強制阻擋：政府資料在同一季別內會
  隨遲繳登記、事後更正持續增補，允許重複抓取同一季別仍有意義；實際
  防止重複資料是由 source_record_id 唯一索引在資料庫層把關，重複
  抓取只會讓新增筆數變成 0（全數判定為重複），不會產生重複紀錄。
- 沒有引入 WP-Cron 背景排程：下載＋解壓＋匯入雙北資料的耗時在部分
  主機上可能達數十秒甚至更久，改成無人值守排程有逾時、記憶體限制、
  以及低流量網站 WP-Cron 觸發不準時等風險；先以管理者按鈕觸發的
  半自動方式上線，待穩定運作後再視需求評估是否加上排程。
- 未安裝 PHP zip 擴充功能（ZipArchive）時會明確提示改用手動上傳，
  不會靜默失敗。

三、測試方式

撰寫 PHP + SQLite 驗證腳本，將 wp_remote_get() mock 為回傳本地建立
的 zip 測試檔（模擬政府端點的實際回應格式：根目錄下的
{代碼}_lvr_land_a.csv，含中文表頭列＋英文表頭列＋資料列），涵蓋：
（1）季別清單產生與格式驗證；（2）首次抓取正確匯入雙北資料、忽略
不支援的縣市檔案；（3）抓取紀錄正確寫入；（4）重複抓取同一季別
不會產生重複資料，全數正確判定為 duplicate；（5）特殊關係交易正確
存入並標記，不被靜默捨棄。全部情境通過。

四、是否建議上正式站

建議更新。核心匯入邏輯完全複用既有且已驗證過的
import_from_csv()，新增部分是下載／解壓／後台 UI，屬於外掛內部
自足功能，未新增對外部服務的長期依賴（僅在管理者主動按下按鈕時
才對外連線一次）。

---

## v1.18.0

更新日期
2026-07-13

版本定位

這是多產業擴充架構規劃（見 docs/industry-expansion-architecture.md）的
Phase 1：把現有寫死在程式碼裡的「都更危老」預設文案與模組預設狀態，
抽成可依產業別切換的設定資料。**本版本純屬結構重構，不改變任何現有
安裝的可見行為**——目前仍然只有都更危老一個產業別，也是所有現有網站
唯一會用到的選項。

一、主要新增

1. 新增 UR_AI_Industry_Profiles（includes/core/class-ur-ai-industry-profiles.php）：
   - get_all() / get($key) / get_active() / is_valid($key)：產業別登錄檔，
     目前僅註冊 'urban_renewal' 一筆資料，內容為 AI 系統提示詞、前台
     標題／副標題、各模組是否為該產業核心工具（供決定模組預設啟用
     狀態）。
   - get_active() 刻意直接以 get_option() 讀取原始設定值，不經過
     UR_AI_Settings::get_all()／defaults()，避免 defaults() 反過來呼叫
     get_active() 造成無窮遞迴（因為 defaults() 需要用 get_active() 的
     回傳值決定系統提示詞等預設文案）。
2. UR_AI_Settings 新增 'industry' 設定（預設值 'urban_renewal'）與
   get_industry()；default_system_prompt() 與 defaults() 裡的
   frontend_title／frontend_subtitle 改為讀取目前啟用中產業別的預設值，
   不再是寫死字串；sanitize_value() 新增 'industry' 案例，無效值會
   靜默退回預設產業別，不會讓設定卡在壞值。
3. UR_AI_Calculator_Settings 的 'enabled' 預設值改為依目前產業別的
   modules.calculator 旗標決定（都更危老產業別預設開啟，與升級前
   完全一致）。

二、設計原則

- 產業別只提供「預設值」，不是強制值：管理者原本就能在後台自行修改
  的系統提示詞、模組啟用開關，覆寫優先權完全不變；產業別只影響「第一
  次安裝、或設定值缺漏時」該補上什麼預設內容。
- 對已安裝網站零影響的關鍵：既有網站的設定 option 早就已經把
  system_prompt／frontend_title／frontend_subtitle／enabled 等值存好了
  （即使當初是空字串，get_system_prompt() 這類方法本來就有「空值時
  才查預設值」的邏輯），這次改動只動到「預設值從哪裡取得」，不動到
  任何已儲存設定值本身的讀寫路徑。

三、測試方式

撰寫 PHP 驗證腳本（不需資料庫，純 get_option／update_option 記憶體
模擬），確認：（1）UR_AI_Settings::get_all() 不會因為新的依賴關係
造成無窮遞迴；（2）預設產業別為 'urban_renewal'；（3）系統提示詞與
重構前寫死的文字逐字元相同；（4）frontend_title／frontend_subtitle
預設值與重構前相同；（5）UR_AI_Industry_Profiles::get_all() 正確
列出唯一的都更危老產業別；（6）'industry' 設定值輸入無效字串時會
靜默退回預設值，輸入合法值可正常往返；（7）分回試算預設啟用狀態
與重構前相同；（8）模擬「舊版設定 option（沒有 industry 欄位）」的
既有網站情境，取值行為仍正確退回都更危老預設值。全部情境通過。

四、是否建議上正式站

建議更新。純結構性重構，經回歸測試確認所有現有安裝的輸出與升級前
逐字元相同；新增的登錄檔本身在目前階段只有一筆資料，尚未實際影響
任何前後台可見畫面。真正的產業內容差異化（FAQ／Quiz 內容包、行情
參考用語、AI 人設調整）留待 Phase 2 選定試點產業後再進行。

---

## v1.19.0

更新日期
2026-07-14

版本定位

多產業擴充架構（見 docs/industry-expansion-architecture.md）Phase 2：
選定「地政士」作為第二個試點產業別（依實際商業判斷，非原規劃建議的
不動產經紀業），實際撰寫內容並驗證整套「選擇產業別→切換方向」機制
在真實內容下是否成立。**都更危老產業別的所有預設文案、模組行為完全
不變**，這次新增的是第二個選項，不動到既有選項的內容。

一、主要新增

1. UR_AI_Industry_Profiles 新增 'land_agent'（地政士）產業設定檔：
   - AI 系統提示詞聚焦所有權移轉登記、繼承登記、抵押權設定與塗銷、
     土地增值稅、契稅、印花稅、地籍測量等地政業務知識，並特別強調
     稅額計算、個案資格認定、法律權利判斷不可由 AI 直接代替，須以
     地政機關、稅捐機關或地政士的正式核算為準（地政業務涉及具體
     稅務與登記效果，法遵風險考量比都更危老更需要明確界線）。
   - 前台標題／副標題改為「地政諮詢 AI 助理」。
   - modules.calculator 設為 false（分回試算僅適用都更危老權利變換
     情境，地政士業務用不到）。
   - 新增 market_price 文案欄位（ranking_title／ranking_column／
     ranking_intro），地政士對應「雙北區域行情漲幅排行榜」／
     「行情漲幅」欄位／地政視角的說明文字。
2. UR_AI_Market_Price_Ranking_Shortcode／market-price-ranking-view.php
   改為讀取目前啟用中產業別的 market_price 文案（標題、欄位名稱、
   說明文字），都更危老的預設值與原文字逐字元相同，不影響現有網站。
3. 後台「功能設定」頁新增「產業別」下拉選單，與「套用此產業別的
   預設文案」按鈕：按下後會把選定產業別的系統提示詞／前台標題／
   副標題填入對應欄位（純前端預覽，不會自動送出儲存），管理者確認
   或修改後仍需自行按「儲存設定」才會生效，避免切換產業別誤蓋既有
   客製化內容。
4. 新增地政士內容包：
   - `data/industry-packs/land_agent/faq.csv`：16 篇 FAQ，涵蓋所有權
     移轉登記、繼承登記、抵押權登記、土地增值稅、契稅與印花稅、
     地籍測量與其他登記（時效取得、預告登記）、地政士服務共 7 個
     分類。
   - `data/industry-packs/land_agent/quiz.csv`：12 題測驗，難度分布
     easy／medium／hard，對應 FAQ 涵蓋的知識範圍。
   - 兩份內容包皆符合既有 FAQ／Quiz CSV 匯入格式（分類、標準問題、
     固定回答、關鍵字、狀態、排序／分類、難度、題目、選項 A～D、
     正確答案、解析），可直接透過後台既有的「上傳 CSV」表單匯入，
     不需要新增任何匯入邏輯。

二、設計原則

- 內容包內文字避免陳述容易隨法規調整的具體數字（例如稅率級距的
  精確門檻），改採一般性說明並提醒「以稅捐機關／地政機關最新公告
  為準」，降低法規變動導致內容過時或誤導的風險。
- 「套用此產業別的預設文案」刻意設計成需要管理者手動點擊＋確認，
  而非切換下拉選單就自動套用，避免管理者只是想「看看」其他產業別
  有什麼內容，卻不小心洗掉現有已客製化的系統提示詞。

三、測試方式

撰寫 PHP 驗證腳本，涵蓋：（1）用 Reflection 呼叫既有、未經修改的
UR_AI_FAQ_Admin::parse_faq_csv() 與 UR_AI_Quiz_Admin::parse_quiz_csv()
私有方法，直接解析兩份內容包 CSV，確認筆數（16／12）、必要欄位
完整、狀態／難度／正確答案格式皆正確——證明這兩份檔案今天就能透過
後台既有的上傳表單匯入，不是「理論上格式對」而已；（2）未設定
industry 時，系統提示詞、分回試算預設啟用狀態、行情排行榜文案皆與
升級前逐字元相同；（3）切換 industry 為 land_agent 後，AI 系統提示詞、
前台標題、分回試算預設關閉、行情排行榜標題／欄位名稱皆正確切換，
共 7 項檢查全部通過；（4）無效的產業別 key 安全退回都更危老預設值，
不會導致例外或未定義行為。全部情境通過。

四、是否建議上正式站

建議更新。都更危老產業別行為完全不變（已驗證逐字元相同）；新增的
地政士選項預設不會自動套用到任何現有網站（industry 設定值本身沒有
變動就不會有任何行為差異），是否要實際切換使用地政士內容，仍由
管理者手動至設定頁選擇並確認。內容包本身以一般性知識說明為主，並
於系統提示詞中特別提醒稅務與登記結論須以正式機關核算為準，控制
法遵風險。

---

## v1.20.0

更新日期
2026-07-14

版本定位

把原本單一的「都更危老」產業別，依實際受眾差異拆成兩個，並新增
「產業別對應推廣網站」的曝光機制。

一、主要新增

1. `UR_AI_Industry_Profiles` 拆分：
   - `urban_renewal`：改為廣義「都更重建／都市更新（含危老重建）」，
     適用建設公司／規劃公司／都更顧問。系統提示詞、前台標題／副標題、
     行情參考排行榜文案皆與拆分前逐字元相同，僅調整後台顯示的 label
     文字，不影響任何既有行為。
   - 新增 `self_renewal`：狹義「自主更新」，系統提示詞明確限縮在
     「地主自組更新會、不透過建商或其他機構主導實施」的範圍，前台
     標題改為「自主更新 AI 助理」。分回試算模組維持預設開啟（權利
     變換分回試算的計算邏輯不因是否由建商主導而不同）；行情參考文案
     未特別設定，沿用與都更相同的預設用語（同一套實價登錄資料本來
     就適用兩者，不需要為了差異化而刻意分裂用語）。
2. 每個產業別新增選填的 `promotion` 欄位（`site_label`／`site_url`）：
   - `urban_renewal` 對應 www.ur-promoter.com（本外掛作者網站，與
     外掛標頭原有的 Plugin URI／Author URI 一致）。
   - `self_renewal` 對應 www.fudawang.com。
   - `land_agent` 未設定，前台不顯示任何推廣內容。
3. 新增 `UR_AI_Industry_Profiles::get_active_promotion()` 與
   `render_promotion_attribution()`：後者回傳一段低調的「本服務由 OO
   提供」附連結 HTML（無設定時回傳空字串），已接進 7 個前台 View
   （AI 助理、FAQ 知識庫頁、行情參考查詢、都更效益排行榜、分回試算、
   知識大考驗、知識大考驗排行榜）畫面最下方，並在對應的 4 個前台
   CSS 檔（public.css／calculator.css／market-price.css／quiz.css）
   加上一致的低調樣式（12px 灰階文字＋上方細分隔線）。

二、設計原則

- 「有設定才顯示」：`promotion` 是選填欄位，沒有配置對應網站的產業別
  （目前是地政士）前台完全不會多出任何內容，未來新增產業別時也不會
  被強制要求要對應一個網站。
- 曝光方式刻意選擇「低調附連結」而非置入 FAQ 回答內容或獨立宣傳
  區塊：不影響 AI 回答本身的客觀性，避免使用者對回答內容的中立性
  產生疑慮。

三、測試方式

撰寫 PHP 驗證腳本，涵蓋：（1）`get_all()` 正確列出三個產業別；
（2）`is_valid('self_renewal')` 為真；（3）都更危老產業別的系統
提示詞、前台標題、行情榜單文案與拆分前逐字元相同（迴歸測試）；
（4）自主更新產業別的系統提示詞明確限縮在自主更新範圍、前台標題
正確切換、分回試算維持開啟、未設定專屬行情文案時正確回退共用預設；
（5）都更危老、自主更新的推廣連結分別正確對應 ur-promoter.com／
fudawang.com；（6）地政士沒有設定推廣連結時，`get_active_promotion()`
回傳 null、`render_promotion_attribution()` 回傳空字串；（7）自主更新
的推廣連結 HTML 輸出格式正確（含正確網址、顯示文字、`rel="noopener
noreferrer"`）。另外重新執行先前 Phase 1／地政士試點的驗證腳本，
確認除了「目前只有一個產業別」這類隨版本自然變動的斷言外，其餘全部
維持通過。全部情境通過。

四、是否建議上正式站

建議更新。都更危老產業別的可見內容經驗證逐字元不變；新增的自主更新
產業別、推廣連結曝光機制皆需要管理者主動至設定頁切換產業別才會生效，
不影響任何現有安裝的預設行為。

---

## v1.21.0

更新日期
2026-07-14

版本定位

兩項調整：（1）依實際使用回饋，重新定義「自主更新」產業別的範圍與
系統提示詞方向；（2）把「系統稱呼」從只有前台聊天小工具會跟著產業別
切換，擴大到後台選單、所有後台頁面標題、前台列印標題、知識大考驗、
行情參考說明文字、提問框提示文字等外掛內幾乎所有會顯示「都更 AI
助理」字樣的地方，確保切換到非都更產業別（例如地政士）時，系統各處
看到的稱呼是一致的，不會有些地方換了、有些地方還卡在「都更」。

一、自主更新範圍調整

原本 v1.20.0 把「自主更新」定義成「地主自組更新會、不透過建商主導
實施」，是相對狹義且帶有「排他」意味的定義（暗示委託建商就不算自主
更新）。這一版依實際回饋修正為廣義解釋：**只要是由地主主動發起
（相對於建商先找上地主推銷合建），都算自主更新**，不預先限定後續
一定要地主自己包辦到底。系統提示詞同步調整，強調「發起後凝聚多數
地主共識」是最關鍵的第一階段，執行方式（自組更新會自行執行、委託
專業機構協助、後續納入建商參與）留待共識形成後再決定，回答時不
預設特定執行方式的立場，避免暗示某種執行方式優於其他方式。

二、系統稱呼全面依產業別切換

1. `UR_AI_Industry_Profiles` 新增 `brand_name` 欄位（都更 AI 助理／
   自主更新 AI 助理／地政 AI 助理）與 `get_active_brand_name()`，
   跟 `assistant.frontend_title`（前台聊天小工具自己的標題文案，
   通常較長）刻意分開維護。
2. `UR_AI_Admin_Menu` 新增 `brand_name()` 靜態方法，後台頂層選單
   標題與全部 12 個後台頁面的 `<h1>` 標題（總覽、功能設定、FAQ
   知識庫、問答紀錄、相關頁面推薦、熱門問題、回饋分析、內容缺口、
   知識大考驗、行情參考等）都改為讀取這個方法，不再寫死「都更 AI
   助理」。分回試算相關頁面（試算器設定、試算名單）與其內部欄位
   說明維持不變——這些是描述「分回試算」這個功能本身在算什麼（都更
   容積獎勵等），屬於功能名稱與計算邏輯的準確描述，不論都更或自主
   更新產業別都成立，不屬於「系統稱呼」的範疇。
3. 新增並串接以下依產業別切換的文案，修掉原本只有前台聊天小工具會
   換、其他地方仍寫死「都更」字樣的殘留：
   - 前台列印標題（`print_document_title`）。
   - 知識大考驗預設標題（`quiz.default_title`）與挑戰賽介紹文案的
     主題敘述（`quiz.topic_label`，例如「都市更新與危老重建」／
     「自主更新」／「地政登記與稅務」）。
   - 行情參考查詢 widget 的說明文字（`market_price.query_intro`）
     與行情變化提示語（`market_price.uplift_label`）。
   - 知識大考驗作答回顧提示使用者「可至「OO」搜尋此問題」的 JS
     字串，改為帶入目前產業別的品牌名稱。
   - 前台提問框的預設 placeholder 文字（`assistant.placeholder`）。
4. `UR_AI_Settings` 新增 `get_frontend_title()`／`get_frontend_subtitle()`
   兩個 getter，行為對應既有的 `get_system_prompt()`：設定值非空
   則回傳設定值，否則回退到目前啟用中產業別的預設文案。原本分散在
   `class-ur-ai-shortcode.php`、`assistant-view.php`、
   `settings-page.php` 三處各自寫死一份「都更危老 AI 助理」／
   前台副標題全文的重複字串，統一改呼叫這兩個 getter，之後只需要
   改一個地方。

三、設計原則

- 品牌簡稱（`brand_name`，後台選單／頁面標題用）與前台聊天小工具
  自己的標題（`assistant.frontend_title`）刻意分開：即使是都更危老
  這個既有產業別，兩者本來就不同一份文字（後台選單一直是「都更 AI
  助理」，前台小工具卻是「都更危老 AI 助理」），刻意保留這個既有
  差異，不做不必要的統一。
- 分回試算模組內部的計算相關字樣（都更容積獎勵、都更審議結果等）
  不視為「系統稱呼」處理：這些是準確描述計算邏輯本身的專有名詞，
  在都更與自主更新兩個仍啟用分回試算的產業別下都成立，刻意不比照
  品牌名稱做動態替換，避免用字面替換破壞計算邏輯說明的準確性。

四、測試方式

撰寫 PHP 驗證腳本，涵蓋：（1）都更危老產業別的 8 項文案（品牌簡稱、
知識大考驗標題／主題敘述、行情變化提示語、行情查詢說明文字、提問框
提示文字、前台標題／副標題）與調整前的寫死字串逐字元相同；（2）
自主更新產業別切換後品牌簡稱、知識大考驗標題／主題敘述、提問框
提示文字、前台標題皆正確切換，行情變化提示語因未特別設定而正確
回退共用預設；（3）地政士產業別切換後對應 7 項文案皆正確切換；
（4）已手動自訂前台標題的既有網站，切換產業別後不會被覆蓋，自訂
內容予以保留；（5）自主更新的推廣連結（fudawang.com）在這次調整
後仍正確保留，未受影響。全部情境通過。

五、是否建議上正式站

建議更新。都更危老產業別的所有文案皆與調整前逐字元相同；管理者
已手動自訂過的前台標題／副標題不受影響，仍會被優先採用。

---

## v1.22.0

更新日期
2026-07-15

版本定位

回應「後台功能是否還能運用 AI API」的可行性評估，選定風險最低、
價值最直接的一項先落地：內容缺口頁「熱門但尚未建立 FAQ」清單的
「轉 FAQ 草稿」，從只會產生一段制式佔位文字，改為優先請 AI 先草擬
一版真正的回答內容。

一、背景

盤點現況後發現外掛只有兩處真的會呼叫 OpenAI：前台 FAQ 未命中時的
補位回答、知識大考驗依 FAQ 內容出題。內容缺口頁「熱門但尚未建立
FAQ」清單的轉 FAQ 草稿，過去建立的草稿 answer 欄位只有一段固定文字
（「這是一筆由熱門問題轉入的 FAQ 草稿，請管理者補上完整回答。」），
管理者等於要從零開始寫回答；同一頁「重複被問、一直靠 AI 回答」的
清單則不受影響，因為那份草稿本來就是直接取用過去真實對話中已經
產生的 AI 回答內容，不需要额外呼叫。

二、主要新增

1. `UR_AI_FAQ_Draft_Service` 新增 `draft_answer_via_ai()`：直接沿用
   既有的 `UR_AI_OpenAI_Client::chat()`——跟前台補位回答完全相同的
   system prompt（目前啟用中產業別的人設）、溫度與字數上限設定，把
   熱門問題本身當作使用者提問送出，取得一版真正的回答內容，不另外
   維護一套 prompt。
2. `create_from_popular_question()` 改為優先呼叫上述方法；只有在
   未設定 API Key、API 呼叫失敗、或回傳內容為空時，才退回原本的
   純文字佔位草稿——維持「沒設定 AI 功能的站台，行為與升級前完全
   一致」。
3. AI 草擬成功時，分類／關鍵字建議改用 AI 回答內容判斷（比原本只有
   熱門問題的簡短說明、甚至常常是空字串，判斷依據更準確）；管理
   備註也會特別註明「回答內容為 AI 草擬，上線前請務必核對事實正確性」，
   跟原本單純提示「請補上完整回答」的措辭有意做出區隔，提醒審核者
   AI 草擬與人工已驗證內容需要用不同嚴謹度檢視。
4. 更新內容缺口頁對應的說明文字，讓管理者在按下「轉 FAQ 草稿」前
   就知道行為已經改變。

三、設計原則

- 只加強這一個入口：「重複被問、一直靠AI回答」清單本來就有真實的
  AI 回答可用，不需要重複呼叫；「沒幫助最多的FAQ」清單是要改寫既有
  已上線內容，跟「產生全新草稿」的風險層級不同，這次不一併擴大範圍。
- 草稿一律維持「停用／待審核」狀態不變：AI 草擬只是提高「草稿的
  起點品質」，完全沒有動到既有的人工審核關卡，AI 生成的內容一樣
  要人工核可才會上線。
- 呼叫失敗時安靜退回原本行為，不讓管理者困惑於 API 錯誤訊息——這個
  功能本來就是「錦上添花」，不能因為呼叫失敗就讓原本能用的轉檔
  功能整個掛掉。

四、測試方式

撰寫 PHP 驗證腳本，把 `wp_remote_post()` mock 成四種情境：（1）AI
呼叫成功，確認草稿 answer 為真正的 AI 回答內容、status／review_status
仍是 inactive／draft、管理備註正確標註「AI 草擬」與「核對事實正確性」；
（2）未設定 API Key，確認完全退回原本的佔位文字與備註措辭，逐字元
不變；（3）API 回傳錯誤，確認優雅退回佔位草稿、不拋出例外；（4）AI
回傳內容為空字串，同樣正確退回佔位草稿。全部情境通過。

五、是否建議上正式站

建議更新。沒有設定 OpenAI API Key 的網站行為與升級前逐字元相同；
有設定的網站只會讓「轉 FAQ 草稿」這個既有動作的草稿品質變好，沒有
新增任何前台可見功能、沒有新增資料表，人工審核關卡完全保留。

---

## v1.23.0

更新日期
2026-07-15

版本定位

回應「行情的更新，之前有上傳過的內容，是否會判斷重複？不重複上傳，
或是採用覆蓋？」的提問後，明確定調：政府資料才是最終依據，政府端
更正／補件時，資料庫應該跟著變，而不是永遠只認第一次上傳的內容。
把行情參考的重複比對邏輯，從「只判斷 source_record_id 是否已存在」
升級為「同一個 source_record_id 底下，內容是否真的有變化」。

一、背景

行情參考的匯入（手動上傳 CSV、後台「自動抓取」）都是以政府資料
原有的「編號」欄位（source_record_id）防止重複。升級前的行為是：
只要這個編號已經存在，不論內容是否相同，一律略過，永遠不會更新。
但內政部實價登錄開放資料本身會持續有「同一筆交易事後訂正」的情況
（例如補正總價、地址、屋齡等欄位），舊行為會讓資料庫卡住第一次
上傳當下的內容，之後政府端的更正永遠反映不到資料庫裡。

二、主要新增

1. 資料表新增 `updated_at DATETIME NULL` 欄位（`UR_AI_Schema_Market_Prices`），
   記錄最後一次因內容異動而被更新的時間；透過既有的 DB_VERSION
   （1.3.0 → 1.4.0）自動升級機制新增，沿用 dbDelta 既有的欄位比對
   升級流程，不需要額外寫遷移腳本。
2. `UR_AI_Market_Price_Repository::insert()` 全面改寫：同一個
   source_record_id 已存在時，不再直接視為重複略過，而是逐欄位比對
   內容是否有實質差異——完全相同才回傳 `duplicate`（該筆紀錄完全
   不會被觸碰，created_at／updated_at 都維持原樣）；只要總價、面積、
   地址、屋齡、建物型態等任一欄位不同，就直接覆蓋更新既有紀錄並
   回傳 `updated`（created_at 保留最初建立時間，updated_at 更新為
   這次異動時間）；全新的 source_record_id 則仍回傳 `inserted`。
   `import_batch`（本次匯入批次代碼，每次匯入都會重新產生）刻意排除
   在比對範圍外，避免單純重複上傳同一份未變動的資料被誤判成異動。
3. 大量匯入時原本用來預先查重複的 `get_existing_source_record_ids()`，
   改為預先撈出可比對的完整欄位內容（而非只有是否存在），讓逐筆
   比對不需要對資料庫額外下查詢，效能特性與升級前一致。
4. `UR_AI_Market_Price_Import_Service::import_from_csv()`、
   `UR_AI_Market_Price_Remote_Fetch_Service::fetch_and_import()`
   的回傳結果新增 `updated` 統計欄位，並貫穿到後台重導參數
   （`imp_updated`／`fetch_updated`）與抓取紀錄的持久化內容。
5. 行情參考後台頁面：CSV 匯入成功訊息、自動抓取成功訊息、抓取
   紀錄一覽表，皆新增顯示「更新 N 筆（政府資料異動）」。

三、設計原則

- 政府資料為準：這是本次調整的核心出發點——外掛的行情資料庫只是
  政府公開資料的本地備份與統計快取，不應該比政府原始資料更「固執」。
- 不無謂改動：內容真的相同時，連 updated_at 都不會被寫入，維持
  「這筆紀錄自建立以來從未變動」的可追溯性；只有真的有差異時才
  留下更新痕跡。
- 批次代碼不算內容異動：避免「今天重新上傳同一份還沒變動的資料」
  這種最常見的操作，被誤判成大量更新而讓管理者誤以為政府資料
  異動頻繁。

四、測試方式

撰寫 PHP＋SQLite 驗證腳本，涵蓋：（1）全新 source_record_id 匯入
正確回傳 inserted，created_at 有值、updated_at 為空；（2）重新
匯入完全相同內容（但匯入批次代碼不同）正確回傳 duplicate，
created_at／updated_at 皆完全不受影響；（3）同一 source_record_id
內容有異動（總價訂正）正確回傳 updated，異動後的值確實寫入、
created_at 保留、updated_at 有值；（4）混合批次（新增＋不變＋
異動各一筆以上）在 `import_from_csv()` 的統計結果中 created／
updated／duplicate 三項計數皆正確；（5）`fetch_and_import()` 對
兩個城市的資料分別抓取兩次（其中一個城市的資料在第二次做了更正），
確認 updated 統計正確跨城市加總，且正確寫入抓取紀錄；（6）未預先
載入比對集合的單筆 `insert()`（大量匯入以外的呼叫路徑）也能正確
判斷 inserted／duplicate／updated。全部情境通過。

五、是否建議上正式站

建議更新。既有資料表會透過既有的 DB_VERSION 升級機制自動新增
updated_at 欄位，不需要手動介入；內容完全相同的既有紀錄行為與
升級前一致（略過不動），只有政府資料真的有異動時才會更新，符合
「以政府資料為準」的預期。

---

## v1.23.1

更新日期
2026-07-15

版本定位

小修正：前台各頁面底部「本服務由 OO 提供」的推廣曝光文字過於精簡，
只有站名（甚至只有網域），沒有邀請使用者前往了解或分享的語句。

一、主要調整

1. `UR_AI_Industry_Profiles` 的 `promotion` 設定新增 `site_name` 欄位：
   都更重建／都市更新對應「都更危老重建資訊平台」（ur-promoter.com），
   自主更新對應「自主更新指南-福大資訊」（fudawang.com）。
2. `render_promotion_attribution()` 輸出的句子改為：
   「本服務由 {網站名稱}（{網域}）提供，歡迎前往了解更多，也歡迎
   分享給有需要的親友。」連結的 href 仍指向完整網址，網域則以
   括號附註在網站名稱後面，讓使用者同時看到「是誰提供」與「網址
   長什麼樣子」，並附上明確的邀請分享語句。
3. 地政士（未設定推廣網站）不受影響，`render_promotion_attribution()`
   仍回傳空字串，前台不顯示任何內容。

二、測試方式

撰寫 PHP 驗證腳本，確認：（1）都更重建／都市更新的輸出同時包含
站名、網域、正確的 href、邀請分享語句；（2）自主更新的輸出同時
包含其對應的站名、網域、正確的 href、邀請分享語句；（3）地政士
仍輸出空字串。全部情境通過。

三、是否建議上正式站

建議更新。純文案調整，不影響任何資料結構或既有功能行為，唯一
可見變化是前台底部推廣文字變得更完整、更明確。

---

## v1.24.0

更新日期
2026-07-15

版本定位

回應「我想要同時的曝光與推廣兩個網站（www.ur-promoter.com 與
www.fudawang.com），所有的這個外掛安裝（別人安裝也是）都能夠推廣
我的兩個網站資訊」的需求，把推廣曝光從「跟著產業別切換只顯示其中
一個網站」，改為「不論哪個產業別、不論誰安裝這個外掛，都同時固定
曝光兩個網站」。

一、背景

升級前，前台底部的推廣曝光是跟著啟用中的產業別切換的：都更重建／
都市更新只顯示 ur-promoter.com，自主更新只顯示 fudawang.com，地政士
則完全沒有設定、不顯示任何內容。這樣的設計沒有達到「不論誰安裝都
能同時看到兩個網站」的目的。

二、主要調整

1. `UR_AI_Industry_Profiles` 新增 `get_promotion_sites()`：回傳固定的
   兩筆推廣網站資料（都更危老重建資訊平台 ur-promoter.com、自主更新
   指南-福大資訊 fudawang.com），與目前啟用中的產業別完全無關。
2. 移除各產業別設定裡個別的 `promotion` 欄位與已不再被使用的
   `get_active_promotion()` 方法——推廣曝光不再是「產業別的其中一項
   設定」，而是每一份外掛安裝都固定具備的內容。
3. `render_promotion_attribution()` 改為同時輸出兩個網站的連結（各自
   顯示網站名稱與網址），文字調整為「本服務由 A（網域）、B（網域）
   提供，歡迎前往了解更多，也歡迎分享給有需要的親友。」
4. 地政士（先前完全不顯示推廣內容）現在也會顯示與其他產業別完全
   相同的兩個網站曝光內容。

三、設計原則

- 推廣曝光與「產業別」脫鉤：這是外掛作者的固定回饋連結，不應該
  因為安裝者選了哪個產業別、或選了哪個產業別「沒有設定推廣網站」，
  而有些安裝看得到、有些看不到。
- 不提供後台開關：目前沒有在後台增加「是否顯示推廣連結」的設定，
  維持與升級前一致（升級前也沒有可關閉推廣連結的設定，只能透過
  切換產業別間接影響是否顯示）。

四、測試方式

撰寫 PHP 驗證腳本，確認：（1）都更重建／都市更新、（2）自主更新、
（3）地政士、（4）尚未設定產業別（全新安裝的預設狀態），四種情境
下 `render_promotion_attribution()` 的輸出都同時包含兩個網站各自的
名稱、網域、正確的 href，以及邀請前往了解／分享的文字；（5）
`get_promotion_sites()` 不論目前啟用中的產業別為何，永遠回傳這
固定的兩筆資料。全部情境通過。

五、是否建議上正式站

建議更新。純前台文案／曝光內容調整，不影響任何資料結構或既有
功能行為；唯一可見變化是前台底部的推廣文字，從「依產業別顯示其中
一個網站（或地政士完全不顯示）」，改為「固定同時顯示兩個網站」。

---

## v1.24.1

更新日期
2026-07-15

版本定位

修正回報問題：「知識大考驗，無法批次核准，不論有無篩選皆是」。這是
一個從批次核准／退回／刪除功能上線以來就存在的實際功能性錯誤（非
單純文案問題），根源是 HTML 巢狀表單，藏得很深，一般程式碼閱讀
（甚至只用假造 `$_POST` 的 PHP 測試腳本）都無法發現，必須實際用
瀏覽器渲染＋操作才會現形。

一、根本原因

後台「知識大考驗」題庫列表，每一列的「核准上線／退回／刪除」都是
各自獨立的一個小 `<form method="post">`，而這些小表單全部畫在「批次
操作」外層那個大 `<form class="ur-ai-bulk-form">` 裡面——形成瀏覽器
規格不允許的巢狀 `<form>`。

瀏覽器解析巢狀表單時的實際行為（已用真實 Chromium 瀏覽器驗證，非
理論推測）：某一列的小表單在解析時會被「忽略」（不會真的產生獨立
的表單元素），但它裡面的隱藏欄位（包含同樣命名為 `ur_ai_quiz_action`
的欄位，值是 `review_question`）卻會被直接併入外層的批次表單。當
使用者勾選多筆題目、選擇「核准上線」、按下「套用」送出批次表單時，
瀏覽器實際送到後台的表單資料裡，會同時存在兩個 `ur_ai_quiz_action`
欄位（外層表單本來的 `bulk_questions`，以及被併入的 `review_question`）。
PHP 的 `$_POST` 對同名欄位只會保留「最後出現」的那一個值，結果就是
後台永遠收到 `ur_ai_quiz_action=review_question`，完全沒有真正進入
批次處理的程式碼路徑——批次核准／退回／刪除因此對使用者來說「完全
沒有作用」，而且不論當下有沒有套用篩選條件都一樣會發生，因為問題
根源與篩選邏輯無關，是表單結構本身的錯誤。

二、主要修正

1. `admin/pages/quiz-page.php`：批次表單加上固定 `id`
   （`ur-ai-quiz-bulk-form`），並把 `</form>` 結尾標籤往前移到「批次
   操作」工具列結束、資料表格開始之前，讓每一列的小表單不再巢狀包在
   批次表單裡面，變成一般、合法、彼此獨立的表單。
2. 「全選」勾選框與每一列的項目勾選框，改用 HTML5 的 `form="ur-ai-
   quiz-bulk-form"` 屬性明確歸屬回批次表單——效果與原本巢狀在表單裡
   完全相同（送出批次表單時一樣會帶上這些勾選框的值），但不會再有
   巢狀表單的解析問題。
3. `admin/assets/js/admin.js`（後台共用腳本）：原本用
   `$form.find(...)`／`$checkbox.closest(bulkForm)` 尋找批次表單與其
   勾選框，這類寫法只認得到「DOM 上的子節點」，勾選框改用 `form=""`
   屬性歸屬後就找不到了。改為透過表單原生的 `.elements`（會正確包含
   用 `form=""` 屬性歸屬的欄位）與 `.form`（原生屬性，同時支援巢狀在
   表單內與 `form=""` 屬性兩種寫法）尋找，同時相容尚未套用這次修正
   的其他頁面，不會造成其他頁面的批次操作 JS 邏輯跟著壞掉。

三、設計原則

- 修正巢狀表單問題時，優先選擇「移動表單邊界＋改用 `form=""` 屬性」
  而非重寫每一列的操作按鈕邏輯：既有的每列小表單（核准／退回／
  刪除）完全不用改寫，只是不再被巢狀包住而已，改動範圍最小。
- JS 共用邏輯改用原生 `.elements`／`.form` 而非單純的 jQuery DOM
  遍歷，這兩個原生屬性本來就是瀏覽器為了解決「表單元素不一定是
  `<form>` 的 DOM 子節點」而設計的標準機制，同時相容新舊兩種寫法，
  這次沒有全面修改的其他頁面不會受影響。

四、測試方式

由於這個錯誤的根源是瀏覽器如何解析巢狀 `<form>`，單純用假造
`$_POST` 陣列的 PHP 測試腳本（過去慣用的驗證方式）完全無法重現，
因此這次改用實際的 Chromium 無頭瀏覽器驗證：

1. 用真實的 `admin/pages/quiz-page.php` 樣板（搭配少量 PHP stub）
   渲染出實際的 HTML，確認渲染結果與修正前後的差異。
2. 修正前：載入真實渲染的 HTML 與真實的 `admin.js`，模擬使用者勾選
   兩筆題目、選擇「核准上線」、按下「套用」，攔截表單送出事件並讀出
   實際的 FormData——確認重現了「`ur_ai_quiz_action` 被覆蓋成
   `review_question`、只剩一筆 `question_ids[]`」的錯誤現象。
3. 修正後：同樣的操作步驟，確認 FormData 裡 `ur_ai_quiz_action` 正確
   維持 `bulk_questions`、`question_ids[]` 正確包含兩筆勾選的題目 ID，
   且沒有任何欄位被覆蓋或遺漏。
4. 額外驗證：修正後個別列的「核准上線」小表單單獨點擊送出時，
   FormData 只包含它自己的欄位（`review_question`／`question_id`／
   `decision`），不會外洩到批次表單、也不會被批次表單的欄位污染。
5. `php -l` 與 `node --check` 分別確認修改後的 PHP 與 JS 檔案語法
   正確。全部情境通過。

五、是否建議上正式站

強烈建議更新。這是一個被實際回報、且已用真實瀏覽器確認存在（並非
理論上的邊角案例）的功能性錯誤：修正前，知識大考驗題庫的批次核准／
退回／刪除完全無法使用；修正後行為恢復正常，且已確認不影響個別列
單筆審核／刪除的既有行為。

---

## v1.24.2

更新日期
2026-07-15

版本定位

v1.24.1 修正知識大考驗題庫批次操作失效後，逐一檢查後台其他清單頁
是否有同樣的巢狀表單結構，確認 FAQ 知識庫、熱門問題、相關頁面推薦、
問答紀錄這 4 個頁面的批次操作區塊都有一模一樣的問題，這次一併修正。

一、背景

v1.24.1 記錄的根本原因（每一列的單筆操作小表單巢狀包在批次表單裡，
導致瀏覽器解析時把小表單的隱藏欄位併入批次表單，覆蓋掉同名的批次
動作欄位）並非知識大考驗題庫獨有的問題，而是這幾個清單頁共用的
標記樣式（批次表單包住整張表格、每一列的操作按鈕各自用一個小
`<form>`）。逐頁檢查後確認：

- FAQ 知識庫（`admin/pages/faq-page.php`）：批次表單內有每列的
  「刪除」小表單，同名欄位為 `ur_ai_action`。
- 熱門問題（`admin/pages/popular-questions-page.php`）：批次表單
  內有每列的「轉 FAQ 草稿」與「刪除」兩個小表單，皆與批次表單共用
  `ur_ai_action` 欄位名稱——是這次修正範圍內最複雜的案例。
- 相關頁面推薦（`admin/pages/related-pages-page.php`）：批次表單內
  有每列的「刪除」小表單，同名欄位為 `ur_ai_action`。
- 問答紀錄（`admin/pages/logs-page.php`）：批次表單內有每列的
  「轉 FAQ 草稿」與「刪除」兩個小表單，同名欄位為 `ur_ai_action`。

這 4 個頁面裡，熱門問題與相關頁面推薦其實各自還有「另一個」用途
不同的批次表單（分別是「從 FAQ 匯入熱門問題」與「從文章匯入推薦
頁面」），這兩個表單本身沒有巢狀小表單問題，這次確認無誤後維持
不動，只修正真正受影響的那一個批次表單。

二、主要修正

對上述 4 個頁面，套用與 v1.24.1 知識大考驗題庫完全相同的修正手法：

1. 受影響的批次表單加上固定 `id`，並把 `</form>` 結尾標籤往前移到
   「批次操作」工具列結束、資料表格開始之前，讓每一列的小表單不再
   巢狀包在批次表單裡面。
2. 「全選」勾選框與每一列的項目勾選框，改用 HTML5 的 `form="..."`
   屬性明確歸屬回對應的批次表單。
3. 因為 `admin/assets/js/admin.js`（後台共用腳本）已經在 v1.24.1
   改用原生 `.elements`／`.form` 尋找批次表單與其勾選框，這次不需要
   再修改 JS，4 個頁面直接沿用同一套共用邏輯即可正確運作。

三、測試方式

對每個頁面用真實的頁面樣板（搭配少量 PHP stub）渲染出實際 HTML，
確認批次表單標籤結尾位置正確、勾選框的 `form=""` 屬性指向正確的
表單 id、且整份檔案的 `<form>`／`</form>` 標籤數量前後平衡（不多不
少一個）。並針對修正範圍內最複雜的案例（熱門問題頁，同一列有兩個
各自獨立、且與批次表單同名 `ur_ai_action` 欄位的小表單）另外用真實
Chromium 無頭瀏覽器完整驗證：勾選跨列的多筆項目、選擇批次動作、
送出後攔截表單送出事件讀出實際 FormData，確認 `ur_ai_action` 正確
維持批次動作代碼（不被任一列的小表單覆蓋）、`popular_question_ids[]`
正確包含所有勾選的項目 ID。`php -l` 確認 4 個檔案語法正確。全部
情境通過。

四、是否建議上正式站

強烈建議更新，原因與 v1.24.1 相同：這是實際回報且已用真實瀏覽器
確認存在的功能性錯誤，修正前這 4 個頁面的批次操作（啟用／停用／
刪除／轉 FAQ 草稿）極有可能完全無法使用，修正後行為恢復正常。

---

## v1.25.0

更新日期
2026-07-15

版本定位

回應「詢問 AI 回答的問答數據分析，AI 的編寫在分類與關鍵字，並不會
因為產業不同而有變換，還是一樣的都更方向的，地政產業的問題無法
正確的編寫適當的分類與關鍵字」的回報。把 FAQ／熱門問題／相關頁面
的「建議分類」「建議關鍵字」機制（`UR_AI_FAQ_Category_Helper`）從
固定寫死一套都更專用規則，改為跟著目前啟用中的產業別切換。

一、背景

`UR_AI_FAQ_Category_Helper` 是一套規則式（關鍵字比對）的分類／關鍵字
建議工具，供以下情境使用：內容缺口頁「轉 FAQ 草稿」時建議分類與
關鍵字、熱門問題新增時建議分類、相關頁面推薦建議分類。升級前，
這套工具的分類規則（`get_default_category_rules()`）與關鍵字候選清單
（`get_keyword_candidates()`）都是寫死在程式碼裡的都更專用內容（都市
更新、危老重建、更新會、自主更新、權利變換、協議合建等），完全
不會因為目前啟用中的產業別而改變。地政士產業別的問題（例如「所有
權移轉登記需要準備哪些文件」）內容跟這些都更關鍵字完全對不上，
因此一律被判斷成「待分類」，無法得到有意義的分類與關鍵字建議。

二、主要調整

1. `UR_AI_Industry_Profiles` 新增 `get_active_faq_category_rules()`／
   `get_active_faq_keyword_candidates()`：讀取目前啟用中產業別若有
   自訂的 FAQ 分類規則／關鍵字候選清單就回傳，沒有另外定義時回傳
   `null`，由呼叫端退回外掛既有的預設規則。
2. 地政士產業別新增專屬的分類規則與關鍵字候選清單：所有權移轉
   登記、繼承登記、抵押權登記、土地增值稅、契稅與印花稅、地籍測量
   與其他登記、地政士服務，內容與 `data/industry-packs/land_agent/
   faq.csv` 起始包實際使用的分類完全一致，確保「起始包的分類」與
   「自動建議的分類」是同一套語彙，不會出現兩邊分類名稱兜不起來
   的狀況。
3. 都更重建／都市更新、自主更新兩個產業別沒有另外定義規則，沿用
   外掛既有的都更分類規則與關鍵字候選清單，行為與升級前逐字元
   相同。
4. `UR_AI_FAQ_Category_Helper` 建構子改為在實例化當下讀取目前啟用中
   產業別的規則（無自訂規則則退回預設），不再是寫死的單一套規則；
   `ur_ai_faq_category_rules`／`ur_ai_faq_keyword_candidates` 這兩個
   既有的擴充 filter 掛鉤，改成無論走哪一種產業別都固定只套用一次
   （避免預設路徑被重複套用兩次），也讓地政士等自訂規則一樣可以
   透過相同的 filter 掛鉤客製化。
5. 順帶調整建議關鍵字的排列順序：先前只是依「關鍵字候選清單裡
   宣告的先後順序」列出比對到的關鍵字，跟這篇問答內容的重要程度
   無關；改為依「詞語具體程度」（字數長度，越長越具體）由高到低
   排序，建議分類固定排在最前面，讓排在前面的關鍵字更能代表這篇
   問答真正在討論的主題。

三、設計原則

- 沿用既有的「產業設定檔」擴充架構（`UR_AI_Industry_Profiles`）：
  新增產業別若要有自己的分類規則，只需要在該產業別的設定資料裡
  加上 `faq.category_rules`／`faq.keyword_candidates`，不需要更動
  `UR_AI_FAQ_Category_Helper` 本身的邏輯。
- 沒有另外定義規則的產業別（都更危老／自主更新），行為必須與
  升級前逐字元相同——這是這次修改最重要的回歸測試項目。
- 地政士的分類規則直接取材自實際上線的地政士 FAQ 起始包分類，
  而非另外發明一套分類語彙，降低「自動建議分類」與「內容包本身
  分類」之間互相對不上的風險。

四、測試方式

撰寫 PHP 驗證腳本，涵蓋：（1）都更重建／都市更新的分類／關鍵字
建議與升級前完全相同；（2）自主更新（沒有自訂規則）正確退回與
都更重建／都市更新一模一樣的預設規則；（3）4 則地政士範例問題
（所有權移轉登記、繼承登記、土地增值稅、契稅與印花稅）都正確
分類到對應的地政士專屬分類，不再落入「待分類」；（4）同一句
「登記的辦理流程與應備文件為何？」在都更重建／都市更新（判斷為
「待分類」）與地政士（判斷為「所有權移轉登記」）產業別下得到不同
結果，證明分類建議確實會跟著產業別切換；（5）建議關鍵字的排列
順序——分類固定排最前面，其餘關鍵字依字數長度由高到低排序；
（6）`ur_ai_faq_category_rules`／`ur_ai_faq_keyword_candidates` 這兩個
filter 掛鉤，不論走預設路徑或地政士自訂路徑，都恰好只觸發一次，
沒有被重複套用兩次的回歸風險。全部情境通過。

五、是否建議上正式站

建議更新。都更重建／都市更新、自主更新這兩個既有產業別的分類與
關鍵字建議行為與升級前逐字元相同；地政士產業別則會第一次得到
真正符合地政業務內容的分類與關鍵字建議，不再一律落入「待分類」。

---

## v1.26.0

更新日期
2026-07-15

版本定位

回應「後台想增加一個與 AI 對話的功能」的需求。使用者確認了兩個關鍵
設計方向：（1）對話中途不逐句轉存，而是整段對話結束後，管理者手動
點「產生總結草稿」；（2）獨立成一個新的後台頁面。這一版新增「AI
對話」後台頁，讓管理者能與 AI 助理多輪對話腦力激盪知識庫內容方向，
對話結束後可請 AI 整理成 FAQ 草稿，逐則確認後加入知識庫。

一、主要新增

1. 新增模組 `UR_AI_Admin_Chat_Module`／`UR_AI_Admin_Chat_Ajax`，比照
   知識大考驗模組的 register()/boot() 兩段式架構；新增後台選單頁
   「AI 對話」（`admin/pages/ai-chat-page.php`），只在本頁載入專屬的
   `ai-chat.js`／`ai-chat.css`，不影響其他後台頁面的載入速度。
2. `UR_AI_OpenAI_Client` 新增兩個方法：
   - `chat_conversation($messages)`：多輪對話（帶入完整對話歷史），
     使用專屬的「對管理者說話」系統提示詞——沿用目前啟用中產業別的
     人設與知識範圍（`get_system_prompt()`），再疊加「對話對象是
     管理者、目的是腦力激盪知識庫內容」的補充說明，確保不同產業別
     安裝時，這個功能討論的範圍會跟著目前的產業別走。
   - `summarize_conversation_to_faq_drafts($messages)`：把整段對話
     整理成最多 5 則 FAQ 草稿建議（標準問題＋固定回答），要求 AI
     以嚴格 JSON 格式回傳，解析方式比照既有的知識大考驗出題功能
     （`generate_quiz_question()`）處理 fenced code block 的做法。
   - 對話紀錄會做基本清理：只接受 role 為 user／assistant、內容非
     空白的訊息，並限制最多帶入最近 20 則，避免對話越聊越長時，
     每次呼叫都把完整歷史送給 OpenAI，造成 token 用量不成比例增加。
3. `UR_AI_FAQ_Draft_Service` 新增 `create_from_admin_chat()`：建立
   FAQ 時預設「停用／待審核」（`status=inactive`／`review_status=
   draft`），與外掛既有「AI 產生的內容一律需人工審核」原則一致；
   分類與關鍵字留空時會透過 `UR_AI_FAQ_Category_Helper`（v1.25.0
   已改為依產業別調整）自動建議，若管理者在草稿卡片上已手動編輯，
   則直接採用編輯後的內容，不會被自動建議覆蓋。
4. FAQ 新增來源類型 `ai_chat`（顯示為「AI 對話產生」），與既有的
   `manual`／`ai_log`／`import` 並列，方便日後在 FAQ 列表分辨這則
   內容的產生方式。
5. `UR_AI_Permissions` 新增 `admin_chat` 權限區域（對應
   `manage_options`，與其他後台功能一致），供選單頁與 AJAX 呼叫
   共用同一套權限檢查。

二、設計原則

- 對話內容本身不寫入資料庫，只存在瀏覽器記憶體中（重新整理頁面會
  清空）：真正需要長期保存的是「轉出的 FAQ 草稿」，這部分沿用既有
  的 FAQ 資料表與審核機制，不需要為了保存對話紀錄另外新增資料表，
  降低這次改動的風險與複雜度。
- 每次請求都是把目前累積的對話紀錄整批送給 OpenAI（Chat Completions
  API 本身無狀態），伺服器端也不保存對話狀態，架構單純。
- 後台 AJAX 呼叫沿用既有但先前未被使用的 `UR_AI_Security::
  ajax_verify_admin_nonce_or_die()`（對應後台共用腳本已經在
  localize 的 `UR_AI_ADMIN.nonce`）與 `UR_AI_Permissions::
  ajax_require()`，與前台訪客提問的 AJAX 完全分開，不共用 nonce
  也不共用任何每日用量限制。
- FAQ 草稿建立邏輯集中在 `UR_AI_FAQ_Draft_Service`，與既有「從問答
  紀錄轉入」「從熱門問題轉入」共用同一個服務類別，只是新增一個
  來源方法，不重複實作分類建議、審核狀態等邏輯。

三、測試方式

由於這個功能同時牽涉「後端 AI 呼叫邏輯」與「前端多輪對話的 DOM／
AJAX 互動」，這次分四層驗證：

1. PHP 單元測試：`chat_conversation()` 正確組出含產業別品牌名稱的
   系統提示詞、正確清理並截斷對話歷史（丟棄角色不合法或內容空白的
   訊息、超過 20 則只保留最近 20 則）、缺少 API Key 時正確短路不
   浪費 API 呼叫；`summarize_conversation_to_faq_drafts()` 正確解析
   ```json``` 包裹的回應、捨棄缺少問題或回答的不完整草稿、超過 5
   則正確截斷。
2. PHP 單元測試：`create_from_admin_chat()` 正確建立停用／待審核、
   來源為 `ai_chat` 的 FAQ，分類／關鍵字留空時正確自動建議、已提供
   時原樣保留（不被覆蓋），問題或回答為空時正確拒絕建立。
3. PHP 整合測試：`UR_AI_Admin_Chat_Ajax` 三個 AJAX 處理器（傳送訊息
   ／產生總結草稿／儲存草稿）在模擬真實 `$_POST` 內容下，端對端
   回傳正確的 JSON 成功／失敗結構；權限不足或 nonce 驗證失敗時，
   正確在呼叫任何 AI／資料庫邏輯前就短路回傳錯誤。
4. 真實瀏覽器測試（Chromium 無頭瀏覽器）：載入真實渲染的
   `ai-chat-page.php` 頁面與真實的 `ai-chat.js`，攔截 `$.post` 模擬
   伺服器回應，實際操作「輸入訊息→按傳送→出現對話泡泡→按產生總結
   草稿→出現可編輯的草稿卡片→按加入知識庫→顯示已儲存」完整流程，
   確認每一步送出的 AJAX 內容與畫面呈現都正確——這一層測試特別
   重要，因為先前 v1.24.1／v1.24.2 修正的巢狀表單問題就是只靠 PHP
   測試完全無法發現、必須用真實瀏覽器才能重現的錯誤類型。

全部情境通過。`php -l`（全部新增／修改的 PHP 檔案）與 `node --check`
（`ai-chat.js`）皆確認語法正確。

四、是否建議上正式站

建議更新。這是全新的後台功能，不影響任何現有頁面或既有功能的行為；
沒有設定 OpenAI API Key 的網站，「AI 對話」頁會顯示明確提示並停用
輸入欄位，不會出現無法預期的錯誤。

---

## v1.27.0

更新日期
2026-07-16

版本定位

回應「文章利用 AI 來生成的可行性」的討論。使用者詢問是否可行後，
確認採用建議的方向：不接受管理者憑空輸入主題／大綱生成文章，而是
挑選一則已經人工審核過的 FAQ，請 AI 把簡短問答「擴寫」成一篇完整
的 WordPress 文章草稿——降低內容失真的風險，也順便幫內容量還不多
的產業別（例如新試點的地政士）快速把既有 FAQ 轉成較完整的文章。

一、主要新增

1. `UR_AI_OpenAI_Client` 新增 `generate_article_from_faq($question,
   $answer)`：使用專屬的文章擴寫系統提示詞（沿用目前啟用中產業別的
   品牌名稱），明確要求「只能根據提供的 FAQ 內容擴充說明，不可以
   捏造 FAQ 沒有提到的具體法規名稱、稅率、金額或期限」，遇到需要
   進一步說明但 FAQ 沒有依據的地方，要求 AI 用「應洽詢專業人士確認」
   帶過而非編造答案。要求 AI 以嚴格 JSON 格式（`{"title":...,
   "content":...}`）回傳，解析方式（含處理 ```json``` fenced code
   block）比照既有的知識大考驗出題功能與 AI 對話總結功能。
2. 新增 `UR_AI_FAQ_Article_Service::create_from_faq($faq_id)`：查出
   指定 FAQ 的問答內容，呼叫上述 AI 方法取得標題與內文，並以
   `wp_insert_post()` 建立一篇**狀態為「草稿」**的 WordPress 文章
   （`post_type=post`），文章內容結尾固定附加一段提醒文字（標示
   本文由 AI 依哪則 FAQ 草擬產生、發布前需人工核對事實正確性），
   並透過 `meta_input` 記錄來源 FAQ ID 與「AI 產生」標記，方便日後
   追蹤。文章一律不會自動發布，需管理者在 WordPress 文章編輯畫面
   人工審核、編輯後才會上線。
3. 新增 `UR_AI_FAQ_Ajax`（AJAX action：
   `ur_ai_generate_article_from_faq`），只在後台已登入且具備 FAQ
   管理權限時可呼叫，沿用既有的 `UR_AI_Permissions::ajax_require
   ('faqs')` 與 `UR_AI_Security::ajax_verify_admin_nonce_or_die()`。
4. 「FAQ 知識庫管理」頁每筆 FAQ 的操作列新增「產生文章草稿」按鈕，
   按下後會先跳出確認對話框（提醒會呼叫 AI API、費用需自行負擔、
   文章以草稿狀態建立不會自動發布），確認後以 AJAX 方式呼叫，成功
   後在新分頁開啟該篇文章的編輯畫面，方便管理者立即核對與編輯。

二、設計原則

- 這個按鈕刻意做成純 AJAX 觸發的 `<button type="button">`，不是
  `<form>` 提交——v1.24.1／v1.24.2 修正的巢狀表單問題（每列操作
  的小表單被巢狀包在批次表單裡，導致瀏覽器悄悄丟棄巢狀 `<form>`
  標籤、同名欄位互相覆蓋）已經證明「每列操作」很容易不小心用表單
  重新踩到同一類問題；改用 AJAX 按鈕從架構上完全避開巢狀表單的
  風險，也不需要頁面重新整理。
- 只從已經人工審核過的 FAQ 內容延伸，不接受管理者自行輸入主題／
  大綱憑空生成——與外掛既有「AI 產生的內容一律需人工審核」「AI
  只是輔助草擬，最終品質仍由經營者把關」的一貫原則一致，同時降低
  文章內容出現未經查證的具體數字、法規名稱的風險。
- 產生的文章一律以「草稿」狀態寫入資料庫，不會自動發布；這是外掛
  第一次使用 `wp_insert_post()` 建立實際文章，因此刻意選擇風險
  最低的路徑（草稿、不自動上線），與既有「FAQ 草稿」「知識大考驗
  題目草稿」等其他 AI 輔助功能一律先進入待審核狀態的模式一致。

三、測試方式

1. PHP 單元測試：`generate_article_from_faq()` 正確組出含產業別
   品牌名稱的系統提示詞、正確解析（含處理 ```json``` fenced code
   block）AI 回傳的 JSON、AI 回傳非 JSON 格式時正確捨棄並回傳
   明確錯誤（不中斷整個請求）、問題或回答為空時在呼叫 API 前就
   短路、缺少 API Key 時正確短路不浪費 API 呼叫。
2. PHP 單元測試：`UR_AI_FAQ_Article_Service::create_from_faq()` 正確
   建立狀態為「草稿」、`meta_input` 正確記錄來源 FAQ ID 與 AI 產生
   標記的文章；找不到指定 FAQ、FAQ 問題或回答為空、FAQ ID 不正確時
   都正確拒絕並回傳明確錯誤；`wp_insert_post()` 回傳 `WP_Error` 或
   falsy 值時都能正確處理而不會拋出未攔截的例外。
3. PHP 整合測試：`UR_AI_FAQ_Ajax::handle_generate_article()` 在模擬
   真實 `$_POST` 內容下，端對端回傳正確的 JSON 成功／失敗結構
   （含 `post_id`／`edit_url`）；權限不足或 nonce 驗證失敗時，正確
   在呼叫任何 AI／資料庫邏輯前就短路回傳錯誤。
4. 逐行檢視新增的按鈕標記，確認它是「FAQ 知識庫」列操作區塊中
   刪除表單之後的獨立同層級元素，不是巢狀在任何 `<form>` 內部，
   與 v1.24.1／v1.24.2 的修正原則一致；新增的 `admin.js` 事件處理
   函式沿用既有「複製問題」按鈕相同的純 AJAX／無表單提交模式。

全部情境通過。`php -l`（全部新增／修改的 PHP 檔案）與 `node --check`
（`admin.js`）皆確認語法正確。

四、是否建議上正式站

建議更新。新增的按鈕、AJAX 處理器與服務類別皆為全新程式碼，不修改
任何既有頁面既有功能的行為；沒有設定 OpenAI API Key 的網站，按下
按鈕會顯示明確錯誤提示，不會出現無法預期的錯誤或建立空白文章。

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