<?php
/**
 * UR AI Assistant Market Price Ranking View
 *
 * 雙北都更效益排行榜（SEO 導向，伺服器端直接輸出，不需要 JavaScript）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$title            = isset($args['title']) ? (string) $args['title'] : __('雙北都更效益排行榜', 'ur-ai-assistant');
$column_label     = isset($args['column_label']) && '' !== $args['column_label'] ? (string) $args['column_label'] : __('都更效益', 'ur-ai-assistant');
$intro            = isset($args['intro']) && '' !== $args['intro']
    ? (string) $args['intro']
    : __('依「新成屋相對老屋現況的中位數單價漲幅」由高到低排序，只列出老屋與新成屋樣本數皆充足的行政區，讓您快速掌握雙北各行政區的都更／危老改建效益參考。', 'ur-ai-assistant');
$rankings         = isset($args['rankings']) && is_array($args['rankings']) ? $args['rankings'] : array();
$last_imported_at = isset($args['last_imported_at']) ? $args['last_imported_at'] : null;
$disclaimer       = isset($args['disclaimer']) ? (string) $args['disclaimer'] : '';

if (!function_exists('ur_ai_market_price_ranking_format_wan')) {
    /**
     * 依單價換算為「萬元／坪」字串，跟前台查詢 widget 的顯示方式一致。
     *
     * @param float $value 元／坪。
     * @return string
     */
    function ur_ai_market_price_ranking_format_wan($value) {
        return number_format(((float) $value) / 10000, 1) . ' ' . __('萬', 'ur-ai-assistant');
    }
}

?>
<div class="ur-ai-market-price-ranking">
    <div class="ur-ai-market-price-ranking-container">

        <h2 class="ur-ai-market-price-ranking-title"><?php echo esc_html($title); ?></h2>

        <p class="ur-ai-market-price-ranking-intro">
            <?php echo esc_html($intro); ?>
        </p>

        <?php if (empty($rankings)) : ?>
            <p class="ur-ai-market-price-ranking-empty">
                <?php echo esc_html__('目前尚無足夠資料可產生排行榜，請至後台匯入行情資料。', 'ur-ai-assistant'); ?>
            </p>
        <?php endif; ?>

        <?php foreach ($rankings as $city_row) : ?>
            <?php
            $city_label = isset($city_row['label']) ? (string) $city_row['label'] : '';
            $rows       = isset($city_row['rows']) && is_array($city_row['rows']) ? $city_row['rows'] : array();
            ?>

            <h3 class="ur-ai-market-price-ranking-city"><?php echo esc_html($city_label); ?></h3>

            <?php if (empty($rows)) : ?>
                <p class="ur-ai-market-price-ranking-empty">
                    <?php echo esc_html__('目前尚無行政區樣本數同時達到老屋與新成屋門檻，暫不列入排行榜。', 'ur-ai-assistant'); ?>
                </p>
            <?php else : ?>
                <div class="ur-ai-market-price-ranking-table-wrap">
                    <table class="ur-ai-market-price-ranking-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('排名', 'ur-ai-assistant'); ?></th>
                                <th><?php echo esc_html__('行政區', 'ur-ai-assistant'); ?></th>
                                <th><?php echo esc_html__('老屋現況（元/坪）', 'ur-ai-assistant'); ?></th>
                                <th><?php echo esc_html__('新成屋（元/坪）', 'ur-ai-assistant'); ?></th>
                                <th><?php echo esc_html($column_label); ?></th>
                                <th><?php echo esc_html__('樣本數（老屋／新成屋）', 'ur-ai-assistant'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $row) : ?>
                                <?php
                                $uplift    = isset($row['uplift_percent']) ? (float) $row['uplift_percent'] : 0.0;
                                $is_up     = $uplift >= 0;
                                $uplift_class = $is_up ? 'ur-ai-market-price-ranking-up' : 'ur-ai-market-price-ranking-down';
                                ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($index + 1)); ?></td>
                                    <td><?php echo esc_html(isset($row['district']) ? $row['district'] : ''); ?></td>
                                    <td><?php echo esc_html(ur_ai_market_price_ranking_format_wan($row['old_median'])); ?></td>
                                    <td><?php echo esc_html(ur_ai_market_price_ranking_format_wan($row['new_median'])); ?></td>
                                    <td class="<?php echo esc_attr($uplift_class); ?>">
                                        <?php echo esc_html(($is_up ? '+' : '') . $uplift . '%'); ?>
                                    </td>
                                    <td><?php echo esc_html($row['old_count'] . ' / ' . $row['new_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <p class="ur-ai-market-price-ranking-meta">
            <?php echo esc_html__('資料來源：內政部不動產交易實價查詢服務', 'ur-ai-assistant'); ?>
            <?php if ($last_imported_at) : ?>
                　<?php echo esc_html__('資料最後更新：', 'ur-ai-assistant') . esc_html(substr((string) $last_imported_at, 0, 10)); ?>
            <?php endif; ?>
        </p>

        <?php if ('' !== $disclaimer) : ?>
            <p class="ur-ai-market-price-ranking-disclaimer"><?php echo esc_html($disclaimer); ?></p>
        <?php endif; ?>

        <?php if (class_exists('UR_AI_Industry_Profiles')) : ?>
            <?php echo UR_AI_Industry_Profiles::render_promotion_attribution(); // phpcs:ignore -- pre-escaped HTML from render_promotion_attribution(). ?>
        <?php endif; ?>

    </div>
</div>
