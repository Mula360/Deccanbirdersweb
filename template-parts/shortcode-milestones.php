<?php
/**
 * [db_milestones] — society history timeline, from the About page's
 * "milestones" repeater.
 */

$page_id    = get_page_by_path('about')?->ID;
$milestones = $page_id ? get_field('milestones', $page_id) : [];
if (!$milestones) return;
?>
<div class="milestone-timeline">
  <?php foreach ($milestones as $m): ?>
  <div class="milestone-item">
    <div class="milestone-year"><?php echo esc_html($m['milestone_year']); ?></div>
    <div class="milestone-dot"></div>
    <div class="milestone-text"><?php echo esc_html($m['milestone_text']); ?></div>
  </div>
  <?php endforeach; ?>
</div>
