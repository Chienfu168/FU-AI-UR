=== UR AI Assistant ===
Contributors: ur-promoter
Tags: ai, chatbot, faq, urban renewal, wordpress
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

UR AI Assistant 是一套以 FAQ 知識庫優先、AI 回答輔助的 WordPress 前台問答外掛，主要用於都更危老資訊平台。

== Description ==

UR AI Assistant 是一套專為「都更危老資訊平台」設計的 WordPress AI 問答外掛。

本外掛設計重點不是讓所有問題都直接送 AI API，而是採用「FAQ 知識庫優先」的架構：

1. 使用者在前台提問。
2. 系統先比對後台 FAQ 知識庫。
3. FAQ 命中時，優先使用固定回答。
4. FAQ 未命中時，才呼叫 AI API。
5. 回答後可顯示相關頁面推薦。
6. 問答紀錄可供後續轉成 FAQ 草稿。
7. 使用者回饋可協助改善知識庫與提示詞。

這樣可以降低 API 成本，並提升回答穩定度。

= 主要功能 =

* 前台 AI 問答 shortcode。
* FAQ 知識庫管理。
* FAQ 優先命中回答。
* 知識庫瀏覽（獨立搜尋／分類篩選常見問題，不需先問 AI）。
* AI API 回答補位。
* 問答紀錄管理。
* AI 問答轉 FAQ 草稿。
* 相關頁面推薦。
* 熱門問題管理。
* 使用者回饋分析。
* Token 使用量紀錄。
* CSV 匯出。
* 每日提問限制。
* 後台權限控管。
* 模組化程式架構。

= 適合用途 =

* 都市更新資訊平台。
* 危老重建知識平台。
* 社區自主更新教育網站。
* 不動產重建常見問題網站。
* FAQ 知識庫型 AI 助理。
* 需要降低 AI API 成本的網站問答系統。

== Installation ==

1. 將 `ur-ai-assistant` 資料夾上傳至 WordPress 的 `/wp-content/plugins/` 目錄。
2. 至 WordPress 後台「外掛」頁面啟用 `UR AI Assistant`。
3. 啟用後系統會建立必要資料表。
4. 至外掛設定頁輸入 OpenAI API Key。
5. 建立或匯入 FAQ 知識庫。
6. 在需要顯示 AI 助理的頁面加入 shortcode：

`[ur_ai_assistant]`

= Shortcode =

基本用法：

`[ur_ai_assistant]`

可選參數：

`[ur_ai_assistant title="都更危老 AI 助理" show_popular="1" show_groups="0" popular_limit="6"]`

參數說明：

* `title`：自訂前台標題。
* `subtitle`：自訂前台副標題。
* `show_popular`：是否顯示熱門問題，1 為顯示，0 為不顯示。
* `show_groups`：是否顯示分類熱門問題。
* `popular_limit`：熱門問題顯示數量。
* `group_limit`：每組分類熱門問題顯示數量。
* `placeholder`：自訂輸入框提示文字。
* `show_kb_browse`：是否顯示知識庫瀏覽區塊（需另於後台「功能設定」啟用知識庫瀏覽，此參數才有作用），1 為顯示，0 為不顯示。
* `kb_browse_limit`：知識庫瀏覽每頁筆數，留空則採用後台設定值。

== Frequently Asked Questions ==

= 這個外掛會自動回答所有問題嗎？ =

不會。系統會先比對 FAQ 知識庫，FAQ 未命中時才呼叫 AI API。

= 沒有設定 OpenAI API Key 可以使用嗎？ =

可以使用 FAQ 固定回答功能，但 FAQ 未命中時無法產生 AI 回答。

= AI 回答會直接變成 FAQ 嗎？ =

不會。AI 回答可由管理員手動轉成 FAQ 草稿，檢查後再啟用。

= 使用者回饋有什麼用途？ =

使用者可以標示回答是否有幫助。後台可依回饋分析哪些 FAQ 或 AI 回答需要改善。

