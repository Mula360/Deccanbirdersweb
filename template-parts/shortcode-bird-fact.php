<?php
/**
 * [db_bird_fact] — rotates through the Home page's "bird_facts" repeater,
 * showing the same fact to everyone for a given calendar week.
 */

$home_id = get_page_by_path('home')?->ID;
$facts   = $home_id ? get_field('bird_facts', $home_id) : [];
if (!$facts) return;

$week = floor(time() / 604800) % count($facts);
$fact = $facts[$week];
?>
<div class="fact-card" style="border-left:4px solid var(--blue);border-radius:0 var(--radius-card) var(--radius-card) 0">
  <span class="eyebrow" style="color:var(--blue)">
    BIRD FACT · WEEK OF <?php echo strtoupper((new DateTime('Monday this week'))->format('j M')); ?>
  </span>
  <h3 class="fact-bird"><?php echo esc_html($fact['bird_name']); ?></h3>
  <p class="fact-text"><?php echo esc_html($fact['fact_text']); ?></p>
</div>
