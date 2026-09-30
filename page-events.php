<?php
/**
 * Template for the Events page — rendered directly in PHP (no Elementor).
 *
 * Follows the Events redesign: a hero carrying two live counts, a pill
 * tab pair, then the cards themselves (assets/js/events.js renders those
 * from /wp-json/db/v1/events), and a strip of photos from past trips.
 *
 * The strip comes from Trip Photos (one entry per trip, several photos
 * each: inc/trip-photos.php), and the whole section is left out while
 * there are none.
 */
get_header();
while (have_posts()) : the_post();

$trip_photos = db_trip_strip_photos();
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

<?php if ($trip_photos): ?>
<section class="home-section trip-gallery">
  <div class="section-head">
    <div>
      <span class="eyebrow eyebrow--lede" style="color:var(--blue);">From past trips</span>
      <h2 class="section-h2">Field notes in pictures</h2>
    </div>
    <!-- events.js shows these only when the strip runs past the screen,
         and moves the strip along by itself until someone uses them. -->
    <div class="strip-arrows" id="trip-strip-arrows" hidden>
      <button type="button" class="trip-arrow" data-step="-1" aria-label="<?php esc_attr_e('Earlier photos', 'deccan-birders'); ?>">←</button>
      <button type="button" class="trip-arrow trip-arrow--dark" data-step="1" aria-label="<?php esc_attr_e('More photos', 'deccan-birders'); ?>">→</button>
    </div>
  </div>
  <div class="trip-strip" id="trip-strip" tabindex="0" aria-label="<?php esc_attr_e('Photos from past trips', 'deccan-birders'); ?>">
    <?php foreach ($trip_photos as $i => [$id, $alt]):
      // The tile links to the large copy, which trip-strip's viewer opens
      // on this page (and a browser without scripts simply follows).
      $large = wp_get_attachment_image_url($id, '2048x2048') ?: wp_get_attachment_image_url($id, 'full');
    ?>
      <a class="trip-tile" href="<?php echo esc_url($large); ?>" data-index="<?php echo (int) $i; ?>">
        <?php // Each photo keeps its own shape at the home page's tile height,
              // so a group photo is never cropped. Up to about 350 px wide
              // for a landscape photo: the browser picks the 768 px copy. ?>
        <?php echo wp_get_attachment_image($id, 'medium_large', false, [
          'alt'      => $alt,
          'loading'  => $i < 6 ? 'eager' : 'lazy',
          'decoding' => 'async',
          'sizes'    => '(max-width: 560px) 70vw, 360px',
        ]); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <dialog class="trip-viewer" id="trip-viewer" aria-label="<?php esc_attr_e('Trip photo', 'deccan-birders'); ?>">
    <figure>
      <img alt="">
      <figcaption></figcaption>
    </figure>
    <?php if (count($trip_photos) > 1): ?>
      <button type="button" class="trip-viewer-step trip-viewer-prev" data-step="-1" aria-label="<?php esc_attr_e('Previous photo', 'deccan-birders'); ?>">←</button>
      <button type="button" class="trip-viewer-step trip-viewer-next" data-step="1" aria-label="<?php esc_attr_e('Next photo', 'deccan-birders'); ?>">→</button>
    <?php endif; ?>
    <button type="button" class="trip-viewer-close" aria-label="<?php esc_attr_e('Close', 'deccan-birders'); ?>">×</button>
  </dialog>
</section>
<?php endif; ?>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
