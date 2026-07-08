完整內容如下：

# UR AI Assistant 安裝與啟用說明

UR AI Assistant 是一套以「FAQ 知識庫優先、AI 回答補位」為核心設計的 WordPress AI 問答外掛。

本文件說明如何安裝、啟用、設定與初步測試外掛。

---

## 一、系統需求

建議環境：

- WordPress 6.0 以上
- PHP 7.4 以上
- MySQL / MariaDB
- 支援 WordPress 自訂資料表
- 網站可正常執行 `admin-ajax.php`
- 若要使用 AI 回答，需具備 OpenAI API Key

---

## 二、資料夾位置

外掛資料夾應放在：

```text
wp-content/plugins/ur-ai-assistant/

主要結構如下：

ur-ai-assistant/
├── ur-ai-assistant.php
├── includes/
├── admin/
├── public/
├── templates/
├── languages/
├── docs/
├── uninstall.php
└── readme.txt
三、安裝方式
方式一：FTP / 主機檔案管理器上傳
將完整的 ur-ai-assistant 資料夾上傳至：
wp-content/plugins/
登入 WordPress 後台。
前往：
外掛 > 已安裝外掛
找到：
UR AI Assistant
點選「啟用」。
方式二：ZIP 上傳
將 ur-ai-assistant 資料夾壓縮成：
ur-ai-assistant.zip
登入 WordPress 後台。
前往：
外掛 > 安裝外掛 > 上傳外掛
上傳 ZIP 檔。
安裝完成後點選「啟用」。
四、啟用後會建立的資料表

外掛啟用時，會建立以下資料表：

wp_ur_ai_faqs
wp_ur_ai_logs
wp_ur_ai_related_pages
wp_ur_ai_popular_questions

如果 WordPress 資料表前綴不是 wp_，實際名稱會依網站前綴不同而改變，例如：

abc_ur_ai_faqs
abc_ur_ai_logs
abc_ur_ai_related_pages
abc_ur_ai_popular_questions
五、啟用後基本設定

啟用後，建議先進行以下設定：

1. OpenAI API Key

若要使用 AI 回答，需要在設定頁輸入 OpenAI API Key。

若尚未設定 API Key，系統仍可使用 FAQ 固定回答，但 FAQ 未命中時無法產生 AI 回答。

2. AI 模型

預設模型：

gpt-4o-mini

可依成本與回答品質需求調整。

3. 前台標題與說明

預設前台標題：

都更危老 AI 助理

預設副標題：

用白話方式，快速了解都市更新、危老重建、更新會、自主更新、權利變換與協議合建等基礎問題。
4. 免責提醒

建議保留免責提醒，避免使用者誤以為 AI 回答可取代律師、估價師、建築師、地政士或其他專業判斷。

5. 每日提問限制

建議正式網站初期設定：

訪客每日 20 次
會員每日 50 次
管理員不限

可依 API 成本與網站流量再調整。

六、前台顯示方式

在 WordPress 頁面、文章或區塊中加入：

[ur_ai_assistant]

即可顯示前台 AI 助理。

七、Shortcode 參數

基本用法：

[ur_ai_assistant]

自訂標題：

[ur_ai_assistant title="都更危老 AI 助理"]

顯示熱門問題：

[ur_ai_assistant show_popular="1"]

顯示分類熱門問題：

[ur_ai_assistant show_groups="1"]

限制熱門問題數量：

[ur_ai_assistant popular_limit="6" group_limit="4"]

完整範例：

[ur_ai_assistant title="都更危老 AI 助理" show_popular="1" show_groups="0" popular_limit="6"]

七之一、FAQ 知識庫查詢頁 Shortcode（SEO 用途，v1.7.0 新增）

與上面的 AI 助理 widget 是完全獨立的 shortcode，不會互相影響，建議另外建立一個獨立頁面
（例如「常見問題」）放這個 shortcode：

[ur_ai_faq_kb_page]

自訂標題與每頁筆數：

[ur_ai_faq_kb_page title="常見問題" per_page="20"]

特色：伺服器端直接輸出問答內容（不需 JavaScript）、搜尋／分類／換頁皆使用網址參數
（?kb_q=、?kb_cat=、?kb_page=），可分享、可被搜尋引擎收錄，並自動輸出 Google 支援的
FAQPage 結構化資料。僅在後台「功能設定」的 FAQ 功能啟用時才會顯示內容。

八、建議初始測試流程

啟用後，建議依序測試：

後台是否可正常開啟。
資料表是否建立成功。
設定頁是否可儲存。
FAQ 是否可新增。
FAQ 啟用後，前台是否能命中固定回答。
FAQ 未命中時，是否能呼叫 AI 回答。
問答紀錄是否有寫入 Logs。
使用者回饋是否可送出。
相關頁面推薦是否顯示。
熱門問題按鈕是否可送出問題。
九、正式網站更新建議

若網站已公開使用，建議採用保守更新流程：

1. 先備份

更新前請備份：

外掛資料夾
WordPress 資料庫

尤其是以下資料表：

ur_ai_faqs
ur_ai_logs
ur_ai_related_pages
ur_ai_popular_questions
2. 先於測試站安裝

建議先在測試站或新 WordPress 空白站測試：

啟用是否正常
後台頁面是否正常
前台 shortcode 是否正常
FAQ 命中是否正常
AJAX 是否正常

確認後再更新正式站。

3. 一次只更新一個版本

不建議在正式站直接跨多個版本大幅更新。

建議：

先小版測試
再正式上傳
確認無誤後再進下一版
十、常見問題
Q1：啟用後網站空白怎麼辦？

可能原因：

PHP 語法錯誤
class 未載入
檔案路徑錯誤
PHP 版本不支援

處理方式：

先透過 FTP 或主機檔案管理器停用外掛。
檢查 wp-content/debug.log。
回復上一個穩定版。
再逐檔檢查新增或修改內容。
Q2：前台 shortcode 沒有顯示？

請檢查：

外掛是否啟用
前台問答功能是否啟用
頁面是否正確加入 [ur_ai_assistant]
public/views/assistant-view.php 是否存在
public CSS / JS 是否載入
Q3：FAQ 沒有命中？

請檢查：

FAQ 狀態是否為啟用
問題與關鍵字是否足夠明確
FAQ Matcher 分數門檻是否過高
使用者問題是否太短
Q4：AI 沒有回答？

請檢查：

OpenAI API Key 是否已設定
主機是否可連外
API 額度是否足夠
模型名稱是否正確
FAQ 未命中時才會呼叫 AI
Q5：刪除外掛會刪除資料嗎？

預設不會刪除 FAQ、Logs、Related Pages、Popular Questions、計算機名單、行情參考資料表。

這是為了避免正式營運資料被誤刪。

十一、建議正式上線前檢查

正式上線前，至少確認：

API Key 已設定
每日提問限制已設定
FAQ 至少建立 10～20 筆基礎資料
前台免責提醒已顯示
問答紀錄可正常寫入
使用者回饋可正常送出
錯誤訊息不會暴露敏感資訊
若有建立 FAQ 知識庫查詢頁（[ur_ai_faq_kb_page]），搜尋／分類／換頁與 FAQPage
結構化資料皆已用 Google 結構化資料測試工具驗證正常
十二、維護建議

建議每週或每月定期檢查：

問答紀錄
沒幫助回饋
高頻 AI 問題
無推薦頁面的問題
高點擊熱門問題
Token 使用量

並把穩定的 AI 回答整理成 FAQ，逐步降低 API 成本。


---

## 這個檔案的設計重點

### 1. 安裝流程給未來維護者看

之後不論是你自己、助理或其他工程人員接手，都能照著流程安裝與測試。

---

### 2. 特別強調正式站保守更新

因為這套外掛已經朝公開網站使用方向發展，正式站更新一定要保守。

---

### 3. 有初始測試清單

安裝文件先提供基本測試，下一個檔案會再做更完整的測試清單。