= 刪除外掛時會刪除資料表嗎？ =

預設不會。為避免正式資料被誤刪，解除安裝時預設保留 FAQ、Logs、Related Pages、Popular Questions 與計算機名單資料表。

== Screenshots ==

1. 前台 AI 助理問答畫面。
2. FAQ 知識庫管理。
3. 問答紀錄管理。
4. 相關頁面推薦管理。
5. 熱門問題管理。
6. 回饋分析頁。

== Changelog ==

= 1.6.0 =
* 新增前台「知識庫瀏覽」功能：可直接搜尋／分類篩選並瀏覽已啟用的常見問題，不需先向 AI 提問、也不經過 AI 比對演算法。
* 後台新增「知識庫瀏覽」開關與每頁筆數設定（預設關閉，需手動啟用）。

= 1.5.1 =
* 修正解除安裝時漏刪計算機名單表（含個資）的問題。
* 修正 CF7 留資料寫入失敗被靜默忽略、進階試算漏傳個人持分、相關頁面關鍵字匯入未讀取文章內容等問題。
* 計算機每小時流量限制改為原子操作，避免高並發下被繞過。
* 相關頁面推薦查詢加入快取；文章匯入／搜尋改為批次查詢，改善效能。
* CF7 留資料欄位名稱改為後台可調。

= 1.5.0 =
* 土地面積輸入整合為「立即試算」與「進階試算」共用區塊，免重複填寫。
* 樓層／高度概估開放給「立即試算」使用，不再僅限進階模式。
* 試算結果坪數統一改為固定顯示到小數第 2 位。

= 1.4.1 =
* 列印版面壓縮，多數試算結果可收在 A4 一頁內；核心數字與公式拆解維持不變。

= 1.4.0 =
* 進階評估新增「樓層／高度概估」。
* 新增「土地持分輸入方式」雙軌選擇（持分坪數／基地面積＋持分比例）。

= 1.3.4 =
* FAQ 知識庫新增 CSV 匯入（upsert），可與既有匯出功能往返使用。

= 1.3.3 =
* 前台 AI 助理問答新增「單則問答友善列印」功能。

= 1.3.0 ~ 1.3.2 =
* 試算結果新增分享連結（LINE／FB／複製連結），並修正連結遺失原頁面 query string 的問題。

= 1.2.x =
* 試算器新增「進階評估：三案擇優」（依三種容積獎勵路徑取最有利者）。

= 1.1.x =
* 新增「都更分回效益試算器」模組（含後台設定頁、CF7 留資料整合）。
* 安全與效能強化：每日提問計數改為原子操作、IP 偽造防護、FAQ 查詢加入快取、OpenAI 呼叫加入重試機制。

= 1.0.0 =
* 建立模組化外掛架構。
* 新增前台 AI 助理 shortcode。
* 新增 FAQ 知識庫管理。
* 新增 FAQ 優先命中回答。
* 新增 OpenAI API 串接。
* 新增問答紀錄管理。
* 新增 AI 問答轉 FAQ 草稿。
* 新增相關頁面推薦。
* 新增熱門問題管理。
* 新增使用者回饋分析。
* 新增 CSV 匯出工具。
* 新增資料庫 Schema 管理。
* 新增每日提問限制。
* 新增前台 CSS / JS。
* 新增解除安裝清理檔。

== Upgrade Notice ==

= 1.6.0 =
新增知識庫瀏覽功能，預設關閉。若要啟用，請至「功能設定 → 知識庫瀏覽」勾選開啟。

= 1.5.1 =
建議更新。修正一項解除安裝漏刪個資表的問題，以及數項計算機／相關頁面推薦的正確性與效能問題。

= 1.5.0 =
建議先於測試站確認試算器共用輸入區塊與樓層估算顯示正常，再上傳正式網站。

= 1.0.0 =
初始正式模組化版本。建議先於測試站安裝確認，再上傳正式網站。

== License ==

This plugin is licensed under the GPLv2 or later.

UR AI Assistant is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation.