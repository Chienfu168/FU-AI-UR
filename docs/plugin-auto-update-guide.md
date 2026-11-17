# WordPress 外掛「自架 GitHub 一鍵更新」實作說明

本文件說明本專案（FU-AI-UR）中 **UR AI Assistant** 外掛所採用的「自動更新」
機制，並整理成**可套用到其他外掛**的通用做法與參考。

目標：讓一個**沒有上架 wordpress.org**、原始碼放在自己 GitHub repo 的外掛，
也能在客戶站的後台「外掛」頁看到「有新版本可更新」、按一下就更新，體驗與
官方外掛完全一致。

> 本機制由三個環節組成，缺一不可：
> 1. **外掛端**：內嵌 Plugin Update Checker（PUC）函式庫＋一支 Updater 類別。
> 2. **版本號慣例**：外掛主檔的 `Version:` 標頭是唯一的版本來源。
> 3. **CI 自動發佈**：GitHub Action 在版本號變動時，自動打包 zip 並建立 Release。

---

## 一、整體流程

```
開發者把版本號 Version: 1.x.y 改好 → 合併進 main 分支
        │
        ▼
GitHub Action（.github/workflows/release.yml）在 push 到 main 時觸發
        │ 讀外掛主檔的 Version 標頭 → 該版本還沒有 Release？
        ├─ 有了 → 跳過（不重複發）
        └─ 沒有 → 打包「結構正確」的 zip → 建立 tag vX.Y.Z 與 Release，附上 zip
        │
        ▼
客戶站的外掛內建 PUC 定期（WordPress 更新檢查時）向 GitHub 查最新 Release
        │ 最新 Release 版本 > 目前安裝版本？
        ▼
後台「外掛」頁顯示「有新版本可更新」 → 使用者按「立即更新」
        │ PUC 下載 Release 附加的 .zip → WordPress 解壓覆蓋安裝
        ▼
完成（與 wordpress.org 外掛一致的一鍵更新體驗）
```

**開發者唯一要做的事：改版本號、合併 main。** 其餘全自動，不需手動打 tag 或
建 Release。

---

## 二、為什麼用「GitHub Release 附加 zip」，而不是 tag 原始碼壓縮檔

GitHub 會對每個 tag 自動產生一個「原始碼壓縮檔」。但本專案（也是多數情況）
**外掛檔案放在 repo 的子目錄**（例如 `ur-ai-assistant/`），repo 根目錄還有
其他非外掛內容（另一個外掛、docs、workflow 等）。tag 原始碼壓縮檔會把
**整個 repo 根目錄**包進去，解壓後資料夾結構不對，WordPress 無法正確安裝。

因此改採 **Release 附加檔案（release assets）模式**：由 CI 自行打包一個
**只含外掛子目錄、且頂層資料夾名稱正確**的 zip，附在 Release 上；PUC 設定成
**只採用副檔名為 `.zip` 的附加檔**，忽略 GitHub 自動產生的原始碼壓縮檔。

---

## 三、外掛端實作

### 3.1 內嵌 Plugin Update Checker（PUC）

- 函式庫：**Plugin Update Checker v5**（作者 YahnisElsts，MIT 授權）。
- 位置：外掛目錄下 `vendor/plugin-update-checker/`（整包放進外掛，隨外掛一起
  發佈，客戶站不需另外安裝 composer 套件）。
- 取得：<https://github.com/YahnisElsts/plugin-update-checker>（下載 release 解壓
  到上述路徑即可）。

### 3.2 Updater 類別

以本專案 `includes/core/class-ur-ai-updater.php` 為範本。**換新外掛時只需改
三個地方**（下方以 `★` 標示）：

```php
class UR_AI_Updater {

    // ★(1) 放外掛原始碼與 Release 的 GitHub repo 網址
    const REPO_URL = 'https://github.com/<OWNER>/<REPO>/';

    // ★(2) 外掛 slug（＝外掛資料夾名＝zip 內頂層資料夾名，三者必須一致）
    const SLUG = 'your-plugin-slug';

    public static function init() {
        $library_file = YOUR_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

        if (!file_exists($library_file)) {
            return null;
        }

        require_once $library_file;

        if (!class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
            return null;
        }

        $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            self::REPO_URL,
            YOUR_PLUGIN_FILE, // ★(3) 外掛主檔完整路徑（通常是 __FILE__ 存成的常數）
            self::SLUG
        );

        // 只採用檔名結尾為 .zip 的 Release 附加檔，忽略 GitHub 原始碼壓縮檔。
        if (is_object($update_checker) && method_exists($update_checker, 'getVcsApi')) {
            $vcs_api = $update_checker->getVcsApi();
            if (is_object($vcs_api) && method_exists($vcs_api, 'enableReleaseAssets')) {
                $vcs_api->enableReleaseAssets('/\.zip($|[?&#])/i');
            }
        }

        return $update_checker;
    }
}
```

