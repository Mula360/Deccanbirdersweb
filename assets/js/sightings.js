/**
 * Deccan Birders — Sightings page + homepage strip.
 * Vanilla ES6, no dependencies. Talks to the deccan-birders-api Vercel
 * endpoint (DB_CONFIG.api_base) at /api/sightings.
 *
 * Expected markup (page-sightings.php): a .gallery-tabs bar with
 * data-tab="notable|recent|hotspots", the matching #sightings-* panels,
 * the #sightings-lookup card (with #species-search-input / -dropdown /
 * -results), and #sightings-otd. Scope is all of India.
 *
 *   <!-- Homepage strip -->
 *   <div id="home-sightings-rows"></div>
 */

(function () {
'use strict';

// Same-origin WP REST proxy (see functions.php) — avoids the Vercel API's
// CORS restriction to the production domain, and caches responses server-side.
const API      = '/wp-json/db/v1';
const region   = 'IN';
let activeTab  = 'notable';
let controller = null;

/* -------------------------------------------------------------------------
 * Utilities
 * ---------------------------------------------------------------------- */

function timeAgo(dateStr) {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.floor(hrs / 24);
  return `${days}d ago`;
}

function showSkeleton(id, rows = 4) {
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = Array(rows).fill(0).map(() => `
    <div class="sighting-row sighting-skeleton">
      <span class="sk-block" style="width:130px"></span>
      <span class="sk-block" style="width:80px;margin-left:12px"></span>
      <span class="sk-block" style="width:100px;margin-left:12px"></span>
    </div>`).join('');
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

function renderSightingRow(r) {
  // Homepage strip — compact single line.
  return `<div class="sighting-row">
    <span class="species">${escapeHtml(r.species)} <em class="scientific">${escapeHtml(r.scientific)}</em></span>
    <span class="locality">${escapeHtml(r.locality)}</span>
    <span class="when">${timeAgo(r.when)}</span>
  </div>`;
}

// Notable tab — bordered card with a yellow top rule.
function renderSightingCard(r) {
  return `<div class="sighting-card">
    <div class="sighting-species">${escapeHtml(r.species)}</div>
    <div class="sighting-sci">${escapeHtml(r.scientific)}</div>
    <div class="sighting-lines">
      <div>${escapeHtml(r.locality)}</div>
      <div class="sighting-when">${timeAgo(r.when)}</div>
    </div>
    <div class="sighting-chips">
      <span class="chip chip-green">${escapeHtml(String(r.count))} birds</span>
      <span class="chip chip-blue">${escapeHtml(r.status)}</span>
    </div>
  </div>`;
}

// All-recent tab — one row per record inside a single bordered card.
function renderRecentRow(r) {
  return `<div class="recent-row">
    <div class="recent-row-main">
      <span class="recent-species">${escapeHtml(r.species)}</span>
      <span class="recent-loc">${escapeHtml(r.locality)}</span>
    </div>
    <div class="recent-row-meta">
      <span class="chip chip-green">${escapeHtml(String(r.count))}</span>
      <span class="recent-when">${timeAgo(r.when)}</span>
    </div>
  </div>`;
}

function emptyState(msg) {
  return `<p class="db-empty">${escapeHtml(msg)}</p>`;
}

/* -------------------------------------------------------------------------
 * Core fetch. By default (standalone=false) this shares one AbortController
 * with the main tab-switching area (notable/recent/hotspots), so clicking a
 * new tab cancels a still-in-flight fetch for the tab you just left — that's
 * the only place cancel-the-previous-one is actually wanted. Every other
 * caller (on-this-day, the species-lookup index, a hotspot's species list,
 * the homepage strip) passes standalone=true so it gets its own controller
 * and can't cancel — or be cancelled by — an unrelated fetch. These used to
 * all share the one controller, which meant loadOnThisDay() running right
 * after loadTab('notable') on page load would immediately abort the
 * Notable tab's request before it could ever resolve.
 * ---------------------------------------------------------------------- */

async function fetchTab(tab, extra = {}, standalone = false) {
  let signal;
  if (standalone) {
    signal = new AbortController().signal;
  } else {
    if (controller) controller.abort();
    controller = new AbortController();
    signal = controller.signal;
  }
  const params = new URLSearchParams({ region, tab, ...extra });
  try {
    const res  = await fetch(`${API}/sightings?${params}`, { signal });
    const json = await res.json();
    if (json.error) {
      console.error('Sightings API error:', json.message);
      return [];
    }
    return json.data || [];
  } catch (e) {
    if (e.name !== 'AbortError') console.error('Sightings fetch error:', e);
    return null;
  }
}

/* -------------------------------------------------------------------------
 * Tab renderers
 * ---------------------------------------------------------------------- */

/* -------------------------------------------------------------------------
 * Shared pagination. The design lays Notable out as a 3-4 across card grid,
 * so a "page" is 10 rows of that grid — 30 records. The same page size is
 * used for the recent list so both tabs behave consistently.
 * ---------------------------------------------------------------------- */

const PAGE_SIZE = 30;

function paginateInto(el, items, renderItem, wrapClass) {
  let page = 1;
  const pages = Math.max(1, Math.ceil(items.length / PAGE_SIZE));

  function paint() {
    const start = (page - 1) * PAGE_SIZE;
    const slice = items.slice(start, start + PAGE_SIZE);

    const nav = pages > 1 ? `
      <nav class="events-pager" aria-label="Sightings pages">
        <button type="button" class="events-pager-btn" data-step="-1"${page === 1 ? ' disabled' : ''}>← Previous</button>
        <span class="events-pager-status">Page ${page} of ${pages} · ${items.length} records</span>
        <button type="button" class="events-pager-btn" data-step="1"${page === pages ? ' disabled' : ''}>Next →</button>
      </nav>` : `<p class="records-count">${items.length} record${items.length === 1 ? '' : 's'}</p>`;

    el.innerHTML = `<div class="${wrapClass}">${slice.map(renderItem).join('')}</div>${nav}`;

    el.querySelectorAll('.events-pager-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        page = Math.min(pages, Math.max(1, page + Number(btn.dataset.step)));
        paint();
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  paint();
}

function renderNotable(data) {
  const el = document.getElementById('sightings-notable');
  if (!el || data === null) return;
  if (!data.length) { el.innerHTML = emptyState('No notable sightings reported recently.'); return; }
  paginateInto(el, data, renderSightingCard, 'sighting-cards');
}

function renderRecent(data) {
  const el = document.getElementById('sightings-recent');
  if (!el || data === null) return;
  if (!data.length) { el.innerHTML = emptyState('No recent sightings reported in the last 14 days.'); return; }
  paginateInto(el, data, renderRecentRow, 'recent-list');
}

// Hotspots: 5 rows, each expandable to show that hotspot's species list.
const hotspotSpeciesCache = new Map();

// eBird's /ref/hotspot endpoint returns numSpeciesAllTime but no checklist
// count, so h.checklists is absent — render that stat only when the API
// actually supplies a number.
function renderHotspots(data) {
  const el = document.getElementById('sightings-hotspots');
  if (!el || data === null) return;
  if (!data.length) { el.innerHTML = emptyState('No hotspot data available.'); return; }

  el.innerHTML = `<div class="hotspot-list">${data.map((h, i) => `
    <div class="hotspot-item">
      <button class="hotspot-row" type="button" data-loc-id="${escapeHtml(h.locId)}" aria-expanded="false">
        <span class="hotspot-left">
          <span class="hotspot-rank">${i + 1}</span>
          <span class="hotspot-name">${escapeHtml(h.name)}</span>
        </span>
        <span class="hotspot-right">
          <span class="hotspot-stat">
            <span class="hotspot-stat-num hotspot-stat-species">${escapeHtml(String(h.species))}</span>
            <span class="hotspot-stat-label">species</span>
          </span>
          ${Number.isFinite(Number(h.checklists)) ? `
          <span class="hotspot-stat">
            <span class="hotspot-stat-num hotspot-stat-checklists">${escapeHtml(String(h.checklists))}</span>
            <span class="hotspot-stat-label">checklists</span>
          </span>` : ''}
          <span class="hotspot-arrow" aria-hidden="true">+</span>
        </span>
      </button>
      <div class="hotspot-species-panel" hidden></div>
    </div>`).join('')}</div>`;

  el.querySelectorAll('.hotspot-row').forEach((row) => {
    row.addEventListener('click', () => toggleHotspot(row));
  });
}

async function toggleHotspot(row) {
  const panel = row.nextElementSibling;
  const locId = row.dataset.locId;
  const expanded = row.classList.toggle('expanded');
  row.setAttribute('aria-expanded', String(expanded));

  if (!expanded) {
    panel.hidden = true;
    return;
  }

  panel.hidden = false;

  if (hotspotSpeciesCache.has(locId)) {
    panel.innerHTML = hotspotSpeciesCache.get(locId);
    return;
  }

  panel.innerHTML = '<span class="hotspot-loading">Loading species…</span>';
  const species = await fetchTab('hotspot_species', { locId }, true);

  if (species === null) return; // aborted — leave whatever is showing

  const SHOWN = 24;
  const html = species.length
    ? `<div class="hotspot-panel-label">Recorded here</div>
       <div class="hotspot-species-list">
         ${species.slice(0, SHOWN).map((code) => `<span class="species-pill">${escapeHtml(code)}</span>`).join('')}
         ${species.length > SHOWN ? `<span class="species-pill species-pill--more">+${species.length - SHOWN} more on the full list</span>` : ''}
       </div>`
    : '<p class="db-empty">No species list available for this hotspot.</p>';

  hotspotSpeciesCache.set(locId, html);
  panel.innerHTML = html;
}

// Species lookup: builds a local index from already-fetched notable +
// recent records (no separate taxonomy endpoint needed), then filters
// client-side as the user types.
let lookupIndex = null;
let lookupInitialized = false;

async function ensureLookupIndex() {
  if (lookupIndex) return lookupIndex;
  const [notable, recent] = await Promise.all([fetchTab('notable', {}, true), fetchTab('recent', {}, true)]);
  const all = [...(notable || []), ...(recent || [])];

  lookupIndex = new Map();
  all.forEach((r) => {
    if (!lookupIndex.has(r.species)) {
      lookupIndex.set(r.species, { scientific: r.scientific, records: [] });
    }
    lookupIndex.get(r.species).records.push(r);
  });
  return lookupIndex;
}

function renderSpeciesLookup() {
  const panel = document.getElementById('sightings-lookup');
  if (!panel || lookupInitialized) return;
  lookupInitialized = true;

  const input    = panel.querySelector('#species-search-input');
  const dropdown = panel.querySelector('#species-search-dropdown');
  const results  = panel.querySelector('#species-search-results');
  if (!input || !dropdown || !results) return;

  let debounceTimer = null;
  let highlighted = -1;

  const closeDropdown = () => {
    dropdown.hidden = true;
    dropdown.innerHTML = '';
    highlighted = -1;
  };

  const openDropdown = (matches) => {
    if (!matches.length) {
      dropdown.innerHTML = '<div class="autocomplete-empty">No matching species.</div>';
      dropdown.hidden = false;
      return;
    }
    dropdown.innerHTML = matches.map((name, i) => `
      <button type="button" class="autocomplete-option" data-species="${escapeHtml(name)}" data-index="${i}">
        ${escapeHtml(name)} <span class="sci-name">${escapeHtml(lookupIndex.get(name).scientific)}</span>
      </button>
    `).join('');
    dropdown.hidden = false;
    dropdown.querySelectorAll('.autocomplete-option').forEach((opt) => {
      opt.addEventListener('click', () => selectSpecies(opt.dataset.species));
    });
  };

  const selectSpecies = (species) => {
    const entry = lookupIndex.get(species);
    closeDropdown();
    input.value = species;

    if (!entry || !entry.records.length) {
      results.innerHTML = emptyState(`No recent locations found for ${species} nearby.`);
      return;
    }

    results.innerHTML = `
      <h3 class="lookup-heading">Where to see ${escapeHtml(species)} nearby</h3>
      <div class="sighting-cards">${entry.records.map(renderSightingCard).join('')}</div>
    `;
  };

  input.addEventListener('input', () => {
    const value = input.value.trim();
    clearTimeout(debounceTimer);

    if (value.length < 3) {
      closeDropdown();
      results.innerHTML = '';
      return;
    }

    debounceTimer = setTimeout(async () => {
      await ensureLookupIndex();
      const q = value.toLowerCase();
      const matches = [...lookupIndex.keys()]
        .filter((name) => name.toLowerCase().includes(q) || lookupIndex.get(name).scientific.toLowerCase().includes(q))
        .slice(0, 8);
      openDropdown(matches);
    }, 300);
  });

  input.addEventListener('keydown', (e) => {
    const options = dropdown.querySelectorAll('.autocomplete-option');
    if (dropdown.hidden || !options.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      highlighted = (highlighted + 1) % options.length;
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      highlighted = (highlighted - 1 + options.length) % options.length;
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (highlighted >= 0) selectSpecies(options[highlighted].dataset.species);
      return;
    } else if (e.key === 'Escape') {
      closeDropdown();
      return;
    } else {
      return;
    }

    options.forEach((opt, i) => opt.classList.toggle('is-highlighted', i === highlighted));
    options[highlighted].scrollIntoView({ block: 'nearest' });
  });

  document.addEventListener('click', (e) => {
    if (!panel.contains(e.target)) closeDropdown();
  });
}

function renderOnThisDay(data) {
  const el = document.getElementById('sightings-otd');
  if (!el || data === null) return;
  if (!data.length) { el.innerHTML = emptyState("No historic records found for today's date."); return; }

  el.innerHTML = data.map((r) => {
    const yearMatch = String(r.when).match(/\d{4}/);
    const year = yearMatch ? yearMatch[0] : '';
    return `<div class="sighting-row">
      <span class="species">${escapeHtml(r.species)}</span>
      <span class="chip chip-green">${escapeHtml(String(r.count))}</span>
      <span class="locality">${escapeHtml(r.locality)}</span>
      <span class="when">${escapeHtml(year)}</span>
    </div>`;
  }).join('');
}

/* -------------------------------------------------------------------------
 * Tab switching
 * ---------------------------------------------------------------------- */

function initTabSwitching() {
  document.querySelectorAll('.gallery-tab[data-tab]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const tab = btn.dataset.tab;
      activeTab = tab;

      document.querySelectorAll('.gallery-tab').forEach((b) => {
        const on = b === btn;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      document.querySelectorAll('.tab-panel').forEach((p) => {
        const isActive = p.id === `sightings-${tab}`;
        p.hidden = !isActive;
      });

      await loadTab(tab);
    });
  });
}

async function loadTab(tab) {
  switch (tab) {
    case 'notable':
      showSkeleton('sightings-notable');
      renderNotable(await fetchTab('notable'));
      break;
    case 'recent':
      showSkeleton('sightings-recent');
      renderRecent(await fetchTab('recent'));
      break;
    case 'hotspots':
      showSkeleton('sightings-hotspots', 5);
      renderHotspots(await fetchTab('hotspots'));
      break;
    case 'lookup':
      renderSpeciesLookup();
      break;
  }
}

/* -------------------------------------------------------------------------
 * On This Day
 * ---------------------------------------------------------------------- */

async function loadOnThisDay() {
  const now = new Date();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  showSkeleton('sightings-otd', 3);
  const data = await fetchTab('onthisday', { m, d }, true);
  renderOnThisDay(data);
}

/* -------------------------------------------------------------------------
 * Homepage strip
 * ---------------------------------------------------------------------- */

async function initHomeStrip() {
  const strip = document.getElementById('home-sightings-rows');
  if (!strip) return;
  const data = await fetchTab('recent', {}, true);
  if (!data) { strip.innerHTML = '<p class="strip-error">Could not load sightings.</p>'; return; }
  strip.innerHTML = data.slice(0, 4).map(renderSightingRow).join('');
  // Auto-refresh every 15 minutes
  setTimeout(initHomeStrip, 15 * 60 * 1000);
}

/* -------------------------------------------------------------------------
 * Init
 * ---------------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', () => {
  initTabSwitching();

  if (document.getElementById('sightings-notable')) {
    // Full sightings page
    loadTab('notable');
    loadOnThisDay();
  }
  if (document.getElementById('home-sightings-rows')) {
    initHomeStrip();
  }
});

})();
