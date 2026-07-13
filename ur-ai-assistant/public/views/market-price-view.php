<?php
/**
 * UR AI Assistant Market Price View
 *
 * 行情參考前台查詢畫面（簡易版：僅縣市＋行政區）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$title  = isset($args['title']) ? (string) $args['title'] : __('雙北成屋行情參考', 'ur-ai-assistant');
$cities = isset($args['cities']) && is_array($args['cities']) ? $args['cities'] : array();

$instance_id = 'ur-ai-market-price-' . wp_rand(1000, 999999);

$districts_by_city = array();

if (class_exists('UR_AI_Schema_Market_Prices')) {
    foreach (array_keys($cities) as $city_key) {
        $districts_by_city[$city_key] = UR_AI_Schema_Market_Prices::get_known_districts($city_key);
    }
}

?>
<div id="<?php echo esc_attr($instance_id); ?>" class="ur-ai-market-price">
    <div class="ur-ai-market-price-container">

        <h2 class="ur-ai-market-price-title"><?php echo esc_html($title); ?></h2>

        <p class="ur-ai-market-price-intro">
            <?php echo esc_html__('查詢近期「老屋現況」與「新成屋」的成交行情，了解都更／危老重建前後的價值落差參考。目前僅支援台北市、新北市。', 'ur-ai-assistant'); ?>
        </p>

        <form class="ur-ai-market-price-form">
            <div class="ur-ai-market-price-field">
                <label for="<?php echo esc_attr($instance_id); ?>-city"><?php echo esc_html__('縣市', 'ur-ai-assistant'); ?></label>
                <select id="<?php echo esc_attr($instance_id); ?>-city" class="ur-ai-market-price-city-select">
                    <?php foreach ($cities as $city_key => $city_label) : ?>
                        <option value="<?php echo esc_attr($city_key); ?>"><?php echo esc_html($city_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ur-ai-market-price-field">
                <label for="<?php echo esc_attr($instance_id); ?>-district"><?php echo esc_html__('行政區', 'ur-ai-assistant'); ?></label>
                <select id="<?php echo esc_attr($instance_id); ?>-district" class="ur-ai-market-price-district-select">
                    <option value=""><?php echo esc_html__('請選擇行政區', 'ur-ai-assistant'); ?></option>
                    <?php foreach ($districts_by_city as $city_key => $districts) : ?>
                        <?php foreach ($districts as $district) : ?>
                            <option value="<?php echo esc_attr($district); ?>" data-city="<?php echo esc_attr($city_key); ?>">
                                <?php echo esc_html($district); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ur-ai-market-price-field">
                <label for="<?php echo esc_attr($instance_id); ?>-address"><?php echo esc_html__('地址（選填）', 'ur-ai-assistant'); ?></label>
                <input
                    type="text"
                    id="<?php echo esc_attr($instance_id); ?>-address"
                    class="ur-ai-market-price-address-input"
                    placeholder="<?php echo esc_attr__('例如：忠孝東路四段，可讓參考案例更貼近您的地址', 'ur-ai-assistant'); ?>"
                >
            </div>

            <button type="submit" class="ur-ai-market-price-submit">
                <?php echo esc_html__('查詢行情', 'ur-ai-assistant'); ?>
            </button>
        </form>

        <div class="ur-ai-market-price-loading" aria-live="polite"></div>
        <div class="ur-ai-market-price-result" aria-live="polite"></div>

    </div>
</div>
