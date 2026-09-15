/**
 * GIYA - notification panel.
 *
 * The bell loads once at startup and then checks back every ninety seconds
 * while the tab is visible. It used to fetch only on first open, which meant
 * the count was a snapshot taken when the HTML was rendered: anything posted
 * while the devotee sat on a page appeared only after they navigated.
 */
(function (window, document) {
    'use strict';

    var bell = document.getElementById('navBell');
    if (!bell) return;                       // guest, or admin layout

    var panel = document.getElementById('notifPanel');
    var body = document.getElementById('notifBody');
    var dot = document.getElementById('navBellDot');
    var loaded = false;

    function token() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function escapeHtml(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function render(data) {
        if (!data.items || !data.items.length) {
            body.innerHTML = '<p class="notif-empty">Nothing yet. ' +
                'Schedules, replies to your reviews and pilgrimage reminders appear here.</p>';
            return;
        }

        body.innerHTML = data.items.map(function (n) {
            var inner =
                '<span class="notif-icon"><i class="bi bi-' + escapeHtml(n.icon) + '"></i></span>' +
                '<span class="notif-text">' +
                    '<span class="notif-title">' + escapeHtml(n.title) + '</span>' +
                    (n.message ? '<span class="notif-msg">' + escapeHtml(n.message) + '</span>' : '') +
                    '<span class="notif-when">' + escapeHtml(n.when || '') + '</span>' +
                '</span>';

            return n.url
                ? '<a class="notif-item' + (n.read ? '' : ' is-unread') + '" href="' +
                      escapeHtml(n.url) + '" data-notif="' + n.id + '">' + inner + '</a>'
                : '<div class="notif-item' + (n.read ? '' : ' is-unread') + '" data-notif="' + n.id + '">' +
                      inner + '</div>';
        }).join('');
    }

    function badge(count) {
        if (!dot) return;
        dot.textContent = count > 9 ? '9+' : count;
        dot.classList.toggle('is-hidden', count === 0);
    }

    function load(quiet) {
        return fetch('/notifications', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                /* The count always updates. The list only redraws when the
                   panel is shut - redrawing under someone who is reading it
                   moves the row they were about to press. */
                badge(data.unread || 0);

                if (!panel.classList.contains('is-open')) render(data);

                loaded = true;
            })
            .catch(function () {
                // A failed poll is not worth a message; a failed open is.
                if (!quiet) {
                    body.innerHTML = '<p class="notif-empty">Could not load notifications.</p>';
                }
            });
    }

    /* Checking back.

       Without this the bell was only ever right at page load: the count came
       from the server with the HTML, and the list was fetched once, the first
       time the panel was opened. A feast day posted while a devotee sat on
       the map never appeared until they navigated somewhere.

       A poll rather than a socket, because this is one count on a small app
       and a websocket is a server to run, a port to open and a thing to
       explain. Ninety seconds is slower than a push and enormously simpler.

       Nothing polls in a background tab. A phone left on the plan screen
       overnight should not spend the night making requests. */
    var POLL_MS = 90000;
    var timer = null;

    function hidden() {
        return document.visibilityState === 'hidden';
    }

    function startPolling() {
        stopPolling();
        if (hidden()) return;
        timer = window.setInterval(function () { load(true); }, POLL_MS);
    }

    function stopPolling() {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (hidden()) {
            stopPolling();
            return;
        }

        /* Coming back to the tab is the moment a devotee is most likely to
           look at the bell, so it checks immediately rather than waiting out
           the rest of an interval that ran down while they were away. */
        load(true);
        startPolling();
    });

    load(true).then(startPolling);

    bell.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var open = panel.classList.toggle('is-open');
        bell.setAttribute('aria-expanded', String(open));

        // Only one of the two panels should be open at a time.
        var menu = document.getElementById('navMobileMenu');
        if (open && menu) menu.classList.remove('open');

        // The first load has already run, so this is a catch-up for the case
        // where it failed, not the only fetch there is.
        if (open && !loaded) load();
    });

    document.addEventListener('click', function (e) {
        if (!panel.classList.contains('is-open')) return;
        if (panel.contains(e.target) || bell.contains(e.target)) return;
        panel.classList.remove('is-open');
        bell.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) {
            panel.classList.remove('is-open');
            bell.setAttribute('aria-expanded', 'false');
            bell.focus();
        }
    });

    // Opening one marks it read, without blocking the navigation.
    panel.addEventListener('click', function (e) {
        var item = e.target.closest('[data-notif]');
        if (!item || !item.classList.contains('is-unread')) return;

        item.classList.remove('is-unread');
        badge(Math.max(0, panel.querySelectorAll('.is-unread').length));

        fetch('/notifications/' + item.dataset.notif, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' }
        }).catch(function () {});
    });

    var readAll = document.getElementById('notifReadAll');
    if (readAll) {
        readAll.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.querySelectorAll('.is-unread').forEach(function (el) {
                el.classList.remove('is-unread');
            });
            badge(0);

            fetch('/notifications', {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' }
            }).catch(function () {});
        });
    }
})(window, document);
