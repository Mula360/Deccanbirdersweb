<?php
/**
 * Template for the Membership page — rendered directly in PHP (no Elementor).
 */
get_header();
while (have_posts()) : the_post();
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Membership</span>
    <h1>Join 500+ members</h1>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 40px;">
  <div class="section-boxed">
    <p style="text-align:center;">Stay in the loop with everything you need to know about bird watching.</p>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 80px;">
  <div class="section-boxed">
    <?php echo do_shortcode('[db_membership_tiers]'); ?>
  </div>
</section>

<?php endwhile;
get_footer();
