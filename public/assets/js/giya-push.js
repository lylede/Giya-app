/*
 * Phone notifications (Web Push), the browser side.
 *
 * The server half is App\Support\WebPush; public/sw.js shows what arrives.
 * This file asks the browser for permission, subscribes it to GIYA's key and
 * hands the subscription to the server, and drives the Profile switch and the
 * small "Get this on your phone too?" prompt after Notify me on a church.
 *
 * Permission can only be asked from a tap (iPhone refuses otherwise), so
 * nothing here asks on page load.
 *
 * On iPhone, push exists only in GIYA added to the Home Screen and opened from
 * its icon (iOS 16.4+). In Safari itself the switch is replaced by the steps.
 */
(function () {
  'use strict';

  var keyMeta = document.querySelector('meta[name="giya-push-key"]');
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var KEY = keyMeta ? keyMeta.content : '';
  var T = window.GiyaPushText || {};

  function t(k, fallback) { return T[k] || fallback || k; }

  var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var supported = !!KEY && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

  function keyBytes(b64) {
    var pad = '='.repeat((4 - b64.length % 4) % 4);
    var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '',
      },
      body: JSON.stringify(body || {}),
    }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function registration() {
    return navigator.serviceWorker.getRegistration('/').then(function (reg) {
      return reg || navigator.serviceWorker.register('/sw.js', { scope: '/' });
    }).then(function () { return navigator.serviceWorker.ready; });
  }

  /** 'unsupported' | 'ios-install' | 'blocked' | 'on' | 'off' */
  function state() {
    if (!KEY) return Promise.resolve('unsupported');
    if (!supported) return Promise.resolve(isIOS && !standalone ? 'ios-install' : 'unsupported');
    if (Notification.permission === 'denied') return Promise.resolve('blocked');
    return registration()
      .then(function (reg) { return reg.pushManager.getSubscription(); })
      .then(function (sub) {
        if (sub && Notification.permission === 'granted') {
          // Tell the server again: it may have dropped it, or this is a new sign-in.
          post('/push/subscribe', sub.toJSON()).catch(function () {});
          return 'on';
        }
        return 'off';
      })
      .catch(function () { return 'off'; });
  }

  /** Must run inside a tap. Resolves to the new state. */
  function enable() {
    if (!supported) return state();
    return Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') return perm === 'denied' ? 'blocked' : 'off';
      return registration()
        .then(function (reg) {
          return reg.pushManager.getSubscription().then(function (existing) {
            return existing || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(KEY) });
          });
        })
        .then(function (sub) { return post('/push/subscribe', sub.toJSON()); })
        .then(function () { return 'on'; });
    });
  }

  function disable() {
    return registration()
      .then(function (reg) { return reg.pushManager.getSubscription(); })
      .then(function (sub) {
        if (!sub) return 'off';
        var endpoint = sub.endpoint;
        return sub.unsubscribe().then(function () {
          return post('/push/unsubscribe', { endpoint: endpoint }).catch(function () {});
        }).then(function () { return 'off'; });
      });
  }

  function test() { return post('/push/test'); }

  /* ------------------------------------------------------- Profile switch */

  function bindProfile(box) {
    var input = box.querySelector('[data-push-switch]');
    var status = box.querySelector('[data-push-status]');
    var guide = box.querySelector('[data-push-ios]');
    var row = box.querySelector('[data-push-row]');
    var testBtn = box.querySelector('[data-push-test]');

    // Not one of the auto-saved preferences: keep the form's autosave out of it.
    ['change', 'input'].forEach(function (ev) {
      input.addEventListener(ev, function (e) { e.stopPropagation(); });
    });

    function show(s) {
      box.hidden = false;
      box.dataset.state = s;
      guide.hidden = s !== 'ios-install';
      row.hidden = s === 'ios-install' || s === 'unsupported';
      input.checked = s === 'on';
      input.disabled = s === 'blocked';
      testBtn.hidden = s !== 'on';
      status.textContent = {
        on: t('on'), off: t('off'), blocked: t('blocked'), unsupported: t('unsupported'), 'ios-install': '',
      }[s] || '';
    }

    input.addEventListener('change', function () {
      var wantOn = input.checked;
      input.disabled = true;
      status.textContent = wantOn ? t('turning_on') : '';
      (wantOn ? enable() : disable())
        .then(show)
        .catch(function () { status.textContent = t('failed'); input.checked = !wantOn; })
        .then(function () { if (box.dataset.state !== 'blocked') input.disabled = false; });
    });

    testBtn.addEventListener('click', function () {
      testBtn.disabled = true;
      test()
        .then(function (r) { status.textContent = r.message || (r.ok ? t('test_sent') : t('test_failed')); })
        .catch(function () { status.textContent = t('test_failed'); })
        .then(function () { setTimeout(function () { testBtn.disabled = false; }, 4000); });
    });

    state().then(show);
  }

  /* ----------------------------- prompt after Notify me on a church page */

  var asked = false;

  function offer(anchor) {
    if (asked || !anchor) return;
    state().then(function (s) {
      if (s !== 'off' && s !== 'ios-install') return;
      if (s === 'off' && Notification.permission !== 'default') return;
      asked = true;

      var box = document.createElement('div');
      box.className = 'giya-push-offer';
      box.setAttribute('role', 'status');

      var text = document.createElement('p');
      text.textContent = s === 'ios-install' ? t('ios_title') : t('ask');
      box.appendChild(text);

      if (s === 'ios-install') {
        var steps = document.createElement('p');
        steps.className = 'giya-push-offer-steps';
        steps.textContent = t('ios_steps');
        box.appendChild(steps);
      }

      var actions = document.createElement('div');
      actions.className = 'giya-push-offer-actions';
      if (s === 'off') {
        var yes = document.createElement('button');
        yes.type = 'button';
        yes.className = 'btn btn-primary btn-sm';
        yes.textContent = t('ask_yes');
        yes.addEventListener('click', function () {
          yes.disabled = true;
          enable().then(function (r) {
            text.textContent = r === 'on' ? t('on') : (r === 'blocked' ? t('blocked') : t('off'));
            actions.remove();
            setTimeout(function () { box.remove(); }, 3500);
          }).catch(function () { text.textContent = t('failed'); yes.disabled = false; });
        });
        actions.appendChild(yes);
      }
      var no = document.createElement('button');
      no.type = 'button';
      no.className = 'btn btn-outline btn-sm';
      no.textContent = t('ask_no');
      no.addEventListener('click', function () { box.remove(); });
      actions.appendChild(no);
      box.appendChild(actions);

      anchor.insertAdjacentElement('afterend', box);
    });
  }

  window.GiyaPush = { state: state, enable: enable, disable: disable, test: test, offer: offer };

  function init() {
    var box = document.querySelector('[data-giya-push]');
    if (box) { bindProfile(box); return; }

    /* Already allowed: check the subscription on any page, so a phone whose
       push address changed (the push service rotates them) or whose row the
       server dropped is registered again without visiting Profile. Once a
       day is plenty. */
    if (supported && Notification.permission === 'granted') {
      try {
        var last = Number(localStorage.getItem('giya-push-checked') || 0);
        if (Date.now() - last < 86400000) return;
        localStorage.setItem('giya-push-checked', String(Date.now()));
      } catch (e) { /* private mode: check every time */ }
      state();
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
