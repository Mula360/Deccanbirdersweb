<?php
/**
 * Template for the Executive Committee page — rendered directly in PHP.
 *
 * In the design this is its own page (reached from the About page's
 * "Executive Committee →" card-link), not a section inside About.
 * The member grid itself comes from the [db_committee_grid] shortcode,
 * which reads the About page's committee_members ACF repeater.
 */
get_header();
while (have_posts()) : the_post();
  $id        = get_the_ID();
  $about_id  = get_page_by_path('about')?->ID;
  $committee = $about_id ? get_field('committee_members', $about_id) : [];
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">The Society</span>
    <h1>Executive Committee</h1>
    <p>Ten members, elected by the society, who run the trips, the newsletter and the census work.</p>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 40px;">
  <div class="section-boxed">
    <?php db_hero_image('committee_hero_image', $id, 'Deccan Birders committee members in the field'); ?>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 80px;">
  <div class="section-boxed">
    <?php if (!empty($committee)): ?>
      <?php echo do_shortcode('[db_committee_grid]'); ?>
    <?php else: ?>
      <p style="text-align:center;color:var(--text-muted);">Committee details will be updated shortly.</p>
    <?php endif; ?>
  </div>
</section>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
