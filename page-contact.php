<?php
/**
 * Template for the Contact page — rendered directly in PHP (no Elementor).
 *
 * Layout follows the design (Mula360/DBHTMLSite):
 *   Row 1 — two auto-fit columns:
 *     left  : "Send us a message" card
 *     right : stacked "Phone and WhatsApp" card + tinted
 *             "Volunteer · Report a sighting" card
 *   Row 2 — full-width "Submit a photograph" card, itself two columns
 *           (notes on the left, form on the right with paired field rows).
 * There is no join band on this page, per the design's showJoinBand rule.
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

<section class="section-boxed contact-grid">
  <div class="contact-card">
    <h2 class="card-heading">Send us a message</h2>
    <p class="card-intro">Questions about membership, trips or a bird you can't identify.</p>
    <form id="db-contact-form" class="stacked-form" novalidate>
      <label class="stacked-field">
        <span class="stacked-label">Name</span>
        <input type="text" name="name" placeholder="Your name" required>
        <span class="field-error" id="error-name"></span>
      </label>
      <label class="stacked-field">
        <span class="stacked-label">Email</span>
        <input type="email" name="email" placeholder="you@example.com" required>
        <span class="field-error" id="error-email"></span>
      </label>
      <label class="stacked-field">
        <span class="stacked-label">Message</span>
        <textarea name="message" rows="5" placeholder="How can we help?" required></textarea>
        <span class="field-error" id="error-message"></span>
      </label>
      <p class="field-error" id="form-global-error"></p>
      <button type="submit" class="btn btn-primary">Send message</button>
    </form>
  </div>

  <div class="contact-col">
    <div class="contact-card">
      <h2 class="card-heading">Phone and WhatsApp</h2>
      <div class="contact-details">
        <div>
          <span class="contact-info-label">Enquiries</span>
          <div><a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></div>
        </div>
        <div>
          <?php if ($wa): ?>
            <span class="contact-info-label">WhatsApp</span>
            <div><a href="https://wa.me/<?php echo esc_attr($wa); ?>" target="_blank" rel="noopener">Message us on WhatsApp</a></div>
          <?php endif; ?>
        </div>
        <div>
          <span class="contact-info-label">Email</span>
          <div><a href="mailto:info@deccanbirders.org">info@deccanbirders.org</a></div>
        </div>
      </div>
    </div>

    <div class="contact-card contact-card--tint">
      <span class="eyebrow" style="color:var(--green);">Volunteer · Report a sighting</span>
      <h2 class="card-heading">Seen something unusual?</h2>
      <p class="card-intro">Tell us what you saw, where and when. You can also put your hand up for the winter waterfowl census or a school outreach session.</p>
      <form id="db-sighting-report-form" class="stacked-form stacked-form--tight" novalidate>
        <label class="stacked-field">
          <span class="stacked-label">Species and location</span>
          <input type="text" name="species_location" placeholder="e.g. Indian Skimmer, Manjeera" required>
          <span class="field-error" id="sr-error-species-location"></span>
        </label>
        <label class="stacked-field">
          <span class="stacked-label">I'd like to help with</span>
          <select name="help_with">
            <option>Reporting a sighting only</option>
            <option>Annual waterfowl census</option>
            <option>Field trip coordination</option>
            <option>School and college outreach</option>
            <option>PITTA newsletter</option>
          </select>
        </label>
        <p class="field-error" id="sr-error-global"></p>
        <button type="submit" class="btn btn-secondary">Submit</button>
      </form>
    </div>
  </div>
</section>

<section class="section-boxed" style="padding: 0 20px clamp(60px,8vw,96px);">
  <div class="photo-submit-card">
    <div>
      <span class="eyebrow" style="color:var(--blue);">Members only</span>
      <h2 class="card-heading">Submit a photograph</h2>
      <p class="card-intro">Your entry goes to <a href="mailto:photos@deccanbirders.org"><strong>photos@deccanbirders.org</strong></a> for review. Once a committee member approves it, the photograph appears in the gallery credited to you by name.</p>
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
</section>

<?php endwhile;
get_footer();
