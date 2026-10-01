/* Deccan Birders — Birding Tools → Bird Trends.
 *
 * Draws the page from the figures page-bird-trends.php prints as JSON
 * (#bt-data, from inc/birding-tools/engine.php, recalculated every 3
 * days): a detail card with a two-state chart, the ranked declines and
 * increases, and a species search. The drawing is the design's own.
 */
(function () {
  'use strict';
  const el = document.getElementById('bt-data');
  if (!el) return;
  const D = JSON.parse(el.textContent);
  const $ = (id) => document.getElementById(id);
  const COLORS = { TS: '#2B72B8', AP: '#17924C' };
  const SHORT = { TS: 'TS', AP: 'AP' };
  const KEYS = ['TS', 'AP'].filter((k) => D.states[k] && D.states[k].years);
  if (!KEYS.length) return;

  const ST = {};
  KEYS.forEach((k) => {
    const y = D.states[k].years;
    ST[k] = { name: D.states[k].name, short: SHORT[k], y0: y[0], y1: y[y.length - 1], color: COLORS[k] };
  });
  const Y0 = Math.min(...KEYS.map((k) => ST[k].y0));
  const Y1 = Math.max(...KEYS.map((k) => ST[k].y1));
  const BY = {};
  D.species.forEach((s) => { BY[s.code] = s; });

  const esc = (v) => String(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmt = (v) => { const s = Math.round(v); return (s < 0 ? '−' : '+') + Math.abs(s) + '%'; };
  const stable = (x) => Math.abs(x.pct) < 1.5;
  const CHIP = { 'clear-d': ['Clear decline', '#F8E3E1', '#B3261E'], 'poss-d': ['Possible decline', '#FFF4D6', '#7A5A00'], 'clear-i': ['Clear increase', '#E6F4EC', '#0F7A3E'], 'poss-i': ['Possible increase', '#E6F4EC', '#0F7A3E'], none: ['No clear change', '#E7EDE9', '#41514A'] };

  /** One state's figures for a species, or null when there's no verdict. */
  function series(code, k) {
    const s = BY[code];
    const row = s && s[k];
    if (!row || row.label === 'few' || !ST[k]) return null;
    return { vals: row.vals, y0: row.y0, n: row.vals.length, r: row.r, pct: row.pct, tot: row.tot, lab: row.label, wet: !!s.wet };
  }
  function list(k) {
    return D.species.filter((s) => series(s.code, k)).map((s) => ({ code: s.code, name: s.name, x: series(s.code, k) }));
  }
  const declines = (k) => list(k).filter((s) => s.x.lab.endsWith('-d')).sort((a, b) => a.x.r - b.x.r);
  const increases = (k) => list(k).filter((s) => s.x.lab.endsWith('-i')).sort((a, b) => b.x.r - a.x.r);

  // Opens on the chosen state's top decliner, unless a species was linked
  // to (#code) or picked.
  const st = { s: KEYS[0], sel: null, picked: false, decAll: false, incAll: false };
  const topOf = (k) => (declines(k)[0] || list(k)[0] || { code: D.species[0].code }).code;
  const hash = decodeURIComponent(location.hash.slice(1));
  if (BY[hash]) { st.sel = hash; st.picked = true; } else { st.sel = topOf(st.s); }

  function spark(v, w, h) {
    const m = Math.max(...v) * 1.1;
    const pts = v.map((y, i) => [i / (v.length - 1) * (w - 4) + 2, h - 2 - y / m * (h - 6)]);
    const line = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
    return { line, area: line + ` L${pts[pts.length - 1][0].toFixed(1)},${h} L2,${h} Z` };
  }

  function chart(sel, W) {
    const H = W < 520 ? 240 : 300, L = W < 520 ? 34 : 44, R = W - (W < 520 ? 36 : 120), T = 12, B = H - 28;
    const xs = (y) => L + (y - Y0) / Math.max(1, Y1 - Y0) * (R - L);
    const ser = KEYS.map((k) => [k, series(sel, k)]).filter((a) => a[1]);
    if (!ser.length) return '<p class="bt-chart-note">Too few records in either state to draw a trend.</p>';
    // The scale fits both the yearly points and the fitted (dashed) line.
    const fitted = (x) => { const a = x.vals.map((v, i) => Math.log(v) - x.r * i).sort((p, q) => p - q)[Math.floor(x.n / 2)]; return x.vals.map((_, i) => Math.exp(a + x.r * i)); };
    const raw = Math.max(...ser.flatMap((a) => a[1].vals.concat(fitted(a[1])))) * 1.15;
    const step = raw > 40 ? 20 : raw > 20 ? 10 : raw > 8 ? 4 : raw > 4 ? 2 : raw > 2 ? 1 : raw > 1 ? 0.5 : 0.2;
    const ymax = Math.ceil(raw / step) * step, ys = (v) => B - v / ymax * (B - T);
    let o = `<svg viewBox="0 0 ${W} ${H}" height="${H}" role="img" aria-label="Reporting trend chart">`;
    for (let v = 0; v <= ymax + 1e-9; v += step) {
      const lab = step < 1 ? v.toFixed(1) : Math.round(v);
      o += `<line x1="${L}" x2="${R}" y1="${ys(v)}" y2="${ys(v)}" stroke="#E7EDE9"/><text x="${L - 8}" y="${ys(v) + 4}" text-anchor="end" font-size="12" fill="#5C6B63">${lab}</text>`;
    }
    for (let y = Y0; y <= Y1; y++) if (W >= 520 || y % 2 === 0) o += `<text x="${xs(y)}" y="${H - 6}" text-anchor="middle" font-size="12" fill="#5C6B63">${W < 520 ? "'" + String(y).slice(2) : y}</text>`;
    const labs = [];
    ser.forEach(([k, x]) => {
      const c = ST[k].color, yrs = x.vals.map((_, i) => x.y0 + i);
      const a = x.vals.map((v, i) => Math.log(v) - x.r * i).sort((p, q) => p - q)[Math.floor(x.n / 2)];
      const path = yrs.map((y, i) => `${i ? 'L' : 'M'}${xs(y).toFixed(1)},${ys(x.vals[i]).toFixed(1)}`).join(' ');
      o += `<path d="${path} L${xs(yrs[yrs.length - 1])},${B} L${xs(x.y0)},${B} Z" fill="${c}" opacity=".07"/>`;
      o += `<path d="${yrs.map((y, i) => `${i ? 'L' : 'M'}${xs(y).toFixed(1)},${ys(Math.exp(a + x.r * i)).toFixed(1)}`).join(' ')}" fill="none" stroke="${c}" stroke-width="1.5" stroke-dasharray="5 5" opacity=".7"/>`;
      o += `<path d="${path}" fill="none" stroke="${c}" stroke-width="3" stroke-linejoin="round"/>`;
      x.vals.forEach((v, i) => { o += `<circle cx="${xs(yrs[i])}" cy="${ys(v)}" r="3.5" fill="#fff" stroke="${c}" stroke-width="2"><title>${esc(ST[k].name)} ${yrs[i]}: ${v.toFixed(1)} per 1,000 records</title></circle>`; });
      labs.push({ t: W < 520 ? ST[k].short : ST[k].name, c, x: xs(yrs[yrs.length - 1]), y: ys(x.vals[x.n - 1]) + 5 });
    });
    if (labs.length === 2 && Math.abs(labs[0].y - labs[1].y) < 16) { const [p, q] = labs[0].y < labs[1].y ? labs : [labs[1], labs[0]]; p.y -= 8; q.y += 8; }
    labs.forEach((l) => { o += `<text x="${l.x + 8}" y="${l.y}" font-family="Sora" font-size="13" font-weight="600" fill="${l.c}">${esc(l.t)}</text>`; });
    return o + '</svg>';
  }

  function renderTabs() {
    $('tabs').innerHTML = KEYS.map((k) => `<button class="bt-pill" type="button" data-s="${k}" aria-pressed="${st.s === k}">${esc(ST[k].name)}</button>`).join('');
  }
  function renderDetail() {
    const sp = BY[st.sel];
    const stats = KEYS.map((k) => {
      const x = series(st.sel, k);
      if (!x) return `<div class="bt-stat"><div class="bt-lab"><span class="bt-dot" style="background:${ST[k].color}"></span>${esc(ST[k].name)}</div><div class="bt-big">Too few records</div><div class="bt-sub">Not enough data for a verdict</div></div>`;
      return `<div class="bt-stat"><div class="bt-lab"><span class="bt-dot" style="background:${ST[k].color}"></span>${esc(ST[k].name)}</div><div class="bt-big">${stable(x) ? 'About stable' : fmt(x.pct) + ' a year'}</div><div class="bt-sub">${fmt(x.tot)} since ${x.y0} · ${x.vals[x.n - 1].toFixed(1)} per 1,000</div></div>`;
    }).join('');
    $('detail').innerHTML = `<div><h2>${esc(sp.name)}</h2><div class="bt-sci">${esc(sp.sci)}</div><div class="bt-stats">${stats}</div>${sp.wet ? '<div class="bt-wet">Wetland bird: trends depend on a few well-watched lakes, and water levels.</div>' : ''}</div><div class="bt-chart" id="chart"></div>`;
    renderChart();
  }
  function renderChart() {
    const c = $('chart');
    if (!c) return;
    c.innerHTML = chart(st.sel, Math.max(280, Math.round(c.clientWidth))) + '<div class="bt-chart-note">Records of this species per 1,000 records. Dashed line: fitted trend.</div>';
  }
  function renderLists() {
    const name = ST[st.s].name;
    const dec = declines(st.s), inc = increases(st.s);
    $('decTitle').textContent = 'Declining in ' + name;
    const decShown = st.decAll ? dec : dec.slice(0, 9);
    $('dec').innerHTML = decShown.length ? decShown.map((s, i) => {
      const c = CHIP[s.x.lab], sp = spark(s.x.vals, 320, 56);
      return `<button class="bt-dec" type="button" data-sp="${esc(s.code)}" aria-pressed="${st.sel === s.code}"><span class="bt-row"><span class="bt-rk">#${i + 1}</span><span class="bt-chip" style="background:${c[1]};color:${c[2]}">${c[0]}</span></span><span class="bt-nm">${esc(s.name)}</span><span class="bt-pc"><b>${fmt(s.x.pct)}</b><span>a year · ${fmt(s.x.tot)} since ${s.x.y0}</span></span>${s.x.wet ? '<span class="bt-wl"><span class="bt-dot" style="background:#F2B705;width:8px;height:8px"></span>Wetland bird</span>' : ''}<svg viewBox="0 0 320 56" preserveAspectRatio="none" aria-hidden="true"><path d="${sp.area}" fill="#B3261E" opacity=".08"/><path d="${sp.line}" fill="none" stroke="#B3261E" stroke-width="2" vector-effect="non-scaling-stroke"/></svg></button>`;
    }).join('') : `<p>No species shows a decline in ${esc(name)}.</p>`;
    more('decMore', dec.length, 9, st.decAll);
    const incShown = st.incAll ? inc : inc.slice(0, 8);
    $('inc').innerHTML = incShown.length ? incShown.map((s) => `<button class="bt-inc" type="button" data-sp="${esc(s.code)}" aria-pressed="${st.sel === s.code}"><span class="bt-nm">${esc(s.name)}</span><b>${fmt(s.x.pct)} <small>a year</small></b></button>`).join('') : `<p>No species shows an increase in ${esc(name)}.</p>`;
    more('incMore', inc.length, 8, st.incAll);
  }
  function more(id, total, first, all) {
    const box = $(id);
    box.hidden = total <= first;
    box.querySelector('button').textContent = all ? 'Show fewer' : `Show all ${total}`;
  }
  function select(code) {
    st.sel = code; st.picked = true;
    history.replaceState(null, '', '#' + code);
    renderDetail(); renderLists();
    const d = $('detail');
    window.scrollTo({ top: d.getBoundingClientRect().top + scrollY - 100, behavior: 'smooth' });
  }

  document.addEventListener('click', (e) => {
    const s = e.target.closest('[data-s]'), p = e.target.closest('[data-sp]');
    if (s) {
      st.s = s.dataset.s; st.decAll = st.incAll = false;
      if (!st.picked) st.sel = topOf(st.s);
      renderTabs(); renderDetail(); renderLists();
    }
    if (p) select(p.dataset.sp);
    if (e.target.closest('#decMore button')) { st.decAll = !st.decAll; renderLists(); }
    if (e.target.closest('#incMore button')) { st.incAll = !st.incAll; renderLists(); }
    if (e.target.id === 'methodLink') $('method').open = true;
  });

  // Search: any species, by common or scientific name.
  const q = $('q'), sg = $('sugg');
  let hits = [], hi = -1;
  function trendFor(code) {
    const x = series(code, st.s) || KEYS.map((k) => series(code, k)).find(Boolean);
    if (!x) return ['Too few records', '#5C6B63'];
    return [stable(x) ? 'About stable' : fmt(x.pct) + ' a year', x.pct <= -1.5 ? '#B3261E' : x.pct >= 1.5 ? '#0F7A3E' : '#5C6B63'];
  }
  function openSugg() {
    const v = q.value.trim().toLowerCase();
    if (v) {
      // Whole words first ("sparrow" → House Sparrow before Sparrow-Lark),
      // then names starting with it, then the rest.
      const rank = (s) => { const n = s.name.toLowerCase(); return n.split(' ').includes(v) ? 0 : n.startsWith(v) ? 1 : n.split(/[\s-]/).some((w) => w.startsWith(v)) ? 2 : 3; };
      hits = D.species.filter((s) => (s.name + ' ' + s.sci + ' ' + s.code).toLowerCase().includes(v))
        .sort((a, b) => (rank(a) - rank(b)) || a.name.localeCompare(b.name));
    } else {
      hits = declines(st.s).map((s) => BY[s.code]);
    }
    hits = hits.slice(0, 40);
    hi = hits.length ? 0 : -1;
    sg.innerHTML = hits.length ? hits.map((s, i) => { const [t, c] = trendFor(s.code); return `<li role="option" id="o-${esc(s.code)}" data-pick="${esc(s.code)}" aria-selected="${i === hi}"><span class="bt-n"><b>${esc(s.name)}</b><i>${esc(s.sci)}</i></span><span class="bt-t" style="color:${c}">${t}</span></li>`; }).join('')
      : `<li class="bt-none">No species found for “${esc(q.value.trim())}”</li>`;
    sg.classList.add('open');
    q.setAttribute('aria-expanded', 'true');
    q.setAttribute('aria-activedescendant', hi >= 0 ? 'o-' + hits[hi].code : '');
  }
  function closeSugg() { sg.classList.remove('open'); q.setAttribute('aria-expanded', 'false'); }
  function moveHi(d) {
    if (!hits.length) return;
    hi = (hi + d + hits.length) % hits.length;
    sg.querySelectorAll('li').forEach((li, i) => li.setAttribute('aria-selected', i === hi));
    const li = sg.children[hi];
    q.setAttribute('aria-activedescendant', li.id);
    sg.scrollTop = Math.max(0, li.offsetTop - sg.clientHeight / 2);
  }
  function pick(code) { q.value = ''; closeSugg(); q.blur(); select(code); }
  q.addEventListener('input', openSugg);
  q.addEventListener('focus', openSugg);
  q.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); sg.classList.contains('open') ? moveHi(1) : openSugg(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); moveHi(-1); }
    else if (e.key === 'Enter') { e.preventDefault(); if (hi >= 0) pick(hits[hi].code); }
    else if (e.key === 'Escape') closeSugg();
  });
  sg.addEventListener('mousedown', (e) => { const li = e.target.closest('[data-pick]'); if (li) { e.preventDefault(); pick(li.dataset.pick); } });
  document.addEventListener('click', (e) => { if (!e.target.closest('.bt-search')) closeSugg(); });

  let rt;
  window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(renderChart, 150); });
  renderTabs(); renderDetail(); renderLists();
})();
