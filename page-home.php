<?php
/**
 * Template for the Home page — rendered directly in PHP (no Elementor).
 *
 * Section order follows the design: photo hero, stats band, upcoming trips,
 * activities (text + card grid), the dark PITTA panel with its collage,
 * the eBird strip, the three "This week at the hide" cards, and the
 * gallery strip. Live data is filled in by events.js / sightings.js.
 */
get_header();
while (have_posts()) : the_post();
  $id = get_the_ID();
  $hero_badge    = get_field('hero_badge', $id) ?: 'Founded 1980 · Hyderabad';
  $hero_title    = get_field('hero_title', $id) ?: 'Spreading the message of bird conservation';
  $hero_subtitle = get_field('hero_subtitle', $id) ?: 'Since 1980, the Deccan Birders have documented the birds of the Deccan Plateau through field trips, citizen science, and the monthly PITTA bulletin.';
  $stats         = get_field('stats', $id);
  if (!$stats) {
    $stats = [
      ['stat_number' => '1980', 'stat_label' => 'Founded, as the Birdwatchers Society of Andhra Pradesh'],
      ['stat_number' => '12',   'stat_label' => 'Issues of PITTA published every year'],
      // Counts itself from the founding year, so it never goes stale.
      ['stat_number' => (string) db_years_active(), 'stat_label' => 'Years of field records and waterfowl counts'],
    ];
  }

  $hero_bg     = get_field('hero_bg_image', $id);
  $hero_bg_url = is_array($hero_bg) ? ($hero_bg['url'] ?? '') : (string) $hero_bg;

  // Three-image collage beside the PITTA panel.
  $collage = [];
  for ($i = 1; $i <= 3; $i++) {
    $im = get_field("pitta_collage_$i", $id);
    $u  = is_array($im) ? ($im['url'] ?? '') : ($im ? wp_get_attachment_image_url($im, 'large') : '');
    if ($u) $collage[] = $u;
  }

  $gallery_photos = get_posts(['post_type' => 'db_gallery_photo', 'posts_per_page' => 5, 'post_status' => 'publish']);
?>

<section class="hero-photo hero-photo--home">
  <?php if ($hero_bg_url): ?>
    <img class="hero-photo-bg" src="<?php echo esc_url($hero_bg_url); ?>" alt="" aria-hidden="true">
  <?php endif; ?>
  <div class="hero-photo-scrim hero-photo-scrim--home"></div>
  <div class="hero-photo-inner">
    <span class="hero-badge"><span class="hero-badge-dot" aria-hidden="true"></span><?php echo esc_html($hero_badge); ?></span>
    <h1><?php echo esc_html($hero_title); ?></h1>
    <p><?php echo esc_html($hero_subtitle); ?></p>
    <div class="hero-buttons">
      <a href="/sightings" class="btn btn-primary">Explore sightings</a>
      <a href="/membership" class="btn btn-outline">Join us</a>
    </div>
  </div>
</section>

<section class="stats-band">
  <div class="stats-band-inner">
    <?php foreach ($stats as $s):
      // A stat labelled "Years …" holding a plain age counts itself from
      // the founding year, so the number stays right without anyone
      // editing the page each January. A year like 1987 is left alone,
      // as is anything else on the band.
      $age = trim((string) $s['stat_number']);
      $number = (preg_match('/^\s*years\b/i', (string) $s['stat_label']) && ctype_digit($age) && (int) $age <= 150)
        ? (string) db_years_active()
        : $s['stat_number'];
    ?>
      <div class="stat-col">
        <div class="stat-number"><?php echo esc_html($number); ?></div>
        <div class="stat-label"><?php echo esc_html($s['stat_label']); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="home-section">
  <div class="section-head">
    <div>
      <span class="eyebrow eyebrow--lede" style="color:var(--blue);">Upcoming</span>
      <h2 class="section-h2">Come out with us</h2>
      <p class="section-lede">Trips run most weekends. Turn up, borrow a pair of binoculars, and put your name on the checklist.</p>
    </div>
    <a href="/events" class="text-link">Full calendar →</a>
  </div>
  <div class="events-grid" id="home-events-grid">
    <?php db_bird_loader('Checking the calendar…'); ?>
  </div>
</section>

