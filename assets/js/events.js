/**
 * Deccan Birders — Events (homepage strip + full Events page).
 * Vanilla ES6, no dependencies.
 *
 * Upcoming events come from the deccan-birders-api Vercel endpoint
 * (DB_CONFIG.api_base, sourced from Google Calendar). Past events come
 * from the site's own WP REST API (db_event CPT + ACF fields), since
 * past-trip write-ups (species count, leader, highlights) live in
 * WordPress, not the calendar.
 */

const API = (window.DB_CONFIG?.api_base || '').replace(/\/$/, '');

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

function typeColor(type) {
  const map = {
    'Field Trip': '#EAF2FA', 'Bird Walk': '#E6F4EC', 'Webinar': '#FFF8E6',
    'Nature Camp': '#F0E6F4', 'Bird Race': '#FAE6E6', 'Census': '#E6EEF4'
  };
  return map[type] || '#F1EFE8';
}

function renderEventCard(e) {
  const { day, month, dayName } = formatDate(e.date);
  const note = e.note ? escapeHtml(e.note.length > 120 ? e.note.slice(0, 120) + '…' : e.note) : '';

  return `
  <div class="event-card">
    <div class="date-block">
      <span class="day">${day}</span>
      <span class="month">${escapeHtml(month)}</span>
      <span class="dayname">${escapeHtml(dayName)}</span>
    </div>
    <div class="event-info">
      ${e.event_type ? `<span class="event-type-badge" style="background:${typeColor(e.event_type)}">${escapeHtml(e.event_type)}</span>` : ''}
      <div class="event-title">${escapeHtml(e.title)}</div>
      ${e.place ? `<div class="event-meta">📍 ${escapeHtml(e.place)}</div>` : ''}
      ${note ? `<div class="event-note">${note}</div>` : ''}
      <div class="event-badges">
        ${e.loanerBins ? '<span class="loaner-badge">Loaner bins available</span>' : ''}
        ${e.fee ? `<span class="fee-badge">₹${escapeHtml(e.fee)}</span>` : ''}
      </div>
    </div>
  </div>`;
}

/* -------------------------------------------------------------------------
 * Homepage strip
 * ---------------------------------------------------------------------- */

async function initHomeEvents() {
  const grid = document.getElementById('home-events-grid');
  if (!grid) return;
  try {
    const res  = await fetch(`${API}/api/events`);
    const json = await res.json();
    if (json.error) throw new Error(json.message || 'Request failed');
    const data = json.data || [];
    if (!data.length) { grid.innerHTML = '<p>No upcoming trips. Check back soon.</p>'; return; }
    grid.innerHTML = data.slice(0, 3).map(renderEventCard).join('');
  } catch (e) {
    grid.innerHTML = '<p>Could not load events.</p>';
  }
}

/* -------------------------------------------------------------------------
 * Full Events page — upcoming (Vercel API) + past (WP REST / ACF)
 * ---------------------------------------------------------------------- */

async function initEventsPage() {
  const upcoming = document.getElementById('events-upcoming');
  if (!upcoming) return;

  try {
    const res  = await fetch(`${API}/api/events`);
    const json = await res.json();
    if (json.error) throw new Error(json.message || 'Request failed');
    const data = json.data || [];
    if (!data.length) {
      upcoming.innerHTML = '<div class="events-empty"><p>No upcoming trips scheduled. We plan trips every month — check back soon.</p></div>';
    } else {
      upcoming.innerHTML = `<div class="events-list">${data.map(renderEventCard).join('')}</div>`;
    }
  } catch (e) {
    upcoming.innerHTML = '<p>Could not load events.</p>';
  }

  // Past events — merged from two sources:
  //  1. WordPress db_event posts with a full write-up (species count, leader,
  //     turnout, highlights, PITTA report link) — the richer card.
  //  2. The Google Calendar feed itself (via ?scope=past), for trips that
  //     happened but haven't had a write-up added in WordPress yet.
  const past = document.getElementById('events-past');
  if (!past) return;

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
    const res  = await fetch(`${API}/api/events?scope=past`);
    const json = await res.json();
    if (json.error) throw new Error(json.message || 'Request failed');
    calendarPastEvents = (json.data || []).map((ev) => ({ source: 'calendar', sortDate: ev.date, event: ev }));
  } catch (e) {
    console.error('Could not load calendar past events:', e);
    calendarFailed = true;
  }

  const merged = [...wpPastEvents, ...calendarPastEvents]
    .sort((a, b) => new Date(b.sortDate) - new Date(a.sortDate));

  if (!merged.length) {
    past.innerHTML = (wpFailed && calendarFailed)
      ? '<p>Could not load past events.</p>'
      : '<p>No past trip records yet.</p>';
    return;
  }

  past.innerHTML = merged.map((item) => {
    if (item.source === 'wp') {
      const e = item.post;
      return `
      <div class="event-card past-event-card">
        <div class="past-event-species">${escapeHtml(e.acf?.species_count ?? '—')}<span>species</span></div>
        <div class="event-info">
          <div class="event-title">${e.title.rendered}</div>
          <div class="event-meta">${escapeHtml(e.acf?.event_date || '')} · ${escapeHtml(e.acf?.location || '')}</div>
          <div class="event-meta">Led by ${escapeHtml(e.acf?.leader || '—')} · ${escapeHtml(e.acf?.turnout ?? '?')} participants</div>
          ${e.acf?.highlights ? `<div class="event-highlights">${escapeHtml(e.acf.highlights)}</div>` : ''}
          ${e.acf?.report_link ? `<a href="${escapeHtml(e.acf.report_link)}" class="pitta-link" target="_blank" rel="noopener">PITTA report →</a>` : ''}
        </div>
      </div>`;
    }
    // Calendar-only record: no write-up yet, so render with the plain event card.
    return renderEventCard(item.event);
  }).join('');
}

/* -------------------------------------------------------------------------
 * Tab switching on events page
 * ---------------------------------------------------------------------- */

document.querySelectorAll('.tab-btn[data-tab]').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach((b) => b.classList.toggle('active', b === btn));
    document.querySelectorAll('.events-tab-panel').forEach((p) => {
      p.hidden = p.id !== `events-${btn.dataset.tab}`;
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  initHomeEvents();
  initEventsPage();
});
