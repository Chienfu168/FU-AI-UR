<?php
/**
 * 都更分回試算 後台「試算名單」頁。
 *
 * 可用變數：
 * @var array $query        repository->query() 結果。
 * @var array $statuses     狀態選項。
 * @var array $city_choices 縣市選項。
 * @var array $counts       各狀態計數。
 * @var string $status      目前篩選狀態。
 * @var string $city        目前篩選縣市。
 *
 * @package UR_AI_Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

$items       = isset($query['items']) ? $query['items'] : array();
$total       = isset($query['total']) ? (int) $query['total'] : 0;
$paged       = isset($query['paged']) ? (int) $query['paged'] : 1;
$total_pages = isset($query['total_pages']) ? (int) $query['total_pages'] : 1;
$base_url    = admin_url('admin.php?page=' . UR_AI_Calculator_Module::ADMIN_MENU_SLUG);
?>
<div class="wrap">
    <h1><?php esc_html_e('都更試算名單', 'ur-ai-assistant'); ?></h1>

    <?php if (class_exists('UR_AI_Admin_Menu')) : ?>
        <?php UR_AI_Admin_Menu::render_group_tabs('calculator'); ?>
    <?php endif; ?>

    <p class="description">
        <?php esc_html_e('每筆名單帶有完整試算情境（縣市、分區、坪數、分回坪數），供業務跟進使用。', 'ur-ai-assistant'); ?>
    </p>

    <!-- 操作教學（可摺疊） -->
    <details style="margin:1em 0;padding:0.5em 1em;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;border-radius:4px;">
        <summary style="cursor:pointer;font-weight:600;font-size:14px;padding:0.3em 0;">
            <?php esc_html_e('▸ 使用說明（如何放置試算器、名單怎麼來、狀態怎麼用）', 'ur-ai-assistant'); ?>
        </summary>

        <div style="padding:0.5em 0 0.5em;font-size:13px;line-height:1.7;color:#3c434a;">

            <p><strong><?php esc_html_e('一、把試算器放到頁面上', 'ur-ai-assistant'); ?></strong><br>
            <?php esc_html_e('編輯任一頁面或文章，插入「簡碼（Shortcode）」區塊，貼上以下其中一個短代碼：', 'ur-ai-assistant'); ?></p>
            <ul style="list-style:disc;margin-left:1.5em;">
                <li><code>[ur_ai_calculator]</code> — <?php esc_html_e('地主版：只有「換坪比」試算，最簡單，適合一般屋主。', 'ur-ai-assistant'); ?></li>
                <li><code>[ur_ai_calculator mode="pro"]</code> — <?php esc_html_e('進階版：另含可摺疊的「基地總量回推」，適合整合公司初步評估。', 'ur-ai-assistant'); ?></li>
            </ul>

            <p><strong><?php esc_html_e('二、名單怎麼進來', 'ur-ai-assistant'); ?></strong><br>
            <?php esc_html_e('訪客輸入坪數試算 → 看到前後對比與半遮罩鉤子 → 填寫聯絡表單送出後，該筆名單就會出現在本頁，並自動帶上試算情境（縣市、分區、坪數、分回坪數區間、使用的算法）。聯絡表單使用「都更獎勵試算」表單（CF7 ID 1157393）。', 'ur-ai-assistant'); ?></p>

            <p><strong><?php esc_html_e('三、名單狀態與備註', 'ur-ai-assistant'); ?></strong><br>
            <?php esc_html_e('每筆名單右側可更新「狀態」（新名單／已聯繫／已結案／無效）並填寫備註，按「更新」儲存。上方可依狀態、縣市篩選，方便跟進。電話可直接點擊撥號。', 'ur-ai-assistant'); ?></p>

            <p><strong><?php esc_html_e('四、提醒', 'ur-ai-assistant'); ?></strong><br>
            <?php esc_html_e('試算結果為初步估算、非正式權利變換評估，僅供引導留資料與業務初談之用，實際分回應以正式估價與審議為準。', 'ur-ai-assistant'); ?></p>

        </div>
    </details>

    <!-- 篩選 -->
    <form method="get" style="margin:1em 0;">
        <input type="hidden" name="page" value="<?php echo esc_attr(UR_AI_Calculator_Module::ADMIN_MENU_SLUG); ?>">

        <select name="status">
            <option value=""><?php esc_html_e('全部狀態', 'ur-ai-assistant'); ?></option>
            <?php foreach ($statuses as $skey => $slabel) : ?>
                <option value="<?php echo esc_attr($skey); ?>" <?php selected($status, $skey); ?>>
                    <?php echo esc_html($slabel); ?>
                    <?php echo isset($counts[$skey]) ? '(' . (int) $counts[$skey] . ')' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="city">
            <option value=""><?php esc_html_e('全部縣市', 'ur-ai-assistant'); ?></option>
            <?php foreach ($city_choices as $ckey => $clabel) : ?>
                <option value="<?php echo esc_attr($ckey); ?>" <?php selected($city, $ckey); ?>>
                    <?php echo esc_html($clabel); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button"><?php esc_html_e('篩選', 'ur-ai-assistant'); ?></button>
    </form>

    <p><?php printf(esc_html__('共 %d 筆名單', 'ur-ai-assistant'), $total); ?></p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('時間', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('姓名', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('電話', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('Email', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('試算情境', 'ur-ai-assistant'); ?></th>
                <th><?php esc_html_e('狀態 / 備註', 'ur-ai-assistant'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)) : ?>
                <tr><td colspan="6"><?php esc_html_e('目前沒有名單。', 'ur-ai-assistant'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($items as $row) : ?>
                    <?php
                    $city_label = isset($city_choices[$row['city']]) ? $city_choices[$row['city']] : $row['city'];
                    $track_label = ('site' === $row['track']) ? __('基地總量', 'ur-ai-assistant') : __('換坪比', 'ur-ai-assistant');
                    ?>
                    <tr>
                        <td><?php echo esc_html(mysql2date('Y-m-d H:i', $row['created_at'])); ?></td>
                        <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                        <td><a href="tel:<?php echo esc_attr($row['tel']); ?>"><?php echo esc_html($row['tel']); ?></a></td>
                        <td><?php echo esc_html($row['email']); ?></td>
                        <td>
                            <?php echo esc_html($city_label); ?>
                            <span class="description">(<?php echo esc_html($track_label); ?>)</span><br>
                            <?php echo esc_html($row['result_summary']); ?>
                            <?php if (!empty($row['message'])) : ?>
                                <br><em><?php echo esc_html($row['message']); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="<?php echo esc_attr(UR_AI_Calculator_Module::LEAD_UPDATE_ACTION); ?>">
                                <?php wp_nonce_field(UR_AI_Calculator_Module::LEAD_UPDATE_ACTION); ?>
                                <input type="hidden" name="lead_id" value="<?php echo (int) $row['id']; ?>">
                                <select name="status">
                                    <?php foreach ($statuses as $skey => $slabel) : ?>
                                        <option value="<?php echo esc_attr($skey); ?>" <?php selected($row['status'], $skey); ?>>
                                            <?php echo esc_html($slabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="admin_note" value="<?php echo esc_attr($row['admin_note']); ?>" placeholder="<?php esc_attr_e('備註', 'ur-ai-assistant'); ?>" style="width:140px;">
                                <button type="submit" class="button button-small"><?php esc_html_e('更新', 'ur-ai-assistant'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav"><div class="tablenav-pages">
            <?php
            $args = array();
            if ('' !== $status) {
                $args['status'] = $status;
            }
            if ('' !== $city) {
                $args['city'] = $city;
            }

            echo paginate_links(
                array(
                    'base'      => add_query_arg(array_merge($args, array('paged' => '%#%')), $base_url),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $total_pages,
                    'prev_text' => '«',
                    'next_text' => '»',
                )
            );
            ?>
        </div></div>
    <?php endif; ?>
</div>
