<?php
/**
 * [db_birding_joke] — rotates through the Home page's "birding_jokes"
 * repeater, showing the same joke to everyone for a given calendar week.
 */

$home_id = get_page_by_path('home')?->ID;
$jokes   = $home_id ? get_field('birding_jokes', $home_id) : [];
if (!$jokes) return;

$week = floor(time() / 604800) % count($jokes);
$joke = $jokes[$week];
?>
<div class="humour-card" style="border-left:4px solid var(--green);border-radius:0 var(--radius-card) var(--radius-card) 0">
  <span class="eyebrow" style="color:var(--green)">BIRDING HUMOUR</span>
  <p><?php echo esc_html($joke['joke_text']); ?></p>
</div>
