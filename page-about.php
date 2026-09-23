<?php
/**
 * Template for the About page — rendered directly in PHP (no Elementor).
 *
 * Heading, banner image, intro paragraph, the two card-links out to the
 * Committee and Aims pages, and then what the society actually does —
 * the activities list, which used to be a page of its own (/activities
 * now redirects here). The Executive Committee grid deliberately does NOT
 * live here: it is its own page, reached via the card-link below.
 */
get_header();
while (have_posts()) : the_post();
  $id = get_the_ID();
  $intro = get_field('about_intro', $id);
  if (!$intro) {
    $intro = '<p>Deccan Birders is a non-governmental organization founded in 1980 (as Birdwatchers '
      . 'Society of Andhra Pradesh and renamed Deccan Birders in 2018) with the primary objective '
      . 'of spreading the message of bird conservation. Deccan Birders is registered under the '
      . 'Public Societies Registration Act – I of 1350F.</p>';
  }
?>

<section class="about-hero">
  <div class="about-hero-inner">
    <div class="about-hero-text">
      <span class="eyebrow" style="color:var(--blue);">The Society</span>
      <h1>About Us</h1>
      <div class="about-intro"><?php echo wp_kses_post($intro); ?></div>
    </div>
    <div class="about-hero-media">
      <?php db_hero_image('about_hero_image', $id, 'A bird photographed by a Deccan Birders member', 'clamp(240px, 34vw, 420px)'); ?>
    </div>
  </div>
</section>

<section class="section-white section-boxed about-links">
  <a class="about-card about-card--green" href="/committee">
    <span class="about-card-arrow" aria-hidden="true">→</span>
    <span class="about-card-text">
      <span class="about-card-title">Executive Committee</span>
      <span class="about-card-desc">The members who run the society.</span>
    </span>
  </a>
  <a class="about-card about-card--blue" href="/aims">
    <span class="about-card-arrow" aria-hidden="true">→</span>
    <span class="about-card-text">
      <span class="about-card-title">Aims and Objectives</span>
      <span class="about-card-desc">What we set out to do, in eight points.</span>
    </span>
  </a>
  <div class="contact-note">
    <!-- One child, so the flex box lays out the paragraph rather than
         each link separately. -->
    <div>
      For more details, contact
      <a href="mailto:secretary@deccanbirders.org">secretary@deccanbirders.org</a>,
      <a href="mailto:president@deccanbirders.org">president@deccanbirders.org</a>, or
      <a href="mailto:treasurer@deccanbirders.org">treasurer@deccanbirders.org</a>
    </div>
  </div>
</section>

<?php get_template_part('template-parts/activities-list'); ?>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
