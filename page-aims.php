<?php
/**
 * Template for the Aims page — rendered directly in PHP (no Elementor).
 */
get_header();
while (have_posts()) : the_post();
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">The Society</span>
    <h1>Aims and Objectives</h1>
  </div>
</section>

<section class="section-white" style="padding: 20px 20px 80px;">
  <div class="section-boxed">
    <?php echo do_shortcode('[db_aims]'); ?>
  </div>
</section>

<?php endwhile;
get_footer();
