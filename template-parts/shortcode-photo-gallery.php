<?php
/**
 * [db_photo_gallery] — masonry of Gallery photos (db_gallery_photo CPT).
 *
 * Design: CSS-columns masonry of bordered white cards, each a figure with
 * the species as the title, the location beneath it, and the photographer
 * credit on its own rule-separated line. Clicking a card opens a lightbox
 * (an addition to the design, but it stays out of the way until used).
 *
 * Paged at 24 a page via ?photos=N, so a growing gallery never loads every
 * full-size photo in one page. Links carry #photos-panel so the Gallery
 * page comes back on the Photographs tab.
 */

$per_page = 24;

$paged = max(1, (int) ($_GET['photos'] ?? 1));
$query = new WP_Query([
  'post_type'      => 'db_gallery_photo',
  'post_status'    => 'publish',
  'posts_per_page' => $per_page,
  'paged'          => $paged,
  'no_found_rows'  => false, // pagination needs the total
]);
if (!$query->have_posts()) {
  echo '<p class="db-empty">' . esc_html__('No photos yet.', 'deccan-birders') . '</p>';
  return;
}
?>
<div class="photo-masonry" id="photos-grid">
  <?php foreach ($query->posts as $photo):
    $img      = get_field('photo', $photo->ID);
    $species  = get_field('species_name', $photo->ID);
    $location = get_field('photo_location', $photo->ID);
    $credit   = get_field('photographer', $photo->ID);
    if (!$img) continue;
    $alt = trim($species . ($location ? ' photographed at ' . $location : ''));
  ?>
  <figure class="photo-card" tabindex="0" role="button"
          aria-label="<?php echo esc_attr(trim($species . ($location ? ' — ' . $location : ''))); ?>">
    <?php
    // wp_get_attachment_image() adds srcset/sizes, so phones fetch a phone-sized
    // file instead of the full "large" one.
    echo wp_get_attachment_image($img['ID'], 'large', false, [
      'alt'      => $alt,
      'loading'  => 'lazy',
      'decoding' => 'async',
      'sizes'    => '(max-width: 700px) 100vw, (max-width: 1100px) 50vw, 360px',
    ]);
    ?>
    <figcaption>
      <?php if ($species): ?><span class="photo-species"><?php echo esc_html($species); ?></span><?php endif; ?>
      <?php if ($location): ?><span class="photo-place"><?php echo esc_html($location); ?></span><?php endif; ?>
      <?php if ($credit): ?><span class="photo-credit"><?php echo esc_html($credit); ?></span><?php endif; ?>
    </figcaption>
  </figure>
  <?php endforeach; ?>
</div>

<?php if ($query->max_num_pages > 1):
  $page_url = function($n) {
    return esc_url(add_query_arg('photos', $n, get_permalink()) . '#photos-panel');
  };
?>
<nav class="events-pager" aria-label="Photo gallery pages">
  <?php if ($paged > 1): ?>
    <a class="events-pager-btn" href="<?php echo $page_url($paged - 1); ?>">← Newer</a>
  <?php else: ?>
    <span class="events-pager-btn" aria-disabled="true">← Newer</span>
  <?php endif; ?>
  <span class="events-pager-status">Page <?php echo (int) $paged; ?> of <?php echo (int) $query->max_num_pages; ?> · <?php echo (int) $query->found_posts; ?> photographs</span>
  <?php if ($paged < $query->max_num_pages): ?>
    <a class="events-pager-btn" href="<?php echo $page_url($paged + 1); ?>">Older →</a>
  <?php else: ?>
    <span class="events-pager-btn" aria-disabled="true">Older →</span>
  <?php endif; ?>
</nav>
<?php endif; ?>

<div class="lightbox" id="db-lightbox" hidden aria-modal="true" role="dialog" aria-label="Photo lightbox">
  <button class="lightbox-close" aria-label="Close lightbox">✕</button>
  <button class="lightbox-prev" aria-label="Previous photo">‹</button>
  <button class="lightbox-next" aria-label="Next photo">›</button>
  <img src="" alt="" class="lightbox-img">
  <div class="lightbox-caption"></div>
</div>

<script>
(function () {
  'use strict';

  var grid     = document.getElementById('photos-grid');
  var lightbox = document.getElementById('db-lightbox');
  if (!grid || !lightbox) return;

  var cards = Array.prototype.slice.call(grid.querySelectorAll('.photo-card'));
  if (!cards.length) return;
  var currentIndex = -1;

  var lbImg     = lightbox.querySelector('.lightbox-img');
  var lbCaption = lightbox.querySelector('.lightbox-caption');

  function openLightbox(index) {
    currentIndex = (index + cards.length) % cards.length;
    var card    = cards[currentIndex];
    var img     = card.querySelector('img');
    var species = card.querySelector('.photo-species');
    var credit  = card.querySelector('.photo-credit');

    lbImg.src = img.src;
    lbImg.alt = img.alt;
    lbCaption.textContent = (species ? species.textContent : '') + (credit ? ' — ' + credit.textContent : '');

    lightbox.hidden = false;
    document.body.classList.add('lightbox-open');
  }

  function closeLightbox() {
    lightbox.hidden = true;
    document.body.classList.remove('lightbox-open');
    lbImg.src = '';
  }

  cards.forEach(function (card, i) {
    card.addEventListener('click', function () { openLightbox(i); });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openLightbox(i); }
    });
  });

  lightbox.querySelector('.lightbox-close').addEventListener('click', closeLightbox);
  lightbox.querySelector('.lightbox-prev').addEventListener('click', function () { openLightbox(currentIndex - 1); });
  lightbox.querySelector('.lightbox-next').addEventListener('click', function () { openLightbox(currentIndex + 1); });
  lightbox.addEventListener('click', function (e) { if (e.target === lightbox) closeLightbox(); });

  document.addEventListener('keydown', function (e) {
    if (lightbox.hidden) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') openLightbox(currentIndex - 1);
    if (e.key === 'ArrowRight') openLightbox(currentIndex + 1);
  });
})();
</script>
