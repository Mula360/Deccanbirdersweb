<?php
/**
 * Template for the Aims page — rendered directly in PHP (no Elementor).
 */
get_header();
while (have_posts()) : the_post();
?>

<section class="hero-light hero-light--aims">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">The Society</span>
    <h1>Aims and Objectives</h1>
  </div>
</section>

<div class="page-banner">
  <?php db_hero_image('aims_hero_image', get_the_ID(), 'Deccan Birders members in the field', 'clamp(200px, 34vw, 400px)'); ?>
</div>

<div class="aims-wrap">
  <?php echo do_shortcode('[db_aims]'); ?>
</div>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
