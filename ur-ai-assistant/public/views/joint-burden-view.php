<?php
/**
 * UR AI Assistant Joint Burden Estimator View
 *
 * 都市更新共同負擔提列估算前台畫面（第一階段，依新北市提列基準）。
 *
 * @var string $disclaimer
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$disclaimer  = isset($disclaimer) ? (string) $disclaimer : '';
$instance_id = 'ur-ai-jb-' . wp_rand(1000, 999999);
?>
<div id="<?php echo esc_attr($instance_id); ?>" class="ur-ai-jb">

    <div class="ur-ai-jb__intro">
        <h3 class="ur-ai-jb__title"><?php esc_html_e('都市更新共同負擔提列估算（新北市）', 'ur-ai-assistant'); ?></h3>
        <p class="ur-ai-jb__lead">
            <?php esc_html_e('依「新北市」都市更新提列總表與各分項說明之公開公式，輸入基地與權屬條件，概算共同負擔 A～H 各項——工程費用A、權利變換費用C、貸款利息D、稅捐E（印花稅／營業稅）、管理費用F（F1／F3／F4／F5）。填入「更新後總權利價值」後，會一併算出營業稅與「共同負擔比率」。個案認定項目（建築設計費、公共設施、拆遷補償、信託費、B／G／H等）可於下方「個案選填」自行帶入。', 'ur-ai-assistant'); ?>
        </p>
        <p class="ur-ai-jb__phase-note">
            <?php esc_html_e('※ 本工具目前僅依「新北市」提列基準計算，臺北市及其他縣市之公式與費率不同，不適用本結果。營業稅依財政部109年令釋公式，因共同負擔含營業稅本身屬循環定義，已以代數封閉解求出。', 'ur-ai-assistant'); ?>
        </p>
        <p class="ur-ai-jb__caution">
            <strong><?php esc_html_e('重要：', 'ur-ai-assistant'); ?></strong>
            <?php esc_html_e('本估算僅供事前概算參考，實際金額會因個案條件（營建單價調整、各項個案認定費用、審查認定範圍等）而有差異，最終一律以「都市更新及爭議處理審議會」審查委員審議通過之數額為準。', 'ur-ai-assistant'); ?>
        </p>
    </div>

    <div class="ur-ai-jb__form">

        <!-- 建物條件 -->
        <fieldset class="ur-ai-jb__section">
            <legend class="ur-ai-jb__legend"><?php esc_html_e('一、建物與工程條件（工程費用 A）', 'ur-ai-assistant'); ?></legend>

            <div class="ur-ai-jb__grid">
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('新建構造別', 'ur-ai-assistant'); ?></span>
                    <select class="ur-ai-jb__input" data-jb="structure">
                        <option value="rc"><?php esc_html_e('鋼筋混凝土造（RC）', 'ur-ai-assistant'); ?></option>
                        <option value="src"><?php esc_html_e('鋼骨鋼筋混凝土造（SRC）', 'ur-ai-assistant'); ?></option>
                        <option value="steel"><?php esc_html_e('鋼骨造（SC）', 'ur-ai-assistant'); ?></option>
                    </select>
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('地上樓層數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="floors_above" placeholder="<?php esc_attr_e('例：14', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('地下樓層數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="floors_below" placeholder="<?php esc_attr_e('例：3', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('總樓地板面積（坪）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="0.01" class="ur-ai-jb__input" data-jb="total_floor_area_ping" placeholder="<?php esc_attr_e('含地下層、屋突、夾層', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('更新後戶數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="household_count" placeholder="<?php esc_attr_e('影響外接管線、地籍整理費', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('拆除構造別', 'ur-ai-assistant'); ?></span>
                    <select class="ur-ai-jb__input" data-jb="demolition_structure">
                        <option value="rc"><?php esc_html_e('鋼筋混凝土造（1,050/㎡）', 'ur-ai-assistant'); ?></option>
                        <option value="reinforced"><?php esc_html_e('加強磚造（900/㎡）', 'ur-ai-assistant'); ?></option>
                        <option value="brick"><?php esc_html_e('磚造（620/㎡）', 'ur-ai-assistant'); ?></option>
                        <option value="src"><?php esc_html_e('鋼骨鋼筋混凝土造（1,400/㎡）', 'ur-ai-assistant'); ?></option>
                        <option value="steel"><?php esc_html_e('鋼骨造（1,720/㎡）', 'ur-ai-assistant'); ?></option>
                        <option value="wood"><?php esc_html_e('竹、木造（230/㎡）', 'ur-ai-assistant'); ?></option>
                        <option value="stone"><?php esc_html_e('漿砌卵石（200/㎡）', 'ur-ai-assistant'); ?></option>
                        <option value="metal_shed"><?php esc_html_e('金屬或鋼鐵棚架（350/㎡）', 'ur-ai-assistant'); ?></option>
                    </select>
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('拆除面積（平方公尺）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="0.01" class="ur-ai-jb__input" data-jb="demolition_area" placeholder="<?php esc_attr_e('更新前建物實測面積', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('營建單價加成率（%，選填）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="0.01" class="ur-ai-jb__input" data-jb="surcharge_rate" placeholder="<?php esc_attr_e('地下層/樓高/綠建築等加成合計', 'ur-ai-assistant'); ?>">
                </label>
            </div>

            <details class="ur-ai-jb__advanced">
                <summary><?php esc_html_e('營建單價物價指數調整（選填）', 'ur-ai-assistant'); ?></summary>
                <p class="ur-ai-jb__hint"><?php esc_html_e('營建單價表基準日為民國112年4月。若帶入基準日與現行的「營造工程物價指數總指數」，系統會就指數增減率超過2.5%的部分調整單價。', 'ur-ai-assistant'); ?></p>
                <div class="ur-ai-jb__grid">
                    <label class="ur-ai-jb__field">
                        <span class="ur-ai-jb__label"><?php esc_html_e('基準日物價指數（112/4）', 'ur-ai-assistant'); ?></span>
                        <input type="number" min="0" step="0.01" class="ur-ai-jb__input" data-jb="price_index_base">
                    </label>
                    <label class="ur-ai-jb__field">
                        <span class="ur-ai-jb__label"><?php esc_html_e('現行物價指數', 'ur-ai-assistant'); ?></span>
                        <input type="number" min="0" step="0.01" class="ur-ai-jb__input" data-jb="price_index_current">
                    </label>
                </div>
            </details>
        </fieldset>

        <!-- 更新單元／權屬 -->
        <fieldset class="ur-ai-jb__section">
            <legend class="ur-ai-jb__legend"><?php esc_html_e('二、更新單元與權屬（規劃費、估價費、地籍）', 'ur-ai-assistant'); ?></legend>

            <div class="ur-ai-jb__grid">
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('更新單元面積（平方公尺）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="0.01" class="ur-ai-jb__input" data-jb="unit_area_sqm" placeholder="<?php esc_attr_e('含公共設施用地', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('權利人人數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="rights_holders" placeholder="<?php esc_attr_e('土地/建物/違建戶權利人聯集', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('更新前主建物筆數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="main_building_parcels_before">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('更新前土地筆數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="land_parcels">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('更新後主建物筆數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="main_building_parcels_after">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('鑑界地號筆數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="boundary_survey_parcels" placeholder="<?php esc_attr_e('每筆4千元', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('鑽探孔數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="drilling_holes" placeholder="<?php esc_attr_e('每孔7.5萬元', 'ur-ai-assistant'); ?>">
                </label>
            </div>
        </fieldset>

        <!-- 管理費／貸款參數 -->
        <fieldset class="ur-ai-jb__section">
            <legend class="ur-ai-jb__legend"><?php esc_html_e('三、費率與貸款參數（F1／F3／F5、貸款利息 D）', 'ur-ai-assistant'); ?></legend>

            <div class="ur-ai-jb__grid">
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('土地公告現值總值（元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="land_current_value_total" placeholder="<?php esc_attr_e('更新單元內合計，F1依此×2.5%', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('基地面積（平方公尺）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="0.01" class="ur-ai-jb__input" data-jb="base_site_area_sqm" placeholder="<?php esc_attr_e('決定F3人事行政管理費率', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('門牌戶數', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="door_count" placeholder="<?php esc_attr_e('產權級別用', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('所有權人數（聯集）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="owner_count" placeholder="<?php esc_attr_e('產權級別=(門牌戶數+人數)/2', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('自有資金比率（%）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" max="100" step="0.01" class="ur-ai-jb__input" data-jb="own_capital_ratio" placeholder="<?php esc_attr_e('例：20', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('郵政一年定存利率（%）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="0.001" class="ur-ai-jb__input" data-jb="postal_rate" placeholder="<?php esc_attr_e('例：1.2', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('五大銀行平均基準利率（%）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="0.001" class="ur-ai-jb__input" data-jb="bank_rate" placeholder="<?php esc_attr_e('例：2.5', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field ur-ai-jb__field--checkbox">
                    <input type="checkbox" data-jb="top_down_construction">
                    <span><?php esc_html_e('採逆打工法（地下層每層施工期減1.5月）', 'ur-ai-assistant'); ?></span>
                </label>
            </div>
        </fieldset>

        <!-- 更新後總權利價值與稅捐（第二階段） -->
        <fieldset class="ur-ai-jb__section">
            <legend class="ur-ai-jb__legend"><?php esc_html_e('四、更新後總權利價值與稅捐（營業稅 E、銷售管理費 F4、共同負擔比率）', 'ur-ai-assistant'); ?></legend>
            <p class="ur-ai-jb__hint"><?php esc_html_e('填入「更新後總權利價值」才會計算營業稅與共同負擔比率；未填則結果僅到「不含營業稅」的部分。', 'ur-ai-assistant'); ?></p>

            <div class="ur-ai-jb__grid">
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('更新後總權利價值（元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="post_renewal_total_value" placeholder="<?php esc_attr_e('主管機關核定之更新後總權利價值', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('實施者實際獲配總價值（元，算 F4）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="allocated_value" placeholder="<?php esc_attr_e('單元及車位總價值', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('營業稅計算方式', 'ur-ai-assistant'); ?></span>
                    <select class="ur-ai-jb__input" data-jb="business_tax_method">
                        <option value="house_ratio"><?php esc_html_e('房屋評定比例法', 'ur-ai-assistant'); ?></option>
                        <option value="cost_ratio"><?php esc_html_e('費用比例法', 'ur-ai-assistant'); ?></option>
                    </select>
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('房屋評定標準價格（元，房評法用）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="house_assessed_value" placeholder="<?php esc_attr_e('房屋及車位產權面積×評定標準單價', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('土地公告現值（元，房評法用）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="land_announced_value_for_tax" placeholder="<?php esc_attr_e('留空則沿用上方土地公告現值總值', 'ur-ai-assistant'); ?>">
                </label>

                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('公共設施用地負擔（元，費用比例法用）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="public_facility_land_burden">
                </label>
            </div>
        </fieldset>

        <!-- 個案選填 -->
        <details class="ur-ai-jb__section ur-ai-jb__optional">
            <summary class="ur-ai-jb__legend"><?php esc_html_e('五、個案認定項目（選填，有數字才計入）', 'ur-ai-assistant'); ?></summary>
            <p class="ur-ai-jb__hint"><?php esc_html_e('以下項目屬個案認定，無單一公式，請依實際契約／估價／審定金額填入；會一併納入A、C合計並影響D、F3、F5的計算。', 'ur-ai-assistant'); ?></p>
            <div class="ur-ai-jb__grid">
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('建築設計費計算方式', 'ur-ai-assistant'); ?></span>
                    <select class="ur-ai-jb__input" data-jb="design_fee_mode">
                        <option value="auto"><?php esc_html_e('自動（依建築師酬金標準表）', 'ur-ai-assistant'); ?></option>
                        <option value="manual"><?php esc_html_e('手動填入', 'ur-ai-assistant'); ?></option>
                    </select>
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('建築物種別（自動計算用）', 'ur-ai-assistant'); ?></span>
                    <select class="ur-ai-jb__input" data-jb="design_fee_category">
                        <option value="public_highrise"><?php esc_html_e('公共及高層建築（5樓以上集合住宅）', 'ur-ai-assistant'); ?></option>
                        <option value="general"><?php esc_html_e('一般建築（4層以下住宅）', 'ur-ai-assistant'); ?></option>
                        <option value="special"><?php esc_html_e('特殊建築（高級住宅別墅等）', 'ur-ai-assistant'); ?></option>
                    </select>
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('建築設計費（元，手動時填入）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="design_fee">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('工程管理費（元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="construction_mgmt_fee">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('公共及公益設施費用（元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="public_facility_fee">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('公寓大廈公共基金計算方式', 'ur-ai-assistant'); ?></span>
                    <select class="ur-ai-jb__input" data-jb="condo_fund_mode">
                        <option value="auto"><?php esc_html_e('自動（依施行細則第5條，以營建費用為工程造價）', 'ur-ai-assistant'); ?></option>
                        <option value="manual"><?php esc_html_e('手動填入', 'ur-ai-assistant'); ?></option>
                    </select>
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('公寓大廈公共基金（元，手動時填入）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="condo_fund">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('合法建築物拆遷補償費（元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="demolition_compensation">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('拆遷安置費（元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="relocation_fee">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('其他權變費用（鄰房鑑定/測量/審查，元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="other_c_fee">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('規劃費特殊加計（萬元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="0.01" class="ur-ai-jb__input" data-jb="planning_extra_wan" placeholder="<?php esc_attr_e('分別報核/複數區段/更新會等', 'ur-ai-assistant'); ?>">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('信託費（全額，元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="trust_fee">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('信託費提列身分', 'ur-ai-assistant'); ?></span>
                    <select class="ur-ai-jb__input" data-jb="trust_fee_type">
                        <option value="self"><?php esc_html_e('自組更新會／代執行機構（全額）', 'ur-ai-assistant'); ?></option>
                        <option value="developer"><?php esc_html_e('一般建商（50%）', 'ur-ai-assistant'); ?></option>
                    </select>
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('容積獎勵後續管理維護費 B（元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="b_cost">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('都市計畫變更負擔費用 G（元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="g_cost">
                </label>
                <label class="ur-ai-jb__field">
                    <span class="ur-ai-jb__label"><?php esc_html_e('容積移轉費用 H（元）', 'ur-ai-assistant'); ?></span>
                    <input type="number" min="0" step="1" class="ur-ai-jb__input" data-jb="h_cost">
                </label>
            </div>
        </details>

        <button type="button" class="ur-ai-jb__btn" data-jb-action="compute">
            <?php esc_html_e('估算共同負擔', 'ur-ai-assistant'); ?>
        </button>
    </div>

    <!-- 結果 -->
    <div class="ur-ai-jb__result" data-jb-result hidden>

        <div class="ur-ai-jb__result-head">
            <span class="ur-ai-jb__result-date" data-jb-date></span>
            <button type="button" class="ur-ai-jb__print-btn" data-jb-action="print">
                <?php esc_html_e('🖨 友善列印', 'ur-ai-assistant'); ?>
            </button>
        </div>

        <div class="ur-ai-jb__result-final">
            <span class="ur-ai-jb__result-label" data-jb-subtotal-label><?php esc_html_e('共同負擔總額', 'ur-ai-assistant'); ?></span>
            <span class="ur-ai-jb__result-value" data-jb-subtotal>—</span>
        </div>

        <div class="ur-ai-jb__ratio" data-jb-ratio hidden></div>

        <p class="ur-ai-jb__result-caution">
            <?php esc_html_e('本結果僅供事前概算參考，實際金額因個案而異，最終以都市更新及爭議處理審議會審查委員審議通過之數額為準。', 'ur-ai-assistant'); ?>
        </p>

        <div class="ur-ai-jb__groups" data-jb-groups></div>

        <div class="ur-ai-jb__notes" data-jb-notes></div>

        <?php if ('' !== $disclaimer) : ?>
        <div class="ur-ai-jb__disclaimer-box">
            <span class="ur-ai-jb__disclaimer-badge"><?php esc_html_e('重要提醒', 'ur-ai-assistant'); ?></span>
            <p class="ur-ai-jb__disclaimer-text"><?php echo esc_html($disclaimer); ?></p>
        </div>
        <?php endif; ?>

        <div class="ur-ai-jb__print-footer">
            <?php echo esc_html(get_bloginfo('name')); ?> ・ <?php echo esc_html(home_url('/')); ?>
        </div>
    </div>

    <div class="ur-ai-jb__error" data-jb-error hidden></div>

    <?php if (class_exists('UR_AI_Industry_Profiles')) : ?>
        <?php echo UR_AI_Industry_Profiles::render_promotion_attribution(); // phpcs:ignore -- pre-escaped HTML from render_promotion_attribution(). ?>
    <?php endif; ?>
</div>
