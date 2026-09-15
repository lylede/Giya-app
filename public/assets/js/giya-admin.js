/**
 * GIYA admin - the collapsible sidebar.
 *
 * Two widths: the full menu and a rail of icons. Which one you get is
 * remembered, because an admin who wants the rail wants it on every page and
 * not just the one where they pressed the button.
 *
 * The class goes on the layout rather than the sidebar: the width is a custom
 * property read by the sidebar and by anything else that has to line up with
 * it, so collapsing is one class and nothing has to be kept in step by hand.
 */
(function (window, document) {
    'use strict';

    var layout = document.querySelector('.admin-layout');
    var button = document.getElementById('adminRail');

    if (!layout || !button) return;

    var KEY = 'giya.admin.rail';

    function apply(railed) {
        layout.classList.toggle('is-railed', railed);
        button.setAttribute('aria-expanded', String(!railed));
        button.setAttribute('aria-label', railed ? 'Expand the menu' : 'Collapse the menu');
    }

    /* Storage can be unavailable - a private window, or site data blocked -
       and an admin panel that will not load because of a preference is worse
       than one that forgets it. */
    function remembered() {
        try {
            return window.localStorage.getItem(KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function remember(railed) {
        try {
            window.localStorage.setItem(KEY, railed ? '1' : '0');
        } catch (e) { /* forgetting is fine; failing is not */ }
    }

    apply(remembered());

    button.addEventListener('click', function () {
        var railed = !layout.classList.contains('is-railed');
        apply(railed);
        remember(railed);
    });
})(window, document);
