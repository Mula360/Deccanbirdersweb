<?php
/**
 * Template for the Home page — rendered directly in PHP (no Elementor).
 * Pulls structured content from the "Home Page" ACF field group where
 * available, falls back to the field defaults otherwise.
 */
get_header();
while (have_posts()) : the_post();
  $id = get_the_ID();
  $hero_badge    = get_field('hero_badge', $id) ?: 'Founded 1980 · Hyderabad';
  $hero_title    = get_field('hero_title', $id) ?: 'Spreading the message of bird conservation across Telangana, Andhra Pradesh and India';
  $hero_subtitle = get_field('hero_subtitle', $id) ?: 'Deccan Birders is a non-governmental organization with the primary objective of spreading the message of bird conservation.';
  $stats         = get_field('stats', $id);
  if (!$stats) {
    $stats = [
      ['stat_number' => '1980', 'stat_label' => 'Founded, as the Birdwatchers Society of Andhra Pradesh'],
      ['stat_number' => '500+', 'stat_label' => 'Members across the Deccan and beyond'],
      ['stat_number' => '12',   'stat_label' => 'Issues of PITTA published every year'],
      ['stat_number' => '45',  'stat_label' => 'Years of field records and waterfowl counts'],
    ];
  }
?>

<section class="hero-dark">
  <div class="hero-dark-inner">
    <p class="eyebrow" style="color:var(--yellow);"><span style="display:inline-block;background:var(--yellow);color:var(--ink);padding:7px 18px;border-radius:999px;font-weight:600;font-size:13px;"><?php echo esc_html($hero_badge); ?></span></p>
    <h1 style="font-family:var(--font-head);font-size:clamp(32px,5vw,56px);font-weight:700;margin:20px 0 16px;"><?php echo esc_html($hero_title); ?></h1>
    <p><?php echo esc_html($hero_subtitle); ?></p>
    <div class="hero-buttons">
      <a href="/sightings" class="btn btn-primary">Explore sightings</a>
      <a href="/membership" class="btn btn-outline">Join us</a>
    </div>
  </div>
</section>

<section class="stats-bar">
  <?php foreach ($stats as $s): ?>
    <div class="stat-col">
      <div class="stat-number"><?php echo esc_html($s['stat_number']); ?></div>
      <div class="stat-label"><?php echo esc_html($s['stat_label']); ?></div>
    </div>
  <?php endforeach; ?>
</section>

<section class="section-white section-pad">
  <div class="section-boxed">
    <span class="eyebrow" style="color:var(--green);">Upcoming</span>
    <h2>Come out with us</h2>
    <p>Trips run most weekends. Turn up, borrow a pair of binoculars, and put your name on the checklist.</p>
    <div class="events-grid" id="home-events-grid">
      <div class="event-card"><div class="date-block"><span class="sk-block" style="width:36px"></span></div>
        <div class="event-info"><span class="sk-block" style="width:70%;margin-bottom:8px"></span><span class="sk-block" style="width:50%"></span></div></div>
      <div class="event-card"><div class="date-block"><span class="sk-block" style="width:36px"></span></div>
        <div class="event-info"><span class="sk-block" style="width:70%;margin-bottom:8px"></span><span class="sk-block" style="width:50%"></span></div></div>
      <div class="event-card"><div class="date-block"><span class="sk-block" style="width:36px"></span></div>
        <div class="event-info"><span class="sk-block" style="width:70%;margin-bottom:8px"></span><span class="sk-block" style="width:50%"></span></div></div>
    </div>
    <a href="/events" class="btn btn-ghost">Full calendar →</a>
  </div>
</section>

<section class="section-white" style="padding: 20px 20px 80px;">
  <div class="section-boxed">
    <h3 style="font-family:var(--font-head);font-size:22px;">Where we've been</h3>
    <div class="past-events-grid" id="home-past-events-grid">
      <div class="event-card past-event-card"><div class="past-event-species"><span class="sk-block" style="width:24px"></span></div>
        <div class="event-info"><span class="sk-block" style="width:70%;margin-bottom:8px"></span><span class="sk-block" style="width:50%"></span></div></div>
      <div class="event-card past-event-card"><div class="past-event-species"><span class="sk-block" style="width:24px"></span></div>
        <div class="event-info"><span class="sk-block" style="width:70%;margin-bottom:8px"></span><span class="sk-block" style="width:50%"></span></div></div>
      <div class="event-card past-event-card"><div class="past-event-species"><span class="sk-block" style="width:24px"></span></div>
        <div class="event-info"><span class="sk-block" style="width:70%;margin-bottom:8px"></span><span class="sk-block" style="width:50%"></span></div></div>
    </div>
    <a href="/events" class="btn btn-ghost">All past events →</a>
  </div>
</section>

