/**
 * Deccan Birders — Sightings page + homepage strip.
 * Vanilla ES6, no dependencies. Talks to the deccan-birders-api Vercel
 * endpoint (DB_CONFIG.api_base) at /api/sightings.
 *
 * Expected markup (page-sightings.php): a .gallery-tabs bar with
 * data-tab="notable|recent|hotspots", the matching #sightings-* panels,
 * the #sightings-lookup card (with #species-search-input / -dropdown /
 * -results), and #sightings-otd. Scope is all of India; the WP proxy puts
 * Telangana and Andhra Pradesh records first (db_sightings_regional()),
 * and tags each record with local: true/false.
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

function showSkeleton(id, message) {
  // A bird crossing the panel, rather than grey bars: it says "waiting"
  // without pretending to be the content that hasn't arrived.
  const el = document.getElementById(id);
  if (el && window.DB && DB.birdLoader) el.innerHTML = DB.birdLoader(message || 'Fetching the latest checklists…');
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

// Homepage strip — hairline-divided cells, per the design.
function renderSightingRow(r) {
  return `<div class="home-sighting-cell">
    <div class="home-sighting-species">${escapeHtml(r.species)}</div>
    <div class="home-sighting-loc">${escapeHtml(r.locality)}</div>
    <div class="home-sighting-meta">
      <span class="chip chip-green">${escapeHtml(String(r.count))}</span>
      <span class="home-sighting-when">${timeAgo(r.when)}</span>
    </div>
  </div>`;
}

// Notable tab — bordered card with a yellow top rule.
/* Hyderabad — the distances in the species lookup are measured from here. */
const HYDERABAD = { lat: 17.3850, lng: 78.4867 };

/**
 * Great-circle distance in km. Good to a fraction of a percent at these
 * ranges, which is far better than the "~" on the label implies.
 */
