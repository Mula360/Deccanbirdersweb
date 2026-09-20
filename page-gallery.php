<?php
/**
 * Template for the Gallery page — rendered directly in PHP (no Elementor).
 *
 * Design: heading with standfirst, an underlined tab bar, then either the
 * photo masonry (plus the "Submit a photograph" card, which belongs to the
 * Photographs tab) or the video grid. Videos are populated by videos.js.
 */
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
        <span class="eyebrow" style="color:var(--blue);">Members only</span>
        <h2 class="card-heading card-heading--xl">Submit a photograph</h2>
        <p class="card-intro card-intro--flush">Your entry goes to <a href="mailto:photos@deccanbirders.org"><strong>photos@deccanbirders.org</strong></a> for review. Once a committee member approves it, the photograph appears in the gallery credited to you by name.</p>
        <ul class="submit-notes">
          <li>Tell us the name you would like the credit to read.</li>
          <li>One bird per frame, no baiting, no nest photography during breeding.</li>
          <li>Approvals usually take a week; you'll hear back either way.</li>
        </ul>
      </div>
      <form id="db-photo-submit-form" class="stacked-form" novalidate>
        <div class="field-row">
          <label class="stacked-field">
            <span class="stacked-label">Photographer name</span>
            <input type="text" name="name" placeholder="As it should be credited" required>
          </label>
          <label class="stacked-field">
            <span class="stacked-label">Email</span>
            <input type="email" name="email" placeholder="you@example.com" required>
          </label>
        </div>
        <div class="field-row">
          <label class="stacked-field">
            <span class="stacked-label">Species</span>
            <input type="text" name="species" placeholder="e.g. Indian Roller" required>
          </label>
          <label class="stacked-field">
            <span class="stacked-label">Where and when</span>
            <input type="text" name="location" placeholder="Ameenpur Lake, Sep 2026" required>
          </label>
        </div>
        <label class="stacked-field">
          <span class="stacked-label">Photograph</span>
          <div class="dropzone">
            <div>Drop a JPEG here, or browse</div>
            <div class="form-hint">Up to 10 MB. Please keep the EXIF data intact.</div>
            <input type="file" name="photo" accept="image/jpeg" required>
          </div>
        </label>
        <label class="form-checkbox">
          <input type="checkbox" name="consent" required>
          I took this photograph and allow Deccan Birders to publish it with my credit.
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
