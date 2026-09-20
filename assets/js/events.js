/**
 * Deccan Birders — Events (homepage strip + full Events page).
 * Vanilla ES6, no dependencies.
 *
 * Upcoming events come from the deccan-birders-api Vercel endpoint
 * (DB_CONFIG.api_base, sourced from Google Calendar). Past events come
 * from the site's own WP REST API (db_event CPT + ACF fields), since
 * past-trip write-ups (species count, turnout, pick of the day) live in
 * WordPress, not the calendar.
 *
 * Both tabs on the Events page paginate at 10 per page and each card opens
 * to an expanded view in place.
 */

(function () {
'use strict';

// Same-origin WP REST proxy (see functions.php) — avoids the Vercel API's
// CORS restriction to the production domain, and caches responses server-side.
const API = '/wp-json/db/v1';

/* -------------------------------------------------------------------------
 * Utilities
 * ---------------------------------------------------------------------- */

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return {
    day:     d.getDate(),
    month:   d.toLocaleString('en-IN', { month: 'short' }),
    dayName: d.toLocaleString('en-IN', { weekday: 'short' })
  };
}

// Calendar event titles come in as "Deccan Birders | 28-SEP-2026 | 0600 | Keesara" —
// pull just the location out and present it as a readable field trip name.
function cleanTitle(t) {
  const m = String(t || '').match(/Deccan Birders\s*\|\s*[\d\-A-Z]+\s*\|\s*\d+\s*\|\s*(.+)/i);
  return m ? 'Field Trip — ' + m[1].trim() : t;
}

// The calendar's note field is raw HTML (mail-merge style). keepBreaks
// preserves paragraph breaks as \n for the expanded view; otherwise
// everything collapses to a single line.
function stripHtml(h, keepBreaks) {
  let s = h || '';
  if (keepBreaks) {
    s = s.replace(/<\/(p|div|li)>/gi, '\n').replace(/<br\s*\/?>/gi, '\n');
  }
  s = s.replace(/<[^>]*>/g, ' ').replace(/[ \t]+/g, ' ');
  s = keepBreaks ? s.replace(/ *\n */g, '\n').replace(/\n{3,}/g, '\n\n').trim() : s.replace(/\s+/g, ' ').trim();
  return s;
}

/* -------------------------------------------------------------------------
 * Homepage strip
 * ---------------------------------------------------------------------- */

async function initHomeEvents() {
  const grid = document.getElementById('home-events-grid');
  if (!grid) return;
  try {
    const res  = await fetch(`${API}/events`);
    const json = await res.json();
    if (json.error) throw new Error(json.message || 'Request failed');
    const data = json.data || [];
    if (!data.length) { grid.innerHTML = '<p>No upcoming trips. Check back soon.</p>'; return; }
    grid.innerHTML = data.slice(0, 3).map((e, i) => renderCard('upcoming', upcomingFields(e), i, { linkToEvents: true })).join('');
  } catch (e) {
    grid.innerHTML = '<p>Could not load events.</p>';
  }
}

/* -------------------------------------------------------------------------
 * Past events — merged from two sources:
 *  1. WordPress db_event posts with a full write-up (species count, leader,
 *     turnout, pick of the day) — the richer card.
 *  2. The Google Calendar feed itself (via ?scope=past), for trips that
 *     happened but haven't had a write-up added in WordPress yet.
 * Shared by the full Events page and the homepage "Where we've been" strip.
 * ---------------------------------------------------------------------- */

async function fetchMergedPastEvents() {
  let wpPastEvents = [];
  let calendarPastEvents = [];
  let wpFailed = false;
  let calendarFailed = false;

  try {
    const res  = await fetch('/wp-json/wp/v2/db_event?per_page=20&_embed=false');
    const data = await res.json();

    // WP core's REST controller doesn't support ordering by arbitrary ACF
    // meta out of the box, so fetch by post date and sort/filter client-side.
    wpPastEvents = data
      .filter((e) => e.acf?.is_past === true)
      .map((e) => ({ source: 'wp', sortDate: e.acf?.event_date || 0, post: e }));
  } catch (e) {
    console.error('Could not load WP past events:', e);
    wpFailed = true;
  }

  try {
    const res  = await fetch(`${API}/events?scope=past`);
    const json = await res.json();
    if (json.error) throw new Error(json.message || 'Request failed');
    calendarPastEvents = (json.data || []).map((ev) => ({ source: 'calendar', sortDate: ev.date, event: ev }));
  } catch (e) {
    console.error('Could not load calendar past events:', e);
    calendarFailed = true;
  }

  const merged = [...wpPastEvents, ...calendarPastEvents]
    .sort((a, b) => new Date(b.sortDate) - new Date(a.sortDate));

  return { merged, bothFailed: wpFailed && calendarFailed };
}

function pastEventFields(item) {
  // Normalises a WP db_event post and a calendar-only past event into the
  // same shape so one card renderer handles both.
  if (item.source === 'wp') {
    const a = item.post.acf || {};
    return {
      title:    stripHtml(item.post.title.rendered),
      date:     a.event_date || '',
      place:    a.location || '',
      leader:   a.leader || '',
      species:  a.species_count,
      turnout:  a.turnout,
      pick:     a.pick_of_the_day || '',
      notes:    a.highlights || ''
    };
  }
  const e = item.event;
  return {
    title:   cleanTitle(e.title),
    date:    e.date,
    place:   e.place || '',
    leader:  '',
    species: null,
    turnout: null,
    pick:    '',
    notes:   stripHtml(e.note)
  };
}

function detailRow(label, value) {
  if (value === null || value === undefined || value === '') return '';
  return `<div class="event-detail"><span class="event-detail-label">${escapeHtml(label)}</span><span>${escapeHtml(String(value))}</span></div>`;
}

/**
 * One card, collapsed by default. `kind` is 'upcoming' or 'past'.
 * The summary line stays minimal; everything else lives in the panel that
 * opens when the card is activated.
 */
/**
 * Whom to ring about a trip, read out of the calendar invitation by
 * db_event_coordinators() in functions.php. It sits right under the trip
 * name — on the Events page and on the home page, which uses these same
 * cards — so nobody has to open the details to find a number. Each one
 * is a tel: link, so it dials straight from a phone.
 */
function coordinatorLine(coordinators) {
  if (!coordinators || !coordinators.length) return '';
  const people = coordinators.map((c) =>
    `<a class="event-coordinator" href="tel:${escapeHtml(c.tel)}">${escapeHtml(c.name)} · ${escapeHtml(c.phone)}</a>`
  ).join('<span class="event-coordinator-sep" aria-hidden="true">,</span> ');
  return `<div class="event-coordinators">
      <span class="event-coordinators-label">${coordinators.length > 1 ? 'Coordinators' : 'Coordinator'}</span>
      ${people}
    </div>`;
}

function renderCard(kind, data, index, opts = {}) {
  const { day, month, dayName } = formatDate(data.date);
  const id = `${kind}-${index}`;
  // On the home page the details live on the Events page: the link opens
  // that trip there, already expanded (see openRequestedEvent).
  const detailsHref = opts.linkToEvents && data.id
    ? `/events/#event-${encodeURIComponent(data.id)}`
    : '';

  const chips = kind === 'past'
    ? `<div class="event-chips">
         ${data.species != null && data.species !== '' ? `<span class="event-chip"><strong>${escapeHtml(String(data.species))}</strong> species</span>` : ''}
         ${data.turnout != null && data.turnout !== '' ? `<span class="event-chip"><strong>${escapeHtml(String(data.turnout))}</strong> out</span>` : ''}
       </div>`
    : '';

  const details = kind === 'past'
    ? detailRow('Led by', data.leader) + detailRow('Pick of the day', data.pick) +
      (data.notes ? `<div class="event-detail-notes">${escapeHtml(data.notes)}</div>` : '')
    : detailRow('Meeting point', data.meetingPoint) + detailRow('Starts', data.time) +
      detailRow('Led by', data.leader) + detailRow('Fee', data.fee ? `₹${data.fee}` : '') +
      (data.loanerBins ? detailRow('Binoculars', 'Loaner pairs available') : '') +
      (data.notes ? `<div class="event-detail-notes">${escapeHtml(data.notes)}</div>` : '');

  const hasDetails = details.trim() !== '';

  return `
  <article class="event-card${hasDetails ? ' is-expandable' : ''}" data-card="${id}"${data.id ? ` data-event-id="${escapeHtml(data.id)}" id="event-${escapeHtml(data.id)}"` : ''}>
    <div class="event-date-block">
      <span class="event-day">${day}</span>
      <span class="event-month">${escapeHtml(month)}</span>
    </div>
    <div class="event-body">
      <div class="event-title">${escapeHtml(data.title)}</div>
      ${kind === 'upcoming' ? coordinatorLine(data.coordinators) : ''}
      ${data.place ? `<div class="event-meta">${escapeHtml(data.place)}</div>` : ''}
      ${kind === 'upcoming' && data.time ? `<div class="event-meta">${escapeHtml(dayName)} · ${escapeHtml(data.time)}</div>` : ''}
      ${chips}
    </div>
    ${hasDetails && detailsHref ? `
      <div class="event-toggle-wrap">
        <a class="event-toggle" href="${escapeHtml(detailsHref)}">
          <span class="event-toggle-more">More details</span>
        </a>
      </div>` : ''}
    ${hasDetails && !detailsHref ? `
      <div class="event-toggle-wrap">
        <button type="button" class="event-toggle" aria-expanded="false" aria-controls="panel-${id}">
          <span class="event-toggle-more">More details</span>
          <span class="event-toggle-less">Hide details</span>
        </button>
      </div>
      <div class="event-details" id="panel-${id}" hidden>${details}</div>` : ''}
  </article>`;
}

function upcomingFields(e) {
  return {
    id:           e.id || '',
    title:        cleanTitle(e.title),
    date:         e.date,
    place:        e.place || '',
    time:         e.date ? new Date(e.date).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }) : '',
    meetingPoint: '',
    leader:       '',
    fee:          e.fee || '',
    loanerBins:   !!e.loanerBins,
    coordinators: e.coordinators || [],
    notes:        stripHtml(e.note)
  };
}

