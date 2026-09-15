/**
 * Deccan Birders — Video gallery (Gallery page).
 * Vanilla ES6, no dependencies. Talks to the deccan-birders-api Vercel
 * endpoint (DB_CONFIG.api_base) at /api/videos.
 */

(function () {
'use strict';

// Same-origin WP REST proxy (see functions.php) — avoids the Vercel API's
// CORS restriction to the production domain, and caches responses server-side.
const API = '/wp-json/db/v1';

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

function openVideo(videoId) {
  window.open(`https://youtube.com/watch?v=${encodeURIComponent(videoId)}`, '_blank', 'noopener');
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
    const data = json.data || [];

    if (!data.length) { grid.innerHTML = '<p>No videos found.</p>'; return; }

    grid.innerHTML = `<div class="videos-grid-inner">${data.map((v) => `
      <div class="video-card" data-video-id="${escapeHtml(v.videoId)}" role="button" tabindex="0" aria-label="Watch ${escapeHtml(v.title)} on YouTube">
        <div class="video-thumb-wrap">
          <img class="video-thumb" src="${escapeHtml(v.thumbnail)}" alt="${escapeHtml(v.title)}" loading="lazy">
          <div class="video-thumb-scrim" aria-hidden="true"></div>
          <div class="video-play" aria-hidden="true"><span></span></div>
        </div>
        <div class="video-info">
          <div class="video-title">${escapeHtml(v.title)}</div>
          <div class="video-meta">${escapeHtml(v.duration)} · ${escapeHtml(v.views)} views</div>
        </div>
      </div>`).join('')}</div>`;

    // Click + keyboard support
    grid.querySelectorAll('.video-card').forEach((card) => {
      card.addEventListener('click', () => openVideo(card.dataset.videoId));
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openVideo(card.dataset.videoId); }
      });
    });
  } catch (e) {
    grid.innerHTML = '<p>Could not load videos.</p>';
  }
}

document.addEventListener('DOMContentLoaded', initVideos);

})();
