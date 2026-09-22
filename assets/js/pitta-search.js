/* Deccan Birders — PITTA archive (Archives page)
 *
 * The markup comes from template-parts/shortcode-pitta-accordion.php: a
 * search pill, an empty results panel, year pills, one grid per year (only
 * the newest visible) and a year stepper. This file does three things:
 *
 *  - switches the visible year, from the pills or the stepper
 *  - fills #pitta-results from /wp-json/db/v1/pitta-search (the full text
 *    of every issue), as cards with a cover, a page line and a snippet
 *  - narrows the archive while a search is running: years without a match
 *    dim, the first year with one is selected, and covers that didn't
 *    match dim within it
 *
 * The query is mirrored to ?q= so a search can be shared or reloaded.
 */
(function () {
  'use strict';

  const MIN_CHARS = 3;
  const FIRST_PAGE = 6; // results shown before "Show all"

  document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('pitta-search');
    const results = document.getElementById('pitta-results');
    if (!input || !results) return;

    const box = document.getElementById('pitta-search-box');
    const clearBtn = document.getElementById('pitta-search-clear');
    const pills = Array.from(document.querySelectorAll('.year-pill'));
    const panels = Array.from(document.querySelectorAll('.pitta-year-panel'));
    const foot = document.getElementById('pitta-foot');
    const footStatus = document.getElementById('pitta-foot-status');
    const endpoint = ((window.DB_CONFIG && DB_CONFIG.rest_url) || '/wp-json/db/v1/') + 'pitta-search';
    const years = panels.map((p) => p.dataset.year);
    const baseStatus = footStatus ? footStatus.textContent : '';

    let current = years[0];
    let timer = null;
    let controller = null;
    let lastResults = [];
    let expanded = false;

    function el(tag, className, text) {
      const node = document.createElement(tag);
      if (className) node.className = className;
      if (text != null) node.textContent = text;
      return node;
    }

    // --- Years ------------------------------------------------------------
    function showYear(year) {
      if (!years.includes(String(year))) return;
      current = String(year);
      panels.forEach((p) => { p.hidden = p.dataset.year !== current; });
      pills.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.year === current)));
      if (foot) {
        const i = years.indexOf(current);
        foot.querySelector('[data-step="-1"]').disabled = i <= 0;
        foot.querySelector('[data-step="1"]').disabled = i >= years.length - 1;
      }
      if (footStatus) footStatus.textContent = current + ' · ' + baseStatus;
    }

    function scrollToYear(year) {
      const panel = panels.find((p) => p.dataset.year === String(year));
      const title = panel && panel.querySelector('.year-title');
      if (!title) return;
      const top = window.scrollY + title.getBoundingClientRect().top - 24;
      if (Math.abs(top - window.scrollY) > 8) window.scrollTo(0, top);
    }

    pills.forEach((b) => b.addEventListener('click', () => {
      showYear(b.dataset.year);
      // Bring the grid into view: covers are lazy, so a year switched to
      // while scrolled elsewhere would otherwise sit blank until scrolled.
      // Instant rather than smooth — switching years should feel like a
      // swap, not a journey past the years in between.
      scrollToYear(b.dataset.year);
    }));

    if (foot) {
      foot.querySelectorAll('.btn[data-step]').forEach((btn) => {
        btn.addEventListener('click', () => {
          // Years run newest first, so "Older" steps forward through the list.
          const next = years[years.indexOf(current) + Number(btn.dataset.step)];
          if (next) {
            showYear(next);
            scrollToYear(next);
          }
        });
      });
    }

    // --- Narrowing the archive to matches ---------------------------------
    function narrow(urls) {
      const wanted = urls && urls.length ? new Set(urls) : null;
      panels.forEach((panel) => {
        let anyInYear = false;
        panel.querySelectorAll('.issue[href], .special[href]').forEach((a) => {
          const match = !wanted || wanted.has(a.href);
          a.classList.toggle('dim', !match);
          if (match) anyInYear = true;
        });
        panel.querySelectorAll('.gap').forEach((g) => g.classList.toggle('dim', !!wanted));
        const pill = pills.find((b) => b.dataset.year === panel.dataset.year);
        if (pill) pill.classList.toggle('dim', !!wanted && !anyInYear);
      });

      // Jump to the first year holding a match, so one is on screen.
      if (wanted) {
        const firstHit = panels.find((p) =>
          Array.from(p.querySelectorAll('.issue[href], .special[href]')).some((a) => wanted.has(a.href)));
        if (firstHit) showYear(firstHit.dataset.year);
      }
    }

    function clearNarrowing() {
      document.querySelectorAll('.dim').forEach((n) => n.classList.remove('dim'));
    }

    // --- Results ----------------------------------------------------------
    function message(text, className) {
      results.className = 'results show';
      results.replaceChildren(el('p', 'results-msg' + (className ? ' ' + className : ''), text));
    }

    function resultCard(r) {
      const card = el('a', 'result');
      card.href = (r.pages && r.pages[0] && r.pages[0].link) || r.url;
      card.target = '_blank';
      card.rel = 'noopener';

      if (r.cover) {
        const img = document.createElement('img');
        img.src = r.cover;
        img.alt = '';
        img.loading = 'lazy';
        card.appendChild(img);
      } else {
        card.appendChild(el('span', 'thumb'));
      }

      const body = document.createElement('div');
      body.appendChild(el('div', 't', r.title));

      // "Special issue · 2015 · p. 2, 7, 11" / "Regular issue · p. 4"
      const pageList = (r.pageNumbers || []).slice(0, 3);
      const bits = [r.special ? 'Special issue' : 'Regular issue'];
      if (r.special) bits.push(String(r.year));
      if (pageList.length) bits.push('p. ' + pageList.join(', '));
      body.appendChild(el('div', 'k', bits.join(' · ')));

      if (r.pages && r.pages.length) {
        const snippet = el('div', 's');
        snippet.innerHTML = r.pages[0].snippet; // escaped server-side; only <mark> is markup
        body.appendChild(snippet);
      }
      card.appendChild(body);
      return card;
    }

    function paintResults(q, data) {
      const list = lastResults;
      if (!list.length) {
        message('No issues mention “' + q + '”.', 'is-empty');
        narrow([]);
        return;
      }

      const total = data.total || list.length;
      const shown = expanded ? list : list.slice(0, FIRST_PAGE);

      const head = el('div', 'results-head');
      const strong = el('strong');
      strong.textContent = data.mode === 'all_words'
        ? 'No exact match — ' + total + ' issue' + (total === 1 ? ' contains' : 's contain') + ' all those words'
        : total + ' issue' + (total === 1 ? ' matches' : 's match') + ' “' + q + '”';
      head.appendChild(strong);
      head.appendChild(el('span', null, 'Ranked by mentions · showing ' + shown.length + ' of ' + total));

      const grid = el('div', 'results-grid');
      shown.forEach((r) => grid.appendChild(resultCard(r)));

      const footNote = el('div', 'results-foot');
      footNote.appendChild(el('span', null, 'The archive below is narrowed to matching issues. '));
      if (list.length > shown.length) {
        const more = el('button', null, 'Show all ' + list.length + ' results ›');
        more.type = 'button';
        more.addEventListener('click', () => { expanded = true; paintResults(q, data); });
        footNote.appendChild(more);
      }

      results.className = 'results show';
      results.replaceChildren(head, grid, footNote);
    }

    function search(raw) {
      const q = raw.trim().replace(/\s+/g, ' ');
      if (box) box.classList.toggle('has-query', q.length > 0);

      const url = new URL(window.location.href);
      if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
      history.replaceState(null, '', url);

      if (controller) controller.abort();

      if (q.length < MIN_CHARS) {
        results.className = 'results';
        results.replaceChildren();
        clearNarrowing();
        return;
      }

      expanded = false;
      if (window.DB && DB.birdLoader) {
        results.className = 'results show';
        results.innerHTML = DB.birdLoader('Searching every issue…');
      } else {
        message('Searching every issue…', 'is-loading');
      }
      controller = new AbortController();
      fetch(endpoint + '?q=' + encodeURIComponent(q), { signal: controller.signal })
        .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
          if (!ok || data.error) throw new Error(data.message || data.error || 'Search failed');
          lastResults = data.results || [];
          paintResults(q, data);
          narrow(lastResults.map((r) => r.url && new URL(r.url, window.location.href).href).filter(Boolean));
        })
        .catch((err) => {
          if (err.name === 'AbortError') return;
          clearNarrowing();
          message('Search is unavailable right now — you can still browse by year below.', 'is-error');
        });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(() => search(input.value), 300);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(timer);
        search(input.value);
      }
    });
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        input.value = '';
        clearTimeout(timer);
        search('');
        input.focus();
      });
    }

    showYear(current);

    const initial = new URLSearchParams(window.location.search).get('q');
    if (initial) {
      input.value = initial;
      search(initial);
    }
  });
})();
