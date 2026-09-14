/**
 * Deccan Birders — Sightings page + homepage strip.
 * Vanilla ES6, no dependencies. Talks to the deccan-birders-api Vercel
 * endpoint (DB_CONFIG.api_base) at /api/sightings.
 *
 * Expected markup (page-sightings.php or wherever [db_sightings] lives):
 *
 *   <div class="tab-bar" role="tablist">
 *     <button class="tab-btn active" data-tab="notable" role="tab" aria-selected="true">Notable</button>
 *     <button class="tab-btn" data-tab="recent" role="tab" aria-selected="false">Recent</button>
 *     <button class="tab-btn" data-tab="hotspots" role="tab" aria-selected="false">Hotspots</button>
 *     <button class="tab-btn" data-tab="lookup" role="tab" aria-selected="false">Species Lookup</button>
 *   </div>
 *
 *   <div class="region-toggle">
 *     <button class="region-btn" data-region="IN-TG">Telangana</button>
 *     <button class="region-btn active" data-region="IN-AP">Andhra Pradesh</button>
 *   </div>
 *
 *   <div class="tab-panel" id="sightings-notable" role="tabpanel"></div>
 *   <div class="tab-panel" id="sightings-recent" role="tabpanel" hidden></div>
 *   <div class="tab-panel" id="sightings-hotspots" role="tabpanel" hidden></div>
 *   <div class="tab-panel" id="sightings-lookup" role="tabpanel" hidden>
 *     <div class="autocomplete">
 *       <input type="text" id="species-search-input" class="autocomplete-input"
 *              placeholder="Search a species..." autocomplete="off">
 *       <div class="autocomplete-dropdown" id="species-search-dropdown" hidden></div>
 *     </div>
 *     <div id="species-search-results"></div>
 *   </div>
 *
 *   <h2>On this day</h2>
 *   <div id="sightings-otd"></div>
 *
 *   <!-- Homepage strip -->
 *   <div id="home-sightings-rows"></div>
 */

(function () {
'use strict';

const API      = (window.DB_CONFIG?.api_base || '').replace(/\/$/, '');
let region     = new URLSearchParams(location.search).get('region') || 'IN-AP';
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
  return `<div class="sighting-row">
    <span class="species">${escapeHtml(r.species)} <em class="scientific">${escapeHtml(r.scientific)}</em></span>
    <span class="locality">${escapeHtml(r.locality)}</span>
    <span class="when">${timeAgo(r.when)}</span>
    <span class="status-badge ${r.status === 'Confirmed' ? 'confirmed' : 'under-review'}">${escapeHtml(r.status)}</span>
  </div>`;
}

function renderSightingCard(r) {
  return `<div class="sighting-card"${r.rare ? ' style="border-left:4px solid var(--blue)"' : ''}>
    <div class="sighting-card-top">
      <span class="species">${escapeHtml(r.species)}${r.rare ? ' <span class="rare-star" title="Rare">★</span>' : ''}</span>
      <em class="scientific">${escapeHtml(r.scientific)}</em>
    </div>
    <div class="sighting-card-mid">
      <span class="count-badge">${escapeHtml(r.count)}</span>
      <span class="locality">${escapeHtml(r.locality)}</span>
      <span class="when">${timeAgo(r.when)}</span>
    </div>
    <div class="sighting-card-bottom">
      <span class="status-badge ${r.status === 'Confirmed' ? 'confirmed' : 'under-review'}">${escapeHtml(r.status)}</span>
    </div>
  </div>`;
}

function emptyState(msg) {
  return `<p class="db-empty">${escapeHtml(msg)}</p>`;
}

/* -------------------------------------------------------------------------
 * Core fetch — shared AbortController cancels the previous in-flight
 * request; an aborted call resolves to null so callers can no-op instead
 * of overwriting the UI with an empty state.
 * ---------------------------------------------------------------------- */

async function fetchTab(tab, extra = {}) {
  if (controller) controller.abort();
  controller = new AbortController();
  const params = new URLSearchParams({ region, tab, ...extra });
  try {
    const res  = await fetch(`${API}/api/sightings?${params}`, { signal: controller.signal });
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

function renderNotable(data) {
  const el = document.getElementById('sightings-notable');
  if (!el || data === null) return;
  if (!data.length) { el.innerHTML = emptyState('No notable sightings reported recently.'); return; }
  el.innerHTML = data.map(renderSightingCard).join('');
}

// Recent tab: the API returns the full 14-day window in one response
// (no server-side offset support), so pagination is client-side over the
// already-fetched array.
let recentAll  = [];
let recentShown = 0;
const RECENT_PAGE_SIZE = 20;

function renderRecent(data) {
  const el = document.getElementById('sightings-recent');
  if (!el) return;
  if (data === null) return;

  recentAll   = data;
  recentShown = Math.min(RECENT_PAGE_SIZE, recentAll.length);
  paintRecent();
}

function paintRecent() {
  const el = document.getElementById('sightings-recent');
  if (!el) return;

  if (!recentAll.length) {
    el.innerHTML = emptyState('No recent sightings reported in the last 14 days.');
    return;
  }

  const shown = recentAll.slice(0, recentShown);
  const hasMore = recentShown < recentAll.length;

  el.innerHTML = `
    <p class="records-count">Showing ${shown.length} of ${recentAll.length} records</p>
    <div class="sighting-cards">${shown.map(renderSightingCard).join('')}</div>
    ${hasMore ? '<button type="button" class="btn btn-ghost" id="recent-load-more">Load more</button>' : ''}
  `;

  const loadMoreBtn = document.getElementById('recent-load-more');
  if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', () => {
      recentShown = Math.min(recentShown + RECENT_PAGE_SIZE, recentAll.length);
      paintRecent();
    });
  }
}

