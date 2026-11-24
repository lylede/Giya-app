/*
 * "Change stops" on a trip: add a church (as the next stop or at the end),
 * remove or swap a stop not yet visited, and move the stops still to come
 * up or down. Each change is saved at once; Done reloads the trip so the
 * map, the candles and the current stop all follow the new route.
 *
 * Up and down buttons rather than dragging: they work the same with a
 * finger, a mouse and a keyboard, and on a small phone screen.
 *
 * Free accounts see the Premium offer in the same window instead.
 */
(function () {
  'use strict';

  var cfg = window.GIYA_EDIT_STOPS;
  var modal = document.getElementById('editStopsModal');
  var openBtn = document.getElementById('editStopsBtn');
  if (!cfg || !modal || !openBtn) return;

  var doneBtn = document.getElementById('esDone');
  var changed = false;

  /* On a phone the window fills the screen. The keyboard shrinks only the
     visible part of the page, so the window follows that part: Done and the
     search results stay above the keyboard instead of behind it. */
  var vv = window.visualViewport;
  function fit() {
    if (modal.hidden || !vv) return;
    modal.style.top = vv.offsetTop + 'px';
    modal.style.height = vv.height + 'px';
    modal.style.bottom = 'auto';
  }
  if (vv) { vv.addEventListener('resize', fit); vv.addEventListener('scroll', fit); }

  // A mouse and keyboard can start typing at once; on a phone the keyboard
  // would cover the stops before they are even seen.
  var finePointer = window.matchMedia && window.matchMedia('(pointer: fine)').matches;

  function open() {
    modal.hidden = false;
    document.body.classList.add('has-plan-modal');
    fit();
    if (cfg.can) { render(); if (finePointer) setTimeout(function () { search.focus(); }, 50); }
  }
  function close() {
    modal.hidden = true;
    modal.style.top = modal.style.height = modal.style.bottom = '';
    document.body.classList.remove('has-plan-modal');
    if (changed) location.reload();
  }
  openBtn.addEventListener('click', open);
  doneBtn.addEventListener('click', close);
  modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });

  if (!cfg.can) return;

  var T = cfg.text;
  var stops = cfg.stops.slice();
  var list = document.getElementById('esList');
  var search = document.getElementById('esSearch');
  var results = document.getElementById('esResults');
  var errorBox = document.getElementById('esError');
  var swapBar = document.getElementById('esSwapping');
  var swapText = document.getElementById('esSwapText');
  var swapping = null;   // the stop being swapped, or null
  var busy = false;
  var box = modal.querySelector('.es-box');
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function todo() { return stops.filter(function (s) { return !s.visited; }); }
  function stopUrl(id) { return cfg.urls.stop.replace(/\/0$/, '/' + id); }

  function showError(msg) {
    errorBox.textContent = msg || '';
    errorBox.hidden = !msg;
  }

  function send(url, method, body) {
    if (busy) return Promise.resolve(false);
    busy = true;
    showError('');
    modal.classList.add('is-busy');
    return fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
      body: body ? JSON.stringify(body) : null,
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (r.ok && d.ok) {
          stops = d.stops;
          changed = true;
          if (d.finished) { close(); return true; }
          render();
          renderResults(lastItems);
          return true;
        }
        showError(d.message || T.fail);
        return false;
      });
    }).catch(function () {
      showError(T.fail);
      return false;
    }).finally(function () {
      busy = false;
      modal.classList.remove('is-busy');
    });
  }

  function render() {
    var rows = todo();
    if (!rows.length) {
      list.innerHTML = '<li class="es-empty">' + esc(T.allVisited) + '</li>';
      return;
    }
    list.innerHTML = rows.map(function (s, i) {
      return '<li class="es-row' + (swapping === s.id ? ' is-swapping' : '') + '" data-id="' + s.id + '">' +
        '<button type="button" class="es-grip" aria-label="' + esc(T.drag.replace(':name', s.name)) + '" title="' + esc(T.dragHint) + '">' +
          '<svg viewBox="0 0 10 16" aria-hidden="true" focusable="false"><circle cx="2.5" cy="2.5" r="1.5"/><circle cx="7.5" cy="2.5" r="1.5"/><circle cx="2.5" cy="8" r="1.5"/><circle cx="7.5" cy="8" r="1.5"/><circle cx="2.5" cy="13.5" r="1.5"/><circle cx="7.5" cy="13.5" r="1.5"/></svg>' +
        '</button>' +
        '<span class="es-num">' + (i + 1) + '</span>' +
        '<span class="es-name"><b>' + esc(s.name) + '</b><small>' + esc(s.location) + '</small></span>' +
        '<span class="es-actions">' +
          '<button type="button" class="es-text" data-act="swap" aria-label="' + esc(T.swap) + '"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span class="es-btn-label">' + esc(T.swap) + '</span></button>' +
          '<button type="button" class="es-text es-danger" data-act="remove" aria-label="' + esc(T.remove) + '"><i class="bi bi-trash3" aria-hidden="true"></i><span class="es-btn-label">' + esc(T.remove) + '</span></button>' +
        '</span>' +
      '</li>';
    }).join('');
  }

  list.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-act]');
    if (!btn) return;
    var id = Number(btn.closest('[data-id]').dataset.id);
    var act = btn.dataset.act;

    if (act === 'remove') {
      if (swapping === id) setSwap(null);
      send(stopUrl(id), 'DELETE');
    } else if (act === 'swap') {
      setSwap(swapping === id ? null : id);
    }
  });

  /* ---- Dragging to reorder (the same feel as the Visita Iglesia planner) ----
     Pointer events serve a finger, a mouse and a pen alike. The stop lifts and
     follows the finger, the others slide aside to make room, and on letting
     go it glides into its place and the new order is saved. The handle has
     touch-action: none so dragging it does not scroll the window; near the top
     or bottom the window scrolls along. On a keyboard the handle moves its stop
     with the up and down arrow keys. */
  var SLIDE = 'transform .2s cubic-bezier(.2, .8, .2, 1)';
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var drag = null;

  function saveOrder(ids) {
    var before = todo().map(function (s) { return s.id; });
    if (ids.join() === before.join()) { render(); return; }
    // Show the new order at once; the saved one replaces it (or the old one, on failure).
    var byId = {};
    stops.forEach(function (s) { byId[s.id] = s; });
    stops = stops.filter(function (s) { return s.visited; }).concat(ids.map(function (id) { return byId[id]; }));
    render();
    send(cfg.urls.order, 'POST', { stops: ids }).then(function (ok) {
      if (!ok) {
        stops = stops.filter(function (s) { return s.visited; }).concat(before.map(function (id) { return byId[id]; }));
        render();
      }
    });
  }

  list.addEventListener('pointerdown', function (e) {
    var grip = e.target.closest('.es-grip');
    if (!grip || busy || (e.pointerType === 'mouse' && e.button !== 0)) return;
    e.preventDefault();
    var row = grip.closest('.es-row');
    var rows = Array.prototype.slice.call(list.querySelectorAll('.es-row'));
    if (rows.length < 2) return;
    var rects = rows.map(function (r) { return r.getBoundingClientRect(); });
    var from = rows.indexOf(row);
    drag = { row: row, pointer: e.pointerId, rows: rows, rects: rects, from: from, to: from,
             gap: Math.max(0, rects[1].top - rects[0].bottom), y0: e.clientY, s0: box.scrollTop };
    grip.setPointerCapture(e.pointerId);
    rows.forEach(function (r) { r.style.transition = (r === row || still) ? 'none' : SLIDE; });
    row.classList.add('is-dragging');
    list.classList.add('is-sorting');
  });

  list.addEventListener('pointermove', function (e) {
    var d = drag;
    if (!d || e.pointerId !== d.pointer) return;
    e.preventDefault();
    var dy = e.clientY - d.y0 + (box.scrollTop - d.s0);
    d.row.style.transform = 'translateY(' + dy + 'px) scale(1.02)';

    var mid = d.rects[d.from].top + d.rects[d.from].height / 2 + dy;
    var to = 0;
    d.rows.forEach(function (r, i) { if (i !== d.from && d.rects[i].top + d.rects[i].height / 2 < mid) to++; });
    d.to = to;

    var step = d.rects[d.from].height + d.gap;
    d.rows.forEach(function (r, i) {
      if (i === d.from) return;
      var shift = 0;
      if (d.from < to && i > d.from && i <= to) shift = -step;
      if (d.from > to && i >= to && i < d.from) shift = step;
      r.style.transform = shift ? 'translateY(' + shift + 'px)' : '';
    });

    var area = box.getBoundingClientRect(), edge = 60;
    if (e.clientY < area.top + edge) box.scrollTop -= 12;
    else if (e.clientY > area.bottom - edge) box.scrollTop += 12;
  });

  function finishDrag(e) {
    var d = drag;
    if (!d || (e && e.pointerId !== d.pointer)) return;
    drag = null;
    list.classList.remove('is-sorting');

    var land = 0, i;
    if (d.to > d.from) for (i = d.from + 1; i <= d.to; i++) land += d.rects[i].height + d.gap;
    if (d.to < d.from) for (i = d.to; i < d.from; i++) land -= d.rects[i].height + d.gap;
    d.row.style.transition = still ? 'none' : SLIDE;
    d.row.style.transform = 'translateY(' + land + 'px)';

    setTimeout(function () {
      d.row.classList.remove('is-dragging');
      d.rows.forEach(function (r) { r.style.transition = ''; r.style.transform = ''; });
      var ids = d.rows.map(function (r) { return Number(r.dataset.id); });
      var moved = ids.splice(d.from, 1)[0];
      ids.splice(d.to, 0, moved);
      saveOrder(ids);
    }, still ? 0 : 200);
  }
  list.addEventListener('pointerup', finishDrag);
  list.addEventListener('pointercancel', finishDrag);

  list.addEventListener('keydown', function (e) {
    var grip = e.target.closest('.es-grip');
    if (!grip || busy || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
    e.preventDefault();
    var id = Number(grip.closest('.es-row').dataset.id);
    var ids = todo().map(function (s) { return s.id; });
    var i = ids.indexOf(id), j = i + (e.key === 'ArrowUp' ? -1 : 1);
    if (j < 0 || j >= ids.length) return;
    ids[i] = ids[j]; ids[j] = id;
    saveOrder(ids);
    setTimeout(function () {
      var again = list.querySelector('.es-row[data-id="' + id + '"] .es-grip');
      if (again) again.focus();
    }, 0);
  });

  function setSwap(id) {
    swapping = id;
    var s = stops.filter(function (x) { return x.id === id; })[0];
    swapBar.hidden = !s;
    swapText.textContent = s ? T.swapFor.replace(':name', s.name) : '';
    render();
    renderResults(lastItems);
    if (s) { box.scrollTop = 0; search.focus(); }
  }
  document.getElementById('esCancelSwap').addEventListener('click', function () { setSwap(null); });

  /* Search: GIYA's own church search, the same one as the top bar. */
  var lastItems = [];
  var timer = null;
  var asked = 0;
  /* While typing, the list of stops steps aside so the results sit right
     under the search box, above a phone's keyboard. */
  function searching() { box.classList.toggle('is-searching', search.value.trim() !== ''); }
  search.addEventListener('focus', function () { box.scrollTop = 0; });
  search.addEventListener('input', function () {
    searching();
    clearTimeout(timer);
    var q = search.value.trim();
    if (q.length < 2) { lastItems = []; results.innerHTML = ''; return; }
    timer = setTimeout(function () {
      var mine = ++asked;
      fetch(cfg.urls.search + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (mine !== asked) return;   // an older search answering late
          lastItems = d.items || [];
          renderResults(lastItems);
        })
        .catch(function () { showError(T.fail); });
    }, 250);
  });

  function renderResults(items) {
    if (!search.value.trim()) { results.innerHTML = ''; return; }
    var onTrip = {};
    stops.forEach(function (s) { onTrip[s.church] = true; });
    var shown = (items || []).filter(function (c) { return !onTrip[c.id]; });
    if (!shown.length) { results.innerHTML = '<li class="es-empty">' + esc(T.none) + '</li>'; return; }
    results.innerHTML = shown.map(function (c) {
      var buttons = swapping
        ? '<button type="button" class="btn btn-primary btn-sm" data-pick="swap">' + esc(T.swapIn) + '</button>'
        : '<button type="button" class="btn btn-primary btn-sm" data-pick="next">' + esc(T.next) + '</button>' +
          '<button type="button" class="btn btn-ghost btn-sm" data-pick="end">' + esc(T.end) + '</button>';
      return '<li class="es-result" data-church="' + c.id + '">' +
        '<span class="es-name"><b>' + esc(c.name) + '</b><small>' + esc(c.location || '') + '</small></span>' +
        '<span class="es-pick">' + buttons + '</span></li>';
    }).join('');
  }

  results.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-pick]');
    if (!btn) return;
    var church = Number(btn.closest('[data-church]').dataset.church);
    var pick = btn.dataset.pick;
    var done = pick === 'swap'
      ? send(stopUrl(swapping) + '/swap', 'POST', { church_id: church })
      : send(cfg.urls.add, 'POST', { church_id: church, position: pick });
    done.then(function (ok) {
      if (!ok) return;
      if (pick === 'swap') setSwap(null);
      search.value = '';
      lastItems = [];
      results.innerHTML = '';
      searching();
      search.blur();   // the keyboard goes away and the new list shows
    });
  });
})();
