/* Deccan Birders — Birding Tools → Migration Season.
 *
 * Draws the page from the figures page-migration-season.php prints as
 * JSON (#bt-data, from inc/birding-tools/engine.php, recalculated every 3
 * days): countdowns to each habitat group's peak, the winter visitors to
 * expect in a chosen month, the hotspots on a map, and an arrival
 * calendar. The drawing is the design's own; the map outline is loaded
 * from the theme (DB_BT.geo).
 */
(function () {
  'use strict';
  const el = document.getElementById('bt-data');
  if (!el) return;
  const D = JSON.parse(el.textContent);
  const $ = (id) => document.getElementById(id);
  const esc = (v) => String(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const M = ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr'];
  const MF = ['September', 'October', 'November', 'December', 'January', 'February', 'March', 'April'];
  const HAB = { wet: ['Wetland', '#2B72B8', '#EAF2FA', '#1A5A9A'], grass: ['Grassland & farmland', '#F2B705', '#FFF4D6', '#7A5A00'], scrub: ['Scrub', '#6B857A', '#E7EDE9', '#41514A'], wood: ['Woodland', '#17924C', '#E6F4EC', '#0F7A3E'] };
  // [code, name, scientific, habitat, first month, last month, peak months, peak per-1000, trend %/yr or null, per-1000 by month] — months Sep=0 … Apr=7.
  const SP = D.species;
  const SPOTS = D.spots;   // [name, area, habitat, best months, what to see, lat, lng]
  const WAVES = D.waves;   // [label, habitat, start, peak, end]
  let GEO = null;

  const now = new Date(), cm = (now.getMonth() + 4) % 12;
  const st = { m: cm <= 7 ? cm : 0, h: 'all', expAll: false, tlAll: false, tlMonth: true };
  const freq = (s, i) => (i < s[4] || i > s[5]) ? 0 : s[9][i];
  const mix = (a, b, t) => { const h = (s) => [1, 3, 5].map((i) => parseInt(s.slice(i, i + 2), 16)), A = h(a), B = h(b); return 'rgb(' + A.map((v, i) => Math.round(v + (B[i] - v) * t)).join(',') + ')'; };
  const fmt = (v) => { const r = Math.round(v); return r === 0 ? '0%' : (r < 0 ? '−' : '+') + Math.abs(r) + '%'; };
  const tc = (v) => v <= -3 ? '#B3261E' : v >= 3 ? '#0F7A3E' : '#5C6B63';
  const status = (s, i) => (i === s[4] && s[4] > 0) ? 'Arriving' : s[6].includes(i) ? 'Peak' : (i === s[5] && s[5] < 7) ? 'Leaving' : 'Present';
  const pct = (x) => (x / 8 * 100).toFixed(2) + '%';
  const sel = () => SP.filter((s) => st.h === 'all' || s[3] === st.h);
  const spots = () => SPOTS.map((s, i) => ({ s, n: i + 1 })).filter((o) => st.h === 'all' || o.s[2] === st.h);

  function renderPills() {
    $('months').innerHTML = M.map((m, i) => `<button class="bt-pill" type="button" data-m="${i}" aria-pressed="${i === st.m}">${m}</button>`).join('');
    $('habs').innerHTML = [['all', 'All habitats'], ...Object.entries(HAB).map(([k, h]) => [k, h[0]])].map(([k, n]) => `<button class="bt-pill" type="button" data-h="${k}" aria-pressed="${st.h === k}">${k === 'all' ? '' : `<span class="bt-dot" style="background:${HAB[k][1]}"></span>`}${esc(n)}</button>`).join('');
  }
  function renderWaves() {
    const day = 864e5, t0 = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const w = WAVES.filter((w) => st.h === 'all' || w[1] === st.h).map((w) => {
      const [, , A, P, E] = w, Pd = new Date(P + 'T00:00:00'), Ed = new Date(E + 'T00:00:00');
      const dp = Math.round((Pd - t0) / day), over = t0 > Ed;
      const big = over ? '—' : dp > 0 ? dp : 'Now';
      const unit = over ? 'Season over' : dp > 0 ? (dp === 1 ? 'day to peak' : 'days to peak') : 'At its peak';
      return `<div class="bt-wave"><b>${big}</b><span><strong>${esc(w[0])}</strong>${unit} · Peak around ${Pd.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</span></div>`;
    });
    $('waves').innerHTML = w.join('') || '<div class="bt-wave"><b>—</b><span><strong>No countdowns for this habitat</strong></span></div>';
  }
  function renderExpected() {
    $('expTitle').textContent = 'Expected in ' + MF[st.m];
    const ex = sel().map((s) => ({ s, f: freq(s, st.m) })).filter((o) => o.f > 0).sort((a, b) => b.f - a.f);
    const shown = st.expAll ? ex : ex.slice(0, 12);
    $('expected').innerHTML = shown.length ? shown.map(({ s, f }) => {
      const h = HAB[s[3]];
      const trend = s[8] === null ? '<span class="bt-tr" style="color:#5C6B63">Too few years for a trend</span>' : `<span class="bt-tr" style="color:${tc(s[8])}">Trend ${fmt(s[8])} a year</span>`;
      return `<div class="bt-sp" style="--c:${h[1]}"><span class="bt-st" style="color:${h[3]}">${status(s, st.m)} · ${esc(h[0])}</span><span class="bt-nm">${esc(s[1])}</span><span class="bt-v"><b>${f.toFixed(1)}</b><small>per 1,000 records</small></span>${trend}</div>`;
    }).join('') : `<div class="bt-empty">No winter visitors of this habitat expected in ${MF[st.m]}.</div>`;
    const box = $('expMore');
    box.hidden = ex.length <= 12;
    box.querySelector('button').textContent = st.expAll ? 'Show fewer' : `Show all ${ex.length}`;
  }
  function renderMap() {
    const el = $('map');
    if (!GEO) return;
    const g = GEO, list = spots();
    const box = el.getBoundingClientRect(), scale = Math.min(box.width / g.w, (box.height || box.width * g.h / g.w) / g.h) || 0.4;
    const r = Math.max(13, Math.min(16, box.width / 40)) / scale;
    const P = (la, lo) => [g.proj.pad + (lo - g.proj.lon0) * g.proj.kx * g.proj.s, g.proj.pad + (g.proj.lat1 - la) * g.proj.s];
    const pins = list.map((o) => { const [x, y] = P(o.s[5], o.s[6]); return { x0: x, y0: y, x, y, n: o.n, c: HAB[o.s[2]][1], ink: o.s[2] === 'grass' ? '#16241D' : '#fff', t: o.s[0] + ' · best ' + o.s[3] }; });
    // Pins nudged apart where places sit close together; a line keeps
    // each tied to its true spot.
    const D2 = r * 2.3;
    for (let it = 0; it < 80; it++) for (let i = 0; i < pins.length; i++) for (let j = i + 1; j < pins.length; j++) {
      const a = pins[i], b = pins[j]; let dx = b.x - a.x, dy = b.y - a.y; const d = Math.hypot(dx, dy) || 0.01;
      if (d < D2) { const p = (D2 - d) / 2; dx /= d; dy /= d; a.x -= dx * p; a.y -= dy * p; b.x += dx * p; b.y += dy * p; }
    }
    const [tx, ty] = P(18.3, 79.0), [ax, ay] = P(15.2, 79.3), fs = Math.max(22, 13 / scale);
    el.innerHTML = `<svg viewBox="0 0 ${g.w} ${g.h}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Map of migration hotspots in Telangana and Andhra Pradesh">
      ${g.ts.map((d) => `<path d="${d}" fill="#fff" stroke="#DDE5DF" stroke-width="1.5"/>`).join('')}
      ${g.ap.map((d) => `<path d="${d}" fill="#F5F4EF" stroke="#DDE5DF" stroke-width="1.5"/>`).join('')}
      <text x="${tx}" y="${ty}" text-anchor="middle" font-family="Sora" font-weight="600" font-size="${fs}" letter-spacing="4" fill="#4E6B5C">TELANGANA</text>
      <text x="${ax}" y="${ay}" text-anchor="middle" font-family="Sora" font-weight="600" font-size="${fs}" letter-spacing="4" fill="#4E6B5C">ANDHRA PRADESH</text>
      ${pins.map((p) => `<g><title>${esc(p.t)}</title><line x1="${p.x0}" y1="${p.y0}" x2="${p.x}" y2="${p.y}" stroke="#16241D" stroke-width="${2 / scale * 0.6}"/><circle cx="${p.x0}" cy="${p.y0}" r="${2.5 / scale}" fill="#16241D"/><circle cx="${p.x}" cy="${p.y}" r="${r}" fill="${p.c}" stroke="#16241D" stroke-width="${1.5 / scale}"/><text x="${p.x}" y="${p.y + r * 0.37}" text-anchor="middle" font-family="Sora" font-weight="700" font-size="${r * 1.05}" fill="${p.ink}">${p.n}</text></g>`).join('')}
    </svg>`;
  }
  function renderSpots() {
    $('spots').innerHTML = spots().map(({ s, n }) => {
      const h = HAB[s[2]];
      return `<div class="bt-card bt-spot"><div class="bt-top"><span class="bt-chip" style="background:${h[2]};color:${h[3]}">${esc(h[0])}</span><span>Best ${esc(s[3])}</span></div><h3><i style="background:${h[1]};color:${s[2] === 'grass' ? '#16241D' : '#fff'}">${n}</i>${esc(s[0])}</h3><div class="bt-ar">${esc(s[1])}</div><p>${esc(s[4])}</p></div>`;
    }).join('') || '<div class="bt-card bt-empty">No hotspots for this habitat.</div>';
  }
  // The calendar follows the month chosen at the top of the page (or in
  // its own month bar): it lists the species expected that month, with
  // that month's column shaded and the birds arriving then marked. "Show
  // every month" lists all winter visitors instead.
  function renderTimeline() {
    const all = sel(), dim = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const sp = st.tlMonth ? all.filter((s) => freq(s, st.m) > 0) : all;
    const todayX = cm <= 7 ? pct(cm + (now.getDate() - 1) / dim) : null;
    const today = todayX ? `<span class="bt-tl-today" style="left:${todayX}"></span>` : '';
    const col = st.tlMonth ? `<span class="bt-tl-col" style="left:${pct(st.m)};width:12.5%"></span>` : '';
    let hidden = 0;
    const groups = Object.entries(HAB).map(([k, h]) => {
      let rows = sp.filter((s) => s[3] === k).sort((a, b) => (st.tlMonth ? freq(b, st.m) - freq(a, st.m) : b[7] - a[7]));
      if (!rows.length) return '';
      if (!st.tlAll && rows.length > 8) { hidden += rows.length - 8; rows = rows.slice(0, 8); }
      rows.sort((a, b) => a[4] - b[4] || b[5] - a[5]);
      return `<div class="bt-tl-group" style="color:${h[3]}"><span class="bt-dot" style="background:${h[1]}"></span>${esc(h[0])}</div>` + rows.map((s) => {
        const p0 = Math.min(...s[6]), p1 = Math.max(...s[6]), n = s[5] - s[4] + 1, stops = [];
        for (let i = s[4]; i <= s[5]; i++) stops.push(mix(h[2], h[1], 0.2 + 0.8 * freq(s, i) / s[7]) + ' ' + (((i - s[4]) + 0.5) / n * 100).toFixed(1) + '%');
        const pk = M[p0] + (p1 > p0 ? '–' + M[p1] : '');
        const arriving = st.tlMonth && s[4] === st.m && st.m > 0;
        const when = s[4] === 0 ? 'Here from Sep' : 'Arrives ' + M[s[4]];
        return `<div class="bt-tl-row"><span class="bt-tl-name"><b>${esc(s[1])}${arriving ? ' <em class="bt-tl-new">Arriving</em>' : ''}</b><small>${when} · peak ${pk}</small></span><span class="bt-tl-track">${col}${today}<span class="bt-tl-bar" title="${esc(s[1])}: ${MF[s[4]]} to ${MF[s[5]]}, peak ${pk}" style="left:${pct(s[4])};width:${pct(n)};background:linear-gradient(90deg,${stops.join(',')})"></span></span></div>`;
      }).join('');
    }).join('');
    const count = st.tlMonth
      ? `${sp.length} species expected in ${MF[st.m]} · <button type="button" class="bt-tl-switch" data-tl-every="1">Show every month</button>`
      : `${sp.length} species · Sep–Apr · <button type="button" class="bt-tl-switch" data-tl-every="0">Only ${MF[st.m]}</button>`;
    const months = M.map((m, i) => `<button type="button" data-m="${i}" class="${i === st.m && st.tlMonth ? 'sel' : ''}${i === cm ? ' now' : ''}" aria-pressed="${i === st.m}"${i === cm ? ' title="This month"' : ''}>${m}</button>`).join('');
    $('timeline').innerHTML = `<div class="bt-tl-head"><span class="bt-tl-count">${count}</span><span class="bt-tl-months" role="group" aria-label="Choose a month">${months}</span></div>${groups || `<div class="bt-empty">No winter visitors of this habitat expected in ${MF[st.m]}.</div>`}
      <div class="bt-tl-legend"><span>Fewer records<i style="width:72px;height:12px;border-radius:999px;background:linear-gradient(90deg,#EAF2FA,#2B72B8)"></i>More</span>${todayX ? `<span><i style="height:16px;border-left:2px dashed #16241D"></i>Today, ${now.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</span>` : ''}</div>`;
    const box = $('tlMore');
    box.hidden = !st.tlAll && hidden === 0;
    box.querySelector('button').textContent = st.tlAll ? 'Show the most-recorded only' : `Show all ${sp.length} species`;
  }
  function render() { renderPills(); renderWaves(); renderExpected(); renderMap(); renderSpots(); renderTimeline(); }

  document.addEventListener('click', (e) => {
    const m = e.target.closest('[data-m]'), h = e.target.closest('[data-h]');
    // One month for the whole page: the pills at the top and the calendar's own bar.
    if (m) { st.m = +m.dataset.m; st.expAll = false; st.tlAll = false; st.tlMonth = true; renderPills(); renderExpected(); renderTimeline(); }
    const every = e.target.closest('[data-tl-every]');
    if (every) { st.tlMonth = every.dataset.tlEvery === '0'; st.tlAll = false; renderTimeline(); }
    if (h) { st.h = h.dataset.h; st.expAll = false; render(); }
    if (e.target.closest('#expMore button')) { st.expAll = !st.expAll; renderExpected(); }
    if (e.target.closest('#tlMore button')) { st.tlAll = !st.tlAll; renderTimeline(); }
  });
  let rt;
  window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(renderMap, 150); });
  render();

  if (window.DB_BT && DB_BT.geo) {
    fetch(DB_BT.geo).then((r) => r.json()).then((g) => { GEO = g; renderMap(); }).catch(() => {});
  }
})();
