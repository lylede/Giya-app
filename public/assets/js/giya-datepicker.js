/**
 * GIYA — date picker.
 *
 * Chrome, Edge and Safari each draw their own calendar for <input type="date">,
 * inside a shadow DOM that CSS cannot reach. So the palette can only be matched
 * by replacing the popup entirely.
 *
 * The real <input type="date"> is kept as the value holder — form submission,
 * validation, min/max and required all keep working exactly as before. Only the
 * popup is ours.
 *
 * Every date input on the page is enhanced automatically. Opt out with
 * data-native-picker on the input.
 */
(function (window, document) {
    'use strict';

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
                  'July', 'August', 'September', 'October', 'November', 'December'];
    var DAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    var panel = null;
    var input = null;
    var viewYear = 0;
    var viewMonth = 0;

    /* ------------------------------------------------------------- helpers */

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function toValue(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }

    function parse(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        return m ? { y: +m[1], m: +m[2] - 1, d: +m[3] } : null;
    }

    function sameDay(a, y, m, d) {
        return a && a.y === y && a.m === m && a.d === d;
    }

    /** Respect min/max if the input declares them. */
    function outOfRange(y, m, d) {
        var value = toValue(y, m, d);
        if (input.min && value < input.min) return true;
        if (input.max && value > input.max) return true;
        return false;
    }

    /* -------------------------------------------------------------- render */

    function build() {
        if (panel) return panel;

        panel = document.createElement('div');
        panel.className = 'gdp';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Choose a date');
        document.body.appendChild(panel);

        panel.addEventListener('mousedown', function (e) { e.preventDefault(); });

        panel.addEventListener('click', function (e) {
            e.stopPropagation();
            var nav = e.target.closest('[data-move]');
            if (nav) {
                shift(+nav.dataset.move);
                return;
            }

            var day = e.target.closest('[data-day]');
            if (day && !day.disabled) {
                commit(day.dataset.day);
                return;
            }

            if (e.target.closest('[data-today]')) {
                var now = new Date();
                viewYear = now.getFullYear();
                viewMonth = now.getMonth();
                commit(toValue(viewYear, viewMonth, now.getDate()));
                return;
            }

            if (e.target.closest('[data-clear]')) {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
                close();
            }
        });

        return panel;
    }

    function shift(delta) {
        viewMonth += delta;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        draw();
    }

    function draw() {
        var selected = parse(input.value);
        var today = new Date();

        var first = new Date(viewYear, viewMonth, 1).getDay();
        var length = new Date(viewYear, viewMonth + 1, 0).getDate();
        var prevLength = new Date(viewYear, viewMonth, 0).getDate();

        var cells = '';

        // Trailing days of the previous month, greyed.
        for (var i = first - 1; i >= 0; i--) {
            cells += '<span class="gdp-day is-outside">' + (prevLength - i) + '</span>';
        }

        for (var d = 1; d <= length; d++) {
            var classes = 'gdp-day';
            if (sameDay(selected, viewYear, viewMonth, d)) classes += ' is-selected';
            if (viewYear === today.getFullYear() && viewMonth === today.getMonth()
                && d === today.getDate()) classes += ' is-today';

            var blocked = outOfRange(viewYear, viewMonth, d);

            cells += '<button type="button" class="' + classes + '"' +
                     (blocked ? ' disabled' : '') +
                     ' data-day="' + toValue(viewYear, viewMonth, d) + '">' + d + '</button>';
        }

        // Leading days of the next month, to square off the grid.
        var used = first + length;
        for (var t = 1; used % 7 !== 0; t++, used++) {
            cells += '<span class="gdp-day is-outside">' + t + '</span>';
        }

        panel.innerHTML =
            '<div class="gdp-head">' +
                '<button type="button" class="gdp-nav" data-move="-1" aria-label="Previous month">' +
                    '<i class="bi bi-chevron-left"></i></button>' +
                '<span class="gdp-month">' + MONTHS[viewMonth] + ' ' + viewYear + '</span>' +
                '<button type="button" class="gdp-nav" data-move="1" aria-label="Next month">' +
                    '<i class="bi bi-chevron-right"></i></button>' +
            '</div>' +
            '<div class="gdp-weekdays">' +
                DAYS.map(function (d) { return '<span>' + d + '</span>'; }).join('') +
            '</div>' +
            '<div class="gdp-grid">' + cells + '</div>' +
            '<div class="gdp-foot">' +
                '<button type="button" class="gdp-link" data-clear>Clear</button>' +
                '<button type="button" class="gdp-link is-primary" data-today>Today</button>' +
            '</div>';
    }

    /* ------------------------------------------------------------ position */

    function place() {
        // Under 560px the panel is a centred sheet, positioned by CSS.
        if (window.matchMedia('(max-width: 560px)').matches) {
            panel.style.top = '';
            panel.style.left = '';
            return;
        }

        var box = input.getBoundingClientRect();
        var height = panel.offsetHeight || 340;
        var width = panel.offsetWidth || 290;

        // Flip above the field when there is no room below.
        var below = window.innerHeight - box.bottom;
        var top = below < height + 12 && box.top > height + 12
            ? box.top - height - 8
            : box.bottom + 8;

        var left = Math.min(box.left, window.innerWidth - width - 12);

        panel.style.top = (top + window.scrollY) + 'px';
        panel.style.left = (Math.max(12, left) + window.scrollX) + 'px';
    }

    /* --------------------------------------------------------- open, close */

    function open(target) {
        input = target;
        build();

        var current = parse(input.value) || (function () {
            var n = new Date();
            return { y: n.getFullYear(), m: n.getMonth() };
        })();

        viewYear = current.y;
        viewMonth = current.m;

        draw();
        panel.classList.add('is-open');
        place();
    }

    function close() {
        if (panel) panel.classList.remove('is-open');
        input = null;
    }

    function commit(value) {
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    }

    /* ------------------------------------------------------------- binding */

    function isDate(el) {
        return el && el.tagName === 'INPUT'
            && el.type === 'date'
            && !el.hasAttribute('data-native-picker')
            && !el.disabled;
    }

    /*
       Marking the field readonly is what actually stops the native calendar.
       preventDefault() on mousedown is not enough: on a touch device, focusing
       a date input opens the OS picker on its own, so both calendars appeared
       at once. A readonly input never opens one, and still submits its value.

       The field is not readonly to a screen reader's understanding of the form
       — it keeps its name, value and required state; only typing is disabled,
       and the calendar supplies the value instead.
    */
    function prepare(el) {
        if (!isDate(el) || el.dataset.gdpReady) return;
        el.dataset.gdpReady = '1';
        el.readOnly = true;
        el.setAttribute('inputmode', 'none');
        el.setAttribute('autocomplete', 'off');
        el.style.cursor = 'pointer';
    }

    function prepareAll(root) {
        (root || document).querySelectorAll('input[type="date"]').forEach(prepare);
    }

    document.addEventListener('DOMContentLoaded', function () { prepareAll(); });
    prepareAll();

    // Catch inputs added later — Livewire re-renders, modals, JS-built rows.
    if (window.MutationObserver) {
        new MutationObserver(function (records) {
            records.forEach(function (r) {
                r.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches('input[type="date"]')) prepare(node);
                    else prepareAll(node);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    /*
       Opening happens on `click`.

       pointerdown seemed the tidier choice, but calling preventDefault() there
       suppresses the tap on mobile browsers rather than just the focus, so a
       short tap produced nothing and only a long press got through. `click`
       fires reliably for mouse, touch and pen alike.

       pointerdown is still used, for one job only: stopping the field from
       taking focus, which is what opens the operating system's own calendar.
    */
    function fieldFrom(target) {
        var el = target && target.closest ? target.closest('input[type="date"]') : null;
        return isDate(el) ? el : null;
    }

    document.addEventListener('pointerdown', function (e) {
        var field = fieldFrom(e.target);
        if (!field) return;
        prepare(field);
        e.preventDefault();          // no focus, so no native picker
    }, true);

    document.addEventListener('click', function (e) {
        var field = fieldFrom(e.target);

        if (field) {
            e.preventDefault();
            e.stopPropagation();
            prepare(field);

            // A second tap on the same field closes it.
            if (input === field && panel && panel.classList.contains('is-open')) {
                close();
            } else {
                open(field);
            }
            return;
        }

        // Anywhere else closes, unless the click was inside the panel.
        if (!panel || !panel.classList.contains('is-open')) return;
        if (panel.contains(e.target)) return;
        close();
    }, true);

    document.addEventListener('keydown', function (e) {
        if (isDate(e.target) && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            open(e.target);
            return;
        }
        if (e.key === 'Escape') close();
    });

    window.addEventListener('resize', function () { if (input) place(); });
    window.addEventListener('scroll', function () { if (input) place(); }, true);
})(window, document);
