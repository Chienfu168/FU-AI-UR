# UR AI Gateway 安全與成本：體檢項目追蹤

本文件追蹤一份 FU-AI-UR 系統體檢報告中，**與線上收費代管服務（gateway）
成本與安全相關**的項目及其處理進度。gateway 是安裝在服務提供站
（ur-promoter.com）、正在線上收費的獨立服務，因此這些項目分批、謹慎處理：
每一批只提交到開發分支並附驗證，**是否實際部署由服務提供者決定時機**。

> 本表的項目代號沿用原體檢報告（A、B、…）。「位置」欄標示該項目主要
> 落在 gateway 外掛，或客戶端 `ur-ai-assistant` 外掛。

狀態圖例：✅ 已完成（在分支上）／🔜 已排入後續批次／💤 待評估／
👤 需服務提供者在外部系統處理。

---

## 第一批（v1.1.0，已完成，待部署）

| 代號 | 項目 | 位置 | 狀態 | 說明 |
|------|------|------|------|------|
| A | 轉發請求成本防護（拒 stream、限 max_tokens／messages／字元數） | gateway | ✅ | `Request_Validator`，寬鬆非破壞預設，可於設定頁調整 |
| G | model 白名單 | gateway | ✅ | 併入 `Request_Validator`，預設空＝不限制（opt-in） |
| B | 每日額度原子扣用（防併發衝破上限） | gateway | ✅ | `try_consume_daily()` 單一條件式 UPDATE |
| F | token 用量記錄（計費／毛利依據） | gateway | ✅ | 新增 `ur_ai_gateway_usage` 表＋授權碼 `total_tokens` 累計 |
| E | OpenAI 金鑰改放 wp-config.php 常數 | gateway | ✅ | `UR_AI_GATEWAY_OPENAI_KEY`，金鑰不落資料庫；向下相容 |

驗證：25 項 harness 檢查全數通過（見 changelog v1.1.0）。

---

## 後續批次（尚未動工，依序評估）

| 代號 | 項目 | 位置 | 狀態 | 備註 |
|------|------|------|------|------|
| 0 | OpenAI 後台設定每月用量上限（硬性成本天花板） | 外部 | 👤 | 服務提供者在 OpenAI dashboard 操作，與外掛軟性防護互補；最優先 |
| D | REST 端點 IP 層節流（擋未帶有效授權碼的洪水請求） | gateway | 🔜 | 授權碼驗證前先做輕量節流，降低被刷 DB／OpenAI 的風險 |
| C | 授權碼雜湊儲存＋site_url 綁定＋金鑰輪替 | gateway | 🔜 | 授權碼目前明文存 DB；綁定來源站可降低外洩後被盜用 |
| J | FAQ／常見問答回應快取 | 兩者 | 💤 | 降低重複問題的 OpenAI 呼叫成本 |
| H | 已棄用函式清理 | 兩者 | 💤 | PHP 8.4 相容性維護 |
| I | readme「Tested up to」版本更新 | 兩者 | 💤 | 上架資訊維護 |

---

## 不在 gateway 範圍（客戶端外掛 ur-ai-assistant）

體檢報告另有數項落在客戶端外掛（市場行情排名 M／N 的命名與免責、
計算器免責、實價登錄資料授權 O、FAQ 向量比對等）。這些與 gateway 的
收費與成本無直接關係，另行於 `ur-ai-assistant` 處理，不在本表追蹤。

---

## 原則備忘

- gateway 與客戶端 `ur-ai-assistant` 兩個外掛**始終保持分離**，不耦合、
  不合併；修 gateway 自身的 bug 不受此限。
- 每一批修補完成後只推到開發分支並附驗證，**部署時機由服務提供者決定**。
- 合併進主幹僅在服務提供者明確指示時才進行。
