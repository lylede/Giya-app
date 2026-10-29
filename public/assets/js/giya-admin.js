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

/**
 * Phones: the admin menu is a drawer.
 *
 * Stacked above the page, the full menu filled the first screen of every
 * admin page, so the page itself started below the fold. On a phone it now
 * slides in from the left when the menu button is pressed, and closes on the
 * dimmed backdrop, Escape, or choosing a page.
 */
(function (window, document) {
    'use strict';

    var layout = document.querySelector('.admin-layout');
    var button = document.getElementById('adminMenu');
    var scrim  = document.getElementById('adminScrim');
    var side   = document.getElementById('adminSidebar');
    if (!layout || !button || !scrim || !side) return;

    function set(open) {
        layout.classList.toggle('is-drawer-open', open);
        scrim.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Close the menu' : 'Open the menu');
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            var first = side.querySelector('a, button');
            if (first) first.focus();
        }
    }

    button.addEventListener('click', function () { set(!layout.classList.contains('is-drawer-open')); });
    scrim.addEventListener('click', function () { set(false); });
    side.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && layout.classList.contains('is-drawer-open')) { set(false); button.focus(); }
    });
    // Turning a tablet to landscape past the breakpoint leaves no drawer to close.
    window.matchMedia('(min-width: 769px)').addEventListener('change', function (m) { if (m.matches) set(false); });
})(window, document);
