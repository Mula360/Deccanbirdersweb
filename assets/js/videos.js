/**
 * Deccan Birders — Video gallery (Gallery page).
 * Vanilla ES6, no dependencies. Reads the channel's uploads from the WP
 * REST proxy (functions.php: db_youtube_videos), which pages through the
 * whole channel rather than just the newest handful.
 *
 * A page of cards at a time, and clicking one opens the video in a modal
 * player. The YouTube iframe is only created on click — nothing from
 * youtube.com loads until someone actually plays something.
 */

(function () {
'use strict';

const API = '/wp-json/db/v1';
const PAGE_SIZE = 6; // two rows of three on desktop

let videos = [];
let page = 1;

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

// "12 Mar 2024" — short, and unambiguous in any locale.
function formatDate(iso) {
  const d = new Date(iso);
  if (isNaN(d)) return '';
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

/* -------------------------------------------------------------------------
 * Modal player
 * ---------------------------------------------------------------------- */

let modal = null;
let lastFocus = null;

function buildModal() {
  modal = document.createElement('div');
  modal.className = 'video-modal';
  modal.id = 'video-modal';
  modal.hidden = true;
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-label', 'Video player');
  modal.innerHTML = `
    <button class="video-modal-close" type="button" aria-label="Close video">✕</button>
    <div class="video-modal-inner">
      <div class="video-modal-frame"></div>
      <div class="video-modal-caption">
        <h3 class="video-modal-title"></h3>
        <p class="video-modal-meta"></p>
        <a class="video-modal-link" target="_blank" rel="noopener">Watch on YouTube →</a>
      </div>
    </div>`;
  document.body.appendChild(modal);

  modal.addEventListener('click', (e) => {
    if (e.target === modal || e.target.closest('.video-modal-close')) closeVideo();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) closeVideo();
  });
}

function openVideo(video) {
  if (!modal) buildModal();
  lastFocus = document.activeElement;

  // youtube-nocookie defers YouTube's tracking cookies until playback.
  const src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(video.videoId)}?autoplay=1&rel=0`;
  modal.querySelector('.video-modal-frame').innerHTML =
    `<iframe src="${escapeHtml(src)}" title="${escapeHtml(video.title)}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>`;
  modal.querySelector('.video-modal-title').textContent = video.title;
  modal.querySelector('.video-modal-meta').textContent =
    [formatDate(video.published), video.duration, video.views !== '?' ? video.views + ' views' : ''].filter(Boolean).join(' · ');
  const link = modal.querySelector('.video-modal-link');
  link.href = `https://www.youtube.com/watch?v=${encodeURIComponent(video.videoId)}`;

  modal.hidden = false;
  document.body.classList.add('modal-open');
  modal.querySelector('.video-modal-close').focus();
}

function closeVideo() {
  if (!modal || modal.hidden) return;
  modal.querySelector('.video-modal-frame').innerHTML = ''; // stops playback
  modal.hidden = true;
  document.body.classList.remove('modal-open');
  if (lastFocus) lastFocus.focus();
}

/* -------------------------------------------------------------------------
 * Grid
 * ---------------------------------------------------------------------- */

function cardHtml(v, index) {
  const meta = [formatDate(v.published), v.duration, v.views !== '?' ? v.views + ' views' : '']
    .filter(Boolean).map(escapeHtml).join(' · ');
  return `
    <div class="video-card" data-index="${index}" role="button" tabindex="0" aria-label="Play ${escapeHtml(v.title)}">
      <div class="video-thumb-wrap">
        <img class="video-thumb" src="${escapeHtml(v.thumbnail)}" alt="" loading="lazy" width="320" height="180">
        <div class="video-thumb-scrim" aria-hidden="true"></div>
        <div class="video-play" aria-hidden="true"><span></span></div>
        ${v.duration && v.duration !== '?' ? `<span class="video-duration">${escapeHtml(v.duration)}</span>` : ''}
      </div>
      <div class="video-info">
        <div class="video-title">${escapeHtml(v.title)}</div>
        <div class="video-meta">${meta}</div>
        ${v.description ? `<p class="video-desc">${escapeHtml(v.description)}</p>` : ''}
      </div>
    </div>`;
}

function paint(grid) {
  const pages = Math.max(1, Math.ceil(videos.length / PAGE_SIZE));
  page = Math.min(page, pages);
  const start = (page - 1) * PAGE_SIZE;
  const slice = videos.slice(start, start + PAGE_SIZE);

  const nav = pages > 1 ? `
    <nav class="events-pager" aria-label="Video pages">
      <button type="button" class="events-pager-btn" data-step="-1"${page === 1 ? ' disabled' : ''}>← Newer</button>
      <span class="events-pager-status">Page ${page} of ${pages} · ${videos.length} videos</span>
      <button type="button" class="events-pager-btn" data-step="1"${page === pages ? ' disabled' : ''}>Older →</button>
    </nav>` : `<p class="records-count">${videos.length} video${videos.length === 1 ? '' : 's'}</p>`;

  grid.innerHTML = `<div class="videos-grid-inner">${slice.map((v, i) => cardHtml(v, start + i)).join('')}</div>${nav}`;

  grid.querySelectorAll('.video-card').forEach((card) => {
    const video = videos[Number(card.dataset.index)];
    card.addEventListener('click', () => openVideo(video));
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openVideo(video); }
    });
  });

  grid.querySelectorAll('.events-pager-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      page = Math.min(pages, Math.max(1, page + Number(btn.dataset.step)));
      paint(grid);
      grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
}

async function initVideos() {
  const grid = document.getElementById('videos-grid');
  if (!grid) return;

  grid.innerHTML = `<div class="video-skeleton-grid">
    ${Array(6).fill('<div class="video-skeleton"><div class="sk-block" style="width:100%;aspect-ratio:16/9;border-radius:8px 8px 0 0;height:auto"></div><div style="padding:14px"><div class="sk-block" style="width:80%;margin-bottom:6px"></div><div class="sk-block" style="width:50%"></div></div></div>').join('')}
  </div>`;

  try {
    const res  = await fetch(`${API}/videos`);
    const json = await res.json();
    if (json.error) throw new Error(json.message || 'Request failed');
    videos = json.data || [];

    if (!videos.length) { grid.innerHTML = '<p>No videos found.</p>'; return; }
    paint(grid);
  } catch (e) {
    grid.innerHTML = '<p>Could not load videos.</p>';
  }
}

document.addEventListener('DOMContentLoaded', initVideos);

})();
