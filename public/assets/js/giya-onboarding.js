/**
 * Welcome slides. The track is a native scroll-snap row, so swiping is the
 * browser's own; this keeps the dots and the button in step with it.
 */
(function (document) {
    'use strict';

    var track = document.getElementById('onbTrack');
    var next  = document.getElementById('onbNext');
    var login = document.getElementById('onbLogin');
    var dots  = Array.prototype.slice.call(document.querySelectorAll('.onb-dot'));
    if (!track || !next || !dots.length) return;

    var last = dots.length - 1;
    var current = -1;

    function index() {
        return Math.round(track.scrollLeft / track.clientWidth);
    }

    function go(i) {
        i = Math.max(0, Math.min(last, i));
        track.scrollTo({ left: i * track.clientWidth, behavior: 'smooth' });
    }

    function sync() {
        var i = index();
        if (i === current) return;
        current = i;
        dots.forEach(function (d, n) {
            d.classList.toggle('is-on', n === i);
            if (n === i) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current');
        });
        // Next until the last slide, then Get Started (a real link to sign up).
        next.textContent = i === last ? next.dataset.start : next.dataset.next;
        if (login) login.classList.toggle('is-shown', i === last);
    }

    next.addEventListener('click', function (e) {
        if (index() < last) { e.preventDefault(); go(index() + 1); }
    });

    dots.forEach(function (d) {
        d.addEventListener('click', function () { go(+d.dataset.i); });
    });

    track.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { e.preventDefault(); go(index() + 1); }
        if (e.key === 'ArrowLeft')  { e.preventDefault(); go(index() - 1); }
    });

    track.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
    window.addEventListener('resize', function () { current = -1; go(index()); sync(); });

    document.documentElement.classList.add('onb-js');
    sync();
})(document);