重點：
- `enableReleaseAssets('/\.zip.../i')` 是關鍵——告訴 PUC「用 Release 附加的
  .zip，不要用原始碼壓縮檔」。
- `buildUpdateChecker()` 的第二個參數是**外掛主檔**；PUC 會自動讀主檔的
  `Version:` 標頭當作「目前安裝版本」，與 Release 版本比較。

### 3.3 初始化時機：掛在 `plugins_loaded`

在外掛啟動流程中呼叫 `Updater::init()`，時機是 **`plugins_loaded`**（本專案在
`UR_AI_Bootstrap::run()` 內呼叫，而 `run()` 由主檔掛在 `plugins_loaded`）：

```php
// 外掛主檔
add_action('plugins_loaded', 'your_plugin_run');
function your_plugin_run() {
    // ...啟動其他模組...
    if (class_exists('UR_AI_Updater')) {
        UR_AI_Updater::init();
    }
}
```

> 為何是 `plugins_loaded` 而非只在後台：這樣 WP-CLI、網站健康檢查等**非後台
> 畫面**的工具也看得到更新資訊，不只是登入後台的使用者。PUC 官方亦建議在
> 一般載入流程（非僅 `is_admin()`）建立實例。

---

## 四、版本號慣例

**外掛主檔的 `Version:` 標頭是唯一的版本來源**，CI 與 PUC 都讀它：

```php
/**
 * Plugin Name: Your Plugin
 * Version: 1.47.0
 * ...
 */
```

- 格式固定為 ` * Version: X.Y.Z`（星號、空格、`Version:`、空格、版本號）——
  CI 用 `grep -m1 '^ \* Version:'` 抓它，格式跑掉會抓不到。
- 若外掛另有版本常數（如 `define('YOUR_PLUGIN_VERSION', '1.47.0')`）或
  `readme.txt` 的 `Stable tag:`，**三者要一起改成同一個版本號**，避免混亂。
- 遵循語意化版本（SemVer）：功能新增升 minor（1.47→1.48），修 bug 升 patch
  （1.47.0→1.47.1）。

---

## 五、CI 自動發佈（`.github/workflows/release.yml`）

放在 **repo 根目錄** `.github/workflows/release.yml`。範本（換外掛時改 `★` 處）：

```yaml
name: Build plugin zip and publish release

on:
  push:
    branches: [ main ]       # 合併進 main 就觸發
  workflow_dispatch: {}      # 也可到 Actions 頁手動觸發

permissions:
  contents: write            # 建立 tag / Release 需要寫入權限

jobs:
  build-and-release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Read plugin version
        id: version
        run: |
          # ★ 外掛主檔路徑
          VERSION=$(grep -m1 '^ \* Version:' your-plugin/your-plugin.php | sed 's/.*Version: *//' | tr -d '[:space:]')
          [ -z "$VERSION" ] && { echo "no version"; exit 1; }
          echo "version=$VERSION" >> "$GITHUB_OUTPUT"
          echo "tag=v$VERSION" >> "$GITHUB_OUTPUT"

      - name: Check whether this version already has a release
        id: check
        env:
          GH_TOKEN: ${{ github.token }}
        run: |
          if gh release view "${{ steps.version.outputs.tag }}" --repo "${{ github.repository }}" >/dev/null 2>&1; then
            echo "exists=true" >> "$GITHUB_OUTPUT"
          else
            echo "exists=false" >> "$GITHUB_OUTPUT"
          fi

      - name: Build plugin zip
        if: steps.check.outputs.exists == 'false'
        run: zip -r your-plugin.zip your-plugin   # ★ 打包外掛子目錄（頂層資料夾＝slug）

      - name: Create GitHub Release
        if: steps.check.outputs.exists == 'false'
        uses: softprops/action-gh-release@v2
        with:
          tag_name: ${{ steps.version.outputs.tag }}
          files: your-plugin.zip                  # ★ 附上剛打包的 zip
          generate_release_notes: true
```

