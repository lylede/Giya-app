/**
 * GIYA - reveal on scroll.
 *
 * Sections and grids arrive as their own row rather than all at once. The
 * home page is a stack of four sections and the map is a list that can run
 * to a hundred results; both read better when the eye is given an order to
 * follow instead of a wall that is simply there.
 *
 * Progressive enhancement, and that ordering matters: the stylesheet hides
 * nothing on its own. Items are only hidden once THIS script has marked the
 * container armed, so a devotee whose JavaScript failed, or who is on a
 * browser without IntersectionObserver, gets the page fully visible rather
 * than a blank screen. Hiding first and revealing later is how a reveal
 * turns into an outage.
 */
(function (window, document) {
    'use strict';

    var REDUCED = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Past this, the stagger stops being a sequence and becomes a wait.
    var MAX_STEPS = 8;

    function items(root) {
        return Array.prototype.slice.call(root.querySelectorAll(':scope > *'));
    }

    function show(el) {
        el.classList.add('is-in');
    }

    var io = null;

    function arm() {
        var roots = document.querySelectorAll('[data-reveal]');

        if (!roots.length) return;

        /* No observer, or motion turned down: mark everything shown and never
           hide it. The class is still added so anything keyed off is-in stays
           consistent, but nothing was hidden in the first place. */
        if (REDUCED || !('IntersectionObserver' in window)) {
            Array.prototype.forEach.call(roots, function (root) {
                show(root);
                items(root).forEach(show);
            });
            return;
        }

        if (!io) {
            io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting) return;
                    show(e.target);
                    io.unobserve(e.target);      // once, not every scroll
                });
            }, {
                /* Fire a little before the row reaches the viewport, so the
                   movement is finishing as it arrives rather than starting. */
                rootMargin: '0px 0px -8% 0px',
                threshold: 0.05,
            });
        }

        Array.prototype.forEach.call(roots, function (root) {
            root.classList.add('is-armed');

            /* Each item is watched, not the container.

               Watching the container is what made this do nothing on a phone.
               A four-card grid is one column there and taller than the screen,
               so the container is already intersecting at load - the whole
               grid was marked shown at once, including the three cards below
               the fold, and by the time the devotee scrolled to them they had
               finished arriving. On a desktop the container fits, everything
               enters together, and it looked fine. */
            items(root).forEach(function (el, i) {
                el.style.setProperty('--reveal-i', Math.min(i, MAX_STEPS));

                /* Anything already on screen when the page loads should not
                   wait for a scroll that may never come. */
                var box = el.getBoundingClientRect();

                if (box.top < window.innerHeight && box.bottom > 0) {
                    show(el);
                } else {
                    io.observe(el);
                }
            });
        });
    }

    /**
     * Re-arm a container whose contents were just replaced.
     *
     * The map rewrites its result list on every filter change. Without this
     * the new rows inherit the old row's delay, or none at all, and the list
     * either flashes or arrives out of order.
     */
    function refresh(root) {
        if (!root) return;

        var list = items(root);

        if (REDUCED || !('IntersectionObserver' in window)) {
            list.forEach(show);
            return;
        }

        root.classList.add('is-armed');

        list.forEach(function (el, i) {
            el.classList.remove('is-in');
            el.style.setProperty('--reveal-i', Math.min(i, MAX_STEPS));
        });

        // Next frame, so the browser paints the hidden state before the
        // transition to the shown one - without it there is no transition.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                list.forEach(function (el) {
                    var box = el.getBoundingClientRect();

                    if (box.top < window.innerHeight && box.bottom > 0) {
                        show(el);
                    } else {
                        io.observe(el);
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arm);
    } else {
        arm();
    }

    window.GiyaReveal = { refresh: refresh, arm: arm };
})(window, document);