<section class="section-surface section-pad">
  <div class="section-boxed">
    <span class="eyebrow" style="color:var(--green);">What We Do</span>
    <h2>An array of activities</h2>
    <p>Deccan Birders organizes field trips, lectures, film and slide shows, nature camps, treks, waterfowl counts, bird ringing, etc.</p>
    <div class="activity-preview-grid">
      <div class="activity-preview-card"><span class="activity-preview-cadence">EVERY MONTH</span><span class="activity-preview-title">Monthly Field Trips</span></div>
      <div class="activity-preview-card"><span class="activity-preview-cadence">TWELVE ISSUES A YEAR</span><span class="activity-preview-title">PITTA – Monthly Newsletter</span></div>
      <div class="activity-preview-card"><span class="activity-preview-cadence">ONCE A YEAR</span><span class="activity-preview-title">Annual Bird Race</span></div>
      <div class="activity-preview-card"><span class="activity-preview-cadence">EVERY WINTER</span><span class="activity-preview-title">Annual Waterfowl Census</span></div>
      <div class="activity-preview-card"><span class="activity-preview-cadence">THROUGH THE YEAR</span><span class="activity-preview-title">Webinars</span></div>
      <div class="activity-preview-card"><span class="activity-preview-cadence">ONCE A YEAR</span><span class="activity-preview-title">Annual Nature Camps and Trekking</span></div>
    </div>
    <a href="/activities" class="btn btn-ghost">All activities →</a>
  </div>
</section>

<section class="section-white section-pad">
  <div class="section-boxed">
    <span class="eyebrow" style="color:var(--blue);">PITTA · Monthly Newsletter</span>
    <h2 style="font-size:28px;">Forty-five years of trip reports, in one archive.</h2>
    <ul class="pitta-teaser-list">
      <li>Trip reports by members with great details and pictures</li>
      <li>Bird of the month column</li>
      <li>Opportunity to print your articles</li>
    </ul>
    <a href="/archives" class="btn btn-primary">Browse PITTA archives</a>
  </div>
</section>

<section class="section-surface" style="padding: 80px 20px 40px;">
  <div class="section-boxed">
    <span class="eyebrow" style="color:var(--green);">Live From eBird · Telangana</span>
    <h2>What's being seen right now</h2>
    <div class="sightings-rows" id="home-sightings-rows">
      <div class="sighting-row sighting-skeleton"><span class="sk-block" style="width:130px"></span><span class="sk-block" style="width:80px;margin-left:12px"></span><span class="sk-block" style="width:100px;margin-left:12px"></span></div>
      <div class="sighting-row sighting-skeleton"><span class="sk-block" style="width:130px"></span><span class="sk-block" style="width:80px;margin-left:12px"></span><span class="sk-block" style="width:100px;margin-left:12px"></span></div>
      <div class="sighting-row sighting-skeleton"><span class="sk-block" style="width:130px"></span><span class="sk-block" style="width:80px;margin-left:12px"></span><span class="sk-block" style="width:100px;margin-left:12px"></span></div>
      <div class="sighting-row sighting-skeleton"><span class="sk-block" style="width:130px"></span><span class="sk-block" style="width:80px;margin-left:12px"></span><span class="sk-block" style="width:100px;margin-left:12px"></span></div>
    </div>
    <a href="/sightings" class="btn btn-ghost">Notable sightings and hotspots →</a>
  </div>
</section>

<section class="section-surface" style="padding: 0 20px 20px;">
  <div class="section-boxed">
    <h3 style="font-family:var(--font-head);font-size:20px;">This week at the hide</h3>
    <p style="color:var(--text-muted);font-size:13px;margin-top:4px;">Week of 14 Sep – 20 Sep · changes every Monday</p>
  </div>
</section>

<section class="section-surface two-col-50-50" style="padding: 0 20px 60px;max-width:var(--max-w);margin:0 auto;">
  <div><?php echo do_shortcode('[db_birding_joke]'); ?></div>
  <div><?php echo do_shortcode('[db_bird_fact]'); ?></div>
</section>

<section class="section-white" style="padding: 20px 20px 80px;">
  <div class="section-boxed">
    <h2 class="otd-heading">On this day — <span id="otd-date"></span></h2>
    <div id="sightings-otd"></div>
    <script>
      var otdDateEl = document.getElementById('otd-date');
      if (otdDateEl) {
        otdDateEl.textContent = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long' });
      }
    </script>
    <a href="/archives" class="btn btn-ghost">More from the archive →</a>
  </div>
</section>

<section class="section-surface" style="padding: 60px 20px;text-align:center;">
  <h2>From members' cameras</h2>
  <a href="/gallery" class="btn btn-ghost">Full gallery →</a>
</section>

<section class="join-band">
  <p>Join 500+ birders across the Deccan Plateau</p>
  <a href="/membership" class="btn btn-secondary">Become a member →</a>
</section>

<?php endwhile;
get_footer();
