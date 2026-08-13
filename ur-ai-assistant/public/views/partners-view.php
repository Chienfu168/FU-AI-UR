<?php
/**
 * UR AI Assistant Partners View
 *
 * 相關產業名錄前台畫面。
 *
 * @var array  $partners   啟用中的名錄（已排序）。
 * @var array  $cat_counts 各分類筆數。
 * @var string $title
 * @var string $intro
 * @var string $disclaimer
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$partners   = isset($partners) && is_array($partners) ? $partners : array();
$cat_counts = isset($cat_counts) && is_array($cat_counts) ? $cat_counts : array();
$title      = isset($title) && '' !== $title ? $title : __('都市更新專業服務與相關組織名錄', 'ur-ai-assistant');
$intro      = isset($intro) ? (string) $intro : '';
$disclaimer = isset($disclaimer) ? (string) $disclaimer : '';

$instance_id = 'ur-ai-partners-' . wp_rand(1000, 999999);

// 分類按鈕：依建議順序排列，其餘依筆數。
$preferred = array('建築師', '估價師', '地政士', '律師', '實施者', '建商', '營造廠', '金融／融資', '更新顧問', '公協會');
$buttons   = array();
foreach (array_keys($cat_counts) as $k) {
    $buttons[] = array('key' => $k, 'label' => UR_AI_Partner_Service::category_label($k), 'count' => (int) $cat_counts[$k]);
}
usort($buttons, function ($a, $b) use ($preferred) {
    $ia = array_search($a['label'], $preferred, true);
    $ib = array_search($b['label'], $preferred, true);
    $ia = false === $ia ? 999 : $ia;
    $ib = false === $ib ? 999 : $ib;
    if ($ia !== $ib) {
        return $ia <=> $ib;
    }
    return $b['count'] <=> $a['count'];
});

// 縣市下拉：只列出資料裡實際出現過的縣市。
$cities = array();
foreach ($partners as $p) {
    list($city) = UR_AI_Partner_Service::split_region($p['region'] ?? '');
    if ('' !== $city) {
        $cities[$city] = true;
    }
}
$city_order = UR_AI_Partner_Service::city_list();
$cities     = array_values(array_filter($city_order, function ($c) use ($cities) {
    return isset($cities[$c]);
}));

$total = count($partners);
?>
<div id="<?php echo esc_attr($instance_id); ?>" class="ur-ai-partners">

    <div class="ur-ai-partners__intro">
        <span class="ur-ai-partners__pill">👥 <?php esc_html_e('都更相關產業資訊（僅供參考）', 'ur-ai-assistant'); ?></span>
        <h3 class="ur-ai-partners__title"><?php echo esc_html($title); ?></h3>
        <?php if ('' !== $intro) : ?>
            <p class="ur-ai-partners__lead"><?php echo esc_html($intro); ?></p>
        <?php endif; ?>
    </div>

    <div class="ur-ai-partners__controls">
        <input type="search" class="ur-ai-partners__search" data-partners="search" placeholder="<?php esc_attr_e('搜尋名稱、地區、服務內容…', 'ur-ai-assistant'); ?>" aria-label="<?php esc_attr_e('搜尋名錄', 'ur-ai-assistant'); ?>">

        <?php if (!empty($cities)) : ?>
        <select class="ur-ai-partners__city" data-partners="city" aria-label="<?php esc_attr_e('縣市篩選', 'ur-ai-assistant'); ?>">
            <option value=""><?php esc_html_e('全部縣市', 'ur-ai-assistant'); ?></option>
            <?php foreach ($cities as $city) : ?>
                <option value="<?php echo esc_attr($city); ?>"><?php echo esc_html($city); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <div class="ur-ai-partners__cats" data-partners="cats">
        <button type="button" class="ur-ai-partners__cat-btn is-active" data-cat=""><?php esc_html_e('全部', 'ur-ai-assistant'); ?> <span class="ur-ai-partners__cat-count"><?php echo esc_html((string) $total); ?></span></button>
        <?php foreach ($buttons as $b) : ?>
            <button type="button" class="ur-ai-partners__cat-btn" data-cat="<?php echo esc_attr($b['key']); ?>">
                <?php echo esc_html($b['label']); ?> <span class="ur-ai-partners__cat-count"><?php echo esc_html((string) $b['count']); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if (empty($partners)) : ?>
        <p class="ur-ai-partners__empty"><?php esc_html_e('目前尚無名錄資料。', 'ur-ai-assistant'); ?></p>
    <?php else : ?>
        <div class="ur-ai-partners__grid" data-partners="grid">
            <?php
            foreach ($partners as $p) {
                $featured = UR_AI_Partner_Service::is_featured_active($p);
                echo '<div class="ur-ai-partners__col" ' . UR_AI_Partner_Service::data_attrs($p) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- data_attrs() 已逐值 esc_attr。
                echo UR_AI_Partner_Service::render_card($p, $featured); // phpcs:ignore WordPress.Security.EscapeOutput -- render_card() 已逐欄逸出。
                echo '</div>';
            }
            ?>
        </div>
        <p class="ur-ai-partners__noresult" data-partners="noresult" hidden><?php esc_html_e('找不到符合條件的名錄，請調整搜尋或篩選條件。', 'ur-ai-assistant'); ?></p>
    <?php endif; ?>

    <?php if ('' !== $disclaimer) : ?>
    <div class="ur-ai-partners__disclaimer">
        <span class="ur-ai-partners__disclaimer-badge"><?php esc_html_e('重要提醒', 'ur-ai-assistant'); ?></span>
        <p class="ur-ai-partners__disclaimer-text"><?php echo esc_html($disclaimer); ?></p>
    </div>
    <?php endif; ?>

    <?php if (class_exists('UR_AI_Industry_Profiles')) : ?>
        <?php echo UR_AI_Industry_Profiles::render_promotion_attribution(); // phpcs:ignore -- pre-escaped. ?>
    <?php endif; ?>
</div>
