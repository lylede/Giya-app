/**
 * GIYA - checking in at a church from the map.
 *
 * While the map is open and the devotee has allowed location, this watches
 * where they are. Standing within ARRIVE_M of a church for STAY_MS (about a
 * minute) records a visit: driving past in a jeepney does not count, standing
 * at the door does. The page shows "You're at ...: marked as visited" with an
 * Undo, and the server leaves a note in the bell.
 *
 * A web page cannot watch location with the screen off or the tab closed, so
 * this only works while GIYA is open. Nothing here asks for location by
 * itself: it starts when the map already has permission, or after the
 * devotee allows it from the map's own location button.
 */
(function (window, document) {
    'use strict';

    var ARRIVE_M = 75;          // phone GPS in town is good to roughly 20-40 m
    var STAY_MS  = 60 * 1000;   // long enough that passing by does not count
    var MAX_ACCURACY_M = 100;   // a fix vaguer than this cannot place someone at a door

    var cfg = null, churches = [], watchId = null, ticker = null;
    var here = null, near = {}, done = {};

    function metres(a, b) {
        var r = Math.PI / 180;
        var h = Math.pow(Math.sin((b.lat - a.lat) * r / 2), 2)
              + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.pow(Math.sin((b.lng - a.lng) * r / 2), 2);
        return 6371000 * 2 * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    /*
        Only the closest church within reach counts at a time. The Basilica
        and Magellan's Cross share a plaza, 50 m apart: standing at one must
        not check in both. Walk over to the other and stay a minute, and that
        one is checked in too.
    */
    function check() {
        if (!here) return;
        var now = Date.now(), best = null, bestM = Infinity;

        churches.forEach(function (c) {
            if (c.lat == null || c.lng == null) return;
            var m = metres(here, c);
            if (m <= ARRIVE_M && m < bestM) { best = c; bestM = m; }
        });

        // The minute starts again for every church but the one being stood at.
        Object.keys(near).forEach(function (id) {
            if (!best || String(best.id) !== id) delete near[id];
        });

        if (!best || done[best.id]) return;
        if (!near[best.id]) near[best.id] = now;
        if (now - near[best.id] >= STAY_MS) arrive(best);
    }

    function arrive(c) {
        done[c.id] = true;

        fetch(cfg.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
            body: JSON.stringify({ church_id: c.id, lat: here.lat, lng: here.lng })
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
            .then(function (data) {
                if (!data.ok || data.already) return;    // already recorded today: say nothing
                show(data.church || c.name, data.undo_url);
                if (window.GiyaNotifications) window.GiyaNotifications.refresh();
            })
            .catch(function () { done[c.id] = false; delete near[c.id]; });   // try again next time
    }

    function show(name, undoUrl) {
        var box = document.getElementById('visitToast');
        if (!box) return;

        document.getElementById('visitToastName').textContent = name;
        var undo = document.getElementById('visitToastUndo');
        undo.onclick = function () {
            undo.disabled = true;
            fetch(undoUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf }
            }).then(function () {
                hide();
                if (window.GiyaNotifications) window.GiyaNotifications.refresh();
            }).finally(function () { undo.disabled = false; });
        };

        box.hidden = false;
        requestAnimationFrame(function () { box.classList.add('is-open'); });
        clearTimeout(show._t);
        show._t = setTimeout(hide, 10000);
    }

    function hide() {
        var box = document.getElementById('visitToast');
        if (!box) return;
        box.classList.remove('is-open');
        setTimeout(function () { box.hidden = true; }, 300);
    }

    function start() {
        if (!cfg || watchId !== null || !navigator.geolocation) return;

        watchId = navigator.geolocation.watchPosition(
            function (pos) {
                if (pos.coords.accuracy && pos.coords.accuracy > MAX_ACCURACY_M) return;
                here = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                check();
            },
            function () { /* refused or unavailable: the map already says so */ },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 10000 }
        );

        // Standing still sends no new positions, so the minute is counted here too.
        ticker = setInterval(check, 10000);
    }

    window.GiyaArrive = {
        /** Called by the map with its churches. Starts at once if location is already allowed. */
        init: function (options) {
            cfg = options;
            churches = options.churches || [];
            (options.visitedToday || []).forEach(function (id) { done[id] = true; });

            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'geolocation' })
                    .then(function (p) { if (p.state === 'granted') start(); })
                    .catch(function () {});
            }
        },
        /** Called when the devotee allows location from the map. */
        start: start,
        // For tests.
        _check: check,
        _set: function (pos) { here = pos; }
    };
})(window, document);
