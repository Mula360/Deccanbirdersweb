<?php
/**
 * Template for the Contact page — rendered directly in PHP (no Elementor).
 *
 * Matches the design: "Send us a message" form, phone/WhatsApp block, the
 * combined "Volunteer · Report a sighting" section, and "Submit a photograph".
 * The design folds volunteering into the sighting form via the "I'd like to
 * help with" select rather than having a separate volunteer form.
 */
get_header();
while (have_posts()) : the_post();
  $phone = get_field('contact_phone', 'option') ?: '+91 97388 40070';
  // wa.me needs digits only, no +, spaces or dashes.
  $wa_raw = get_field('contact_whatsapp', 'option');
  $wa = preg_replace('/\D+/', '', $wa_raw ?: $phone);
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Contact</span>
    <h1>Get in touch</h1>
  </div>
</section>

<section class="section-white two-col-60-40 section-boxed" style="padding-bottom:60px;">
  <div>
    <h2 style="font-size:26px;">Send us a message</h2>
    <p>Questions about membership, trips or a bird you can't identify.</p>
    <form id="db-contact-form" class="submission-form" novalidate>
      <div class="form-field">
        <label for="cf-name">Name</label>
        <input type="text" id="cf-name" name="name" required>
        <span class="field-error" id="error-name"></span>
      </div>
      <div class="form-field">
        <label for="cf-email">Email</label>
        <input type="email" id="cf-email" name="email" required>
        <span class="field-error" id="error-email"></span>
      </div>
      <div class="form-field">
        <label for="cf-message">Message</label>
        <textarea id="cf-message" name="message" rows="5" required></textarea>
        <span class="field-error" id="error-message"></span>
      </div>
      <p class="field-error" id="form-global-error"></p>
      <button type="submit" class="btn btn-primary">Send message</button>
    </form>
  </div>
  <div>
    <div class="contact-info-block">
      <h2 style="font-size:20px;margin-bottom:12px;">Phone and WhatsApp</h2>
      <span class="contact-info-label">Enquiries</span>
      <p><a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></p>
      <?php if ($wa): ?>
        <p><a href="https://wa.me/<?php echo esc_attr($wa); ?>" target="_blank" rel="noopener" class="btn btn-secondary">Message us on WhatsApp</a></p>
      <?php endif; ?>
      <span class="contact-info-label">Email</span>
      <p><a href="mailto:info@deccanbirders.org">info@deccanbirders.org</a></p>
    </div>
  </div>
</section>

<section class="section-surface" style="padding: 60px 20px 80px;">
  <div class="section-boxed">
    <span class="eyebrow" style="color:var(--green);">Volunteer · Report a sighting</span>
    <h2>Seen something unusual?</h2>
    <p>Tell us what you saw, where and when. You can also put your hand up for the winter waterfowl census or a school outreach session.</p>
    <form id="db-sighting-report-form" class="submission-form" novalidate>
      <div class="form-field">
        <label for="sr-species-location">Species and location</label>
        <input type="text" id="sr-species-location" name="species_location" placeholder="e.g. Indian Skimmer, Manjeera" required>
        <span class="field-error" id="sr-error-species-location"></span>
      </div>
      <div class="form-field">
        <label for="sr-help-with">I'd like to help with</label>
        <select id="sr-help-with" name="help_with">
          <option>Reporting a sighting only</option>
          <option>Annual waterfowl census</option>
          <option>Field trip coordination</option>
          <option>School and college outreach</option>
          <option>PITTA newsletter</option>
        </select>
      </div>
      <div class="form-field">
        <label for="sr-email">Email <span class="label-optional">(optional — so we can follow up)</span></label>
        <input type="email" id="sr-email" name="email">
        <span class="field-error" id="sr-error-email"></span>
      </div>
      <p class="field-error" id="sr-error-global"></p>
      <button type="submit" class="btn btn-secondary">Submit</button>
    </form>
  </div>
</section>

<section class="section-white" style="padding: 60px 20px 80px;">
  <div class="section-boxed">
    <span class="eyebrow" style="color:var(--blue);">Members Only</span>
    <h2>Submit a photograph</h2>
    <p>Your entry goes to <a href="mailto:photos@deccanbirders.org">photos@deccanbirders.org</a> for review. Once a committee member approves it, the photograph appears in the gallery credited to you by name.</p>
    <ul>
      <li>Tell us the name you would like the credit to read.</li>
      <li>One bird per frame, no baiting, no nest photography during breeding.</li>
      <li>Approvals usually take a week; you'll hear back either way.</li>
    </ul>
    <form id="db-photo-submit-form" class="submission-form" novalidate>
      <div class="form-field">
        <label for="ps-name-2">Photographer name</label>
        <input type="text" id="ps-name-2" name="name" required>
      </div>
      <div class="form-field">
        <label for="ps-email-2">Email</label>
        <input type="email" id="ps-email-2" name="email" required>
      </div>
      <div class="form-field">
        <label for="ps-species-2">Species</label>
        <input type="text" id="ps-species-2" name="species" required>
      </div>
      <div class="form-field">
        <label for="ps-location-2">Where and when</label>
        <input type="text" id="ps-location-2" name="location" required>
      </div>
      <div class="form-field">
        <label for="ps-photo-2">Photograph</label>
        <div class="dropzone">Drop a JPEG here, or browse
          <input type="file" id="ps-photo-2" name="photo" accept="image/jpeg" required>
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

<?php endwhile;
get_footer();
