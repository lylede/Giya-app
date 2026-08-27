/**
 * GIYA - in-app navigation.
 *
 * Everything a pilgrim needs to follow a route without leaving the app:
 * the road-following line, the list of turns with street names, the live
 * position, and the distance to the next manoeuvre.
 *
 * This is deliberately NOT a hand-off to another map. The manuscript promises
 * one app; sending someone to Google Maps mid-pilgrimage breaks that, and they
 * lose their progress on the way back.
 */
window.GiyaNav = (function (window, document) {
    'use strict';

    /* ------------------------------------------------------------ geometry */

    function toRad(d) { return d * Math.PI / 180; }

    /** Great-circle distance in metres. */
    function metres(a, b) {
        var R = 6371000;
        var dLat = toRad(b.lat - a.lat), dLng = toRad(b.lng - a.lng);
        var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) *
                Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
    }

    /** Shortest distance from a point to the drawn route, in metres. */
    function offRouteBy(point, line) {
        var best = Infinity;
        for (var i = 0; i < line.length; i++) {
            var d = metres(point, { lat: line[i][0], lng: line[i][1] });
            if (d < best) best = d;
        }
        return best;
    }

    function human(m) {
        if (m == null) return '';
        return m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(1) + ' km';
    }

    function humanTime(seconds) {
        var mins = Math.round(seconds / 60);
        if (mins < 60) return mins + ' min';
        var h = Math.floor(mins / 60);
        return h + ' h ' + (mins % 60) + ' min';
    }

    /**
     * OpenRouteService returns a manoeuvre type as a number. Mapping it to an
     * arrow is what makes a turn list readable at a glance while walking.
     */
    var ARROWS = {
        0: 'arrow-90deg-left',   1: 'arrow-90deg-right',
        2: 'arrow-90deg-left',   3: 'arrow-90deg-right',
        4: 'arrow-up-left',      5: 'arrow-up-right',
        6: 'arrow-up',           7: 'arrow-repeat',
        8: 'arrow-repeat',       9: 'arrow-return-left',
        10: 'geo-alt-fill',      11: 'arrow-up',
        12: 'arrow-up-left',     13: 'arrow-up-right'
    };

    function arrowFor(type) { return ARROWS[type] || 'arrow-up'; }

    /* ------------------------------------------------------------- session */

    function create(options) {
        var map = options.map;
        var onUpdate = options.onUpdate || function () {};
        var onStatus = options.onStatus || function () {};

        var line = null;          // the drawn polyline
        var travelled = null;     // the part already walked
        var meMarker = null;
        var geometry = [];
        var steps = [];
        var stepIndex = 0;
        var watchId = null;
        var here = null;
        var summary = { distance: 0, duration: 0 };

        function meIcon(heading) {
            return L.divIcon({
                html: '<span class="nav-me" style="transform:rotate(' + (heading || 0) + 'deg)">' +
                          '<span class="nav-me-arrow"></span></span>',
                className: 'giya-pin-wrap',
                iconSize: [26, 26],
                iconAnchor: [13, 13]
            });
        }

        function draw() {
            if (line) map.removeLayer(line);
            if (travelled) map.removeLayer(travelled);

            line = L.polyline(geometry, {
                color: '#8E3B2F', weight: 7, opacity: .9,
                lineCap: 'round', lineJoin: 'round'
            }).addTo(map);

            L.polyline(geometry, {
                color: '#D7A94A', weight: 2.5, opacity: .95
            }).addTo(line);

            map.fitBounds(line.getBounds().pad(0.15));
        }

        /** Ask the server for the route, then render it. */
        function build(stops, profile) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta) return Promise.resolve(false);

            onStatus('Finding the way…', 'info');

            return fetch('/api/route', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': meta.content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ stops: stops, profile: profile })
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok || !d.geometry || !d.geometry.length) {
                        onStatus(reasonText(d.reason), 'warn');
                        return false;
                    }

                    geometry = d.geometry;
                    summary = { distance: d.distance, duration: d.duration };

                    steps = [];
                    (d.legs || []).forEach(function (leg, legIndex) {
                        (leg.steps || []).forEach(function (s) {
                            s.leg = legIndex;
                            steps.push(s);
                        });
                    });

                    stepIndex = 0;
                    draw();
                    onStatus('', 'clear');
                    push();
                    return true;
                })
                .catch(function () {
                    onStatus('No connection, so the route is a straight line.', 'warn');
                    return false;
                });
        }

        function reasonText(reason) {
            return {
                no_key:   'Street directions need a routing key. Showing straight lines.',
                quota:    'Daily routing limit reached. Showing straight lines.',
                offline:  'No connection, so the route is a straight line.',
                no_route: 'No road route between these stops.',
                too_few:  'Add another stop to build a route.'
            }[reason] || 'Street directions are unavailable right now.';
        }

        /** Report the current state to the page. */
        function push() {
            var step = steps[stepIndex] || null;
            var next = steps[stepIndex + 1] || null;
            var toTurn = null;

            if (step && here && step.lat) {
                toTurn = metres(here, { lat: step.lat, lng: step.lng });
            }

            onUpdate({
                step: step,
                next: next,
                stepIndex: stepIndex,
                total: steps.length,
                toTurn: toTurn,
                toTurnText: human(toTurn),
                arrow: step ? arrowFor(step.type) : 'arrow-up',
                remaining: human(summary.distance),
                eta: humanTime(summary.duration),
                here: here
            });
        }

        /**
         * Advance when the walker passes the current manoeuvre. Twenty-five
         * metres is forgiving enough for phone GPS in a built-up area without
         * skipping turns that are close together.
         */
        function advance() {
            if (!here) return;

            var step = steps[stepIndex];
            if (!step || !step.lat) return;

            if (metres(here, { lat: step.lat, lng: step.lng }) < 25 && stepIndex < steps.length - 1) {
                stepIndex++;
            }
        }

        function locate() {
            if (!navigator.geolocation) {
                onStatus('This browser cannot share a location.', 'warn');
                return;
            }

            watchId = navigator.geolocation.watchPosition(
                function (pos) {
                    here = { lat: pos.coords.latitude, lng: pos.coords.longitude };

                    if (!meMarker) {
                        meMarker = L.marker([here.lat, here.lng], {
                            icon: meIcon(pos.coords.heading), zIndexOffset: 1000
                        }).addTo(map);
                    } else {
                        meMarker.setLatLng([here.lat, here.lng]);
                        meMarker.setIcon(meIcon(pos.coords.heading));
                    }

                    advance();

                    // Wandered well off the line? Ask for a new one.
                    if (geometry.length && offRouteBy(here, geometry) > 60) {
                        onStatus('You are off the route - recalculating…', 'warn');
                        if (options.onOffRoute) options.onOffRoute(here);
                    }

                    push();
                },
                function (err) {
                    onStatus(err.code === 1
                        ? 'Location permission denied, so live guidance is off.'
                        : 'Could not get a location fix.', 'warn');
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 3000 }
            );
        }

        function stop() {
            if (watchId !== null) navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        function recentre() {
            if (here) map.setView([here.lat, here.lng], 17, { animate: true });
            else if (line) map.fitBounds(line.getBounds().pad(0.15));
        }

        function goTo(index) {
            if (index < 0 || index >= steps.length) return;
            stepIndex = index;
            var s = steps[index];
            if (s && s.lat) map.setView([s.lat, s.lng], 17, { animate: true });
            push();
        }

        return {
            build: build,
            locate: locate,
            stop: stop,
            recentre: recentre,
            goTo: goTo,
            steps: function () { return steps.slice(); },
            arrowFor: arrowFor,
            human: human
        };
    }

    return { create: create, human: human, metres: metres };
})(window, document);
