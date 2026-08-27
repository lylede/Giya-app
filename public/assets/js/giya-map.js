/**
 * GIYA - Leaflet map layer.
 *
 * Wraps Leaflet with the behaviour the app needs: category-coloured pins for
 * every destination, search and filtering, itinerary routing, and turn-by-turn
 * navigation.
 *
 * Leaflet and the tile server are both optional. If either is unavailable the
 * caller is told via the `onUnavailable` callback and can fall back to the
 * server-rendered SVG map, so the page still works with no connection.
 */
(function (window, document) {
    'use strict';

    const TILE_URL     = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const TILE_ATTRIB  = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    const OSRM_BASE    = 'https://router.project-osrm.org/route/v1';
    const CEBU_CENTRE  = [10.3157, 123.8854];
    const CEBU_BOUNDS  = [[9.20, 122.90], [11.50, 124.60]];

    /* ── Pin rendering ─────────────────────────────────────────────── */

    function pinIcon(color, label, size) {
        size = size || 32;
        const inner = label
            ? '<span style="transform:rotate(45deg);font-size:' + Math.round(size * 0.34) +
              'px;font-weight:700;color:#fff;line-height:1">' + label + '</span>'
            : '';

        return L.divIcon({
            className: 'giya-pin',
            html:
                '<div style="width:' + size + 'px;height:' + size + 'px;border-radius:50% 50% 50% 0;' +
                'background:' + color + ';border:2.5px solid #fff;' +
                'box-shadow:0 2px 6px rgba(0,0,0,.35);display:flex;align-items:center;' +
                'justify-content:center;transform:rotate(-45deg)">' + inner + '</div>',
            iconSize:   [size, size],
            iconAnchor: [size / 2, size],
            popupAnchor:[0, -size],
        });
    }

    function meIcon() {
        return L.divIcon({
            className: 'giya-me',
            html: '<div class="giya-me-dot"></div>',
            iconSize: [22, 22],
            iconAnchor: [11, 11],
        });
    }

    /* ── OSRM routing client ───────────────────────────────────────── */

    /**
     * Request a driving/walking/cycling route through an ordered list of
     * [lat, lng] waypoints. Resolves with { coords, distance, duration, steps }.
     */
    function fetchRoute(waypoints, profile) {
        profile = profile || 'driving';

        if (waypoints.length < 2) {
            return Promise.reject(new Error('A route needs at least two points.'));
        }

        // OSRM expects lng,lat order.
        const path = waypoints.map(w => w[1] + ',' + w[0]).join(';');
        const url  = OSRM_BASE + '/' + profile + '/' + path +
                     '?overview=full&geometries=geojson&steps=true';

        return fetch(url)
            .then(res => res.ok ? res.json() : Promise.reject(new Error('Routing service returned ' + res.status)))
            .then(function (body) {
                if (body.code !== 'Ok' || !body.routes || !body.routes.length) {
                    throw new Error(body.message || 'No route could be calculated.');
                }

                const route = body.routes[0];
                const steps = [];

                route.legs.forEach(function (leg, legIndex) {
                    leg.steps.forEach(function (step) {
                        steps.push({
                            leg:         legIndex,
                            instruction: describeStep(step),
                            distance:    step.distance,
                            name:        step.name || '',
                            location:    [step.maneuver.location[1], step.maneuver.location[0]],
                        });
                    });
                });

                return {
                    coords:   route.geometry.coordinates.map(c => [c[1], c[0]]),
                    distance: route.distance,
                    duration: route.duration,
                    legs:     route.legs.map(l => ({ distance: l.distance, duration: l.duration })),
                    steps:    steps,
                };
            });
    }

    /** Turn an OSRM maneuver into a readable instruction. */
    function describeStep(step) {
        const m    = step.maneuver;
        const road = step.name ? ' onto ' + step.name : '';
        const mod  = m.modifier || '';

        switch (m.type) {
            case 'depart':      return 'Head ' + (mod || 'out') + (step.name ? ' on ' + step.name : '');
            case 'arrive':      return 'Arrive at your destination';
            case 'turn':        return 'Turn ' + mod + road;
            case 'new name':    return 'Continue' + road;
            case 'merge':       return 'Merge ' + mod + road;
            case 'on ramp':     return 'Take the ramp ' + mod + road;
            case 'off ramp':    return 'Take the exit ' + mod + road;
            case 'fork':        return 'Keep ' + mod + ' at the fork' + road;
            case 'end of road': return 'At the end of the road, turn ' + mod + road;
            case 'roundabout':
            case 'rotary':      return 'At the roundabout, take exit ' + (m.exit || 1) + road;
            case 'continue':    return 'Continue ' + mod + road;
            default:            return 'Continue' + road;
        }
    }

    function formatDistance(metres) {
        return metres < 1000
            ? Math.round(metres) + ' m'
            : (metres / 1000).toFixed(1) + ' km';
    }

    function formatDuration(seconds) {
        const mins = Math.round(seconds / 60);
        if (mins < 60) return mins + ' min';
        return Math.floor(mins / 60) + ' h ' + (mins % 60) + ' min';
    }

    /* ── Map controller ────────────────────────────────────────────── */

    /**
     * @param {Object} options
     *   el            {string}    id of the map container
     *   places        {Array}     [{ id, name, lat, lng, color, category, ... }]
     *   labelled      {boolean}   draw the stop number inside each pin
     *   fitTo         {Array}     optional [lat,lng] list to fit the view to
     *   onSelect      {Function}  called with a place when its pin is clicked
     *   onUnavailable {Function}  called when Leaflet itself is missing
     *   onTileError   {Function}  called once if tiles fail to load
     */
    function create(options) {
        if (typeof L === 'undefined') {
            if (options.onUnavailable) options.onUnavailable();
            return null;
        }

        const map = L.map(options.el, {
            zoomControl: false,
            maxBounds: CEBU_BOUNDS,
            maxBoundsViscosity: 0.6,
        }).setView(CEBU_CENTRE, 10);

        let tilesFailed = false;
        const tiles = L.tileLayer(TILE_URL, {
            attribution: TILE_ATTRIB,
            maxZoom: 19,
            minZoom: 8,
        });

        tiles.on('tileerror', function () {
            if (tilesFailed) return;
            tilesFailed = true;
            if (options.onTileError) options.onTileError();
        });

        tiles.addTo(map);
        L.control.zoom({ position: 'topright' }).addTo(map);
        L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);

        const markers   = {};
        let routeLine   = null;
        let routeCasing = null;
        let meMarker    = null;
        let meAccuracy  = null;
        let watchId     = null;

        (options.places || []).forEach(function (place, index) {
            if (!place.lat || !place.lng) return;

            const marker = L.marker([place.lat, place.lng], {
                icon: pinIcon(place.color || '#8E3B2F',
                              options.labelled ? (place.label || index + 1) : '',
                              options.labelled ? 34 : 30),
                title: place.name,
            });

            marker.bindTooltip(place.name, { direction: 'top', offset: [0, -6], className: 'giya-tooltip' });
            marker.on('click', function () {
                if (options.onSelect) options.onSelect(place);
            });

            marker.addTo(map);
            markers[place.id] = marker;
        });

        if (options.fitTo && options.fitTo.length > 1) {
            map.fitBounds(options.fitTo, { padding: [60, 60] });
        } else if (options.fitTo && options.fitTo.length === 1) {
            map.setView(options.fitTo[0], 15);
        }

        return {
            map: map,
            markers: markers,

            focus(latlng, zoom) {
                map.flyTo(latlng, zoom || 16, { duration: 1 });
            },

            reset() {
                map.flyTo(CEBU_CENTRE, 10, { duration: .9 });
            },

            fitAll() {
                const pts = Object.values(markers).map(m => m.getLatLng());
                if (pts.length > 1) map.fitBounds(pts, { padding: [50, 50] });
            },

            setVisible(idSet) {
                Object.keys(markers).forEach(function (id) {
                    const marker = markers[id];
                    const show   = idSet === null || idSet.has(Number(id));
                    if (show && !map.hasLayer(marker))      marker.addTo(map);
                    else if (!show && map.hasLayer(marker)) map.removeLayer(marker);
                });
            },

            recolour(id, color, label) {
                const marker = markers[id];
                if (marker) marker.setIcon(pinIcon(color, label || '', 34));
            },

            /** Draw a route polyline and fit the view to it. */
            drawRoute(coords) {
                this.clearRoute();
                routeCasing = L.polyline(coords, {
                    color: '#fff', weight: 9, opacity: .85, lineJoin: 'round',
                }).addTo(map);
                routeLine = L.polyline(coords, {
                    color: '#8E3B2F', weight: 5, opacity: .95, lineJoin: 'round',
                }).addTo(map);
                map.fitBounds(routeLine.getBounds(), { padding: [60, 60] });
            },

            /** Straight-line fallback used when the routing service is unreachable. */
            drawDirectLine(points) {
                this.clearRoute();
                routeLine = L.polyline(points, {
                    color: '#8E3B2F', weight: 3, opacity: .7, dashArray: '8 6',
                }).addTo(map);
                if (points.length > 1) {
                    map.fitBounds(routeLine.getBounds(), { padding: [60, 60] });
                }
            },

            clearRoute() {
                if (routeLine)   { map.removeLayer(routeLine);   routeLine   = null; }
                if (routeCasing) { map.removeLayer(routeCasing); routeCasing = null; }
            },

            /** Show the device position once. Resolves with [lat, lng]. */
            locate() {
                const self = this;
                return new Promise(function (resolve, reject) {
                    if (!navigator.geolocation) {
                        reject(new Error('This browser does not provide location services.'));
                        return;
                    }
                    navigator.geolocation.getCurrentPosition(
                        function (pos) {
                            const ll = [pos.coords.latitude, pos.coords.longitude];
                            self.showMe(ll, pos.coords.accuracy);
                            resolve(ll);
                        },
                        function () { reject(new Error('Unable to determine your location.')); },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
                    );
                });
            },

            /** Continuously track the device while navigating. */
            watch(onMove) {
                const self = this;
                if (!navigator.geolocation || watchId !== null) return;

                watchId = navigator.geolocation.watchPosition(
                    function (pos) {
                        const ll = [pos.coords.latitude, pos.coords.longitude];
                        self.showMe(ll, pos.coords.accuracy);
                        if (onMove) onMove(ll, pos.coords.accuracy);
                    },
                    function () { /* transient GPS errors are ignored while tracking */ },
                    { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
                );
            },

            stopWatching() {
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }
            },

            showMe(latlng, accuracy) {
                if (meMarker)   map.removeLayer(meMarker);
                if (meAccuracy) map.removeLayer(meAccuracy);

                if (accuracy && accuracy < 2000) {
                    meAccuracy = L.circle(latlng, {
                        radius: accuracy, color: '#2E86DE', weight: 1,
                        fillColor: '#2E86DE', fillOpacity: .12,
                    }).addTo(map);
                }

                meMarker = L.marker(latlng, { icon: meIcon(), zIndexOffset: 1000 })
                    .addTo(map)
                    .bindTooltip('You are here', { direction: 'top', className: 'giya-tooltip' });
            },

            invalidate() {
                setTimeout(() => map.invalidateSize(), 60);
            },
        };
    }

    window.GiyaMapKit = {
        create: create,
        fetchRoute: fetchRoute,
        formatDistance: formatDistance,
        formatDuration: formatDuration,
        CEBU_CENTRE: CEBU_CENTRE,
    };
})(window, document);