=== UR AI Gateway ===
Contributors: urpromoter
Tags: ai, openai, woocommerce, subscriptions, saas
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

「UR AI Assistant」外掛的代管 AI 服務授權與代理外掛。

== Description ==

這個外掛安裝在**服務提供者自己的網站**（例如 ur-promoter.com），不是安裝在客戶端網站上，用途是：

1. 依 WooCommerce／WooCommerce Subscriptions 的訂單與訂閱狀態，自動發放、暫停、終止授權碼。
2. 提供一個 REST API 端點（`/wp-json/ur-ai-gateway/v1/chat`），代理客戶端網站對 OpenAI 的呼叫——客戶端的「UR AI Assistant」外掛只要在「功能設定」頁選擇「使用代管服務」、填入這個端點網址與自己的授權碼，就不需要自行申請 OpenAI API Key。

= 設計原則 =

* 這個外掛完全不處理收費本身：金流、扣款排程、發票都交給 WooCommerce ＋ WooCommerce Subscriptions ＋ 金流外掛（例如綠界）處理，這裡只在訂閱狀態變化時決定授權碼要不要開通／暫停。
* REST 代理端點刻意做成「透明代理」：成功與失敗的回應都維持與 OpenAI 官方 API 相同的 JSON 格式，客戶端既有的 `UR_AI_OpenAI_Client` 解析邏輯完全不用修改就能相容。
* 每組授權碼有獨立的每日呼叫次數上限，避免單一客戶用量異常影響其他客戶或造成 OpenAI 費用失控。

== Installation ==

1. 上傳並啟用本外掛。
2. 到「AI 代管服務」→「設定」頁，填入服務提供者自己的 OpenAI API Key，以及新授權碼的預設每日呼叫上限。
3. 若有安裝 WooCommerce Subscriptions，設定好對應的訂閱方案商品即可；訂閱進入 active 狀態時會自動建立授權碼並寄信通知客戶。
4. 也可以在「授權碼管理」頁手動建立授權碼（測試或特殊個案使用）。
5. 把 REST 端點網址（`設定」頁會顯示完整網址）與客戶的授權碼提供給客戶，讓客戶在「UR AI Assistant」外掛的「功能設定」頁填入並切換「使用代管服務」。

== Changelog ==

= 1.0.0 =
* 首次發布：授權碼資料表與管理頁、WooCommerce Subscriptions 狀態同步、REST API 代理端點（相容 OpenAI 官方回應格式）、每日用量上限。
