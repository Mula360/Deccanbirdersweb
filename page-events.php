<?php
/**
 * Template for the Events page — rendered directly in PHP (no Elementor).
 *
 * Follows the Events redesign: a hero carrying two live counts, a pill
 * tab pair, then the cards themselves (assets/js/events.js renders those
 * from /wp-json/db/v1/events), and a carousel of photos from past trips.
 *
 * The carousel comes from the Trip Photos post type — Trip Photos → Add
 * New, set the featured image — and the whole section is left out while
 * there are none.
 */
get_header();
while (have_posts()) : the_post();

$trip_photos = get_posts([
  'post_type'      => 'db_trip_photo',
  'posts_per_page' => 12,
  'post_status'    => 'publish',
  'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
]);
$gallery_page = get_page_by_path('gallery');
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
<section class="trip-gallery">
  <div class="trip-gallery-head">
    <div>
      <span class="eyebrow eyebrow--lede" style="color:var(--blue);">From past trips</span>
      <h2 class="section-h2">Field notes in pictures</h2>
    </div>
    <?php if (count($trip_photos) > 1): ?>
      <div class="trip-gallery-arrows">
        <button type="button" class="trip-arrow" data-step="-1" aria-label="Previous photo">←</button>
        <button type="button" class="trip-arrow trip-arrow--dark" data-step="1" aria-label="Next photo">→</button>
      </div>
    <?php endif; ?>
  </div>

  <div class="trip-gallery-frame">
    <div class="trip-gallery-track" id="trip-gallery-track">
      <?php foreach ($trip_photos as $i => $photo):
        $thumb_id = get_post_thumbnail_id($photo->ID);
        if (!$thumb_id) continue;
      ?>
        <div class="trip-slide" role="group" aria-roledescription="slide"
             aria-label="<?php printf(esc_attr__('Photo %1$d of %2$d', 'deccan-birders'), $i + 1, count($trip_photos)); ?>">
          <?php echo wp_get_attachment_image($thumb_id, 'large', false, [
            'alt'      => $photo->post_title,
            'loading'  => $i === 0 ? 'eager' : 'lazy',
            'decoding' => 'async',
            'sizes'    => '(max-width: 900px) 100vw, 1160px',
          ]); ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="trip-gallery-foot">
    <div class="trip-dots" id="trip-gallery-dots"></div>
    <p class="trip-gallery-count">
      <span id="trip-gallery-counter">1 / <?php echo count($trip_photos); ?></span>
      <?php if ($gallery_page): ?>
        · <a href="<?php echo esc_url(get_permalink($gallery_page)); ?>">Open the full gallery</a>
      <?php endif; ?>
    </p>
  </div>
</section>
<?php endif; ?>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
