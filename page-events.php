<?php
/**
 * Template for the Events page — rendered directly in PHP (no Elementor).
 *
 * The design file has no Events page, so this follows the design system's
 * own event card (the home page's "Come out with us" strip) and the
 * Gallery tab bar. Both tabs are paginated at 10 per page and each card
 * opens to an expanded view in place; events.js does the rendering.
 */
get_header();
while (have_posts()) : the_post();
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Events</span>
    <h1>Bird walks and events</h1>
    <p class="hero-standfirst">Walks run most weekends and are open to everyone, members or not. Past outings keep their checklist totals so you can see what a site produces at a given time of year.</p>
  </div>
</section>

<div class="gallery-tabs-wrap">
  <div class="gallery-tabs" role="tablist">
    <button class="gallery-tab is-active" data-tab="upcoming" role="tab" aria-selected="true" aria-controls="events-upcoming">
      Upcoming<span class="gallery-tab-bar" aria-hidden="true"></span>
    </button>
    <button class="gallery-tab" data-tab="past" role="tab" aria-selected="false" aria-controls="events-past">
      Past events<span class="gallery-tab-bar" aria-hidden="true"></span>
    </button>
  </div>
</div>

<div class="gallery-panel">
  <div class="events-tab-panel" id="events-upcoming" role="tabpanel"></div>
  <div class="events-tab-panel" id="events-past" role="tabpanel" hidden></div>
</div>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
