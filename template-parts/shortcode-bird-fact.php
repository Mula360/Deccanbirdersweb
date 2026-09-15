<?php
/**
 * [db_bird_fact] — rotates through the Home page's "bird_facts" repeater,
 * showing the same fact to everyone for a given calendar week.
 *
 * Emits bare content for the green "This week at the hide" card.
 */

$home_id = get_page_by_path('home')?->ID;
$facts   = $home_id ? get_field('bird_facts', $home_id) : [];
if (!$facts) return;

$week = floor(time() / 604800) % count($facts);
$fact = $facts[$week];
?>
<span class="hide-card-label">Bird Fact</span>
<div class="hide-setup"><?php echo esc_html($fact['bird_name']); ?></div>
<div class="hide-card-spacer"></div>
<div class="hide-punch"><?php echo esc_html($fact['fact_text']); ?></div>
