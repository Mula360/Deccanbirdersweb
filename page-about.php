<?php
/**
 * Template for the About page — rendered directly in PHP (no Elementor).
 *
 * Mirrors the design exactly: heading, banner image, intro paragraph, and
 * the two card-links out to the Committee and Aims pages. The Executive
 * Committee grid deliberately does NOT live here — it is its own page
 * (page-committee.php), reached via the card-link below.
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

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">The Society</span>
    <h1>About Us</h1>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 40px;">
  <div class="section-boxed">
    <?php db_hero_image('about_hero_image', $id, 'A bird photographed by a Deccan Birders member', 'clamp(220px, 38vw, 440px)'); ?>
  </div>
</section>

<section class="section-white two-col-60-40 section-boxed" style="padding-bottom:60px;">
  <div><?php echo wp_kses_post($intro); ?></div>
  <div>
    <div class="card-links">
      <a class="card-link" href="/committee">
        <span class="card-link-title">Executive Committee →</span>
        <span class="card-link-desc">The members who run the society.</span>
      </a>
      <a class="card-link" href="/aims">
        <span class="card-link-title">Aims and Objectives →</span>
        <span class="card-link-desc">What we set out to do, in eight points.</span>
      </a>
    </div>
    <div class="contact-note" style="margin-top:16px;">
      For more details, contact
      <a href="mailto:secretary@deccanbirders.org">secretary@deccanbirders.org</a>,
      <a href="mailto:president@deccanbirders.org">president@deccanbirders.org</a>, or
      <a href="mailto:treasurer@deccanbirders.org">treasurer@deccanbirders.org</a>
    </div>
  </div>
</section>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
