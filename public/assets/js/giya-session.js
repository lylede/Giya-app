/*
 * Keeping a page usable after a long pause, and the admin's idle sign-out.
 *
 * 1. A page left open for hours (a phone locked on the bus to the next
 *    church) holds an old form token, so its next tap is refused with 419.
 *    Every same-origin request goes through the wrapper below: on 419 it asks
 *    /session/refresh for a fresh token and repeats the request once, so
 *    "Mark visited" still counts. If the devotee is no longer signed in
 *    (401, or the refresh says so), they go to sign in and come back to the
 *    same page afterwards.
 *
 * 2. Admin pages only (<meta name="giya-admin-idle">): after 29 minutes
 *    without a click or key, a "Still there?" box counts down the last
 *    minute; at 30 the page goes to sign in. While someone is working on a
 *    page (typing a long form makes no requests), a keep-alive tells the
 *    server every few minutes, so the server's 30-minute rule
 *    (AdminIdleTimeout) never cuts off someone who is busy. Activity in any
 *    admin tab counts for all of them.
 *
 * Loaded in <head>, before the other scripts, so it wraps their requests.
 */
(function () {
  'use strict';

  var ORIGIN = location.origin;
  var nativeFetch = window.fetch ? window.fetch.bind(window) : null;

  function sameOrigin(url) {
    try { return new URL(url, location.href).origin === ORIGIN; } catch (e) { return false; }
  }

  function tokenMeta() { return document.querySelector('meta[name="csrf-token"]'); }

  function goSignIn(loginUrl) {
    location.href = loginUrl || '/login';
  }

  function refresh() {
    return nativeFetch('/session/refresh?back=' + encodeURIComponent(location.href), {
      headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store',
    }).then(function (r) { return r.json(); });
  }

  /* The same request with the new token, in the header and in the body. */
  function withToken(init, token) {
    var next = Object.assign({}, init || {});
    var headers = new Headers(next.headers || {});
    headers.set('X-CSRF-TOKEN', token);
    next.headers = headers;
    var body = next.body;
    if (body instanceof FormData || body instanceof URLSearchParams) {
      if (body.has('_token')) body.set('_token', token);
    } else if (typeof body === 'string' && body.indexOf('"_token"') !== -1) {
      try { var o = JSON.parse(body); o._token = token; next.body = JSON.stringify(o); } catch (e) { /* not JSON */ }
    }
    return next;
  }

  if (nativeFetch) {
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      return nativeFetch(input, init).then(function (res) {
        if ((res.status !== 419 && res.status !== 401) || !sameOrigin(url) || (init && init.__giyaRetry)) return res;
        if (typeof input !== 'string' && !(input instanceof URL)) return res;   // a Request object cannot be re-sent safely

        return refresh().then(function (s) {
          if (!s.signed_in) { goSignIn(s.login_url); return res; }
          var m = tokenMeta(); if (m) m.content = s.token;
          document.querySelectorAll('input[name="_token"]').forEach(function (i) { i.value = s.token; });
          if (res.status === 401) return res;   // signed in again by the cookie: the page itself is fine
          var again = withToken(init, s.token);
          again.__giyaRetry = true;
          return nativeFetch(input, again);
        }).catch(function () { return res; });
      });
    };
  }

  /* Livewire forms (sign in, register, profile): reload instead of its "expired" dialog. */
  document.addEventListener('livewire:init', function () {
    if (!window.Livewire || !window.Livewire.hook) return;
    window.Livewire.hook('request', function (ctx) {
      if (!ctx || !ctx.fail) return;
      ctx.fail(function (f) {
        if (f && f.status === 419) { if (f.preventDefault) f.preventDefault(); location.reload(); }
      });
    });
  });

  /* ---------------- admin idle sign-out ---------------- */
  var idleMeta = document.querySelector('meta[name="giya-admin-idle"]');
  if (!idleMeta || !nativeFetch) return;

  var LIMIT = (Number(idleMeta.content) || 1800) * 1000;
  var WARN = 60 * 1000;
  var PING_EVERY = 5 * 60 * 1000;
  var KEY = 'giya-admin-active';
  var lastLocal = Date.now();
  var lastPing = Date.now();
  var shown = false;

  function lastActive() {
    var shared = 0;
    try { shared = Number(localStorage.getItem(KEY) || 0); } catch (e) { /* private mode */ }
    return Math.max(lastLocal, shared);
  }
  function touch() {
    lastLocal = Date.now();
    try { localStorage.setItem(KEY, String(lastLocal)); } catch (e) { /* private mode */ }
    if (shown) hideWarning();
  }
  ['click', 'keydown', 'input', 'touchstart', 'wheel'].forEach(function (ev) {
    document.addEventListener(ev, touch, { passive: true, capture: true });
  });
  touch();

  function ping() {
    lastPing = Date.now();
    var m = tokenMeta();
    return nativeFetch('/admin/keep-alive', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': m ? m.content : '' },
    }).then(function (r) {
      if (r.status === 401 || r.status === 419) location.reload();   // the server already signed them out
    }).catch(function () { /* offline: try again next round */ });
  }

  var box = null, countdown = null;
  function showWarning() {
    shown = true;
    if (!box) {
      box = document.createElement('div');
      box.className = 'admin-idle';
      box.setAttribute('role', 'alertdialog');
      box.setAttribute('aria-labelledby', 'adminIdleTitle');
      box.innerHTML =
        '<div class="admin-idle-card">' +
          '<p class="admin-idle-title" id="adminIdleTitle">Still there?</p>' +
          '<p class="admin-idle-text">For security, you will be signed out in <b data-idle-sec>60</b> seconds.</p>' +
          '<div class="admin-idle-actions">' +
            '<button type="button" class="btn btn-primary" data-idle-stay>Stay signed in</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(box);
      box.querySelector('[data-idle-stay]').addEventListener('click', function () { touch(); ping(); });
    }
    box.hidden = false;
    box.querySelector('[data-idle-stay]').focus();
  }
  function hideWarning() {
    shown = false;
    if (box) box.hidden = true;
  }

  var signingOut = false;
  function signOut() {
    if (signingOut) return;
    // Just woken from sleep with no connection yet: a form sent now fails and
    // leaves the browser's own error on screen. Wait for the network.
    if (navigator.onLine === false) {
      window.addEventListener('online', signOut, { once: true });
      return;
    }
    signingOut = true;
    var m = tokenMeta();
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = '/admin/idle-sign-out';
    f.innerHTML = '<input type="hidden" name="_token"><input type="hidden" name="back">';
    f.querySelector('[name="_token"]').value = m ? m.content : '';
    f.querySelector('[name="back"]').value = location.href;
    document.body.appendChild(f);
    f.submit();
  }

  setInterval(function () {
    var idle = Date.now() - lastActive();
    // Someone is working here: keep the server's clock in step.
    if (idle < 60 * 1000 && Date.now() - lastPing > PING_EVERY) ping();

    if (idle >= LIMIT) {
      signOut();
      return;
    }
    if (idle >= LIMIT - WARN) {
      if (!shown) showWarning();
      var left = Math.max(0, Math.ceil((LIMIT - idle) / 1000));
      var sec = box && box.querySelector('[data-idle-sec]');
      if (sec) sec.textContent = left;
    } else if (shown) {
      hideWarning();   // activity in another admin tab
    }
  }, 1000);
})();
