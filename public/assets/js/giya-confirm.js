/**
 * GIYA - confirmation dialog.
 *
 * Replaces the browser's native confirm(), which cannot be styled, shows the
 * host name to the user, and looks like a security warning rather than part of
 * the application.
 *
 * Two ways to use it.
 *
 * 1. Declarative, on any form:
 *
 *      <form method="POST" action="..."
 *            data-confirm="Delete this destination?"
 *            data-confirm-title="Delete destination"
 *            data-confirm-ok="Delete"
 *            data-confirm-tone="danger">
 *
 * 2. Programmatic, returning a promise:
 *
 *      GiyaConfirm.ask({ title: '…', message: '…', tone: 'danger' })
 *          .then(function (ok) { if (ok) … });
 */
window.GiyaConfirm = (function (window, document) {
    'use strict';

    var host = null;
    var resolver = null;
    var lastFocused = null;

    function build() {
        if (host) return host;

        host = document.createElement('div');
        host.className = 'gc-backdrop';
        host.setAttribute('role', 'dialog');
        host.setAttribute('aria-modal', 'true');
        host.setAttribute('aria-labelledby', 'gcTitle');
        host.innerHTML =
            '<div class="gc-box">' +
                '<span class="gc-icon" id="gcIcon"><i class="bi bi-exclamation-triangle-fill"></i></span>' +
                '<h2 class="gc-title" id="gcTitle"></h2>' +
                '<p class="gc-message" id="gcMessage"></p>' +
                '<div class="gc-actions">' +
                    '<button type="button" class="btn btn-ghost gc-cancel" id="gcCancel">Cancel</button>' +
                    '<button type="button" class="btn btn-primary gc-ok" id="gcOk">Confirm</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(host);

        host.querySelector('#gcCancel').addEventListener('click', function () { settle(false); });
        host.querySelector('#gcOk').addEventListener('click', function () { settle(true); });

        // Clicking the backdrop cancels; clicking the box must not.
        host.addEventListener('click', function (e) {
            if (e.target === host) settle(false);
        });

        // Keep focus inside the dialog while it is open.
        host.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { settle(false); return; }
            if (e.key !== 'Tab') return;

            var focusable = host.querySelectorAll('button');
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        });

        return host;
    }

    function settle(answer) {
        if (!host) return;

        host.classList.remove('is-open');
        document.body.style.overflow = '';

        if (lastFocused && lastFocused.focus) lastFocused.focus();
        lastFocused = null;

        var done = resolver;
        resolver = null;
        if (done) done(answer);
    }

    function ask(options) {
        options = options || {};
        build();

        document.getElementById('gcTitle').textContent   = options.title   || 'Are you sure?';
        document.getElementById('gcMessage').textContent = options.message || '';

        var ok = document.getElementById('gcOk');
        ok.textContent = options.ok || 'Confirm';

        var danger = options.tone === 'danger';
        ok.className = 'btn gc-ok ' + (danger ? 'btn-danger-solid' : 'btn-primary');
        host.querySelector('#gcIcon').className = 'gc-icon' + (danger ? ' is-danger' : '');
        document.getElementById('gcCancel').textContent = options.cancel || 'Cancel';

        lastFocused = document.activeElement;
        host.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        // Focus Cancel, not Confirm - a stray Enter should not delete anything.
        setTimeout(function () { document.getElementById('gcCancel').focus(); }, 30);

        return new Promise(function (resolve) { resolver = resolve; });
    }

    /* Any form carrying data-confirm asks before it submits. */
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-confirm]');
        if (!form || form.dataset.gcPassed === '1') return;

        e.preventDefault();

        ask({
            title:   form.dataset.confirmTitle || 'Are you sure?',
            message: form.dataset.confirm,
            ok:      form.dataset.confirmOk || 'Confirm',
            tone:    form.dataset.confirmTone || 'danger'
        }).then(function (yes) {
            if (!yes) return;
            form.dataset.gcPassed = '1';
            form.submit();
        });
    }, true);

    /* Links too: <a href="…" data-confirm="…"> */
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-confirm]');
        if (!link) return;

        e.preventDefault();

        ask({
            title:   link.dataset.confirmTitle || 'Are you sure?',
            message: link.dataset.confirm,
            ok:      link.dataset.confirmOk || 'Confirm',
            tone:    link.dataset.confirmTone || 'danger'
        }).then(function (yes) {
            if (yes) window.location.href = link.href;
        });
    });

    return { ask: ask };
})(window, document);