/* -------------------------------------------------------------------------
 * Pagination — 10 per page, rendered client-side over the already-fetched
 * list so paging never re-hits the API.
 * ---------------------------------------------------------------------- */

const PAGE_SIZE = 10;

function paginate(container, kind, items, toFields, focusId = '') {
  const pages = Math.max(1, Math.ceil(items.length / PAGE_SIZE));
  // Arriving from the home page's "More details": start on whichever page
  // holds that trip rather than always the first.
  const focusIndex = focusId ? items.findIndex((it) => String(it.id || '') === focusId) : -1;
  let page = focusIndex > -1 ? Math.floor(focusIndex / PAGE_SIZE) + 1 : 1;

  function paint() {
    const startIdx = (page - 1) * PAGE_SIZE;
    const slice = items.slice(startIdx, startIdx + PAGE_SIZE);

    const cards = slice.map((it, i) => renderCard(kind, toFields(it), startIdx + i)).join('');

    const nav = pages > 1 ? `
      <nav class="events-pager" aria-label="${kind === 'past' ? 'Past events' : 'Upcoming events'} pages">
        <button type="button" class="events-pager-btn" data-step="-1"${page === 1 ? ' disabled' : ''}>← Newer</button>
        <span class="events-pager-status">Page ${page} of ${pages}</span>
        <button type="button" class="events-pager-btn" data-step="1"${page === pages ? ' disabled' : ''}>Older →</button>
      </nav>` : '';

    container.innerHTML = `<div class="events-list">${cards}</div>${nav}`;

    container.querySelectorAll('.events-pager-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        page = Math.min(pages, Math.max(1, page + Number(btn.dataset.step)));
        paint();
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    container.querySelectorAll('.event-toggle').forEach((btn) => {
      btn.addEventListener('click', () => {
        const panel = document.getElementById(btn.getAttribute('aria-controls'));
        const open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!open));
        btn.closest('.event-card').classList.toggle('is-open', !open);
        if (panel) panel.hidden = open;
      });
    });
  }

  paint();
  if (focusIndex > -1) expandEvent(container, focusId);
}

