<?php
/**
 * UR AI Assistant Quiz Leaderboard View
 *
 * 知識大考驗排行榜（純伺服器端渲染）。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$title       = isset($args['title']) ? (string) $args['title'] : __('知識大考驗排行榜', 'ur-ai-assistant');
$leaderboard = isset($args['leaderboard']) && is_array($args['leaderboard']) ? $args['leaderboard'] : array();

$medals = array(1 => '🥇', 2 => '🥈', 3 => '🥉');
?>
<div class="ur-ai-quiz-leaderboard">
    <div class="ur-ai-quiz-leaderboard-container">

        <h2 class="ur-ai-quiz-leaderboard-title"><?php echo esc_html($title); ?></h2>

        <?php if (empty($leaderboard)) : ?>
            <p class="ur-ai-quiz-leaderboard-empty">
                <?php echo esc_html__('目前還沒有人上榜，成為第一位挑戰者吧！', 'ur-ai-assistant'); ?>
            </p>
        <?php else : ?>
            <ol class="ur-ai-quiz-leaderboard-list">
                <?php foreach ($leaderboard as $index => $row) : ?>
                    <?php
                    $rank     = $index + 1;
                    $nickname = trim((string) $row->nickname);

                    if ('' === $nickname) {
                        $nickname = __('匿名挑戰者', 'ur-ai-assistant');
                    }
                    ?>
                    <li class="ur-ai-quiz-leaderboard-row<?php echo $rank <= 3 ? ' is-top' : ''; ?>">
                        <span class="ur-ai-quiz-leaderboard-rank">
                            <?php echo isset($medals[$rank]) ? esc_html($medals[$rank]) : esc_html($rank); ?>
                        </span>
                        <span class="ur-ai-quiz-leaderboard-nickname"><?php echo esc_html($nickname); ?></span>
                        <span class="ur-ai-quiz-leaderboard-score">
                            <?php
                            printf(
                                /* translators: %d: score */
                                esc_html__('%d 分', 'ur-ai-assistant'),
                                absint($row->score)
                            );
                            ?>
                        </span>
                        <span class="ur-ai-quiz-leaderboard-meta">
                            <?php
                            printf(
                                /* translators: 1: correct count 2: total questions */
                                esc_html__('答對 %1$d／%2$d 題', 'ur-ai-assistant'),
                                absint($row->correct_count),
                                absint($row->total_questions)
                            );
                            ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

    </div>
</div>
