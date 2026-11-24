/*
 * The guided tour: the screen dims, one button at a time stays lit in a
 * spotlight, and a card beside it says what it is for.
 *
 * The page says which buttons through components/tour.blade.php, which only
 * renders while this account has not finished that page's tour. Done (or
 * tapping a lit link that leaves the page) marks this page's tour finished;
 * Skip marks every page's. Both are sent as a beacon so they survive the
 * page closing.
 *
 * The dim is the spotlight's own huge shadow, so the hole has round corners.
 * Four clear strips around the hole catch taps on the dimmed part. A step
 * with tap on leaves the hole open: tapping the lit button does what it
 * does, and the tour moves on. With tap off, the ring covers the hole too.
 */
(function () {
  'use strict';

  var data = document.getElementById('giyaTour');
  if (!data) return;
  var cfg;
  try { cfg = JSON.parse(data.textContent); } catch (e) { return; }
  var steps = cfg.steps || [];
  if (!steps.length) return;

  var PAD = 6;              // room around the lit button
  var GAP = 12;             // between the button and the card
  var root, ring, card, strips = [], at = -1, target = null, active = false, ticker = null, lastFocus = null;
  var W = cfg.words || {};

  function shown(el) {
    if (!el) return false;
    var r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) return false;
    var s = getComputedStyle(el);
    return s.visibility !== 'hidden' && s.display !== 'none' && parseFloat(s.opacity || '1') > 0.05;
  }

  /* A step for one kind of screen only (a phone's slide bar). */
  function fits(s) {
    return !s.media || !window.matchMedia || window.matchMedia(s.media).matches;
  }

  /* Can this step show right now? A step about the whole screen always can. */
  function ready(s) {
    return fits(s) && (!s.target || !!find(s.target));
  }

  function find(sel) {
    if (!sel) return null;
    var list;
    try { list = document.querySelectorAll(sel); } catch (e) { return null; }
    for (var k = 0; k < list.length; k++) if (shown(list[k])) return list[k];
    return null;
  }

  function send(which) {
    var body = new FormData();
    body.append('_token', cfg.token);
    body.append('tour', which);
    try { if (navigator.sendBeacon && navigator.sendBeacon(cfg.url, body)) return; } catch (e) { /* fall back */ }
    try { fetch(cfg.url, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }); } catch (e) { /* best effort */ }
  }

  function el(tag, cls, parent) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (parent) parent.appendChild(n);
    return n;
  }

  function build() {
    root = el('div', 'gt-root', document.body);
    for (var k = 0; k < 4; k++) strips.push(el('div', 'gt-strip', root));
    ring = el('div', 'gt-ring', root);
    card = el('div', 'gt-card', root);
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'true');
    card.setAttribute('aria-labelledby', 'gtTitle');
    card.setAttribute('aria-describedby', 'gtBody');
    card.innerHTML =
      '<p class="gt-count"></p>' +
      '<h2 class="gt-title" id="gtTitle"></h2>' +
      '<p class="gt-body" id="gtBody"></p>' +
      '<p class="gt-hint"></p>' +
      '<div class="gt-actions">' +
        '<button type="button" class="gt-skip"></button>' +
        '<button type="button" class="btn btn-ghost btn-sm gt-back"></button>' +
        '<button type="button" class="btn btn-primary btn-sm gt-next"></button>' +
      '</div>';
    card.querySelector('.gt-skip').textContent = W.skip || 'Skip';
    card.querySelector('.gt-back').textContent = W.back || 'Back';
    card.querySelector('.gt-skip').addEventListener('click', skip);
    card.querySelector('.gt-back').addEventListener('click', function () { go(at - 1, -1); });
    card.querySelector('.gt-next').addEventListener('click', function () { go(at + 1, 1); });
  }

  /* The first step from n (going forward or back) whose button is on screen. */
  function go(n, dir) {
    while (n >= 0 && n < steps.length) {
      var s = steps[n];
      if (ready(s)) { show(n, s.target ? find(s.target) : null); return; }
      n += dir;
    }
    if (n >= steps.length) finish();
  }

  function visibleSteps() {
    var count = 0, index = 0;
    steps.forEach(function (s, k) {
      if (ready(s)) { count++; if (k <= at) index = count; }
    });
    return { n: index, total: count };
  }

  function show(n, t) {
    at = n;
    target = t;
    var s = steps[n];

    if (t) {
      var r = t.getBoundingClientRect();
      var off = r.top < 70 || r.bottom > window.innerHeight - 40;
      if (off && t.scrollIntoView) t.scrollIntoView({ block: 'center', inline: 'nearest' });
    }

    var c = visibleSteps();
    card.querySelector('.gt-count').textContent = (W.step || ':n / :total').replace(':n', c.n).replace(':total', c.total);
    card.querySelector('.gt-title').textContent = s.title;
    card.querySelector('.gt-body').textContent = s.body;
    var hint = card.querySelector('.gt-hint');
    hint.textContent = s.tap && t ? (W.tap || '') : '';
    hint.hidden = !(s.tap && t);
    card.querySelector('.gt-back').hidden = c.n <= 1;
    var last = !hasNext();
    card.querySelector('.gt-next').textContent = last ? (W.done || 'Done') : (W.next || 'Next');

    root.classList.toggle('is-open', !!(t && s.tap));
    place();
    card.querySelector('.gt-next').focus({ preventScroll: true });
  }

  function hasNext() {
    for (var k = at + 1; k < steps.length; k++) {
      if (ready(steps[k])) return true;
    }
    return false;
  }

  function box(n, x, y, w, h) {
    n.style.left = x + 'px'; n.style.top = y + 'px';
    n.style.width = Math.max(0, w) + 'px'; n.style.height = Math.max(0, h) + 'px';
  }

  function place() {
    if (!active) return;
    var vw = window.innerWidth, vh = window.innerHeight;
    var cw = Math.min(340, vw - 24);
    card.style.width = cw + 'px';
    var ch = card.offsetHeight;

    if (!target || !document.body.contains(target)) {
      // A step about the whole screen: dim it all, card in the middle.
      box(strips[0], 0, 0, vw, vh);
      for (var k = 1; k < 4; k++) box(strips[k], 0, 0, 0, 0);
      ring.hidden = true;
      root.classList.add('is-whole');
      card.style.left = Math.round((vw - cw) / 2) + 'px';
      card.style.top = Math.round(Math.max(12, (vh - ch) / 2)) + 'px';
      return;
    }

    root.classList.remove('is-whole');
    ring.hidden = false;
    var r = target.getBoundingClientRect();
    var x = Math.max(4, r.left - PAD), y = Math.max(4, r.top - PAD);
    var x2 = Math.min(vw - 4, r.right + PAD), y2 = Math.min(vh - 4, r.bottom + PAD);

    box(strips[0], 0, 0, vw, y);                 // above
    box(strips[1], 0, y2, vw, vh - y2);          // below
    box(strips[2], 0, y, x, y2 - y);             // left
    box(strips[3], x2, y, vw - x2, y2 - y);      // right
    box(ring, x, y, x2 - x, y2 - y);

    // The card under the button if it fits, else over it, else beside a
    // tall one (the list on a computer), else at the bottom.
    var top, left = null;
    if (vh - y2 >= ch + GAP + 8) top = y2 + GAP;
    else if (y >= ch + GAP + 8) top = y - GAP - ch;
    else if (vw - x2 >= cw + GAP + 12) { left = x2 + GAP; top = (y + y2) / 2 - ch / 2; }
    else if (x >= cw + GAP + 12) { left = x - GAP - cw; top = (y + y2) / 2 - ch / 2; }
    else top = vh - ch - 12;
    top = Math.min(top, vh - ch - 12);
    if (left === null) left = Math.min(Math.max(12, (x + x2) / 2 - cw / 2), vw - cw - 12);
    card.style.left = Math.round(left) + 'px';
    card.style.top = Math.round(Math.max(12, top)) + 'px';
  }

  var queued = false;
  function replace() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(function () { queued = false; place(); });
  }

  /* Tapping the lit button: it does its job, then the tour moves on. A link
     that leaves the page ends this page's tour. */
  function onTap(e) {
    if (!active || !target || !steps[at].tap || !target.contains(e.target)) return;
    var link = e.target.closest('a[href]');
    if (link && link.getAttribute('href').charAt(0) !== '#') { send(cfg.name); close(); return; }
    setTimeout(function () { if (active) go(at + 1, 1); }, 350);
  }

  function onKey(e) {
    if (!active) return;
    if (e.key === 'Escape') { e.preventDefault(); skip(); }
    else if (e.key === 'Tab') {
      // Keep the keyboard in the card (and on the lit button when it can be tapped).
      var stops = Array.prototype.slice.call(card.querySelectorAll('button:not([hidden])'));
      var i = stops.indexOf(document.activeElement);
      if (i === -1 || (e.shiftKey && i === 0) || (!e.shiftKey && i === stops.length - 1)) {
        e.preventDefault();
        (e.shiftKey ? stops[stops.length - 1] : stops[0]).focus();
      }
    }
  }

  function start() {
    if (active) return;
    active = true;
    lastFocus = document.activeElement;
    build();
    document.documentElement.classList.add('has-tour');
    document.addEventListener('click', onTap, true);
    document.addEventListener('keydown', onKey, true);
    window.addEventListener('resize', replace);
    window.addEventListener('scroll', replace, true);
    window.addEventListener('pointermove', replace, true);   // a slide bar being dragged
    ticker = setInterval(place, 500);   // panels and sheets that move on their own
    go(0, 1);
  }

  function close() {
    active = false;
    clearInterval(ticker);
    document.removeEventListener('click', onTap, true);
    document.removeEventListener('keydown', onKey, true);
    window.removeEventListener('resize', replace);
    window.removeEventListener('scroll', replace, true);
    window.removeEventListener('pointermove', replace, true);
    document.documentElement.classList.remove('has-tour');
    if (root) root.remove();
    if (lastFocus && lastFocus.focus) { try { lastFocus.focus({ preventScroll: true }); } catch (e) { /* gone */ } }
  }

  function finish() { send(cfg.name); close(); }
  function skip() { send('all'); close(); }

  /* Wait for the page to be ready (the map has its loading screen). */
  function whenReady(fn) {
    if (!cfg.waitFor) { setTimeout(fn, 600); return; }
    var tries = 0;
    (function poll() {
      if (document.querySelector(cfg.waitFor) || ++tries > 50) { setTimeout(fn, 500); return; }
      setTimeout(poll, 300);
    })();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { whenReady(start); });
  else whenReady(start);
})();
