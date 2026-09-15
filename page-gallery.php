<?php
/**
 * Template for the Gallery page — rendered directly in PHP (no Elementor).
 */
get_header();
while (have_posts()) : the_post();
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Gallery</span>
    <h1>From members' cameras</h1>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 30px;">
  <div class="section-boxed">
    <p>Newest first. Every photograph on this site was taken by a member of Deccan Birders, on a society trip or on their own time.</p>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 20px;">
  <div class="section-boxed">
    <div class="tab-bar">
      <button class="tab-btn active" data-tab="photos">Photographs</button>
      <button class="tab-btn" data-tab="videos">Videos</button>
    </div>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 40px;">
  <div class="section-boxed">
    <div id="photos-panel"><?php echo do_shortcode('[db_photo_gallery]'); ?></div>
    <div id="videos-panel" hidden><div id="videos-grid"></div></div>
  </div>
</section>

<script>
(function () {
  var photosPanel = document.getElementById('photos-panel');
  var videosPanel = document.getElementById('videos-panel');
  document.querySelectorAll('.tab-btn[data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.toggle('active', b === btn); });
      if (photosPanel) photosPanel.hidden = btn.dataset.tab !== 'photos';
      if (videosPanel) videosPanel.hidden = btn.dataset.tab !== 'videos';
    });
  });
})();
</script>

<section class="section-surface" style="padding: 60px 20px 80px;">
  <div class="section-boxed">
    <span class="eyebrow" style="color:var(--blue);">Members Only</span>
    <h2>Submit a photograph</h2>
    <p>Your entry goes to <a href="mailto:photos@deccanbirders.org">photos@deccanbirders.org</a> for review. Once a committee member approves it, the photograph appears in this gallery credited to you by name.</p>
    <ul>
      <li>Tell us the name you would like the credit to read.</li>
      <li>One bird per frame, no baiting, no nest photography during breeding.</li>
      <li>Approvals usually take a week; you'll hear back either way.</li>
    </ul>
    <form id="db-photo-submit-form" class="submission-form" novalidate>
      <div class="form-field">
        <label for="ps-name">Photographer name</label>
        <input type="text" id="ps-name" name="name" required>
      </div>
      <div class="form-field">
        <label for="ps-email">Email</label>
        <input type="email" id="ps-email" name="email" required>
      </div>
      <div class="form-field">
        <label for="ps-species">Species</label>
        <input type="text" id="ps-species" name="species" required>
      </div>
      <div class="form-field">
        <label for="ps-location">Where and when</label>
        <input type="text" id="ps-location" name="location" required>
      </div>
      <div class="form-field">
        <label for="ps-photo">Photograph</label>
        <div class="dropzone">Drop a JPEG here, or browse
          <input type="file" id="ps-photo" name="photo" accept="image/jpeg" required>
          <p class="form-hint">Up to 10 MB. Please keep the EXIF data intact.</p>
        </div>
      </div>
      <label class="form-checkbox">
        <input type="checkbox" name="consent" required>
        I took this photograph and allow Deccan Birders to publish it with my credit.
      </label>
      <button type="submit" class="btn btn-primary">Send for approval</button>
    </form>
  </div>
</section>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
