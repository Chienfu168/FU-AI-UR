完整內容如下：

# UR AI Assistant 開發者維護筆記

本文件作為 UR AI Assistant 外掛後續維護、擴充與交接使用。

UR AI Assistant 是一套 WordPress 外掛，核心設計方向為：

```text
FAQ 知識庫優先
AI 回答補位
降低 API 成本
提升回答穩定度
保留問答紀錄
持續沉澱知識庫
一、外掛定位

UR AI Assistant 主要用於「都更危老資訊平台」。

前台使用者可以輸入都市更新、危老重建、更新會、自主更新、權利變換、協議合建等相關問題。

系統流程：

使用者提問
↓
FAQ 知識庫比對
↓
FAQ 命中：回傳固定回答
↓
FAQ 未命中：呼叫 AI API
↓
寫入問答紀錄
↓
顯示相關頁面推薦
↓
蒐集使用者回饋
↓
後台整理成 FAQ
二、核心開發原則
1. 不讓所有問題直接進 AI

本外掛不是單純 AI Chatbot。

核心策略是：

FAQ 固定回答優先
AI 回答只作為補位

原因：

降低 API 成本
提高回答穩定度
避免 AI 回答過度發散
讓高頻問題逐步沉澱成固定知識庫
2. 正式站更新採保守策略

本外掛可能安裝於公開網站，因此更新時應避免一次大量修改。

建議原則：

先測試站
後正式站
先備份
再更新
一次只更新一個階段
遇到錯誤先回穩定版
3. 模組分離，但不要過度分離

目前採模組化架構，但仍應避免過度抽象。

建議分層：

Module：註冊 hooks 與啟動模組
Admin：處理後台操作
Service：整理商業邏輯
Repository：資料庫操作
Schema：資料表結構
View：畫面輸出
Assets：CSS / JS 載入
Shared：共用工具
三、主要資料夾結構
ur-ai-assistant/
├── ur-ai-assistant.php
├── includes/
│   ├── core/
│   ├── database/
│   ├── integrations/
│   ├── modules/
│   └── shared/
├── admin/
│   ├── assets/
│   └── pages/
├── public/
│   ├── assets/
│   └── views/
├── templates/
├── languages/
├── docs/
├── uninstall.php
└── readme.txt
四、主要模組說明
1. Core

核心啟動與模組管理。

常見檔案：

includes/core/class-ur-ai-assistant.php
includes/core/class-ur-ai-module-manager.php

負責：

載入模組
註冊 hooks
初始化外掛
協調前台、後台、AJAX、資料庫模組
2. Admin

後台頁面與後台資源。

常見檔案：

admin/pages/dashboard-page.php
admin/pages/settings-page.php
admin/pages/faq-page.php
admin/pages/logs-page.php
admin/pages/related-pages-page.php
admin/pages/popular-questions-page.php
admin/pages/feedback-page.php
admin/assets/css/admin.css
admin/assets/js/admin.js

注意：

後台頁面只負責畫面輸出
不要在 page 檔直接寫大量 SQL
資料查詢應透過 Admin / Service / Repository
3. Public

前台 shortcode、前台畫面與前台資源。

常見檔案：

includes/modules/public/class-ur-ai-public-module.php
includes/modules/public/class-ur-ai-public-assets.php
includes/modules/public/class-ur-ai-shortcode.php
includes/modules/public/class-ur-ai-faq-kb-page-shortcode.php
public/views/assistant-view.php
public/views/faq-kb-page-view.php
public/assets/css/public.css
public/assets/js/public.js

負責：

註冊 [ur_ai_assistant]
註冊 [ur_ai_faq_kb_page]（v1.7.0 新增，獨立 SEO 查詢頁，純伺服器端渲染，不載入 public.js）
載入前台 CSS / JS
輸出前台 AI 助理畫面
處理熱門問題點擊
處理使用者回饋
4. AJAX

前台問答 AJAX 入口。

常見檔案：

includes/modules/ajax/class-ur-ai-ajax-module.php

主要 action：

ur_ai_ask
ur_ai_feedback
ur_ai_related_page_click
ur_ai_popular_question_click

注意：

所有前台 AJAX 都必須驗證 nonce
所有輸入都必須 sanitize
所有輸出都必須 wp_send_json_success / wp_send_json_error
5. Assistant

AI 問答核心。

常見檔案：

includes/modules/assistant/class-ur-ai-assistant-module.php
includes/modules/assistant/class-ur-ai-answer-service.php

流程：

接收問題
找相關頁面
先試 FAQ
FAQ 未命中才試 AI
建立問答紀錄
回傳前台資料
6. FAQ

FAQ 知識庫模組。

常見檔案：

includes/modules/faq/class-ur-ai-faq-module.php
includes/modules/faq/class-ur-ai-faq-repository.php
includes/modules/faq/class-ur-ai-faq-service.php
includes/modules/faq/class-ur-ai-faq-matcher.php
includes/modules/faq/class-ur-ai-faq-admin.php
includes/modules/faq/class-ur-ai-faq-draft-service.php
includes/modules/faq/class-ur-ai-faq-category-helper.php

分工：

FAQ Module：註冊後台操作
FAQ Repository：SQL
FAQ Service：資料整理與格式化
FAQ Matcher：前台問題比對
FAQ Admin：後台新增、更新、刪除、匯出
FAQ Draft Service：AI 問答或熱門問題轉 FAQ 草稿
FAQ Category Helper：自動分類與關鍵字建議
7. Logs

問答紀錄模組。

常見檔案：

includes/modules/logs/class-ur-ai-logs-module.php
includes/modules/logs/class-ur-ai-log-repository.php
includes/modules/logs/class-ur-ai-log-service.php
includes/modules/logs/class-ur-ai-log-admin.php

負責：

紀錄問題
紀錄回答
紀錄回答來源
紀錄 FAQ 命中分數
紀錄 token 使用量
紀錄使用者回饋
紀錄錯誤訊息
提供 CSV 匯出
支援轉 FAQ 草稿
8. Related Pages

相關頁面推薦模組。

用途：

在回答後推薦網站內相關文章或頁面
提升網站內容導流
降低使用者只問 AI 不看文章的情況

建議資料來源：

手動建立
WordPress 文章匯入
WordPress 頁面匯入
9. Popular Questions

熱門問題模組。

用途：

前台提供「你可以這樣問」
降低使用者提問門檻
引導使用者問到高品質問題
統計高點擊問題
協助補 FAQ
10. Shared

共用工具。

常見檔案：

includes/shared/class-ur-ai-settings.php
includes/shared/class-ur-ai-security.php
includes/shared/class-ur-ai-permissions.php
includes/shared/class-ur-ai-helper.php
includes/shared/class-ur-ai-formatter.php
includes/shared/class-ur-ai-exporter.php

注意：

共用工具應保持穩定
避免在 Shared 中加入特定頁面的複雜邏輯
五、資料庫設計

目前主要資料表：

ur_ai_faqs
ur_ai_logs
ur_ai_related_pages
ur_ai_popular_questions

資料表前綴依 WordPress 網站而定。

例如：

wp_ur_ai_faqs
abc_ur_ai_faqs
六、資料表用途
1. ur_ai_faqs

儲存固定 FAQ 回答。

重要欄位：

category
question
answer
keywords
status
source
source_log_id
review_status
sort_order
hit_count
2. ur_ai_logs

儲存問答紀錄。

重要欄位：

question
answer
answer_source
model
tokens_used
faq_id
faq_match_score
faq_matched_keywords
related_page_ids
converted_faq_id
feedback
feedback_reason
status
error_code
error_message
3. ur_ai_related_pages

儲存相關頁面推薦。

重要欄位：

category
title
url
description
keywords
status
source
wp_post_id
show_count
click_count
4. ur_ai_popular_questions

儲存前台熱門問題。

重要欄位：

category
question
submit_question
description
status
source
faq_id
sort_order
click_count
七、FAQ 命中邏輯

FAQ 命中由：

UR_AI_FAQ_Matcher

處理。

基本比對來源：

FAQ question
FAQ keywords
FAQ category
重要詞
相似度

保護機制：

短問題懲罰
泛用關鍵字懲罰
最低分數門檻

預設最低命中分數：

45

可用 filter 調整：

add_filter('ur_ai_faq_min_match_score', function () {
    return 55;
});

如果正式站 FAQ 誤命中太多，可提高到：

55 或 60
八、AI API 串接

OpenAI 串接檔案：

includes/integrations/openai/class-ur-ai-openai-client.php

預設 endpoint：

https://api.openai.com/v1/chat/completions

預設模型：

gpt-4o-mini

預設 temperature：

0.3

預設 max tokens：

1200

注意：

API Key 不應寫死在程式中
應由後台設定儲存
不應提交到 GitHub 公開倉庫
九、前台安全注意事項

前台送出問題時：

必須驗證 nonce
必須限制字數
必須檢查每日提問上限
必須 sanitize question
不得直接輸出未清理 HTML

AI 回答輸出時：

先 esc_html
再做簡易 Markdown 格式化
最後 wp_kses_post

避免 AI 回答中夾帶 script 或不安全 HTML。

十、後台安全注意事項

後台所有表單應包含：

UR_AI_Security::admin_form_nonce_field();

處理 POST 時應驗證：

UR_AI_Security::verify_admin_form_nonce_or_die();

後台權限檢查應使用：

UR_AI_Permissions::require_manage_faqs();
UR_AI_Permissions::require_view_logs();
UR_AI_Permissions::require_manage_settings();
十一、CSV 匯出注意事項

CSV 匯出統一使用：

UR_AI_Exporter::output_csv($filename, $headers, $rows);

匯出前必須驗證：

UR_AI_Exporter::verify_export_request_or_die();

CSV 會輸出 UTF-8 BOM，降低 Excel 中文亂碼機率。

十二、解除安裝原則

uninstall.php 預設：

刪除設定
刪除資料庫版本紀錄
保留 FAQ / Logs / Related Pages / Popular Questions 資料表

原因：

避免正式網站資料被誤刪

若未來後台增加「解除安裝時刪除所有資料」選項，可透過：

delete_data_on_uninstall = 1

控制是否刪除資料表。

十三、命名規則
1. Class 命名

使用：

UR_AI_功能_類型

例如：

UR_AI_FAQ_Service
UR_AI_Log_Repository
UR_AI_OpenAI_Client
UR_AI_Public_Module
2. 檔案命名

使用 WordPress 常見格式：

class-ur-ai-xxx.php

例如：

class-ur-ai-faq-service.php
class-ur-ai-log-repository.php
3. AJAX action 命名

使用：

ur_ai_xxx

例如：

ur_ai_ask
ur_ai_feedback
ur_ai_related_page_click
ur_ai_popular_question_click
4. Option 命名

使用：

ur_ai_assistant_xxx

例如：

ur_ai_assistant_settings
ur_ai_assistant_db_version
十四、未來擴充方向
1. CSV 匯入

可新增：

FAQ CSV 匯入
Related Pages CSV 匯入
Popular Questions CSV 匯入
2. REST API

可新增 REST API endpoint：

/wp-json/ur-ai/v1/ask

但要注意權限、nonce、rate limit。

3. 多 AI 供應商

目前 OpenAI Client 獨立於 integrations，可未來新增：

Claude
Gemini
Ollama
自架 LLM
4. FAQ 版本管理

可新增：

FAQ 修改歷程
FAQ 審核流程
FAQ 啟用前預覽
5. 前台 UI 進階功能

可新增：

連續追問
問答歷史
複製回答
分享回答
推薦問題即時搜尋
6. 成本分析

可新增：

每日 token 統計
AI 成本估算
FAQ 節省 API 次數
高成本問題分析
十五、正式站維護建議

每週檢查：

沒幫助回饋
AI 回答高頻問題
FAQ 命中錯誤
API 錯誤紀錄
Token 使用量

每月整理：

高頻 AI 問題轉 FAQ
低 CTR 推薦頁面修正
熱門問題更新
過時 FAQ 檢查

每次更新前：

備份資料庫
備份外掛資料夾
測試站驗證
逐項跑 testing-checklist
十六、已知風險
1. Class 未載入

若主檔或 autoloader 未載入新檔案，會發生：

Class not found

因此新增檔案後，需檢查 autoload 或 require 清單。

2. 資料表未建立

若 schema class 未載入，可能導致資料表未建立。

需確認：

UR_AI_Schema_Manager::install()

執行時 schema class 已經存在。

3. AJAX action 未註冊

若前台 JS 呼叫 action，但 PHP 未註冊，會回傳：

0

或無效 JSON。

4. FAQ 誤命中

若 FAQ 關鍵字太泛，可能導致回答不準。

建議：

使用具體關鍵字
避免只填「都更、危老、重建」
提高 min_match_score
定期檢查 Logs
5. 正式站白畫面

常見原因：

PHP 語法錯誤
class 重複宣告
method 不存在
檔案路徑錯誤
PHP 版本不支援語法

處理方式：

立即回復上一個穩定版本
查看 wp-content/debug.log
在測試站重現並修正
十七、開發結論

UR AI Assistant 的長期方向不是單純「讓 AI 回答問題」，而是建立一套可維護、可沉澱、可降低成本的知識型 AI 助理。

核心維護方向：

FAQ 越完整，AI 成本越低
Logs 越完整，知識庫越能進化
回饋越清楚，回答品質越穩定
模組越清楚，正式站風險越低

---

## 這個檔案的設計重點

### 1. 給未來維護者快速理解架構

這份文件可以讓接手者快速知道：哪些檔案負責哪些功能。

### 2. 強調正式站風險控管

尤其是：

```text
Class not found
資料表未建立
AJAX 未註冊
FAQ 誤命中
白畫面

這些都列入開發注意事項。

3. 作為後續版本規劃基礎

下一版要加功能時，可以先看這份文件，避免把功能放錯位置。