function distanceKm(lat, lng) {
  if (typeof lat !== 'number' || typeof lng !== 'number') return null;
  const R = 6371;
  const toRad = (d) => (d * Math.PI) / 180;
  const dLat = toRad(lat - HYDERABAD.lat);
  const dLng = toRad(lng - HYDERABAD.lng);
  const a = Math.sin(dLat / 2) ** 2 +
            Math.cos(toRad(HYDERABAD.lat)) * Math.cos(toRad(lat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(a));
}

// Rounded the way someone judging a drive would read it, not to the metre.
function formatDistance(km) {
  if (km == null) return '';
  if (km < 1)  return 'under 1 km away';
  if (km < 10) return `~${km.toFixed(1)} km away`;
  return `~${Math.round(km)} km away`;
}

function renderSightingCard(r) {
  const dist = r.distanceKm != null ? formatDistance(r.distanceKm) : '';
  return `<div class="sighting-card">
    <div class="sighting-species">${escapeHtml(r.species)}</div>
    <div class="sighting-sci">${escapeHtml(r.scientific)}</div>
    <div class="sighting-lines">
      <div>${escapeHtml(r.locality)}</div>
      ${dist ? `<div class="sighting-distance">${escapeHtml(dist)}</div>` : ''}
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
      return { data: [] };
    }
    return json;
  } catch (e) {
    if (e.name !== 'AbortError') console.error('Sightings fetch error:', e);
    return null;
  }
}

// Most callers only want the records.
async function fetchRecords(tab, extra = {}, standalone = false) {
  const res = await fetchTab(tab, extra, standalone);
  return res === null ? null : (res.data || []);
}

/* -------------------------------------------------------------------------
 * Tab renderers
 * ---------------------------------------------------------------------- */

/* -------------------------------------------------------------------------
 * Pagination. The server slices the feed (per_page below) so a page view
 * carries 30 records instead of the whole 1,200-record region feed; paging
 * asks for the next slice, which the server already has cached.
 * ---------------------------------------------------------------------- */

const PAGE_SIZE = 30;

function pagerHtml(page, pages, total) {
  if (pages <= 1) return `<p class="records-count">${total} record${total === 1 ? '' : 's'}</p>`;
  return `
    <nav class="events-pager" aria-label="Sightings pages">
      <button type="button" class="events-pager-btn" data-step="-1"${page === 1 ? ' disabled' : ''}>← Previous</button>
      <span class="events-pager-status">Page ${page} of ${pages} · ${total} records</span>
      <button type="button" class="events-pager-btn" data-step="1"${page === pages ? ' disabled' : ''}>Next →</button>
    </nav>`;
}

/**
 * Render one server-supplied page, wiring its pager to fetch the next.
 * res is the endpoint envelope: { data, page, pages, total }.
 */
function renderPage(el, tab, res, renderItem, wrapClass, emptyMsg) {
  if (!el || res === null) return;
  const items = res.data || [];
  if (!items.length && (res.page || 1) === 1) { el.innerHTML = emptyState(emptyMsg); return; }

  const page = res.page || 1;
  const pages = res.pages || 1;
  el.innerHTML = `<div class="${wrapClass}">${items.map(renderItem).join('')}</div>${pagerHtml(page, pages, res.total || items.length)}`;

  el.querySelectorAll('.events-pager-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const next = Math.min(pages, Math.max(1, page + Number(btn.dataset.step)));
      if (next === page) return;
      el.querySelectorAll('.events-pager-btn').forEach((b) => { b.disabled = true; });
      const fresh = await fetchTab(tab, { page: next, per_page: PAGE_SIZE });
      renderPage(el, tab, fresh, renderItem, wrapClass, emptyMsg);
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
}

function renderNotable(res) {
  renderPage(document.getElementById('sightings-notable'), 'notable', res,
    renderSightingCard, 'sighting-cards', 'No notable sightings reported recently.');
}

function renderRecent(res) {
  renderPage(document.getElementById('sightings-recent'), 'recent', res,
    renderRecentRow, 'recent-list', 'No recent sightings reported in the last 14 days.');
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

  panel.innerHTML = (window.DB && DB.birdLoader) ? DB.birdLoader('Listing the species seen here…') : '';
  const species = await fetchRecords('hotspot_species', { locId }, true);

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
  // The lookup filters across everything, so this one asks for the
  // unpaged feeds (no per_page) — the only place that still does.
  const [notable, recent] = await Promise.all([
    fetchRecords('notable', {}, true),
    fetchRecords('recent', {}, true),
  ]);
  const all = [...(notable || []), ...(recent || [])];

  lookupIndex = new Map();
  // "Notable" is a filtered view of "recent", not a separate feed, so
  // every notable record arrives twice. One observation is one species
  // at one place at one moment.
  const seen = new Set();
  all.forEach((r) => {
    const key = `${r.species}|${r.locId}|${r.when}|${r.count}`;
    if (seen.has(key)) return;
    seen.add(key);

    if (!lookupIndex.has(r.species)) {
      lookupIndex.set(r.species, { scientific: r.scientific, code: r.speciesCode || '', records: [] });
    }
    const entry = lookupIndex.get(r.species);
    if (!entry.code && r.speciesCode) entry.code = r.speciesCode;
    entry.records.push({ ...r, distanceKm: distanceKm(r.lat, r.lng) });
  });

  // Every Indian species, so that a bird with no recent records is still
  // findable and can be answered honestly rather than with "no matching
  // species". Recent records win the entry; the rest come in name-only.
  const taxonomy = await fetchRecords('taxonomy', {}, true);
  (taxonomy || []).forEach((t) => {
    if (lookupIndex.has(t.species)) {
      const entry = lookupIndex.get(t.species);
      if (!entry.code) entry.code = t.speciesCode || '';
      return;
    }
    lookupIndex.set(t.species, {
      scientific: t.scientific || '',
      code: t.speciesCode || '',
      records: [],
    });
  });

  // Nearest first — the question the card asks is "where can I see it",
  // so a lake an hour away beats a better count three states over.
  // Records with no coordinates sink to the bottom rather than vanish.
  lookupIndex.forEach((entry) => {
    entry.records.sort((a, b) => {
      if (a.distanceKm == null) return 1;
      if (b.distanceKm == null) return -1;
      return a.distanceKm - b.distanceKm;
    });
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

  /**
   * eBird's own page for a species: every record ever submitted, on a
   * map. We only hold the last 30 days, so anything older is a question
   * for eBird rather than one we can answer here.
   */
  const ebirdLink = (entry, species, label) => {
    const href = entry && entry.code
      ? `https://ebird.org/species/${encodeURIComponent(entry.code)}`
      : `https://ebird.org/search?q=${encodeURIComponent(species)}`;
    return `<a class="btn btn-ghost lookup-all" href="${href}" target="_blank" rel="noopener">${escapeHtml(label)}</a>`;
  };

  const selectSpecies = (species) => {
    const entry = lookupIndex.get(species);
    closeDropdown();
    input.value = species;

    if (!entry || !entry.records.length) {
      results.innerHTML = `
        ${emptyState(`No records of ${species} in the last 30 days.`)}
        ${ebirdLink(entry, species, 'View all records on eBird →')}
      `;
      return;
    }

    results.innerHTML = `
      <h3 class="lookup-heading">Where to see ${escapeHtml(species)}, nearest first</h3>
      <div class="sighting-cards">${entry.records.map(renderSightingCard).join('')}</div>
      <div class="lookup-foot">
        <span class="lookup-count">${entry.records.length} record${entry.records.length === 1 ? '' : 's'} in the last 30 days</span>
        ${ebirdLink(entry, species, 'View all records on eBird →')}
      </div>
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
        // Birds actually being seen come first, then a name that starts
        // with what was typed, then the rest alphabetically — so "pai"
        // offers Painted Stork before Greater Painted-Snipe.
        .sort((a, b) => {
          const ca = lookupIndex.get(a).records.length;
          const cb = lookupIndex.get(b).records.length;
          if ((ca > 0) !== (cb > 0)) return ca > 0 ? -1 : 1;
          const sa = a.toLowerCase().startsWith(q);
          const sb = b.toLowerCase().startsWith(q);
          if (sa !== sb) return sa ? -1 : 1;
          if (ca !== cb) return cb - ca;
          return a.localeCompare(b);
        })
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

// Compact rows for the dark "On this day" card on the home page.
function renderHomeOnThisDay(data) {
  const el = document.getElementById('home-otd');
  if (!el || data === null) return;
  if (!data.length) { el.innerHTML = '<p class="home-otd-loc">No historic records for today.</p>'; return; }
  el.innerHTML = data.slice(0, 4).map((r) => {
    const m = String(r.when).match(/\d{4}/);
    return `<div class="home-otd-row">
      <span class="home-otd-year">${escapeHtml(m ? m[0] : '')}</span>
      <span>
        <span class="home-otd-species">${escapeHtml(r.species)}</span>
        <span class="home-otd-loc">${escapeHtml(r.locality)}</span>
      </span>
    </div>`;
  }).join('');
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
      showSkeleton('sightings-notable', 'Looking for notable birds…');
      renderNotable(await fetchTab('notable', { page: 1, per_page: PAGE_SIZE }));
      break;
    case 'recent':
      showSkeleton('sightings-recent', 'Fetching the latest checklists…');
      renderRecent(await fetchTab('recent', { page: 1, per_page: PAGE_SIZE }));
      break;
    case 'hotspots':
      showSkeleton('sightings-hotspots', 'Finding the best places nearby…');
      renderHotspots(await fetchRecords('hotspots'));
      break;
  }
}

/* -------------------------------------------------------------------------
 * On This Day
 * ---------------------------------------------------------------------- */

/**
 * "On this day" asks eBird for ten years of records, so a cold cache
 * costs ten upstream calls per region. Fetching the three regions in
 * parallel (merge=0) instead of having the server chain them means the
 * first records appear in roughly a third of the time; whatever has
 * arrived is drawn each time a region lands.
 */
const OTD_REGIONS = [
  { region: 'IN-TS', local: true },
  { region: 'IN-AP', local: true },
  { region: 'IN', local: false },
];
const OTD_PER_GROUP = 10;

function mergeOnThisDay(groups) {
  const seen = new Set();
  const out = [];
  [true, false].forEach((wantLocal) => {
    const bucket = groups
      .filter((g) => g.local === wantLocal)
      .flatMap((g) => g.records)
      .sort((a, b) => (Number(b.count) || 0) - (Number(a.count) || 0));
    let kept = 0;
    bucket.forEach((r) => {
      const key = `${r.species}|${r.locId}|${r.when}`;
      if (seen.has(key) || kept >= OTD_PER_GROUP) return;
      seen.add(key);
      out.push({ ...r, local: wantLocal });
      kept++;
    });
  });
  return out;
}

async function loadOnThisDay() {
  const now = new Date();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  showSkeleton('sightings-otd', 'Looking back through the years…');

  const groups = [];
  let painted = false;
  await Promise.all(OTD_REGIONS.map(async ({ region: r, local }) => {
    const records = await fetchRecords('onthisday', { m, d, region: r, merge: '0' }, true);
    if (!records || !records.length) return;
    groups.push({ local, records });
    // Draw as soon as the home states are in, then again as the rest land.
    if (local || !painted) {
      painted = true;
      const merged = mergeOnThisDay(groups);
      renderOnThisDay(merged);
      renderHomeOnThisDay(merged);
    }
  }));

  const merged = mergeOnThisDay(groups);
  renderOnThisDay(merged);
  renderHomeOnThisDay(merged);
}

/* -------------------------------------------------------------------------
 * Homepage strip
 * ---------------------------------------------------------------------- */

async function initHomeStrip() {
  const strip = document.getElementById('home-sightings-rows');
  if (!strip) return;
  // The strip scrolls sideways, so it carries a longer run than the four
  // cells on show — still a fraction of the full feed.
  const data = await fetchRecords('recent', { page: 1, per_page: 15 }, true);
  if (!data) { strip.innerHTML = '<p class="strip-error">Could not load sightings.</p>'; return; }
  strip.innerHTML = data.map(renderSightingRow).join('');
  initStripArrows();
  // Fetched again a few hours on, in step with the server's own cache.
  setTimeout(initHomeStrip, 6 * 60 * 60 * 1000);
}

/**
 * The strip's arrows. It scrolls sideways but shows no scrollbar, so
 * these are how it's moved with a mouse; a trackpad or a swipe still
 * works directly. They hide themselves when everything already fits.
 */
function initStripArrows() {
  const scroller = document.querySelector('.home-sightings-scroller');
  const arrows = document.getElementById('home-sightings-arrows');
  if (!scroller || !arrows) return;

  const overflows = scroller.scrollWidth > scroller.clientWidth + 8;
  arrows.hidden = !overflows;
  if (!overflows) return;

  const step = () => {
    const cell = scroller.querySelector('.home-sighting-cell');
    // Move by whole cells, so a record never sits half off the edge.
    return cell ? Math.round(cell.getBoundingClientRect().width + 14) * 2 : 320;
  };

  const sync = () => {
    const max = scroller.scrollWidth - scroller.clientWidth - 2;
    arrows.querySelector('[data-step="-1"]').disabled = scroller.scrollLeft <= 2;
    arrows.querySelector('[data-step="1"]').disabled = scroller.scrollLeft >= max;
  };

  arrows.querySelectorAll('.trip-arrow').forEach((btn) => {
    btn.addEventListener('click', () => {
      // The easing is CSS's scroll-behavior, which also respects a
      // reader's reduced-motion setting. Some environments ignore smooth
      // scrolling altogether, and a dead arrow is worse than an abrupt
      // one, so jump if nothing has moved shortly after.
      const from = scroller.scrollLeft;
      const by = step() * Number(btn.dataset.step);
      scroller.scrollLeft = from + by;
      setTimeout(() => {
        if (scroller.scrollLeft === from) {
          const easing = scroller.style.scrollBehavior;
          scroller.style.scrollBehavior = 'auto';
          scroller.scrollLeft = from + by;
          scroller.style.scrollBehavior = easing;
        }
        // Don't wait for a scroll event to settle the arrows: a smooth
        // scroll reports its final position late, and some environments
        // don't fire the event at all.
        sync();
      }, 220);
    });
  });
  scroller.addEventListener('scroll', sync, { passive: true });
  window.addEventListener('resize', () => { arrows.hidden = scroller.scrollWidth <= scroller.clientWidth + 8; sync(); });
  sync();
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
  // The lookup card sits below the tabs rather than inside them, so it is
  // wired up directly. Its index is only fetched once someone types.
  if (document.getElementById('sightings-lookup')) {
    renderSpeciesLookup();
  }
  if (document.getElementById('home-sightings-rows')) {
    initHomeStrip();
  }
  if (document.getElementById('home-otd')) {
    loadOnThisDay();
  }
});

})();
