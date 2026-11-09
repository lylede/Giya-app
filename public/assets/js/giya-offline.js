/*
 * Installable app and offline pages (public/sw.js).
 * Signing out clears the pages kept for offline, so the next person on a
 * shared phone never sees the previous one's itineraries.
 */
(function () {
    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () { /* the site works without it */ });
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !/\/logout$/.test(form.getAttribute('action') || '')) return;
        try {
            if (navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage('giya:clear-pages');
            if (window.caches) caches.delete('giya-pages');
        } catch (err) { /* nothing to clear */ }
    }, true);
})();
