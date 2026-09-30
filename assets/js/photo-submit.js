/* Deccan Birders — the "Submit a photograph" form on the Gallery page.
 *
 * The species picker: typing searches the species list (/db/v1/species)
 * by common or scientific name; picking one fills the hidden species_id
 * and asks /db/v1/species-cap whether that species already has its share
 * of photos this month, so the photographer knows before sending.
 * Typing again clears the pick, so what is sent is always a listed species.
 *
 * The file is checked here for type and size before anything is sent;
 * the server checks both again. DB_PHOTO (max_bytes, max_mb) is localized
 * in inc/gallery/submissions.php.
 */
(function () {
  'use strict';

  const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  const EXT = /\.(jpe?g|png|webp)$/i;

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('#db-photo-submit-form');
    if (!form || typeof DB_CONFIG === 'undefined' || typeof DB_PHOTO === 'undefined') return;
    const species = initSpeciesPicker(form);
    initFile(form);
    initSubmit(form, species);
  });

  /* ---------------------------------------------------------------------
   * Species picker
   * ------------------------------------------------------------------ */
  function initSpeciesPicker(form) {
    const input = form.querySelector('#db-species-input');
    const list = form.querySelector('#db-species-list');
    const hidden = form.querySelector('[name=species_id]');
    const note = form.querySelector('#db-species-note');
    let results = [];
    let active = -1;
    let timer = null;
    let seq = 0;

    function setNote(text, cap) {
      note.textContent = text;
      note.classList.toggle('species-note--cap', !!cap && !!text);
    }

    function close() {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    }

    function render() {
      list.innerHTML = '';
      if (!results.length) {
        const li = document.createElement('li');
        li.className = 'species-empty';
        li.textContent = 'No species match. Try the common or the scientific name.';
        list.appendChild(li);
      }
      results.forEach(function (r, i) {
        const li = document.createElement('li');
        li.id = 'db-species-opt-' + i;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', i === active ? 'true' : 'false');
        li.textContent = r.common;
        const sci = document.createElement('span');
        sci.className = 'species-sci';
        sci.textContent = r.scientific;
        li.appendChild(sci);
        // mousedown, not click, so it lands before the input's blur closes the list.
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(i); });
        list.appendChild(li);
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      if (active >= 0) input.setAttribute('aria-activedescendant', 'db-species-opt-' + active);
      else input.removeAttribute('aria-activedescendant');
    }

    function highlight(i) {
      if (!results.length) return;
      active = (i + results.length) % results.length;
      render();
      const el = list.children[active];
      if (el) el.scrollIntoView({ block: 'nearest' });
    }

    async function search(q) {
      const mine = ++seq;
      try {
        const res = await fetch(DB_CONFIG.rest_url + 'species?q=' + encodeURIComponent(q));
        const json = await res.json();
        if (mine !== seq) return; // a newer search has started
        results = json.data || [];
        active = results.length ? 0 : -1;
        render();
      } catch (e) {
        if (mine === seq) close();
      }
    }

    async function checkCap(id) {
      try {
        const res = await fetch(DB_CONFIG.rest_url + 'species-cap?id=' + encodeURIComponent(id), { cache: 'no-store' });
        const json = await res.json();
        if (hidden.value === String(id)) setNote(json.reached ? json.message : '', json.reached);
      } catch (e) { /* the server checks again on submit */ }
    }

    function choose(i) {
      const r = results[i];
      if (!r) return;
      input.value = r.common + ' (' + r.scientific + ')';
      hidden.value = String(r.id);
      setNote('', false);
      close();
      checkCap(r.id);
    }

    input.addEventListener('input', function () {
      hidden.value = '';
      setNote('', false);
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) { seq++; close(); return; }
      timer = setTimeout(function () { search(q); }, 200);
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden && input.value.trim().length >= 2) search(input.value.trim()); else highlight(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
      else if (e.key === 'Enter' && !list.hidden) { e.preventDefault(); if (active >= 0) choose(active); }
      else if (e.key === 'Escape') { close(); }
    });

    input.addEventListener('blur', function () { setTimeout(close, 100); });

    return {
      picked: function () { return hidden.value !== ''; },
      focus: function () { input.focus(); },
      reset: function () { input.value = ''; hidden.value = ''; setNote('', false); close(); },
    };
  }

  /* ---------------------------------------------------------------------
   * File
   * ------------------------------------------------------------------ */
  function fileProblem(file) {
    if (!file) return 'Please choose a photograph.';
    if (!EXT.test(file.name) || (file.type && TYPES.indexOf(file.type) === -1)) {
      return 'Please choose a JPG, PNG or WebP photograph.';
    }
    if (file.size > DB_PHOTO.max_bytes) {
      return 'That photograph is ' + (file.size / 1048576).toFixed(1) + ' MB. The limit is ' +
        DB_PHOTO.max_mb + ' MB, so please resize it and try again.';
    }
    return '';
  }

  function initFile(form) {
    const input = form.querySelector('input[type=file]');
    const zone = form.querySelector('.dropzone');
    const label = form.querySelector('.dropzone-label');
    if (!input) return;

    function describe() {
      const file = input.files[0];
      if (label) label.textContent = file ? file.name : 'Drop a photograph here, or browse';
      if (zone) zone.classList.toggle('has-file', !!file);
      showError(form, file ? fileProblem(file) : '');
    }
    input.addEventListener('change', describe);

    if (!zone) return;
    // Dragging a file onto the box. A file dropped anywhere else on the
    // page would otherwise make the browser leave the page to show it.
    const hasFiles = function (e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') !== -1; };
    ['dragover', 'drop'].forEach(function (type) {
      window.addEventListener(type, function (e) { if (hasFiles(e)) e.preventDefault(); });
    });
    let depth = 0; // dragenter/leave fire for every child the pointer crosses
    zone.addEventListener('dragenter', function (e) {
      if (!hasFiles(e)) return;
      e.preventDefault();
      depth++;
      zone.classList.add('is-over');
    });
    zone.addEventListener('dragover', function (e) {
      if (!hasFiles(e)) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'copy';
    });
    zone.addEventListener('dragleave', function () {
      depth = Math.max(0, depth - 1);
      if (!depth) zone.classList.remove('is-over');
    });
    zone.addEventListener('drop', function (e) {
      if (!hasFiles(e)) return;
      e.preventDefault();
      depth = 0;
      zone.classList.remove('is-over');
      const file = e.dataTransfer.files[0];
      if (!file) return;
      // One photograph per submission: keep the first if several are dropped.
      try {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
      } catch (err) {
        input.files = e.dataTransfer.files;
      }
      describe();
      if (e.dataTransfer.files.length > 1) {
        showError(form, 'One photograph per submission: we kept ' + file.name + '. Send the others one at a time.');
      }
    });
  }

  /* ---------------------------------------------------------------------
   * Submit
   * ------------------------------------------------------------------ */
  function showError(form, message) {
    let el = form.querySelector('.form-error-global');
    if (!el) {
      el = document.createElement('p');
      el.className = 'form-error-global field-error';
      el.setAttribute('role', 'alert');
      form.insertBefore(el, form.querySelector('[type=submit]'));
    }
    el.textContent = message;
  }

  /**
   * The thank-you, in place of the form. The form is only hidden, so
   * "Submit another photo" brings it back ready for the next one, with the
   * name and email kept. The button shows only while this address still
   * has room under its limit.
   */
  function showDone(form, json, species) {
    const fields = Array.from(form.children);
    fields.forEach(function (el) { el.hidden = true; });

    const done = document.createElement('div');
    done.className = 'form-success';
    done.setAttribute('role', 'status');
    const p = document.createElement('p');
    p.textContent = json.message;
    done.appendChild(p);
    if (json.species_note) {
      const n = document.createElement('p');
      n.className = 'species-note species-note--cap';
      n.textContent = json.species_note;
      done.appendChild(n);
    }

    const left = typeof json.remaining === 'number' ? json.remaining : 1;
    if (left > 0) {
      const more = document.createElement('p');
      more.className = 'form-success-more';
      more.textContent = 'You can send ' + left + ' more photograph' + (left === 1 ? '' : 's') +
        ' in the next ' + json.window_days + ' days.';
      done.appendChild(more);
      const again = document.createElement('button');
      again.type = 'button';
      again.className = 'btn btn-primary';
      again.textContent = 'Submit another photo';
      again.addEventListener('click', function () {
        done.remove();
        fields.forEach(function (el) { el.hidden = false; });
        resetForNext(form, species);
      });
      done.appendChild(again);
    } else if (typeof json.remaining === 'number') {
      const full = document.createElement('p');
      full.className = 'form-success-more';
      full.textContent = 'That was your last photograph for now: the limit is reached for the next ' +
        json.window_days + ' days.';
      done.appendChild(full);
      // Not a member: membership is how to send more.
      if (json.member === false && json.membership_url) {
        const why = document.createElement('p');
        why.className = 'form-success-more';
        why.textContent = 'In order to submit more images, get regular updates and get early access to our Newsletter, become a member.';
        done.appendChild(why);
        const join = document.createElement('a');
        join.className = 'btn btn-primary';
        join.href = json.membership_url;
        join.textContent = 'Become a member';
        done.appendChild(join);
      }
    }
    form.appendChild(done);
    done.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  /** Clear everything but who is sending, ready for the next photograph. */
  function resetForNext(form, species) {
    species.reset();
    form.querySelector('[name=location]').value = '';
    const file = form.querySelector('input[type=file]');
    file.value = '';
    file.dispatchEvent(new Event('change'));
    form.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; });
    showError(form, '');
    const btn = form.querySelector('[type=submit]');
    btn.disabled = false;
    btn.textContent = 'Send for approval';
    species.focus();
  }

  function initSubmit(form, species) {
    const btn = form.querySelector('[type=submit]');
    const fileInput = form.querySelector('input[type=file]');

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      showError(form, '');

      const val = function (n) { return (form.querySelector('[name=' + n + ']').value || '').trim(); };
      if (!val('name') || !val('email') || !val('location')) { showError(form, 'Please fill in every field.'); return; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('email'))) { showError(form, 'That email address does not look right.'); return; }
      if (!species.picked()) { showError(form, 'Please pick the species from the list as you type.'); species.focus(); return; }
      const problem = fileProblem(fileInput && fileInput.files[0]);
      if (problem) { showError(form, problem); return; }
      if (!form.querySelector('[name=no_nest]').checked) {
        showError(form, 'Please confirm this is not a nest photograph and the bird was not disturbed.');
        return;
      }
      if (!form.querySelector('[name=consent]').checked) {
        showError(form, 'Please confirm the photograph is yours and that we may show it with your credit.');
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Sending…';
      const data = new FormData(form);
      data.append('action', 'db_photo_submit');
      data.append('nonce', await window.dbFreshNonce());

      try {
        const res = await fetch(DB_CONFIG.ajax_url, { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
          showDone(form, json, species);
        } else {
          showError(form, json.message || 'Something went wrong. Please email photos@deccanbirders.org');
          btn.disabled = false;
          btn.textContent = 'Send for approval';
        }
      } catch (err) {
        showError(form, 'Network error. Please try again, or email photos@deccanbirders.org');
        btn.disabled = false;
        btn.textContent = 'Send for approval';
      }
    });
  }
})();
