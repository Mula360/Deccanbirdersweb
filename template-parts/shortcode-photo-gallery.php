<?php
/**
 * [db_photo_gallery] — filterable, lightbox-enabled grid of Gallery photos
 * (db_gallery_photo CPT).
 */

$photos = get_posts(['post_type' => 'db_gallery_photo', 'posts_per_page' => -1, 'post_status' => 'publish']);
if (!$photos) {
  echo '<p class="db-empty">' . esc_html__('No photos yet.', 'deccan-birders') . '</p>';
  return;
}
?>
<div class="gallery-filters">
  <button class="filter-btn active" data-cat="all">All</button>
  <?php foreach (['Raptors', 'Waders', 'Passerines', 'Waterbirds', 'Mammals', 'Landscapes'] as $cat): ?>
    <button class="filter-btn" data-cat="<?php echo esc_attr(strtolower($cat)); ?>">
      <?php echo esc_html($cat); ?>
    </button>
  <?php endforeach; ?>
</div>

<div class="photo-masonry" id="photo-grid">
  <?php foreach ($photos as $photo):
    $img      = get_field('photo', $photo->ID);
    $species  = get_field('species_name', $photo->ID);
    $location = get_field('photo_location', $photo->ID);
    $credit   = get_field('photographer', $photo->ID);
    $cat      = strtolower(get_field('category', $photo->ID) ?? '');
    if (!$img) continue;
  ?>
  <div class="photo-card" data-cat="<?php echo esc_attr($cat); ?>">
    <img src="<?php echo esc_url($img['sizes']['large'] ?? $img['url']); ?>"
         alt="<?php echo esc_attr($species . ' photographed at ' . $location); ?>"
         loading="lazy" width="<?php echo (int) $img['width']; ?>" height="<?php echo (int) $img['height']; ?>">
    <div class="photo-overlay">
      <span class="photo-species"><?php echo esc_html($species); ?></span>
      <span class="photo-credit">© <?php echo esc_html($credit); ?></span>
    </div>
  </div>
  <?php endforeach; ?>
</div>

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

  var grid    = document.getElementById('photo-grid');
  var lightbox = document.getElementById('db-lightbox');
  if (!grid || !lightbox) return;

  var filterBtns = document.querySelectorAll('.filter-btn');
  var cards       = Array.prototype.slice.call(grid.querySelectorAll('.photo-card'));
  var visibleCards = cards.slice();
  var currentIndex = -1;

  var lbImg     = lightbox.querySelector('.lightbox-img');
  var lbCaption = lightbox.querySelector('.lightbox-caption');
  var lbClose   = lightbox.querySelector('.lightbox-close');
  var lbPrev    = lightbox.querySelector('.lightbox-prev');
  var lbNext    = lightbox.querySelector('.lightbox-next');

  // Filtering
  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      filterBtns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');

      var cat = btn.dataset.cat;
      visibleCards = cards.filter(function (card) {
        var match = cat === 'all' || card.dataset.cat === cat;
        card.style.display = match ? '' : 'none';
        return match;
      });
    });
  });

  // Lightbox
  function openLightbox(index) {
    if (!visibleCards.length) return;
    currentIndex = (index + visibleCards.length) % visibleCards.length;
    var card = visibleCards[currentIndex];
    var img  = card.querySelector('img');
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

  function showNext(delta) {
    openLightbox(currentIndex + delta);
  }

  cards.forEach(function (card, i) {
    card.addEventListener('click', function () { openLightbox(visibleCards.indexOf(card)); });
    card.tabIndex = 0;
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openLightbox(visibleCards.indexOf(card)); }
    });
  });

  lbClose.addEventListener('click', closeLightbox);
  lbPrev.addEventListener('click', function () { showNext(-1); });
  lbNext.addEventListener('click', function () { showNext(1); });

  lightbox.addEventListener('click', function (e) {
    if (e.target === lightbox) closeLightbox();
  });

  document.addEventListener('keydown', function (e) {
    if (lightbox.hidden) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') showNext(-1);
    if (e.key === 'ArrowRight') showNext(1);
  });
})();
</script>
