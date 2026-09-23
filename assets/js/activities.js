/**
 * Deccan Birders — the Activities panel on the About page.
 *
 * Six activities, one shown at a time. It moves on by itself every few
 * seconds so the section shows its whole range without anyone touching
 * it, and a click selects one directly.
 *
 * Every panel is already in the HTML (template-parts/activities-list.php),
 * so this only ever changes which one is marked active — with JavaScript
 * off, the first activity stays on screen and the section still reads.
 */

(function () {
'use strict';

const INTERVAL = 5000;

function initActivities() {
  const root = document.getElementById('acts');
  if (!root) return;

  const tabs    = [...root.querySelectorAll('.acts-item')];
  const figures = [...root.querySelectorAll('.acts-figure')];
  const panels  = [...root.querySelectorAll('.acts-points')];
  if (tabs.length < 2) return;

  let current = 0;
  let timer = null;
  // Someone who has picked an activity is reading it; the panel stops
  // moving rather than pulling the page out from under them.
  let surrendered = false;

  const show = (next) => {
    current = (next + tabs.length) % tabs.length;
    tabs.forEach((t, i) => {
      const on = i === current;
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
    });
    figures.forEach((f, i) => f.classList.toggle('is-active', i === current));
    panels.forEach((p, i) => {
      p.classList.toggle('is-active', i === current);
      p.hidden = i !== current;
    });
  };

  const stop  = () => { clearInterval(timer); timer = null; };
  const start = () => {
    if (timer || surrendered) return;
    timer = setInterval(() => show(current + 1), INTERVAL);
  };

  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => { surrendered = true; stop(); show(i); });
    // Left/right walk the list the way a tablist is expected to.
    tab.addEventListener('keydown', (e) => {
      const step = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
      if (!step) return;
      e.preventDefault();
      surrendered = true; stop();
      show(current + step);
      tabs[current].focus();
    });
  });

  // Pause while someone is reading or tabbing through, then resume.
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', start);

  // Nothing moves for someone who has asked their system for less motion.
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  // Start straight away and let the observer pause it while the section
  // is off-screen. Starting from inside the observer instead would mean
  // never starting at all wherever it doesn't fire.
  start();
  if ('IntersectionObserver' in window) {
    new IntersectionObserver((entries) => {
      entries.forEach((entry) => (entry.isIntersecting ? start() : stop()));
    }, { threshold: 0.2 }).observe(root);
  }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop(); else start();
  });
}

document.addEventListener('DOMContentLoaded', initActivities);

})();