<section class="home-split">
  <div>
    <span class="eyebrow eyebrow--lede" style="color:var(--blue);">What we do</span>
    <h2 class="section-h2 section-h2--lg">An array of activities</h2>
    <p class="split-lede">Deccan Birders organizes field trips, lectures, film and slide shows, nature camps, treks, waterfowl counts, bird ringing, etc.</p>
    <a href="/about#activities" class="text-link text-link--block">All activities →</a>
  </div>
  <div class="activity-brief-grid">
    <div class="activity-brief"><span class="activity-brief-cadence">Every month</span><span class="activity-brief-title">Monthly Field Trips</span></div>
    <div class="activity-brief"><span class="activity-brief-cadence">Twelve issues a year</span><span class="activity-brief-title">PITTA – Monthly Newsletter</span></div>
    <div class="activity-brief"><span class="activity-brief-cadence">Once a year</span><span class="activity-brief-title">Annual Bird Race</span></div>
    <div class="activity-brief"><span class="activity-brief-cadence">Every winter</span><span class="activity-brief-title">Annual Waterfowl Census</span></div>
    <div class="activity-brief"><span class="activity-brief-cadence">Through the year</span><span class="activity-brief-title">Webinars</span></div>
    <div class="activity-brief"><span class="activity-brief-cadence">Once a year</span><span class="activity-brief-title">Annual Nature Camps and Trekking</span></div>
  </div>
</section>

<section class="pitta-band">
  <div class="pitta-band-inner">
    <div>
      <span class="eyebrow eyebrow--lede" style="color:var(--yellow);">PITTA · Monthly newsletter</span>
      <h2 class="pitta-band-title">Forty-five years of trip reports, in one archive.</h2>
      <ul class="pitta-points">
        <li>Trip reports by members with great details and pictures</li>
        <li>Bird of the month column</li>
        <li>Opportunity to print your articles</li>
      </ul>
      <a href="/archives" class="pitta-band-btn">Browse PITTA archives</a>
    </div>
    <?php if ($collage): ?>
      <div class="pitta-collage">
        <?php foreach ($collage as $u): ?>
          <img src="<?php echo esc_url($u); ?>" alt="" aria-hidden="true" loading="lazy">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="home-section">
  <div class="section-head">
    <div>
      <span class="eyebrow eyebrow--lede" style="color:var(--blue);">Live from eBird</span>
      <h2 class="section-h2">What's being seen right now</h2>
      <?php echo db_ebird_credit(true); ?>
    </div>
    <!-- The strip runs longer than the screen, so it is moved with these
         rather than a scrollbar. sightings.js wires them up and hides
         them if everything happens to fit. -->
    <div class="strip-arrows" id="home-sightings-arrows" hidden>
      <button type="button" class="trip-arrow" data-step="-1" aria-label="Show earlier sightings">←</button>
      <button type="button" class="trip-arrow trip-arrow--dark" data-step="1" aria-label="Show more sightings">→</button>
    </div>
  </div>
  <div class="home-sightings-scroller">
    <div class="home-sightings" id="home-sightings-rows">
      <?php db_bird_loader('Fetching the latest checklists…'); ?>
    </div>
  </div>
  <a href="/sightings" class="text-link text-link--block">Notable sightings and hotspots →</a>
</section>

<section class="home-section">
  <div class="section-head section-head--baseline">
    <h2 class="section-h2">This week at the hide</h2>
    <div class="week-label"><?php echo esc_html(date_i18n('j M')); ?> · changes every Monday</div>
  </div>
  <div class="hide-cards">
    <div class="hide-card hide-card--humour"><?php echo do_shortcode('[db_birding_joke]'); ?></div>
    <div class="hide-card hide-card--fact"><?php echo do_shortcode('[db_bird_fact]'); ?></div>
    <div class="hide-card hide-card--otd">
      <span class="hide-card-label">On this day · <?php echo esc_html(date_i18n('j F')); ?></span>
      <div id="home-otd"></div>
      <?php echo db_ebird_credit(true); ?>
      <div class="hide-card-spacer"></div>
      <a href="/archives" class="hide-card-link">More from the archive →</a>
    </div>
  </div>
</section>

<section class="home-section home-section--gallery">
  <div class="section-head">
    <h2 class="section-h2">From members' cameras</h2>
    <a href="/gallery" class="text-link">Full gallery →</a>
  </div>
  <div class="gallery-strip">
    <?php if ($gallery_photos): ?>
      <?php foreach ($gallery_photos as $photo):
        $im = get_field('photo', $photo->ID);
        if (!$im) continue;
        $src = $im['sizes']['large'] ?? $im['url'];
      ?>
        <img src="<?php echo esc_url($src); ?>" alt="<?php echo esc_attr(get_field('species_name', $photo->ID)); ?>" loading="lazy">
      <?php endforeach; ?>
    <?php else: ?>
      <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="img-placeholder" aria-label="Photo coming soon"><span>Photo coming soon</span></div>
      <?php endfor; ?>
    <?php endif; ?>
  </div>
</section>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
