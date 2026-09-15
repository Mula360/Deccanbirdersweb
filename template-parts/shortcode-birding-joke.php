<?php
/**
 * [db_birding_joke] — rotates through the Home page's "birding_jokes"
 * repeater, showing the same joke to everyone for a given calendar week.
 *
 * Emits bare content for the yellow "This week at the hide" card rather
 * than a card of its own. The design splits the joke into a setup and a
 * punchline; the stored text is a single string, so split on the first
 * question mark when there is one and fall back to one block otherwise.
 */

$home_id = get_page_by_path('home')?->ID;
$jokes   = $home_id ? get_field('birding_jokes', $home_id) : [];
if (!$jokes) return;

$week = floor(time() / 604800) % count($jokes);
$text = trim($jokes[$week]['joke_text']);

$setup = $text;
$punch = '';
if (($q = strpos($text, '?')) !== false && $q < strlen($text) - 1) {
    $setup = trim(substr($text, 0, $q + 1));
    $punch = trim(substr($text, $q + 1));
}
?>
<span class="hide-card-label">Bird Humour</span>
<div class="hide-setup"><?php echo esc_html($setup); ?></div>
<div class="hide-card-spacer"></div>
<?php if ($punch): ?>
  <div class="hide-punch"><?php echo esc_html($punch); ?></div>
<?php endif; ?>
