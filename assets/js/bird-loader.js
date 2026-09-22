/* Deccan Birders — the loading state, site-wide.
 *
 * A small bird flapping its way across the space the content will fill,
 * rather than grey bars. It replaces only the element that is waiting, so
 * the rest of the page stays readable while eBird, YouTube or the PITTA
 * index answer.
 *
 * DB.birdLoader('Finding today's sightings…') returns the markup;
 * DB.showBirdLoader(el, message) drops it into an element.
 */
window.DB = window.DB || {};

(function () {
  'use strict';

  // Two wing positions, swapped by CSS — cheaper and steadier than
  // animating a path, and it reads as a flap at any size.
  const BIRD = `
    <svg class="bird-loader-bird" viewBox="0 0 64 40" aria-hidden="true" focusable="false">
      <path class="bird-wing-up" d="M4 26c8 2 14-2 19-9 3-4 6-7 9-7s6 3 9 7c5 7 11 11 19 9-7 6-14 8-19 5-4-2-6-5-9-5s-5 3-9 5c-5 3-12 1-19-5Z"/>
      <path class="bird-wing-down" d="M4 12c8-2 14 2 19 9 3 4 6 7 9 7s6-3 9-7c5-7 11-11 19-9-7-6-14-8-19-5-4 2-6 5-9 5s-5-3-9-5c-5-3-12-1-19 5Z"/>
    </svg>`;

  DB.birdLoader = function (message) {
    return `<div class="bird-loader" role="status" aria-live="polite">
      <div class="bird-loader-flight">${BIRD}</div>
      <p class="bird-loader-text">${message ? String(message).replace(/[<>&]/g, '') : 'Loading…'}</p>
    </div>`;
  };

  DB.showBirdLoader = function (target, message) {
    const el = typeof target === 'string' ? document.getElementById(target) : target;
    if (el) el.innerHTML = DB.birdLoader(message);
    return el;
  };
})();
