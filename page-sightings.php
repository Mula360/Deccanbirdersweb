<?php
/**
 * Template for the Sightings page — rendered directly in PHP (no Elementor).
 * All dynamic content is populated client-side by sightings.js.
 *
 * Scope is all of India, with Telangana and Andhra Pradesh records listed
 * first (see db_sightings_regional() in functions.php) — ordered, not
 * announced. The design's region pill group is dropped rather than offering a
 * switch we no longer support.
 */
get_header();
while (have_posts()) : the_post();
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Live from eBird</span>
    <h1>Recent Sightings</h1>
    <p class="hero-standfirst">Every record below is pulled from checklists submitted to eBird across India. Submit yours and it appears here.</p>
  </div>
</section>

<div class="sightings-scope">
  <div class="sightings-updated">Updated every few hours</div>
</div>

<div class="sightings-tabs-wrap">
  <div class="gallery-tabs" role="tablist">
    <button class="gallery-tab is-active" data-tab="notable" role="tab" aria-selected="true">
      Notable<span class="gallery-tab-bar" aria-hidden="true"></span>
    </button>
    <button class="gallery-tab" data-tab="recent" role="tab" aria-selected="false">
      All recent<span class="gallery-tab-bar" aria-hidden="true"></span>
    </button>
    <button class="gallery-tab" data-tab="hotspots" role="tab" aria-selected="false">
      Hotspots<span class="gallery-tab-bar" aria-hidden="true"></span>
    </button>
  </div>
</div>

<div class="sightings-panels">
  <div id="sightings-notable" role="tabpanel" class="tab-panel"></div>
  <div id="sightings-recent" role="tabpanel" class="tab-panel" hidden></div>
  <div id="sightings-hotspots" role="tabpanel" class="tab-panel" hidden></div>
</div>

<div class="lookup-wrap">
  <div class="lookup-card" id="sightings-lookup">
    <span class="eyebrow" style="color:#1F5A93;">Where can I see it now?</span>
    <h2 class="lookup-title">Look up the nearest recent sighting of a species</h2>
    <p class="lookup-intro">Type a species name. Results are the closest checklists in the last two weeks, with distance from Hyderabad.</p>
    <div class="autocomplete">
      <input type="search" id="species-search-input" class="autocomplete-input" placeholder="e.g. Indian Skimmer" autocomplete="off">
      <div class="autocomplete-dropdown" id="species-search-dropdown" hidden></div>
    </div>
    <div id="species-search-results"></div>
  </div>
</div>

<section class="otd-section">
  <h2 class="otd-heading">What Birders saw on this date in past years — <span id="otd-date"></span></h2>
  <div id="sightings-otd"></div>
  <script>
    var otdDateEl = document.getElementById('otd-date');
    if (otdDateEl) {
      otdDateEl.textContent = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long' });
    }
  </script>
</section>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