// Hotspots: 5 rows, each expandable to show that hotspot's species list.
const hotspotSpeciesCache = new Map();

function renderHotspots(data) {
  const el = document.getElementById('sightings-hotspots');
  if (!el || data === null) return;
  if (!data.length) { el.innerHTML = emptyState('No hotspot data available for this region.'); return; }

  el.innerHTML = data.map((h, i) => `
    <div class="hotspot-row" data-loc-id="${escapeHtml(h.locId)}" tabindex="0" role="button" aria-expanded="false">
      <span class="hotspot-rank">${i + 1}</span>
      <span class="hotspot-info">
        <span class="hotspot-name">${escapeHtml(h.name)}</span>
        <span class="hotspot-stats"><strong class="hotspot-species-count">${escapeHtml(h.species)}</strong> species · ${escapeHtml(h.checklists)} checklists</span>
      </span>
      <span class="hotspot-arrow" aria-hidden="true">▾</span>
    </div>
    <div class="hotspot-species-panel" hidden></div>
  `).join('');

  el.querySelectorAll('.hotspot-row').forEach((row) => {
    row.addEventListener('click', () => toggleHotspot(row));
    row.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleHotspot(row); }
    });
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
  const species = await fetchTab('hotspot_species', { locId });

  if (species === null) return; // aborted — leave whatever is showing

  const html = species.length
    ? `<div class="hotspot-species-list">${species.map((code) => `<span class="species-pill">${escapeHtml(code)}</span>`).join('')}</div>`
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
  const [notable, recent] = await Promise.all([fetchTab('notable'), fetchTab('recent')]);
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
  if (!data.length) { el.innerHTML = emptyState("No historic records found for today's date in this region."); return; }

  el.innerHTML = data.map((r) => {
    const yearMatch = String(r.when).match(/\d{4}/);
    const year = yearMatch ? yearMatch[0] : '';
    return `<div class="sighting-row">
      <span class="species">${escapeHtml(r.species)}</span>
      <span class="count-badge">${escapeHtml(r.count)}</span>
      <span class="locality">${escapeHtml(r.locality)}</span>
      <span class="when">${escapeHtml(year)}</span>
    </div>`;
  }).join('');
}

/* -------------------------------------------------------------------------
 * Tab switching
 * ---------------------------------------------------------------------- */

function initTabSwitching() {
  document.querySelectorAll('.tab-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const tab = btn.dataset.tab;
      activeTab = tab;

      document.querySelectorAll('.tab-btn').forEach((b) => {
        b.classList.toggle('active', b === btn);
        b.setAttribute('aria-selected', b === btn);
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
 * Region toggle
 * ---------------------------------------------------------------------- */

function initRegionToggle() {
  document.querySelectorAll('.region-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      region = btn.dataset.region;
      document.querySelectorAll('.region-btn').forEach((b) => b.classList.toggle('active', b === btn));

      const url = new URL(location.href);
      url.searchParams.set('region', region);
      history.pushState({}, '', url);

      // Changing region invalidates any cached lookup index and hotspot data.
      lookupIndex = null;
      hotspotSpeciesCache.clear();

      loadTab(activeTab);
      loadOnThisDay();
    });
  });
}

/* -------------------------------------------------------------------------
 * On This Day
 * ---------------------------------------------------------------------- */

async function loadOnThisDay() {
  const now = new Date();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  showSkeleton('sightings-otd', 3);
  const data = await fetchTab('onthisday', { m, d });
  renderOnThisDay(data);
}

/* -------------------------------------------------------------------------
 * Homepage strip
 * ---------------------------------------------------------------------- */

async function initHomeStrip() {
  const strip = document.getElementById('home-sightings-rows');
  if (!strip) return;
  const data = await fetchTab('recent');
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
  initRegionToggle();

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