/** Open one card and scroll to it; it collapses again like any other. */
function expandEvent(container, eventId) {
  const card = container.querySelector(`[data-event-id="${CSS.escape(eventId)}"]`);
  if (!card) return;
  const btn = card.querySelector('.event-toggle');
  const panel = card.querySelector('.event-details');
  if (btn && panel && btn.getAttribute('aria-expanded') !== 'true') {
    btn.setAttribute('aria-expanded', 'true');
    card.classList.add('is-open');
    panel.hidden = false;
  }
  card.classList.add('is-linked');
  requestAnimationFrame(() => card.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}

/** The trip asked for in the URL, as /events/#event-<calendar id>. */
function requestedEventId() {
  const hash = decodeURIComponent(window.location.hash || '');
  return hash.startsWith('#event-') ? hash.slice('#event-'.length) : '';
}

/* -------------------------------------------------------------------------
 * Homepage "Where we've been" strip (past events preview)
 * ---------------------------------------------------------------------- */

async function initHomePastEvents() {
  const grid = document.getElementById('home-past-events-grid');
  if (!grid) return;

  const { merged, bothFailed } = await fetchMergedPastEvents();

  if (!merged.length) {
    grid.innerHTML = bothFailed ? '<p>Could not load past events.</p>' : '<p>No past trip records yet.</p>';
    return;
  }

  grid.innerHTML = merged.slice(0, 3).map((item, i) => renderCard('past', pastEventFields(item), i)).join('');
}

/* -------------------------------------------------------------------------
 * Full Events page — upcoming (Vercel API) + past (WP REST / ACF)
 * ---------------------------------------------------------------------- */

async function initEventsPage() {
  const upcoming = document.getElementById('events-upcoming');
  if (!upcoming) return;

  try {
    const res  = await fetch(`${API}/events`);
    const json = await res.json();
    if (json.error) throw new Error(json.message || 'Request failed');
    const data = json.data || [];
    if (!data.length) {
      upcoming.innerHTML = '<div class="events-empty"><p>No upcoming trips scheduled. We plan trips every month — check back soon.</p></div>';
    } else {
      paginate(upcoming, 'upcoming', data, upcomingFields, requestedEventId());
    }
  } catch (e) {
    upcoming.innerHTML = '<p>Could not load events.</p>';
  }

  const past = document.getElementById('events-past');
  if (!past) return;

  const { merged, bothFailed } = await fetchMergedPastEvents();

  if (!merged.length) {
    past.innerHTML = bothFailed ? '<p>Could not load past events.</p>' : '<p>No past trip records yet.</p>';
    return;
  }

  paginate(past, 'past', merged, pastEventFields);
}

/* -------------------------------------------------------------------------
 * Tab switching on events page
 * ---------------------------------------------------------------------- */

function initEventsTabSwitching() {
  document.querySelectorAll('.gallery-tab[data-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.gallery-tab').forEach((b) => {
        const on = b === btn;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      document.querySelectorAll('.events-tab-panel').forEach((p) => {
        p.hidden = p.id !== `events-${btn.dataset.tab}`;
      });
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initEventsTabSwitching();
  initHomeEvents();
  initHomePastEvents();
  initEventsPage();
});

})();
