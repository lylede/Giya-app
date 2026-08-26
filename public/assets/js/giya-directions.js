/**
 * GIYA — the directions sheet.
 *
 * Binds the navigation engine to the panel on the map page: the current turn,
 * the full list of turns, distance remaining, and live tracking.
 */
window.GiyaDirections = (function (window, document) {
    'use strict';

    var sheet, session = null;

    function el(id) { return document.getElementById(id); }

    function open(stops, map) {
        sheet = el('navSheet');
        if (!sheet) return;

        sheet.hidden = false;
        requestAnimationFrame(function () { sheet.classList.add('is-open'); });

        if (session) session.stop();

        session = GiyaNav.create({
            map: map,
            onUpdate: render,
            onStatus: note,
            onOffRoute: function () {
                // Rebuild from where the walker actually is.
                session.build(stops, null);
            }
        });

        session.build(stops, null).then(function (built) {
            if (built) session.locate();
        });

        el('navClose').onclick = close;
        el('navRecentre').onclick = function () { session.recentre(); };
    }

    function close() {
        if (session) session.stop();
        sheet.classList.remove('is-open');
        setTimeout(function () { sheet.hidden = true; }, 240);
    }

    function note(text, kind) {
        var box = el('navNote');
        if (!box) return;

        if (!text || kind === 'clear') { box.hidden = true; return; }

        box.textContent = text;
        box.className = 'nav-note' + (kind === 'warn' ? ' is-warn' : '');
        box.hidden = false;
    }

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function render(state) {
        if (state.step) {
            el('navInstruction').textContent = state.step.text || 'Continue';
            el('navDistance').textContent = state.toTurnText
                ? 'in ' + state.toTurnText
                : GiyaNav.human(state.step.distance);
            el('navArrow').className = 'bi bi-' + state.arrow;
        }

        el('navRemaining').textContent = state.remaining || '';
        el('navEta').textContent = state.eta ? state.eta + ' remaining' : '';

        var list = el('navSteps');
        if (!list.dataset.built && state.total) {
            list.innerHTML = session.steps().map(function (s, i) {
                return '<li class="nav-step" data-step="' + i + '">' +
                        '<span class="nav-step-icon"><i class="bi bi-' +
                            session.arrowFor(s.type) + '"></i></span>' +
                        '<span class="nav-step-body">' +
                            '<span class="nav-step-text">' + esc(s.text) + '</span>' +
                            (s.distance ? '<span class="nav-step-dist">' +
                                GiyaNav.human(s.distance) + '</span>' : '') +
                        '</span>' +
                    '</li>';
            }).join('');
            list.dataset.built = '1';

            list.onclick = function (e) {
                var row = e.target.closest('[data-step]');
                if (row) session.goTo(Number(row.dataset.step));
            };
        }

        // Mark progress through the list.
        list.querySelectorAll('.nav-step').forEach(function (row, i) {
            row.classList.toggle('is-done', i < state.stepIndex);
            row.classList.toggle('is-current', i === state.stepIndex);
        });

        var current = list.querySelector('.is-current');
        if (current) current.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    return { open: open, close: close };
})(window, document);
