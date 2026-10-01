<?php
/**
 * "Field notes in pictures": the photos from past trips (Trip Photos, see
 * inc/trip-photos.php) as a row that scrolls itself, opening large on a
 * click. On the Events page and the home page; events.js runs it. Left
 * out altogether while there are no trip photos.
 *
 * $args['class']: an extra class for the section, for each page's spacing.
 * $args['title'], $args['eyebrow']: the heading and the line above it.
 */
$trip_photos = db_trip_strip_photos();
$extra_class = isset($args['class']) ? ' ' . sanitize_html_class($args['class']) : '';
$title   = $args['title'] ?? 'Field notes in pictures';
$eyebrow = $args['eyebrow'] ?? 'From past trips';
?>
<?php if ($trip_photos): ?>
<section class="home-section trip-gallery<?php echo esc_attr($extra_class); ?>">
  <div class="section-head">
    <div>
      <span class="eyebrow eyebrow--lede" style="color:var(--blue);"><?php echo esc_html($eyebrow); ?></span>
      <h2 class="section-h2"><?php echo esc_html($title); ?></h2>
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
