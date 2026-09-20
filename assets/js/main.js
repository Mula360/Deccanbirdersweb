/* Deccan Birders — global site JS
 * Hamburger nav + AJAX forms: contact (#db-contact-form → wp_ajax db_contact),
 * volunteer (#db-volunteer-form → wp_ajax db_volunteer), report-a-sighting
 * (#db-sighting-report-form → wp_ajax db_sighting_report) and photograph
 * submission (#db-photo-submit-form → wp_ajax db_photo_submit).
 * DB_CONFIG (api_base, ajax_url, nonce, region) is localized by functions.php.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initHamburger();
    initContactForm();
    initVolunteerForm();
    initSightingReportForm();
    initPhotoSubmitForm();
  });

  /**
   * A security token that is definitely current. The one in DB_CONFIG
   * comes from the page HTML, which LiteSpeed caches — tokens expire
   * after 24 hours, so a cached page can carry a dead one and every
   * submission would fail with "Security check failed". Fetching it at
   * submit time sidesteps that; DB_CONFIG is the fallback if the request
   * fails (offline, say), since a stale token beats no token.
   */
  async function freshNonce() {
    const base = (typeof DB_CONFIG !== 'undefined' && DB_CONFIG.rest_url) || '/wp-json/db/v1/';
    try {
      const res = await fetch(base + 'nonce', { cache: 'no-store' });
      const json = await res.json();
      return json.nonce || DB_CONFIG.nonce;
    } catch (e) {
      return DB_CONFIG.nonce;
    }
  }

  function initHamburger() {
    const ham = document.querySelector('.hamburger');
    const nav = document.querySelector('.mobile-nav');
    const overlay = document.querySelector('.nav-overlay');
    if (!ham || !nav || !overlay) return;

    const open = () => {
      document.body.classList.add('nav-open');
      ham.setAttribute('aria-expanded', 'true');
      nav.setAttribute('aria-hidden', 'false');
    };
    const close = () => {
      document.body.classList.remove('nav-open');
      ham.setAttribute('aria-expanded', 'false');
      nav.setAttribute('aria-hidden', 'true');
    };

    ham.addEventListener('click', () => (document.body.classList.contains('nav-open') ? close() : open()));
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    nav.querySelectorAll('a').forEach((a) => a.addEventListener('click', close));
  }

  function initContactForm() {
    const form = document.querySelector('#db-contact-form');
    if (!form || typeof DB_CONFIG === 'undefined') return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('[type=submit]');
      btn.disabled = true;
      btn.textContent = 'Sending...';

      // Clear previous errors
      form.querySelectorAll('.field-error').forEach((el) => (el.textContent = ''));
      const globalError = form.querySelector('#form-global-error');
      if (globalError) globalError.textContent = '';

      // Client-side validation
      let valid = true;
      ['name', 'email', 'message'].forEach((field) => {
        const input = form.querySelector(`[name=${field}]`);
        if (input && !input.value.trim()) {
          const errorEl = form.querySelector(`#error-${field}`);
          if (errorEl) errorEl.textContent = 'This field is required.';
          valid = false;
        }
      });

      if (!valid) {
        btn.disabled = false;
        btn.textContent = 'Send message';
        return;
      }

      const data = new FormData(form);
      data.append('action', 'db_contact');
      data.append('nonce', await freshNonce());

      try {
        const res = await fetch(DB_CONFIG.ajax_url, { method: 'POST', body: data });
        const json = await res.json();

        if (json.success) {
          form.innerHTML = '<div class="form-success"><p>Thank you! We will reply to ' + data.get('email') + ' within 2 working days.</p></div>';
        } else {
          if (globalError) globalError.textContent = json.message || 'Something went wrong. Please email us directly at info@deccanbirders.org';
          btn.disabled = false;
          btn.textContent = 'Send message';
        }
      } catch (err) {
        if (globalError) globalError.textContent = 'Network error. Please try again or email info@deccanbirders.org';
        btn.disabled = false;
        btn.textContent = 'Send message';
      }
    });
  }

  function initVolunteerForm() {
    const form = document.querySelector('#db-volunteer-form');
    if (!form || typeof DB_CONFIG === 'undefined') return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('[type=submit]');
      btn.disabled = true;
      btn.textContent = 'Sending...';

      // Clear previous errors
      form.querySelectorAll('.field-error').forEach((el) => (el.textContent = ''));
      const globalError = form.querySelector('#vf-error-global');
      if (globalError) globalError.textContent = '';

      // Client-side validation
      let valid = true;
      ['name', 'email'].forEach((field) => {
        const input = form.querySelector(`[name=${field}]`);
        if (input && !input.value.trim()) {
          const errorEl = form.querySelector(`#vf-error-${field}`);
          if (errorEl) errorEl.textContent = 'This field is required.';
          valid = false;
        }
      });

      if (!valid) {
        btn.disabled = false;
        btn.textContent = 'Submit';
        return;
      }

      const data = new FormData(form);
      data.append('action', 'db_volunteer');
      data.append('nonce', await freshNonce());

      try {
        const res = await fetch(DB_CONFIG.ajax_url, { method: 'POST', body: data });
        const json = await res.json();

        if (json.success) {
          form.innerHTML = '<div class="form-success"><p>Thank you for offering to help! We will be in touch soon.</p></div>';
        } else {
          if (globalError) globalError.textContent = json.message || 'Something went wrong. Please email us directly at info@deccanbirders.org';
          btn.disabled = false;
          btn.textContent = 'Submit';
        }
      } catch (err) {
        if (globalError) globalError.textContent = 'Network error. Please try again or email info@deccanbirders.org';
        btn.disabled = false;
        btn.textContent = 'Submit';
      }
    });
  }

  /**
   * Photograph submission. Sends the file with the rest of the fields;
   * the server stores it as a pending Gallery entry for a committee
   * member to publish. The dropzone shows the chosen filename so people
   * can tell the upload took.
   */
  function initPhotoSubmitForm() {
    const form = document.querySelector('#db-photo-submit-form');
    if (!form || typeof DB_CONFIG === 'undefined') return;

    const fileInput = form.querySelector('input[type=file]');
    const dropzone = form.querySelector('.dropzone');
    const MAX_BYTES = 10 * 1024 * 1024;

    if (fileInput && dropzone) {
      const label = dropzone.querySelector('div');
      fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (label) label.textContent = file ? file.name : 'Drop a JPEG here, or browse';
      });
    }

    function showError(message) {
      let el = form.querySelector('.form-error-global');
      if (!el) {
        el = document.createElement('p');
        el.className = 'form-error-global field-error';
        form.insertBefore(el, form.querySelector('[type=submit]'));
      }
      el.textContent = message;
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('[type=submit]');
      showError('');

      const file = fileInput && fileInput.files[0];
      if (!file) { showError('Please choose a JPEG photograph.'); return; }
      if (!/\.jpe?g$/i.test(file.name) || (file.type && file.type !== 'image/jpeg')) {
        showError('Please choose a JPEG (.jpg) photograph.');
        return;
      }
      if (file.size > MAX_BYTES) { showError('That file is over 10 MB. Please send a smaller JPEG.'); return; }
      if (!form.querySelector('[name=consent]').checked) {
        showError('Please confirm the photograph is yours to publish.');
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Sending…';

      const data = new FormData(form);
      data.append('action', 'db_photo_submit');
      data.append('nonce', await freshNonce());

      try {
        const res = await fetch(DB_CONFIG.ajax_url, { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
          form.innerHTML = '<div class="form-success"><p>Thank you — your photograph has been sent for review. ' +
            'You will hear back within about a week.</p></div>';
        } else {
          showError(json.message || 'Something went wrong. Please email photos@deccanbirders.org');
          btn.disabled = false;
          btn.textContent = 'Send for approval';
        }
      } catch (err) {
        showError('Network error. Please try again, or email photos@deccanbirders.org');
        btn.disabled = false;
        btn.textContent = 'Send for approval';
      }
    });
  }

  function initSightingReportForm() {
    const form = document.querySelector('#db-sighting-report-form');
    if (!form || typeof DB_CONFIG === 'undefined') return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('[type=submit]');
      btn.disabled = true;
      btn.textContent = 'Sending...';

      // Clear previous errors
      form.querySelectorAll('.field-error').forEach((el) => (el.textContent = ''));
      const globalError = form.querySelector('#sr-error-global');
      if (globalError) globalError.textContent = '';

      // Client-side validation. Per the design this form has exactly two
      // controls — "Species and location" (required) and the "I'd like to
      // help with" select (always has a value) — so there is nothing else
      // to check.
      let valid = true;
      const speciesLocation = form.querySelector('[name=species_location]');
      if (speciesLocation && !speciesLocation.value.trim()) {
        const errorEl = form.querySelector('#sr-error-species-location');
        if (errorEl) errorEl.textContent = 'This field is required.';
        valid = false;
      }

      if (!valid) {
        btn.disabled = false;
        btn.textContent = 'Submit';
        return;
      }

      const data = new FormData(form);
      data.append('action', 'db_sighting_report');
      data.append('nonce', await freshNonce());

      try {
        const res = await fetch(DB_CONFIG.ajax_url, { method: 'POST', body: data });
        const json = await res.json();

        if (json.success) {
          form.innerHTML = '<div class="form-success"><p>Thank you! Your sighting report has been sent.</p></div>';
        } else {
          if (globalError) globalError.textContent = json.message || 'Something went wrong. Please email us directly at info@deccanbirders.org';
          btn.disabled = false;
          btn.textContent = 'Submit';
        }
      } catch (err) {
        if (globalError) globalError.textContent = 'Network error. Please try again or email info@deccanbirders.org';
        btn.disabled = false;
        btn.textContent = 'Submit';
      }
    });
  }
})();
