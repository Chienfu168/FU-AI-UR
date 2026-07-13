<?php
/**
 * UR AI Assistant Quiz View
 *
 * 知識大考驗前台作答畫面。所有題目資料與計分邏輯都由 JS 透過 AJAX
 * 向伺服器索取／送出，這裡只負責畫出殼架與各狀態的容器。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$title          = isset($args['title']) ? (string) $args['title'] : __('都更危老知識大考驗', 'ur-ai-assistant');
$question_count = isset($args['question_count']) ? absint($args['question_count']) : 10;
$question_bank  = isset($args['question_bank']) ? absint($args['question_bank']) : 0;

$instance_id = 'ur-ai-quiz-' . wp_rand(1000, 999999);
?>
<div id="<?php echo esc_attr($instance_id); ?>" class="ur-ai-quiz" data-question-count="<?php echo esc_attr($question_count); ?>">
    <div class="ur-ai-quiz-container">

        <div class="ur-ai-quiz-intro" data-state="intro">
            <p class="ur-ai-quiz-eyebrow"><?php echo esc_html__('知識挑戰賽', 'ur-ai-assistant'); ?></p>
            <h2 class="ur-ai-quiz-title"><?php echo esc_html($title); ?></h2>
            <p class="ur-ai-quiz-intro-text">
                <?php
                printf(
                    /* translators: %d: 題數 */
                    esc_html__('隨機抽 %d 題都市更新與危老重建常識，答對愈多分數愈高，看看你能拿下排行榜第幾名！', 'ur-ai-assistant'),
                    absint($question_count)
                );
                ?>
            </p>

            <?php if ($question_bank < 4) : ?>
                <p class="ur-ai-quiz-notice"><?php echo esc_html__('目前題庫尚未準備好，請稍後再來挑戰。', 'ur-ai-assistant'); ?></p>
            <?php else : ?>
                <button type="button" class="ur-ai-quiz-start-button">
                    <?php echo esc_html__('開始挑戰', 'ur-ai-assistant'); ?>
                </button>
            <?php endif; ?>
        </div>

        <div class="ur-ai-quiz-play" data-state="play" hidden>
            <div class="ur-ai-quiz-progress-bar">
                <div class="ur-ai-quiz-progress-fill"></div>
            </div>
            <p class="ur-ai-quiz-progress-label"></p>

            <div class="ur-ai-quiz-question-card">
                <p class="ur-ai-quiz-question-text"></p>
                <div class="ur-ai-quiz-options" role="radiogroup"></div>
            </div>

            <div class="ur-ai-quiz-actions">
                <button type="button" class="ur-ai-quiz-next-button" disabled>
                    <?php echo esc_html__('下一題', 'ur-ai-assistant'); ?>
                </button>
            </div>
        </div>

        <div class="ur-ai-quiz-nickname" data-state="nickname" hidden>
            <h3><?php echo esc_html__('作答完成！留下您的名號上榜吧', 'ur-ai-assistant'); ?></h3>
            <input
                type="text"
                class="ur-ai-quiz-nickname-input"
                maxlength="20"
                placeholder="<?php echo esc_attr__('例如：土城王小明（選填，可留空匿名）', 'ur-ai-assistant'); ?>"
            >
            <button type="button" class="ur-ai-quiz-submit-button">
                <?php echo esc_html__('送出成績', 'ur-ai-assistant'); ?>
            </button>
        </div>

        <div class="ur-ai-quiz-result" data-state="result" hidden>
            <div class="ur-ai-quiz-result-badge">
                <span class="ur-ai-quiz-result-score"></span>
                <span class="ur-ai-quiz-result-score-label"><?php echo esc_html__('分', 'ur-ai-assistant'); ?></span>
            </div>
            <p class="ur-ai-quiz-result-detail"></p>
            <p class="ur-ai-quiz-result-status"></p>

            <div class="ur-ai-quiz-review"></div>

            <button type="button" class="ur-ai-quiz-retry-button">
                <?php echo esc_html__('再挑戰一次', 'ur-ai-assistant'); ?>
            </button>
        </div>

        <div class="ur-ai-quiz-loading" aria-live="polite" hidden></div>
        <div class="ur-ai-quiz-error" aria-live="polite" hidden></div>

    </div>
</div>
