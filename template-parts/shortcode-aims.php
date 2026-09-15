<?php
/**
 * [db_aims] — numbered list of the society's official aims and objectives,
 * from the Aims page's "aims" repeater.
 *
 * Design: a bordered card split into cells by 2px gaps over a grey backing,
 * each cell a green numbered disc beside the text.
 */

$page_id = get_page_by_path('aims')?->ID;
$aims    = $page_id ? get_field('aims', $page_id) : [];
if (!$aims) return;
?>
<ol class="aims-list">
  <?php foreach ($aims as $i => $aim): ?>
    <li class="aim-row">
      <span class="aim-number"><?php echo (int) $i + 1; ?></span>
      <span class="aim-text"><?php echo esc_html($aim['aim_text']); ?></span>
    </li>
  <?php endforeach; ?>
</ol>
