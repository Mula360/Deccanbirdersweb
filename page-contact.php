<?php
/**
 * Template for the Contact page — rendered directly in PHP (no Elementor).
 *
 * Layout follows the design (Mula360/DBHTMLSite):
 *   Row 1 — two auto-fit columns:
 *     left  : "Send us a message" card
 *     right : stacked "Phone and WhatsApp" card + tinted
 *             "Volunteer · Report a sighting" card
 * Photographs are sent through the form on the Gallery page, not here.
 * There is no join band on this page, per the design's showJoinBand rule.
 */
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) : the_post();
  $phone = db_setting('contact_phone') ?: '+91 97388 40070';
  // wa.me needs digits only, no +, spaces or dashes.
  $wa_raw = db_setting('contact_whatsapp');
  $wa = preg_replace('/\D+/', '', $wa_raw ?: $phone);
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Contact</span>
    <h1>Get in touch</h1>
  </div>
</section>

<section class="contact-grid">
  <div class="contact-card">
    <h2 class="card-heading card-heading--lg">Send us a message</h2>
    <p class="card-intro">Questions about membership, trips or a bird you can't identify.</p>
    <form id="db-contact-form" class="stacked-form" novalidate>
      <div class="hp-field" aria-hidden="true">
        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>
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

    <!-- Volunteering: handled by wp_ajax db_volunteer, which emails the
         committee and appends a row to the volunteer sheet when one is
         configured (Settings → Site Settings). -->
    <div class="contact-card contact-card--volunteer">
      <h2 class="card-heading card-heading--lg">Lend a hand</h2>
      <p class="card-intro">Trips, counts and the newsletter all run on members' time. Tell us what you'd enjoy helping with and a committee member will be in touch.</p>
      <form id="db-volunteer-form" class="stacked-form" novalidate>
        <div class="hp-field" aria-hidden="true">
          <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <label class="stacked-field">
          <span class="stacked-label">Name</span>
          <input type="text" name="name" placeholder="Your name" required>
          <span class="field-error" id="vf-error-name"></span>
        </label>
        <label class="stacked-field">
          <span class="stacked-label">Email</span>
          <input type="email" name="email" placeholder="you@example.com" required>
          <span class="field-error" id="vf-error-email"></span>
        </label>
        <fieldset class="stacked-field checkbox-set">
          <legend class="stacked-label">I'd like to help with</legend>
          <?php foreach ([
            'Field trips and walks',
            'Annual waterfowl census',
            'School and college outreach',
            'PITTA newsletter',
            'Photography and the gallery',
            'Anything that needs doing',
          ] as $option): ?>
            <label class="form-checkbox">
              <input type="checkbox" name="help_with[]" value="<?php echo esc_attr($option); ?>">
              <?php echo esc_html($option); ?>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <p class="field-error" id="vf-error-global"></p>
        <button type="submit" class="btn btn-primary">Submit</button>
      </form>
    </div>
  </div>

  <div class="contact-col">
    <div class="contact-card">
      <h2 class="card-heading card-heading--spaced">Phone and WhatsApp</h2>
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
      <p class="card-intro card-intro--tight">Tell us what you saw, where and when. You can also put your hand up for the winter waterfowl census or a school outreach session.</p>
      <form id="db-sighting-report-form" class="stacked-form stacked-form--tight" novalidate>
        <div class="hp-field" aria-hidden="true">
          <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
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

<?php endwhile;
get_footer();
