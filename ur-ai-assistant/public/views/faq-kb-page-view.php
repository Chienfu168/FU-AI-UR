<?php
/**
 * UR AI Assistant FAQ 知識庫查詢頁（SEO 導向、伺服器端渲染）
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$title       = isset($args['title']) ? (string) $args['title'] : __('常見問題知識庫', 'ur-ai-assistant');
$search      = isset($args['search']) ? (string) $args['search'] : '';
$category    = isset($args['category']) ? (string) $args['category'] : '';
$categories  = isset($args['categories']) && is_array($args['categories']) ? $args['categories'] : array();
$items       = isset($args['items']) && is_array($args['items']) ? $args['items'] : array();
$total       = isset($args['total']) ? absint($args['total']) : 0;
$paged       = isset($args['paged']) ? absint($args['paged']) : 1;
$total_pages = isset($args['total_pages']) ? absint($args['total_pages']) : 0;

$current_url = remove_query_arg(array('kb_page'));

$prev_url = add_query_arg(array('kb_page' => max(1, $paged - 1)), $current_url);
$next_url = add_query_arg(array('kb_page' => $paged + 1), $current_url);

?>
<div class="ur-ai-faq-kb-page">

    <h1 class="ur-ai-faq-kb-page-title"><?php echo esc_html($title); ?></h1>

    <form method="get" class="ur-ai-faq-kb-page-search-form">
        <?php foreach ($_GET as $get_key => $get_value) : ?>
            <?php
            $get_key = sanitize_key($get_key);

            if (in_array($get_key, array('kb_q', 'kb_cat', 'kb_page'), true) || !is_scalar($get_value)) {
                continue;
            }
            ?>
            <input type="hidden" name="<?php echo esc_attr($get_key); ?>" value="<?php echo esc_attr($get_value); ?>">
        <?php endforeach; ?>

        <label class="screen-reader-text" for="ur-ai-faq-kb-page-search">
            <?php echo esc_html__('搜尋常見問題', 'ur-ai-assistant'); ?>
        </label>
        <input
            type="text"
            id="ur-ai-faq-kb-page-search"
            name="kb_q"
            class="ur-ai-faq-kb-page-search-input"
            value="<?php echo esc_attr($search); ?>"
            placeholder="<?php echo esc_attr__('輸入關鍵字搜尋常見問題…', 'ur-ai-assistant'); ?>"
        >

        <?php if (!empty($categories)) : ?>
            <select name="kb_cat" class="ur-ai-faq-kb-page-category-select">
                <option value=""><?php echo esc_html__('全部分類', 'ur-ai-assistant'); ?></option>
                <?php foreach ($categories as $category_name) : ?>
                    <option value="<?php echo esc_attr($category_name); ?>" <?php selected($category, $category_name); ?>>
                        <?php echo esc_html($category_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button type="submit" class="ur-ai-faq-kb-page-search-submit">
            <?php echo esc_html__('搜尋', 'ur-ai-assistant'); ?>
        </button>
    </form>

    <?php if (empty($items)) : ?>

        <p class="ur-ai-faq-kb-page-empty">
            <?php echo esc_html__('找不到符合的常見問題，請換個關鍵字或分類再試一次。', 'ur-ai-assistant'); ?>
        </p>

    <?php else : ?>

        <div class="ur-ai-faq-kb-page-list">
            <?php foreach ($items as $item) : ?>
                <details class="ur-ai-faq-kb-page-item">
                    <summary class="ur-ai-faq-kb-page-item-question">
                        <?php if ('' !== $item['category']) : ?>
                            <span class="ur-ai-faq-kb-page-item-category"><?php echo esc_html($item['category']); ?></span>
                        <?php endif; ?>
                        <span class="ur-ai-faq-kb-page-item-question-text"><?php echo esc_html($item['question']); ?></span>
                    </summary>
                    <div class="ur-ai-faq-kb-page-item-answer">
                        <?php echo wp_kses_post($item['answer_html']); ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <nav class="ur-ai-faq-kb-page-pagination" aria-label="<?php echo esc_attr__('常見問題分頁', 'ur-ai-assistant'); ?>">
                <?php if ($paged > 1) : ?>
                    <a class="ur-ai-faq-kb-page-pagination-link" href="<?php echo esc_url($prev_url); ?>" rel="prev">
                        <?php echo esc_html__('上一頁', 'ur-ai-assistant'); ?>
                    </a>
                <?php endif; ?>

                <span class="ur-ai-faq-kb-page-pagination-info">
                    <?php
                    printf(
                        /* translators: 1: current page, 2: total pages, 3: total items */
                        esc_html__('第 %1$s／%2$s 頁（共 %3$s 筆）', 'ur-ai-assistant'),
                        esc_html($paged),
                        esc_html($total_pages),
                        esc_html($total)
                    );
                    ?>
                </span>

                <?php if ($paged < $total_pages) : ?>
                    <a class="ur-ai-faq-kb-page-pagination-link" href="<?php echo esc_url($next_url); ?>" rel="next">
                        <?php echo esc_html__('下一頁', 'ur-ai-assistant'); ?>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <?php
        $faq_jsonld = array(
            '@context'  => 'https://schema.org',
            '@type'     => 'FAQPage',
            'mainEntity' => array(),
        );

        foreach ($items as $item) {
            if ('' === trim($item['question']) || '' === trim($item['answer_text'])) {
                continue;
            }

            $faq_jsonld['mainEntity'][] = array(
                '@type' => 'Question',
                'name'  => $item['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $item['answer_text'],
                ),
            );
        }

        if (!empty($faq_jsonld['mainEntity'])) {
            $json = wp_json_encode($faq_jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $json = str_replace('</script>', '<\/script>', (string) $json);
            ?>
            <script type="application/ld+json"><?php echo $json; // phpcs:ignore -- pre-encoded JSON, not HTML. ?></script>
            <?php
        }
        ?>

    <?php endif; ?>

</div>
