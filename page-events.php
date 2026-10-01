<?php
/**
 * Template for the Events page — rendered directly in PHP (no Elementor).
 *
 * Follows the Events redesign: a hero carrying two live counts, a pill
 * tab pair, then the cards themselves (assets/js/events.js renders those
 * from /wp-json/db/v1/events), and a strip of photos from past trips.
 *
 * The strip is template-parts/trip-gallery.php, shared with the home page.
 */
get_header();
while (have_posts()) : the_post();

?>

<section class="events-hero">
  <div class="events-hero-inner">
    <div>
      <span class="eyebrow eyebrow--lede" style="color:var(--blue);">Events</span>
      <h1>Bird walks and events</h1>
      <p class="hero-standfirst">Walks run most weekends and are open to everyone, members or not. Past outings keep their checklist totals so you can see what a site produces at a given time of year.</p>
    </div>
    <!-- Filled by events.js once the calendar has loaded. -->
    <div class="events-stats" id="events-stats" hidden>
      <div class="events-stat">
        <div class="events-stat-num" id="events-stat-count">—</div>
        <div class="events-stat-label">Upcoming</div>
      </div>
      <div class="events-stat">
        <div class="events-stat-num" id="events-stat-next">—</div>
        <div class="events-stat-label">Days to next walk</div>
      </div>
    </div>
  </div>
</section>

<div class="events-tabs-wrap">
  <div class="events-tabs" role="tablist">
    <button class="events-tab is-active" data-tab="upcoming" role="tab" aria-selected="true" aria-controls="events-upcoming">Upcoming</button>
    <button class="events-tab" data-tab="past" role="tab" aria-selected="false" aria-controls="events-past">Past events</button>
  </div>
  <p class="events-tabs-note">Sundays · early start · open to all</p>
</div>

<div class="events-panels">
  <div class="events-tab-panel" id="events-upcoming" role="tabpanel"></div>
  <div class="events-tab-panel" id="events-past" role="tabpanel" hidden></div>
</div>

<?php get_template_part('template-parts/trip-gallery'); ?>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
