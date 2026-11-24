/*
 * The Giya button can be moved, like iPhone's AssistiveTouch.
 *
 * Press and drag it anywhere; on letting go it slides to the nearest side
 * of the screen at the height it was dropped, and GIYA remembers that spot
 * on this device (every page, and after reopening). A tap still opens the
 * chat: it only counts as a drag once it has moved a few pixels, and the
 * click that ends a drag is swallowed so the chat does not open by mistake.
 *
 * Pointer events serve a finger and a mouse alike. The button has
 * touch-action: none so dragging it does not scroll the page.
 *
 * It never lands on top of the navbar, or on the trip screen's bottom
 * panel: the reachable area is worked out each time, and the spot is
 * re-fitted when the screen turns or the panel changes height.
 */
(function () {
  'use strict';

  var fab = document.getElementById('giyaFab');
  if (!fab || !window.PointerEvent) return;

  var KEY = 'giya-fab-pos';
  var EDGE = 12;          // gap to the screen edge after snapping
  var MOVE = 6;           // pixels before a press becomes a drag
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function load() {
    try {
      var v = JSON.parse(localStorage.getItem(KEY) || 'null');
      if (v && (v.side === 'left' || v.side === 'right') && typeof v.y === 'number') return v;
    } catch (e) { /* private mode or bad value: the usual corner */ }
    return null;
  }
  function save(v) {
    try { localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) { /* works for this visit */ }
  }

  function px(name, fallback) {
    var v = parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name));
    return isNaN(v) ? fallback : v;
  }

  /* The part of the screen the button may sit in. */
  function bounds() {
    var w = fab.offsetWidth || 52, h = fab.offsetHeight || 52;
    var top = px('--nav-height', 64) + EDGE;
    var bottom = window.innerHeight - EDGE;

    // On a trip, above the bottom panel.
    var sheet = document.querySelector('.active-layout .active-sidebar');
    if (sheet && getComputedStyle(sheet).position === 'fixed') {
      bottom = Math.min(bottom, sheet.getBoundingClientRect().top - EDGE);
    }
    return { w: w, h: h, minX: EDGE, maxX: window.innerWidth - w - EDGE, minY: top, maxY: Math.max(top, bottom - h) };
  }

  function place(x, y) {
    // Inline and important: the stylesheet pins the corner with !important on a phone.
    fab.style.setProperty('left', Math.round(x) + 'px', 'important');
    fab.style.setProperty('top', Math.round(y) + 'px', 'important');
    fab.style.setProperty('right', 'auto', 'important');
    fab.style.setProperty('bottom', 'auto', 'important');
  }

  function sideClasses(side, y) {
    fab.classList.toggle('is-left', side === 'left');
    // Near the top, the greeting bubble opens underneath instead of off the screen.
    fab.classList.toggle('is-high', y < px('--nav-height', 64) + 150);
  }

  /* Put the button where it was saved, fitted to this screen. */
  function apply() {
    var pos = load();
    if (!pos || dragging) return;
    var b = bounds();
    var y = Math.min(Math.max(pos.y * window.innerHeight, b.minY), b.maxY);
    place(pos.side === 'left' ? b.minX : b.maxX, y);
    fab.classList.add('is-placed');
    sideClasses(pos.side, y);
  }

  var start = null, dragging = false, swallow = false;

  fab.addEventListener('pointerdown', function (e) {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    var r = fab.getBoundingClientRect();
    try { fab.setPointerCapture(e.pointerId); } catch (err) { /* not capturable */ }
    start = { id: e.pointerId, x: e.clientX, y: e.clientY, dx: e.clientX - r.left, dy: e.clientY - r.top };
  });

  // On the window: a quick first move already leaves the small button.
  window.addEventListener('pointermove', function (e) {
    if (!start || e.pointerId !== start.id) return;
    if (!dragging) {
      if (Math.abs(e.clientX - start.x) < MOVE && Math.abs(e.clientY - start.y) < MOVE) return;
      dragging = true;
      fab.classList.add('is-dragging', 'is-placed');
      fab.classList.remove('is-saying');
      fab.style.transition = 'none';
    }
    e.preventDefault();
    var b = bounds();
    var x = Math.min(Math.max(e.clientX - start.dx, 0), window.innerWidth - b.w);
    var y = Math.min(Math.max(e.clientY - start.dy, b.minY), b.maxY);
    place(x, y);
  });

  function end(e) {
    if (!start || (e && e.pointerId !== start.id)) return;
    start = null;
    if (!dragging) return;
    dragging = false;
    swallow = true;   // the click that ends this drag is not a tap
    setTimeout(function () { swallow = false; }, 400);

    var r = fab.getBoundingClientRect();
    var side = r.left + r.width / 2 < window.innerWidth / 2 ? 'left' : 'right';
    var b = bounds();
    var y = Math.min(Math.max(r.top, b.minY), b.maxY);

    fab.classList.remove('is-dragging');
    fab.style.transition = still ? 'none' : 'left .28s cubic-bezier(.2, .8, .2, 1), top .28s cubic-bezier(.2, .8, .2, 1)';
    place(side === 'left' ? b.minX : b.maxX, y);
    sideClasses(side, y);
    save({ side: side, y: y / window.innerHeight });
    setTimeout(function () { fab.style.transition = ''; }, 320);
  }
  window.addEventListener('pointerup', end);
  window.addEventListener('pointercancel', end);

  // Before the chat's own click handler: a drag is not a tap.
  window.addEventListener('click', function (e) {
    if (swallow && fab.contains(e.target)) {
      e.stopImmediatePropagation();
      e.preventDefault();
      swallow = false;
    }
  }, true);

  apply();
  window.addEventListener('resize', apply);
  window.addEventListener('orientationchange', function () { setTimeout(apply, 250); });

  // The trip panel changes height (opened, closed, measured): keep clear of it.
  var sheet = document.querySelector('.active-layout .active-sidebar');
  if (sheet && window.ResizeObserver) {
    new ResizeObserver(function () { requestAnimationFrame(apply); }).observe(sheet);
  }
})();
