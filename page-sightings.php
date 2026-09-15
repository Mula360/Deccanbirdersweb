<?php
/**
 * Template for the Sightings page — rendered directly in PHP (no Elementor).
 * All dynamic content is populated client-side by sightings.js.
 */
get_header();
while (have_posts()) : the_post();
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Live From eBird</span>
    <h1>Recent Sightings</h1>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 30px;">
  <div class="section-boxed">
    <p>Every record below is pulled from checklists submitted to eBird for the region. Submit yours and it appears here.</p>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 40px;">
  <div class="section-boxed">
    <p class="sightings-updated-note">Updated every 15 minutes</p>
    <div class="tab-bar" role="tablist">
      <button role="tab" class="tab-btn active" data-tab="notable" aria-selected="true">Notable</button>
      <button role="tab" class="tab-btn" data-tab="recent" aria-selected="false">All recent</button>
      <button role="tab" class="tab-btn" data-tab="hotspots" aria-selected="false">Hotspots</button>
    </div>
    <div class="tab-panels">
      <div id="sightings-notable" role="tabpanel" class="tab-panel active"></div>
      <div id="sightings-recent" role="tabpanel" class="tab-panel" hidden></div>
      <div id="sightings-hotspots" role="tabpanel" class="tab-panel" hidden></div>
    </div>
  </div>
</section>

<section class="section-surface" style="padding: 40px 20px;">
  <div class="section-boxed">
    <span class="eyebrow" style="color:var(--blue);">Where Can I See It Now?</span>
    <h2>Look up the nearest recent sighting of a species</h2>
    <p>Type a species name. Results are the closest checklists in the last two weeks, with distance from Hyderabad.</p>
    <div id="sightings-lookup">
      <div class="autocomplete">
        <input type="search" id="species-search-input" class="autocomplete-input" placeholder="Type a bird name..." autocomplete="off">
        <div class="autocomplete-dropdown" id="species-search-dropdown" hidden></div>
      </div>
      <div id="species-search-results"></div>
    </div>
  </div>
</section>

<section class="section-white" style="padding: 40px 20px 80px;">
  <div class="section-boxed">
    <h2 class="otd-heading">What Birders saw on this date in past years — <span id="otd-date"></span></h2>
    <div id="sightings-otd"></div>
    <script>
      var otdDateEl = document.getElementById('otd-date');
      if (otdDateEl) {
        otdDateEl.textContent = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long' });
      }
    </script>
  </div>
</section>

<?php endwhile;
get_footer();
