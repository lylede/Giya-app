/**
 * GIYA - candle progress.
 *
 * One candle per stop. The screens that use it already track how many stops
 * are done; this turns that number into a row that lights.
 *
 *   GiyaCandles.set(rowEl, done)   set the row to this many lit
 *   GiyaCandles.grow(rowEl, total) rebuild the row for a new stop count
 *
 * set() is the interesting one. It does not simply paint the right candles
 * lit: it works out which ones have JUST been lit and gives those the catch
 * animation, staggered along the row, while the ones that were already lit
 * are left alone. Re-lighting a candle that was already burning makes the
 * whole row flash on every tick, which is the thing that would make this
 * tiring rather than nice.
 */
(function (window, document) {
    'use strict';

    /* Long enough for the bloom (.75s) to finish. If is-lighting were
       stripped sooner the animation would be cut off mid-bloom and the glow
       would jump to its resting size. */
    var CATCH_MS = 800;

    // A beat between one candle catching and the next, when several light at
    // once. Small: eight candles at 70ms is still over in half a second.
    var STAGGER_MS = 70;

    function candles(row) {
        return Array.prototype.slice.call(row.querySelectorAll('.candle'));
    }

    function reducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function set(row, done) {
        if (!row) return;

        var list = candles(row);

        done = Math.max(0, Math.min(Number(done) || 0, list.length));

        var was = Number(row.dataset.done) || 0;
        row.dataset.done = done;

        /* Only candles crossing from unlit to lit get the animation. Going
           backwards - a stop un-marked - just goes dark, because a candle
           being put out is not a moment worth celebrating. */
        var newlyLit = [];

        list.forEach(function (c, i) {
            var lit = i < done;

            if (lit && !c.classList.contains('is-lit')) newlyLit.push(c);

            c.classList.toggle('is-lit', lit);
            c.classList.toggle('is-next', i === done);
        });

        if (reducedMotion()) return;

        newlyLit.forEach(function (c, n) {
            // Restart cleanly if this candle is somehow still mid-catch.
            c.classList.remove('is-lighting');
            void c.offsetWidth;

            window.setTimeout(function () {
                c.classList.add('is-lighting');
                window.setTimeout(function () {
                    c.classList.remove('is-lighting');
                }, CATCH_MS);
            }, n * STAGGER_MS);
        });
    }

    /**
     * Rebuild the row for a different number of stops.
     *
     * The Visita Iglesia screen lets the devotee add and remove churches
     * before starting, so the row has to be able to change length. Rebuilt
     * rather than adjusted: at twenty candles maximum this is a handful of
     * nodes, and the alternative is diffing a list to save nothing.
     */
    function grow(row, total) {
        if (!row) return;

        total = Math.max(0, Number(total) || 0);

        if (Number(row.dataset.total) === total) return;

        var done = Math.min(Number(row.dataset.done) || 0, total);
        var html = '';

        for (var i = 0; i < total; i++) {
            html += '<span class="candle' + (i < done ? ' is-lit' : '') + (i === done ? ' is-next' : '') + '">'
                  + '<span class="candle-glow"></span>'
                  + '<span class="candle-flame"></span>'
                  + '<span class="candle-wick"></span>'
                  + '<span class="candle-wax"></span>'
                  + '</span>';
        }

        row.innerHTML = html;
        row.dataset.total = total;
        row.dataset.done = done;
    }

    window.GiyaCandles = { set: set, grow: grow };
})(window, document);
