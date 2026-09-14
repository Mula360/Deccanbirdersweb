<?php
/**
 * Template for the Events page — rendered directly in PHP (no Elementor).
 * Upcoming/Past tabs populated client-side by events.js.
 */
get_header();
while (have_posts()) : the_post();
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Events</span>
    <h1>Bird walks and events</h1>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 30px;">
  <div class="section-boxed">
    <p>Walks run most weekends and are open to everyone, members or not. Past outings keep their checklist totals so you can see what a site produces at a given time of year.</p>
  </div>
</section>

<section class="section-white" style="padding: 20px 20px 80px;">
  <div class="section-boxed">
    <div class="tab-bar">
      <button class="tab-btn active" data-tab="upcoming">Upcoming</button>
      <button class="tab-btn" data-tab="past">Past events</button>
    </div>
    <div class="events-tab-panel" id="events-upcoming"></div>
    <div class="events-tab-panel" id="events-past" hidden></div>
  </div>
</section>

<section class="join-band">
  <p>Join 500+ birders across the Deccan Plateau</p>
  <a href="/membership" class="btn btn-secondary">Become a member →</a>
</section>

<?php endwhile;
get_footer();
