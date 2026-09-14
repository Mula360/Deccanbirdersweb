/* Deccan Birders — global site JS
 * Hamburger nav + AJAX forms: contact (#db-contact-form → wp_ajax db_contact),
 * volunteer (#db-volunteer-form → wp_ajax db_volunteer), and report-a-sighting
 * (#db-sighting-report-form → wp_ajax db_sighting_report).
 * DB_CONFIG (api_base, ajax_url, nonce, region) is localized by functions.php.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initHamburger();
    initContactForm();
    initVolunteerForm();
    initSightingReportForm();
  });

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
      data.append('nonce', DB_CONFIG.nonce);

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
      data.append('nonce', DB_CONFIG.nonce);

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

      // Client-side validation
      let valid = true;
      ['name', 'email', 'species', 'location'].forEach((field) => {
        const input = form.querySelector(`[name=${field}]`);
        if (input && !input.value.trim()) {
          const errorEl = form.querySelector(`#sr-error-${field}`);
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
      data.append('action', 'db_sighting_report');
      data.append('nonce', DB_CONFIG.nonce);

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
