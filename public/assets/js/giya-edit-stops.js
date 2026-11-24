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

  function open() {
    modal.hidden = false;
    document.body.classList.add('has-plan-modal');
    if (cfg.can) { render(); setTimeout(function () { search.focus(); }, 50); }
  }
  function close() {
    modal.hidden = true;
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
        '<span class="es-num">' + (i + 1) + '</span>' +
        '<span class="es-name"><b>' + esc(s.name) + '</b><small>' + esc(s.location) + '</small></span>' +
        '<span class="es-actions">' +
          '<button type="button" class="es-icon" data-act="up" aria-label="' + esc(T.up) + '" title="' + esc(T.up) + '"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up"></i></button>' +
          '<button type="button" class="es-icon" data-act="down" aria-label="' + esc(T.down) + '" title="' + esc(T.down) + '"' + (i === rows.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-up es-flip"></i></button>' +
          '<button type="button" class="es-text" data-act="swap">' + esc(T.swap) + '</button>' +
          '<button type="button" class="es-text es-danger" data-act="remove">' + esc(T.remove) + '</button>' +
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
    } else {
      var ids = todo().map(function (s) { return s.id; });
      var i = ids.indexOf(id), j = act === 'up' ? i - 1 : i + 1;
      if (j < 0 || j >= ids.length) return;
      ids[i] = ids[j]; ids[j] = id;
      send(cfg.urls.order, 'POST', { stops: ids });
    }
  });

  function setSwap(id) {
    swapping = id;
    var s = stops.filter(function (x) { return x.id === id; })[0];
    swapBar.hidden = !s;
    swapText.textContent = s ? T.swapFor.replace(':name', s.name) : '';
    render();
    renderResults(lastItems);
    if (s) search.focus();
  }
  document.getElementById('esCancelSwap').addEventListener('click', function () { setSwap(null); });

  /* Search: GIYA's own church search, the same one as the top bar. */
  var lastItems = [];
  var timer = null;
  var asked = 0;
  search.addEventListener('input', function () {
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
    });
  });
})();
