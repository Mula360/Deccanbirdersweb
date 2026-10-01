/* Deccan Birders — Birding Tools → Backpack.
 *
 * Every kit category is in the page as written (page-backpack.php); this
 * turns them into tabs, with previous/next arrows and the arrow keys. A
 * link to #cat-<id> (e.g. #cat-safety) opens that category. Without the
 * script, the categories simply read one after another.
 */
(function () {
  'use strict';
  const tabs = document.querySelector('.bt-pack-tabs');
  if (!tabs) return;
  const buttons = Array.from(tabs.querySelectorAll('[role="tab"]'));
  const panels = buttons.map((b) => document.getElementById(b.getAttribute('aria-controls')));
  let current = 0;

  function show(i, focus) {
    current = (i + panels.length) % panels.length;
    buttons.forEach((b, j) => {
      const on = j === current;
      b.setAttribute('aria-selected', on ? 'true' : 'false');
      b.tabIndex = on ? 0 : -1;
      panels[j].hidden = !on;
    });
    if (focus) buttons[current].focus();
    // Keep the chosen tab in view in the bar (on phones it scrolls
    // sideways) without moving the page.
    const b = buttons[current];
    if (b.offsetLeft < tabs.scrollLeft || b.offsetLeft + b.offsetWidth > tabs.scrollLeft + tabs.clientWidth) {
      tabs.scrollLeft = b.offsetLeft - 6;
    }
  }

  tabs.hidden = false;
  document.querySelectorAll('.bt-pack-arrows').forEach((a) => { a.hidden = false; });
  buttons.forEach((b, i) => b.addEventListener('click', () => show(i)));
  tabs.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { e.preventDefault(); show(current + 1, true); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); show(current - 1, true); }
  });
  document.addEventListener('click', (e) => {
    const step = e.target.closest('.bt-pack-arrows [data-step]');
    if (step) show(current + Number(step.dataset.step));
  });

  const fromHash = () => panels.findIndex((p) => '#' + p.id === location.hash);
  show(Math.max(0, fromHash()));
  window.addEventListener('hashchange', () => { const i = fromHash(); if (i >= 0) show(i); });
})();
