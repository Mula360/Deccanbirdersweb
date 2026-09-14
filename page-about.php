<?php
/**
 * Template for the About page — rendered directly in PHP (no Elementor).
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
  $hero_image   = get_field('about_hero_image', $id);
  $committee    = get_field('committee_members', $id);
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">The Society</span>
    <h1>About Us</h1>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 40px;">
  <div class="section-boxed">
    <?php if (!empty($hero_image['url'])): ?>
      <img src="<?php echo esc_url($hero_image['url']); ?>"
           alt="<?php echo esc_attr($hero_image['alt'] ?: 'A bird photographed by a Deccan Birders member'); ?>"
           style="width:100%;aspect-ratio:16/6;object-fit:cover;border-radius:var(--radius-card);">
    <?php else: ?>
      <div class="img-placeholder" aria-label="Photo coming soon" style="aspect-ratio:16/6;">
        <span>Photo coming soon</span>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section-white two-col-60-40 section-boxed" style="padding-bottom:60px;">
  <div><?php echo wp_kses_post($intro); ?></div>
  <div>
    <div class="card-links">
      <a class="card-link" href="#committee">
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

<section class="section-surface section-pad">
  <div class="section-boxed">
    <h2 style="text-align:center;">Our History</h2>
    <?php echo do_shortcode('[db_milestones]'); ?>
  </div>
</section>

<section class="section-white section-pad" id="committee">
  <div class="section-boxed">
    <h2 style="text-align:center;">Executive Committee</h2>
    <?php if (!empty($committee)): ?>
      <p style="text-align:center;max-width:640px;margin:0 auto 32px;">Ten members, elected by the society, who run the trips, the newsletter and the census work.</p>
      <?php echo do_shortcode('[db_committee_grid]'); ?>
    <?php else: ?>
      <p style="text-align:center;color:var(--text-muted);">Committee details will be updated shortly.</p>
    <?php endif; ?>
  </div>
</section>

<section class="join-band">
  <p>Join 500+ birders across the Deccan Plateau</p>
  <a href="/membership" class="btn btn-secondary">Become a member →</a>
</section>

<?php endwhile;
get_footer();
