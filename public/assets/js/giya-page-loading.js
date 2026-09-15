/**
 * GIYA - holding a section until the page's pictures arrive.
 *
 * The home page is server-rendered, so its words are there the moment the
 * HTML is. Its photographs are not: the featured churches are large
 * background images, and while they were still crossing the network the
 * middle of the page was an empty frame between two sections that had
 * finished. The page looked broken rather than busy.
 *
 * So the sections wait with it, and they say so.
 *
 * Three rules this follows, because a loading state is easy to make worse
 * than the thing it replaces:
 *
 *   1. Nothing is hidden by the stylesheet. The real content is in the DOM
 *      and visible until THIS script decides otherwise, so a script that
 *      never runs - a 404, an error in an earlier file - leaves a complete
 *      page rather than a permanent skeleton.
 *
 *   2. A page whose images are already cached never shows one. The check is
 *      synchronous and complete images report so immediately. Note that the
 *      early return below is not what prevents the flash - Promise.all on an
 *      empty list resolves in a microtask, so the class would come off before
 *      the next paint anyway, and the probe passes with the return deleted.
 *      It is there to avoid touching the DOM at all when there is nothing to
 *      wait for.
 *
 *   3. There is a ceiling. A slow image cannot hold the page forever; past
 *      the cap the content is shown whether the picture arrived or not.
 */
(function (window, document) {
    'use strict';

    /* Long enough to cover a slow-ish image on a phone, short enough that a
       broken one is not a broken page. */
    var CAP_MS = 2500;

    function sections() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-loading-section]'));
    }

    /**
     * Every image the page is actually waiting on.
     *
     * <img> elements plus anything carrying a background-image, because the
     * featured procession paints its photographs that way and those are the
     * biggest files on the page.
     */
    function pending() {
        var waiting = [];

        Array.prototype.forEach.call(document.images, function (img) {
            if (!img.complete) waiting.push(loaded(img));
        });

        Array.prototype.forEach.call(document.querySelectorAll('[style*="background-image"]'), function (el) {
            var url = (el.style.backgroundImage || '').replace(/^url\(["']?/, '').replace(/["']?\)$/, '');

            if (!url || url === 'none') return;

            var probe = new Image();
            probe.src = url;

            if (!probe.complete) waiting.push(loaded(probe));
        });

        return waiting;
    }

    /** Settles whether the image loads or fails - a broken photograph is
     *  still an answer, and waiting for one that will never come is the
     *  whole failure mode this is avoiding. */
    function loaded(img) {
        return new Promise(function (done) {
            img.addEventListener('load', done, { once: true });
            img.addEventListener('error', done, { once: true });
        });
    }

    function reveal(list) {
        list.forEach(function (el) { el.classList.remove('is-loading'); });

        /* The cards were hidden while the reveal script measured them, so
           their positions were meaningless. Re-arming makes them arrive in
           order now that they have somewhere to be. */
        if (window.GiyaReveal) {
            document.querySelectorAll('[data-loading-section] [data-reveal]').forEach(function (root) {
                window.GiyaReveal.refresh(root);
            });
        }
    }

    function start() {
        var list = sections();

        if (!list.length) return;

        var waiting = pending();

        // Everything is already here. Nothing to wait for, nothing to say.
        if (!waiting.length) return;

        list.forEach(function (el) { el.classList.add('is-loading'); });

        var done = false;

        var finish = function () {
            if (done) return;
            done = true;
            reveal(list);
        };

        Promise.all(waiting).then(finish);
        window.setTimeout(finish, CAP_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(window, document);