各步驟說明：
1. **讀版本**：從外掛主檔抓 `Version:`，組出 tag `vX.Y.Z`。
2. **查是否已發佈**：若該 tag 的 Release 已存在（代表這次合併沒改版本號），
   後面步驟全部跳過 → **不會重複發佈**。這就是「可以安全地把非版本變更也
   合進 main」的原因。
3. **打包 zip**：`zip -r your-plugin.zip your-plugin` 會產生一個**頂層只有單一
   資料夾 `your-plugin/`** 的 zip——這正是 WordPress 能正確安裝的結構。
4. **建立 Release**：用 `softprops/action-gh-release@v2` 建 tag＋Release，附上 zip。

---

## 六、套用到新外掛的檢查清單

1. [ ] 把 `vendor/plugin-update-checker/`（PUC v5）整包放進外掛目錄。
2. [ ] 複製 Updater 類別，改三處：`REPO_URL`、`SLUG`、主檔常數。
3. [ ] 在外掛 `plugins_loaded` 流程呼叫 `Updater::init()`。
4. [ ] 確認外掛主檔有正確格式的 `Version:` 標頭（必要時同步版本常數／`readme.txt`）。
5. [ ] 複製 `release.yml` 到 repo 根目錄 `.github/workflows/`，改外掛主檔路徑、
       zip 名稱、打包目錄（三者對齊 slug）。
6. [ ] 確認 **slug ＝ 外掛資料夾名 ＝ zip 內頂層資料夾名**，三者完全一致。
7. [ ] 改版本號 → 合併 main → 到 Actions 看 workflow 跑完 → Releases 出現
       `vX.Y.Z` 且附 zip → 客戶站後台即可看到更新。

---

## 七、常見陷阱與注意事項

- **資料夾／slug 命名不一致**：slug、外掛資料夾、zip 頂層資料夾三者只要有一個
  不同，WordPress 更新時會把外掛裝到錯的資料夾（變成兩個外掛）。務必一致。
- **改了程式卻沒改版本號**：CI 會因「該版本 Release 已存在」而跳過，客戶站不會
  收到更新。要發版，一定要升 `Version:`。
- **一個 repo 多個外掛**：`release.yml` 目前只讀**一個**外掛主檔、只打包**一個**
  子目錄（本專案另一個外掛 `ur-ai-gateway` 就不走這套、無自動更新）。若要讓
  多個外掛各自自動更新，需讓 workflow 對每個外掛各跑一份（matrix 或多個 job），
  並各自用自己的 tag 命名空間（例如 `gateway-v1.2.0`）避免 tag 衝突。
- **私有 repo**：PUC 預設讀**公開**的 Release 資產。若 repo 為私有，客戶站無法
  匿名下載 zip，需用 PUC 的 `setAuthentication('<token>')` 帶 GitHub token（但把
  token 發到客戶站有外洩風險，通常不建議）。本機制適合**原始碼可公開**的外掛；
  需保護的商業邏輯應另拆成不公開、不走自動更新的元件。
- **workflow 權限**：`permissions: contents: write` 不可少，否則無法建立 Release。
- **手動補發**：若需要，可在 GitHub → Actions 頁用 `workflow_dispatch` 手動觸發
  重跑一次。

---

## 八、本專案對應檔案（參考實作）

| 環節 | 檔案 |
|------|------|
| Updater 類別 | `ur-ai-assistant/includes/core/class-ur-ai-updater.php` |
| 初始化呼叫 | `ur-ai-assistant/includes/core/class-ur-ai-bootstrap.php`（`init_updater()`） |
| PUC 函式庫 | `ur-ai-assistant/vendor/plugin-update-checker/`（v5，YahnisElsts，MIT） |
| 版本來源 | `ur-ai-assistant/ur-ai-assistant.php` 的 `Version:` 標頭 |
| CI 發佈 | `.github/workflows/release.yml` |

---

## 九、授權

Plugin Update Checker 由 YahnisElsts 以 **MIT** 授權釋出，可自由內嵌於外掛
（含商業外掛）散佈。本說明文件隨本專案一同維護。
