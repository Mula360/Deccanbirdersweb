/* Deccan Birders — PITTA archive search (Archives page)
 * One box drives two things:
 *  - #pitta-results: full-text matches from /wp-json/db/v1/pitta-search
 *    (DB_CONFIG.rest_url), one card per edition with page snippets. The
 *    snippet HTML is escaped server-side with only <mark> added.
 *  - the year grid (.pitta-year): narrowed to editions whose year/month/
 *    title match, plus every edition the full-text search found.
 * With no query the grid is paged, YEARS_PER_PAGE years at a time; a
 * search sets paging aside and shows every matching year.
 * The query is mirrored to ?q= so a search can be shared.
 */
(function () {
  'use strict';

  const MIN_CHARS = 3;
  const YEARS_PER_PAGE = 5;

  document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('pitta-search');
    const results = document.getElementById('pitta-results');
    if (!input || !results) return;

    const years = Array.from(document.querySelectorAll('.pitta-year'));
    const endpoint = ((window.DB_CONFIG && DB_CONFIG.rest_url) || '/wp-json/db/v1/') + 'pitta-search';
    const accordion = document.getElementById('pitta-accordion');
    const yearPages = Math.max(1, Math.ceil(years.length / YEARS_PER_PAGE));
    let yearPage = 1;
    let pager = null;

    let timer = null;
    let controller = null;

    function el(tag, className, text) {
      const node = document.createElement(tag);
      if (className) node.className = className;
      if (text != null) node.textContent = text;
      return node;
    }

    function linkTo(href, className, text) {
      const a = el('a', className, text);
      a.href = href;
      a.target = '_blank';
      a.rel = 'noopener';
      return a;
    }

    // --- Year paging (only while no search is running) -------------------
    function showYearPage(p) {
      yearPage = Math.min(yearPages, Math.max(1, p));
      const from = (yearPage - 1) * YEARS_PER_PAGE;
      years.forEach((yearEl, i) => {
        const onPage = i >= from && i < from + YEARS_PER_PAGE;
        yearEl.hidden = !onPage;
        yearEl.open = onPage && i === from; // newest year on the page opens
        yearEl.querySelectorAll('.pitta-month-col, .pitta-month-link').forEach((n) => { n.hidden = false; });
      });
      if (pager) {
        pager.hidden = false;
        pager.querySelector('.events-pager-status').textContent =
          `Page ${yearPage} of ${yearPages} · ${years.length} years`;
        pager.querySelector('[data-step="-1"]').disabled = yearPage === 1;
        pager.querySelector('[data-step="1"]').disabled = yearPage === yearPages;
      }
    }

    function buildYearPager() {
      if (yearPages < 2 || !accordion) return;
      pager = document.createElement('nav');
      pager.className = 'events-pager';
      pager.setAttribute('aria-label', 'PITTA archive years');
      pager.innerHTML =
        '<button type="button" class="events-pager-btn" data-step="-1">← Newer</button>' +
        '<span class="events-pager-status"></span>' +
        '<button type="button" class="events-pager-btn" data-step="1">Older →</button>';
      accordion.insertAdjacentElement('afterend', pager);
      pager.querySelectorAll('.events-pager-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          showYearPage(yearPage + Number(btn.dataset.step));
          accordion.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });
    }

    // --- Year grid -------------------------------------------------------
    // extraUrls: issue links the full-text search matched (null = not
    // searched yet, so only the local year/title filter applies).
    function filterGrid(q, extraUrls) {
      if (!q) {
        showYearPage(yearPage);
        return;
      }
      if (pager) pager.hidden = true;
      // A bare year ("2019") means that year's issues, not every issue that
      // mentions it — the text matches are still listed in #pitta-results.
      const isYear = years.some((y) => y.dataset.search === q);
      const urls = new Set(isYear ? [] : extraUrls || []);
      let anyVisible = false;

      years.forEach((yearEl) => {
        const yearMatch = yearEl.dataset.search.includes(q);
        let anyMatch = false;

        yearEl.querySelectorAll('.pitta-month-col').forEach((col) => {
          const links = col.querySelectorAll('.pitta-month-link');
          let colMatch = false;
          links.forEach((link) => {
            const match = yearMatch || link.dataset.search.includes(q) || urls.has(link.href);
            link.hidden = !match;
            if (match) colMatch = true;
          });
          // Empty months only stay when the whole year matched.
          col.hidden = links.length ? !colMatch : !yearMatch;
          if (colMatch) anyMatch = true;
        });

        const show = yearMatch || anyMatch;
        yearEl.hidden = !show;
        if (show) {
          yearEl.open = true;
          anyVisible = true;
        }
      });

      // Nothing matched anywhere: show the full grid rather than a blank page.
      if (!anyVisible && extraUrls) filterGrid('');
    }

    // --- Full-text results ----------------------------------------------
    function showMessage(text, className) {
      results.hidden = false;
      results.replaceChildren(el('p', 'pitta-results-msg' + (className ? ' ' + className : ''), text));
    }

    function renderResults(q, data) {
      const list = data.results || [];
      if (!list.length) {
        showMessage('No issues mention “' + q + '”.', 'is-empty');
        return;
      }
      const total = data.total || list.length;
      const summary = data.mode === 'all_words'
        ? 'No exact match for “' + q + '” — ' + total + (total === 1 ? ' issue contains' : ' issues contain') + ' all of those words'
        : total + (total === 1 ? ' issue mentions' : ' issues mention') + ' “' + q + '”';
      const head = el('p', 'pitta-results-count',
        summary + (total > list.length ? ' — showing the top ' + list.length : ''));
      const cards = el('ol', 'pitta-hits');

      list.forEach((r) => {
        const card = el('li', 'pitta-hit');
        const top = el('div', 'pitta-hit-top');
        const heading = el('div', 'pitta-hit-heading');
        heading.appendChild(el('h3', 'pitta-hit-title', r.title));
        // The title already carries the month and year.
        if (r.hits) heading.appendChild(el('span', 'pitta-hit-meta', r.hits + (r.hits === 1 ? ' mention' : ' mentions')));
        top.appendChild(heading);
        if (r.url) top.appendChild(linkTo(r.url, 'pitta-hit-read', 'Read issue'));
        card.appendChild(top);

        if (r.pages && r.pages.length) {
          const snippets = el('ul', 'pitta-hit-snippets');
          r.pages.forEach((p) => {
            const li = el('li');
            const a = linkTo(p.link || r.url, 'pitta-hit-snippet');
            a.appendChild(el('span', 'pitta-hit-page', 'p. ' + p.page));
            const text = el('span', 'pitta-hit-text');
            text.innerHTML = p.snippet; // escaped server-side; only <mark> is markup
            a.appendChild(text);
            li.appendChild(a);
            snippets.appendChild(li);
          });
          card.appendChild(snippets);
        }
        cards.appendChild(card);
      });

      results.hidden = false;
      results.replaceChildren(head, cards);
    }

    function search(raw) {
      const q = raw.trim().replace(/\s+/g, ' ');
      const lower = q.toLowerCase();

      const url = new URL(window.location.href);
      if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
      history.replaceState(null, '', url);

      if (controller) controller.abort();

      if (q.length < MIN_CHARS) {
        filterGrid(lower, null);
        results.hidden = true;
        results.replaceChildren();
        return;
      }

      showMessage('Searching every issue…', 'is-loading');
      controller = new AbortController();
      fetch(endpoint + '?q=' + encodeURIComponent(q), { signal: controller.signal })
        .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
          if (!ok || data.error) throw new Error(data.message || data.error || 'Search failed');
          renderResults(q, data);
          filterGrid(lower, (data.results || []).map((r) => r.url && new URL(r.url, window.location.href).href));
        })
        .catch((err) => {
          if (err.name === 'AbortError') return;
          filterGrid(lower, []);
          showMessage('Search is unavailable right now — you can still browse by year below.', 'is-error');
        });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(() => search(input.value), 300);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        clearTimeout(timer);
        search(input.value);
      }
    });

    buildYearPager();
    showYearPage(1);

    const initial = new URLSearchParams(window.location.search).get('q');
    if (initial) {
      input.value = initial;
      search(initial);
    }
  });
})();
