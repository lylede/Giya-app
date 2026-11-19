/*
 * "Add GIYA to Home Screen".
 *
 * Android (Chrome, Edge, Samsung Internet) and desktop Chrome fire
 * beforeinstallprompt when GIYA can be installed; the button holds on to it and
 * opens the browser's own install dialog. iPhone and iPad have no such event:
 * a website cannot add itself, so the button opens the steps instead (Share,
 * then Add to Home Screen). A browser that offers neither on Android gets the
 * menu steps.
 *
 * The Profile > Preferences card ([data-giya-install-card]) and its button
 * stay hidden until one of those is possible, and hide again once GIYA is
 * running from the Home Screen or was installed.
 */
(function () {
  'use strict';

  var ua = navigator.userAgent;
  var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isAndroid = /Android/i.test(ua);
  var installed = function () {
    return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  };

  var deferred = null;   // the saved beforeinstallprompt event

  function buttons() { return document.querySelectorAll('[data-giya-install]'); }
  function cards() { return document.querySelectorAll('[data-giya-install-card]'); }

  function refresh() {
    var show = !installed() && (deferred !== null || isIOS || isAndroid);
    buttons().forEach(function (b) { b.hidden = !show; });
    cards().forEach(function (c) { c.hidden = !show; });
  }

  function showSteps() {
    var modal = document.getElementById('installModal');
    if (!modal) return;
    modal.querySelectorAll('[data-install-steps]').forEach(function (el) {
      el.hidden = el.dataset.installSteps !== (isIOS ? 'ios' : 'android');
    });
    if (window.GiyaUI) window.GiyaUI.Modal.open('installModal');
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();   // our button asks, not the browser's mini bar
    deferred = e;
    refresh();
  });

  window.addEventListener('appinstalled', function () {
    deferred = null;
    try { localStorage.setItem('giya-installed', '1'); } catch (err) { /* private mode */ }
    buttons().forEach(function (b) { b.hidden = true; });
    cards().forEach(function (c) { c.hidden = true; });
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-giya-install]');
    if (!btn) return;
    e.preventDefault();

    if (deferred) {
      var prompt = deferred;
      deferred = null;   // a prompt can be used once
      prompt.prompt();
      prompt.userChoice.then(function () { refresh(); }).catch(refresh);
      return;
    }
    showSteps();
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh);
  else refresh();
})();
