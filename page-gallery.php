<?php
/**
 * Template for the Gallery page — rendered directly in PHP (no Elementor).
 *
 * Design: heading with standfirst, an underlined tab bar, then either the
 * photo masonry (plus the "Submit a photograph" card, which belongs to the
 * Photographs tab) or the video grid. Videos are populated by videos.js.
 */
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) : the_post();
  $youtube = db_setting('social_youtube') ?: 'https://www.youtube.com/channel/UChYefSo9bbi-BBbRn9euCpg';
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Gallery</span>
    <h1>From members' cameras</h1>
    <p class="hero-standfirst">Newest first. Every photograph on this site was taken by a member of Deccan Birders, on a society trip or on their own time.</p>
  </div>
</section>

<div class="gallery-tabs-wrap">
  <div class="gallery-tabs" role="tablist">
    <button class="gallery-tab is-active" data-tab="photos" role="tab" aria-selected="true" aria-controls="photos-panel">
      Photographs<span class="gallery-tab-bar" aria-hidden="true"></span>
    </button>
    <button class="gallery-tab" data-tab="videos" role="tab" aria-selected="false" aria-controls="videos-panel">
      Videos<span class="gallery-tab-bar" aria-hidden="true"></span>
    </button>
  </div>
</div>

<div id="photos-panel" role="tabpanel">
  <div class="gallery-panel">
    <?php echo do_shortcode('[db_photo_gallery]'); ?>
  </div>

  <div class="photo-submit-wrap">
    <div class="photo-submit-card">
      <div>
        <span class="eyebrow" style="color:var(--blue);">Share your photographs</span>
        <h2 class="card-heading card-heading--xl">Submit a photograph</h2>
        <p class="card-intro card-intro--flush">Every photograph is reviewed by the committee before it appears in the gallery, credited to you by name. Questions: <a href="mailto:photos@deccanbirders.org"><strong>photos@deccanbirders.org</strong></a>.</p>
        <ul class="submit-notes">
          <li><span><strong>No nest photography.</strong> We do not accept photographs of nests, eggs, or chicks at the nest, at any time of year.</span></li>
          <li><span><strong>Never disturb a bird for a picture.</strong> No baiting, no call playback, no flushing, no clearing vegetation; keep your distance and move away if the bird is uneasy.</span></li>
          <li><span>One photograph per submission: JPG, PNG or WebP, up to <?php echo (int) db_gallery_setting('max_upload_mb'); ?> MB. Location data (GPS) is removed before it is published.</span></li>
          <li><span>Members can send <?php echo (int) db_gallery_setting('member_limit'); ?> photographs every <?php echo (int) db_gallery_setting('limit_window_days'); ?> days, and everyone else <?php echo (int) db_gallery_setting('nonmember_limit'); ?>.</span></li>
          <li><span>Approvals usually take a week; you'll hear back either way.</span></li>
        </ul>
      </div>
      <form id="db-photo-submit-form" class="stacked-form" novalidate enctype="multipart/form-data">
        <!-- Honeypot: hidden from people, filled in by bots. -->
        <div class="hp-field" aria-hidden="true">
          <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <div class="field-row">
          <label class="stacked-field">
            <span class="stacked-label">Photographer name</span>
            <input type="text" name="name" placeholder="As it should be credited" autocomplete="name" required>
          </label>
          <label class="stacked-field">
            <span class="stacked-label">Email</span>
            <input type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
          </label>
        </div>
        <div class="stacked-field species-field">
          <label class="stacked-label" for="db-species-input">Species</label>
          <div class="species-combo">
            <input id="db-species-input" type="text" role="combobox" autocomplete="off" spellcheck="false"
                   aria-autocomplete="list" aria-expanded="false" aria-controls="db-species-list"
                   aria-describedby="db-species-note" placeholder="Common or scientific name, e.g. Indian Roller" required>
            <ul id="db-species-list" class="species-list" role="listbox" aria-label="Species" hidden></ul>
          </div>
          <input type="hidden" name="species_id" value="">
          <span id="db-species-note" class="species-note" aria-live="polite"></span>
        </div>
        <label class="stacked-field">
          <span class="stacked-label">Where and when</span>
          <input type="text" name="location" placeholder="Ameenpur Lake, Sep 2026" required>
          <span class="form-hint">Shown with the photograph in the gallery.</span>
        </label>
        <label class="stacked-field">
          <span class="stacked-label">Photograph</span>
          <div class="dropzone">
            <div class="dropzone-label">Drop a photograph here, or browse</div>
            <div class="form-hint">JPG, PNG or WebP, up to <?php echo (int) db_gallery_setting('max_upload_mb'); ?> MB.</div>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required>
          </div>
        </label>
        <label class="form-checkbox">
          <input type="checkbox" name="no_nest" value="1" required>
          <span>This is not a nest photograph and the bird was not disturbed.</span>
        </label>
        <label class="form-checkbox">
          <input type="checkbox" name="consent" value="1" required>
          <span>I took this photograph, it is mine, and I permit Deccan Birders to display it with my credit.</span>
        </label>
        <button type="submit" class="btn btn-primary">Send for approval</button>
      </form>
    </div>
  </div>
</div>

<div id="videos-panel" role="tabpanel" hidden>
  <div class="gallery-panel">
    <div class="videos-intro">
      <p>Trip films, webinar recordings and census footage. Everything plays on our YouTube channel — thumbnails here update automatically as new videos are uploaded.</p>
      <a href="<?php echo esc_url($youtube); ?>" class="videos-yt-btn" target="_blank" rel="noopener">Open our YouTube channel</a>
    </div>
    <div id="videos-grid"></div>
  </div>
</div>

<script>
(function () {
  var panels = { photos: document.getElementById('photos-panel'), videos: document.getElementById('videos-panel') };
  document.querySelectorAll('.gallery-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.dataset.tab;
      document.querySelectorAll('.gallery-tab').forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      Object.keys(panels).forEach(function (k) { if (panels[k]) panels[k].hidden = k !== tab; });
    });
  });
})();
</script>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
