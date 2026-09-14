<?php
/**
 * [db_aims] — numbered list of the society's official aims and objectives,
 * from the Aims page's "aims" repeater.
 */

$page_id = get_page_by_path('aims')?->ID;
$aims    = $page_id ? get_field('aims', $page_id) : [];
if (!$aims) return;
?>
<div class="aims-list">
  <?php foreach ($aims as $i => $aim): ?>
    <div class="aim-row">
      <span class="aim-number"><?php echo (int) $i + 1; ?></span>
      <p><?php echo esc_html($aim['aim_text']); ?></p>
    </div>
  <?php endforeach; ?>
</div>
