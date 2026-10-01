<?php
/**
 * Template for the Membership page — rendered directly in PHP (no Elementor).
 */
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) : the_post();
?>

<?php
  // Design uses a dark hero with a photo bleeding through behind it.
  $hero = get_field('membership_hero_image', get_the_ID());
  $hero_url = !empty($hero['url']) ? $hero['url'] : '';
?>
<section class="hero-photo">
  <?php if (!empty($hero['ID'])): ?>
    <?php // A resized copy with srcset, not the 570 KB original. ?>
    <?php echo wp_get_attachment_image((int) $hero['ID'], '1536x1536', false, [
      'class' => 'hero-photo-bg', 'alt' => '', 'aria-hidden' => 'true', 'sizes' => '100vw',
      'loading' => false, 'fetchpriority' => 'high',
    ]); ?>
  <?php elseif ($hero_url): ?>
    <img class="hero-photo-bg" src="<?php echo esc_url($hero_url); ?>" alt="" aria-hidden="true">
  <?php endif; ?>
  <div class="hero-photo-scrim"></div>
  <div class="hero-photo-inner">
    <span class="eyebrow" style="color:var(--yellow);">Membership</span>
    <h1>Join our members</h1>
    <p>Stay in the loop with everything you need to know about bird watching.</p>
  </div>
</section>

<section class="membership-body">
  <?php echo do_shortcode('[db_membership_tiers]'); ?>
</section>

<?php endwhile;
get_footer();
