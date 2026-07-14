<?php
/**
 * UR AI Assistant Public Assistant View
 *
 * 前台 AI 助理主畫面。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$title              = isset($args['title']) ? (string) $args['title'] : '';
$subtitle           = isset($args['subtitle']) ? (string) $args['subtitle'] : '';
$disclaimer         = isset($args['disclaimer']) ? (string) $args['disclaimer'] : '';
$popular_questions  = isset($args['popular_questions']) && is_array($args['popular_questions']) ? $args['popular_questions'] : array();
$popular_groups     = isset($args['popular_groups']) && is_array($args['popular_groups']) ? $args['popular_groups'] : array();
$max_question_length = isset($args['max_question_length']) ? absint($args['max_question_length']) : 500;
$placeholder        = isset($args['placeholder']) ? (string) $args['placeholder'] : __('請輸入您想了解的都市更新、危老重建、更新會、權利變換或協議合建問題。', 'ur-ai-assistant');

$kb_browse_enabled    = !empty($args['kb_browse_enabled']);
$kb_browse_categories = isset($args['kb_browse_categories']) && is_array($args['kb_browse_categories']) ? $args['kb_browse_categories'] : array();
$kb_browse_per_page   = isset($args['kb_browse_per_page']) ? absint($args['kb_browse_per_page']) : 10;

if ('' === trim($title)) {
    $title = __('都更危老 AI 助理', 'ur-ai-assistant');
}

if ('' === trim($subtitle)) {
    $subtitle = __('用白話方式，快速了解都市更新、危老重建、更新會、自主更新、權利變換與協議合建等基礎問題。', 'ur-ai-assistant');
}

if ($max_question_length <= 0) {
    $max_question_length = 500;
}

$instance_id = 'ur-ai-assistant-' . wp_rand(1000, 999999);

?>

<div
    id="<?php echo esc_attr($instance_id); ?>"
    class="ur-ai-assistant"
    data-max-question-length="<?php echo esc_attr($max_question_length); ?>"
>
    <div class="ur-ai-container">

        <div class="ur-ai-header">
            <h2 class="ur-ai-title">
                <?php echo esc_html($title); ?>
            </h2>

            <?php if ('' !== trim($subtitle)) : ?>
                <p class="ur-ai-subtitle">
                    <?php echo esc_html($subtitle); ?>
                </p>
            <?php endif; ?>

            <?php if ('' !== trim($disclaimer)) : ?>
                <div class="ur-ai-disclaimer">
                    <?php echo wp_kses_post(wpautop($disclaimer)); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="ur-ai-body">

            <?php if (!empty($popular_questions)) : ?>
                <div class="ur-ai-section ur-ai-popular">
                    <div class="ur-ai-popular-header">
                        <h3 class="ur-ai-popular-title">
                            <?php echo esc_html__('你可以這樣問', 'ur-ai-assistant'); ?>
                        </h3>
                    </div>

                    <ul class="ur-ai-popular-list">
                        <?php foreach ($popular_questions as $question_item) : ?>
                            <?php
                            $question_id     = isset($question_item['id']) ? absint($question_item['id']) : 0;
                            $question_text   = isset($question_item['question']) ? (string) $question_item['question'] : '';
                            $submit_question = isset($question_item['submit_question']) ? (string) $question_item['submit_question'] : $question_text;
                            $description     = isset($question_item['description']) ? (string) $question_item['description'] : '';

                            if ('' === trim($question_text)) {
                                continue;
                            }
                            ?>
                            <li class="ur-ai-popular-item">
                                <button
                                    type="button"
                                    class="ur-ai-popular-button"
                                    data-question-id="<?php echo esc_attr($question_id); ?>"
                                    data-question="<?php echo esc_attr($question_text); ?>"
                                    data-submit-question="<?php echo esc_attr($submit_question); ?>"
                                    <?php if ('' !== trim($description)) : ?>
                                        title="<?php echo esc_attr($description); ?>"
                                    <?php endif; ?>
                                >
                                    <?php echo esc_html($question_text); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($popular_groups)) : ?>
                <div class="ur-ai-section ur-ai-popular-group-section">
                    <h3 class="ur-ai-section-title">
                        <?php echo esc_html__('依主題快速提問', 'ur-ai-assistant'); ?>
                    </h3>

                    <div class="ur-ai-popular-groups">
                        <?php foreach ($popular_groups as $category => $items) : ?>
                            <?php if (empty($items) || !is_array($items)) : ?>
                                <?php continue; ?>
                            <?php endif; ?>

                            <div class="ur-ai-popular-group">
                                <h4 class="ur-ai-popular-group-title">
                                    <?php echo esc_html($category); ?>
                                </h4>

                                <ul class="ur-ai-popular-list">
                                    <?php foreach ($items as $question_item) : ?>
                                        <?php
                                        $question_id     = isset($question_item['id']) ? absint($question_item['id']) : 0;
                                        $question_text   = isset($question_item['question']) ? (string) $question_item['question'] : '';
                                        $submit_question = isset($question_item['submit_question']) ? (string) $question_item['submit_question'] : $question_text;

                                        if ('' === trim($question_text)) {
                                            continue;
                                        }
                                        ?>
                                        <li class="ur-ai-popular-item">
                                            <button
                                                type="button"
                                                class="ur-ai-popular-button"
                                                data-question-id="<?php echo esc_attr($question_id); ?>"
                                                data-question="<?php echo esc_attr($question_text); ?>"
                                                data-submit-question="<?php echo esc_attr($submit_question); ?>"
                                            >
                                                <?php echo esc_html($question_text); ?>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($kb_browse_enabled) : ?>
                <div class="ur-ai-section ur-ai-kb-browse" data-kb-per-page="<?php echo esc_attr($kb_browse_per_page); ?>">
                    <h3 class="ur-ai-section-title">
                        <?php echo esc_html__('瀏覽全部常見問題', 'ur-ai-assistant'); ?>
                    </h3>

                    <form class="ur-ai-kb-search-form">
                        <label class="screen-reader-text" for="<?php echo esc_attr($instance_id); ?>-kb-search">
                            <?php echo esc_html__('搜尋常見問題', 'ur-ai-assistant'); ?>
                        </label>

                        <input
                            type="text"
                            id="<?php echo esc_attr($instance_id); ?>-kb-search"
                            class="ur-ai-kb-search-input"
                            placeholder="<?php echo esc_attr__('輸入關鍵字搜尋常見問題…', 'ur-ai-assistant'); ?>"
                        >

                        <?php if (!empty($kb_browse_categories)) : ?>
                            <select class="ur-ai-kb-category-select">
                                <option value=""><?php echo esc_html__('全部分類', 'ur-ai-assistant'); ?></option>
                                <?php foreach ($kb_browse_categories as $category_name) : ?>
                                    <option value="<?php echo esc_attr($category_name); ?>">
                                        <?php echo esc_html($category_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <button type="submit" class="ur-ai-kb-search-submit">
                            <?php echo esc_html__('搜尋', 'ur-ai-assistant'); ?>
                        </button>
                    </form>

                    <div class="ur-ai-kb-results" aria-live="polite"></div>
                    <div class="ur-ai-kb-pagination"></div>
                </div>
            <?php endif; ?>

            <div class="ur-ai-section">
                <form class="ur-ai-form" method="post">
                    <div class="ur-ai-input-wrap">
                        <label class="screen-reader-text" for="<?php echo esc_attr($instance_id); ?>-question">
                            <?php echo esc_html__('請輸入問題', 'ur-ai-assistant'); ?>
                        </label>

                        <textarea
                            id="<?php echo esc_attr($instance_id); ?>-question"
                            class="ur-ai-question-input"
                            maxlength="<?php echo esc_attr($max_question_length); ?>"
                            placeholder="<?php echo esc_attr($placeholder); ?>"
                            required
                        ></textarea>
                    </div>

                    <div class="ur-ai-form-footer">
                        <div class="ur-ai-counter">
                            <span class="ur-ai-counter-current">0</span>
                            <span>/</span>
                            <span><?php echo esc_html($max_question_length); ?></span>
                            <span><?php echo esc_html__('字', 'ur-ai-assistant'); ?></span>
                        </div>

                        <div class="ur-ai-actions">
                            <button type="button" class="ur-ai-clear">
                                <?php echo esc_html__('清除問題', 'ur-ai-assistant'); ?>
                            </button>

                            <button type="submit" class="ur-ai-submit">
                                <?php echo esc_html__('送出提問', 'ur-ai-assistant'); ?>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="ur-ai-loading" aria-live="polite">
                    <span class="ur-ai-spinner" aria-hidden="true"></span>
                    <span><?php echo esc_html__('AI 助理正在處理您的問題，請稍候。', 'ur-ai-assistant'); ?></span>
                </div>

                <div class="ur-ai-result" aria-live="polite"></div>
            </div>

        </div>

        <?php if (class_exists('UR_AI_Industry_Profiles')) : ?>
            <?php echo UR_AI_Industry_Profiles::render_promotion_attribution(); // phpcs:ignore -- pre-escaped HTML from render_promotion_attribution(). ?>
        <?php endif; ?>
    </div>
</div>