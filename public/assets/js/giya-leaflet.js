/* ============================================================================
   GIYA - Leaflet integration
   ----------------------------------------------------------------------------
   Leaflet is served from /assets/js/leaflet.js, so the library never needs the
   network. Tiles try the local folder first and fall back to OpenStreetMap when
   a tile is missing, which lets the map work before `php artisan giya:tiles`
   has been run.

   Two entry points:
     GiyaLeaflet.browse(config)   devotee map - locate me, plan a route
     GiyaLeaflet.picker(config)   admin map  - click to pin, click a pin to edit

   Church pins render as the church's own photo inside a teardrop frame.
   ============================================================================ */
window.GiyaLeaflet = (function () {
    'use strict';

    var CEBU = { lat: 10.3157, lng: 123.8854 };

    /* ---------------------------------------------------------------- tiles */

    function tileLayer(cfg) {
        var local = L.tileLayer(cfg.tileUrl || '/tiles/{z}/{x}/{y}.png', {
            minZoom: 9, maxZoom: 18, subdomains: [''],
            attribution: '&copy; OpenStreetMap contributors',
            errorTileUrl: '/tiles/blank.png'
        });

        // Count misses; if the local cache clearly is not there, swap once.
        var misses = 0, swapped = false;
        local.on('tileerror', function (e) {
            if (swapped || ++misses < 4) return;
            swapped = true;
            e.target.setUrl('https://tile.openstreetmap.org/{z}/{x}/{y}.png');
            e.target.options.subdomains = [''];
            if (cfg.onFallback) cfg.onFallback();
        });

        return local;
    }

    /* ---------------------------------------------------------------- pins */

    /**
     * A teardrop frame with the church photo inside. Falls back to a lettered
     * disc when the church has no image, so a pin always renders.
     */
    function churchIcon(church, opts) {
        opts = opts || {};
        var size = opts.size || 46;
        var ring = opts.selected ? 'var(--gold)' : '#fff';
        var glow = opts.selected ? '0 0 0 3px rgba(215,169,74,.55),' : '';

        var inner = church.image
            ? '<img src="' + church.image + '" alt="" ' +
              'style="width:100%;height:100%;object-fit:cover;display:block">'
            : '<span style="display:flex;width:100%;height:100%;align-items:center;' +
              'justify-content:center;background:' + (church.color || '#8E3B2F') +
              ';color:#fff;font-weight:700;font-size:' + Math.round(size * 0.42) + 'px">' +
              (church.name || '?').charAt(0) + '</span>';

        var html =
            '<div class="giya-pin' + (opts.selected ? ' is-selected' : '') + '" ' +
                 'style="width:' + size + 'px;height:' + size + 'px">' +
              '<div class="giya-pin-frame" style="border-color:' + ring + ';' +
                   'box-shadow:' + glow + '0 3px 10px rgba(0,0,0,.4)">' + inner + '</div>' +
              '<span class="giya-pin-tail" style="border-top-color:' + ring + '"></span>' +
            '</div>';

        return L.divIcon({
            html: html,
            className: 'giya-pin-wrap',
            iconSize: [size, size * 1.3],
            iconAnchor: [size / 2, size * 1.3],
            popupAnchor: [0, -size * 1.15]
        });
    }

    function userIcon() {
        return L.divIcon({
            html: '<span class="giya-me"><span class="giya-me-dot"></span></span>',
            className: 'giya-pin-wrap',
            iconSize: [22, 22],
            iconAnchor: [11, 11]
        });
    }

    /* ------------------------------------------------------------- geometry */

    function km(a, b) {
        var R = 6371, dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
        var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) *
                Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
    }

    function rad(d) { return d * Math.PI / 180; }

    /** Nearest-neighbour ordering from a start point. */
    function orderStops(start, stops) {
        var left = stops.slice(), here = start, out = [];
        while (left.length) {
            var best = 0, bestD = Infinity;
            for (var i = 0; i < left.length; i++) {
                var d = km(here, left[i]);
                if (d < bestD) { bestD = d; best = i; }
            }
            here = left[best];
            out.push(left.splice(best, 1)[0]);
        }
        return out;
    }

    function escapeHtml(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* =========================================================== DEVOTEE MAP */

    function browse(cfg) {
        var map = L.map(cfg.element, {
            center: [cfg.center ? cfg.center.lat : CEBU.lat, cfg.center ? cfg.center.lng : CEBU.lng],
            zoom: cfg.zoom || 12,
            zoomControl: false      // replaced by the .map-tools stack
        });

        tileLayer(cfg).addTo(map);

        var markers = {};
        var churches = (cfg.churches || []).filter(function (c) { return c.lat && c.lng; });
        var group = L.layerGroup().addTo(map);
        var routeLine = null;
        var meMarker = null;
        var me = null;
        var selected = [];

        churches.forEach(function (c) {
            var m = L.marker([c.lat, c.lng], { icon: churchIcon(c), title: c.name })
                .bindPopup(popupHtml(c))
                .addTo(group);

            m.on('click', function () { if (cfg.onSelect) cfg.onSelect(c.id); });
            markers[c.id] = m;
        });

        function frameAll() {
            if (!churches.length) return;
            map.fitBounds(L.latLngBounds(churches.map(function (c) {
                return [c.lat, c.lng];
            })).pad(0.18));
        }

        function popupHtml(c) {
            return '<div class="giya-popup">' +
                (c.image ? '<img src="' + c.image + '" alt="">' : '') +
                '<strong>' + escapeHtml(c.name) + '</strong>' +
                '<span>' + escapeHtml(c.category || '') + ' &middot; ' + escapeHtml(c.location || '') + '</span>' +
                (c.hours ? '<span>' + escapeHtml(c.hours) + '</span>' : '') +
                (c.details
                    ? '<a class="giya-popup-btn is-secondary" href="' + escapeHtml(c.details) + '">See details</a>'
                    : '') +
                '<button type="button" class="giya-popup-btn"' +
                        ' onclick="GiyaLeaflet.addStop(' + c.id + ')">Add to route</button>' +
                '</div>';
        }

        /* ---- locate me ---- */
        function locate(onDone) {
            if (!navigator.geolocation) {
                if (cfg.onStatus) cfg.onStatus('This browser cannot share a location.', 'error');
                return;
            }

            if (cfg.onStatus) cfg.onStatus('Finding your location…', 'info');

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    me = { lat: pos.coords.latitude, lng: pos.coords.longitude };

                    if (meMarker) map.removeLayer(meMarker);
                    meMarker = L.marker([me.lat, me.lng], { icon: userIcon(), zIndexOffset: 900 })
                        .bindPopup('You are here')
                        .addTo(map);

                    L.circle([me.lat, me.lng], {
                        radius: pos.coords.accuracy,
                        color: '#2563EB', weight: 1,
                        fillColor: '#2563EB', fillOpacity: .08
                    }).addTo(map);

                    map.setView([me.lat, me.lng], 14);

                    if (cfg.onLocated) cfg.onLocated(me, nearest(me));
                    if (cfg.onStatus) cfg.onStatus('', 'clear');
                    if (onDone) onDone();
                },
                function (err) {
                    var msg = err.code === 1
                        ? 'Location permission was denied. Allow it in the address bar to use this.'
                        : 'Could not get a location fix. Try again outdoors or check GPS.';
                    if (cfg.onStatus) cfg.onStatus(msg, 'error');
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
            );
        }

        function nearest(from) {
            return churches
                .map(function (c) {
                    return { church: c, km: km(from, { lat: c.lat, lng: c.lng }) };
                })
                .sort(function (a, b) { return a.km - b.km; })
                .slice(0, 8);
        }

        /* ---- route ----
           Two passes. The straight line appears immediately so the map never
           looks frozen, then the road-following geometry replaces it when the
           routing service answers. If that call fails for any reason - no key,
           quota spent, no connection - the straight line simply stays. */
        function drawRoute() {
            if (routeLine) { map.removeLayer(routeLine); routeLine = null; }

            var stops = selected.map(function (id) {
                var c = churches.filter(function (x) { return x.id === id; })[0];
                return c ? { id: c.id, name: c.name, lat: c.lat, lng: c.lng } : null;
            }).filter(Boolean);

            if (!stops.length) {
                if (cfg.onRoute) cfg.onRoute([], 0, { mode: 'none' });
                return;
            }

            var start = me || { lat: stops[0].lat, lng: stops[0].lng };
            var ordered = orderStops(start, stops);
            var path = (me ? [[me.lat, me.lng]] : []).concat(
                ordered.map(function (s) { return [s.lat, s.lng]; })
            );

            paint(path, true);

            var direct = 0;
            for (var i = 1; i < path.length; i++) {
                direct += km({ lat: path[i - 1][0], lng: path[i - 1][1] },
                             { lat: path[i][0],     lng: path[i][1] });
            }

            if (cfg.onRoute) cfg.onRoute(ordered, direct, { mode: 'direct', pending: true });

            fetchRoads(path, ordered, direct);
        }

        /** Draw the line. `dashed` marks it as an approximation. */
        function paint(path, dashed) {
            if (routeLine) { map.removeLayer(routeLine); }

            routeLine = L.layerGroup().addTo(map);

            L.polyline(path, {
                color: '#8E3B2F', weight: dashed ? 4 : 6, opacity: .85,
                dashArray: dashed ? '1 8' : null, lineCap: 'round', lineJoin: 'round'
            }).addTo(routeLine);

            L.polyline(path, {
                color: '#D7A94A', weight: dashed ? 1.5 : 2, opacity: .9
            }).addTo(routeLine);

            map.fitBounds(L.polyline(path).getBounds().pad(0.2));
        }

        /** Ask the server for road geometry; stay silent on failure. */
        function fetchRoads(path, ordered, directKm) {
            var token = document.querySelector('meta[name="csrf-token"]');
            if (!token || path.length < 2) { return; }

            fetch('/api/route', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token.content
                },
                body: JSON.stringify({
                    stops: path.map(function (p) { return { lat: p[0], lng: p[1] }; })
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok || !data.geometry || !data.geometry.length) {
                        if (cfg.onRoute) {
                            cfg.onRoute(ordered, directKm, { mode: 'direct', reason: data.reason });
                        }
                        return;
                    }

                    paint(data.geometry, false);

                    if (cfg.onRoute) {
                        cfg.onRoute(ordered, data.distance_km, {
                            mode: 'road',
                            minutes: data.duration_min,
                            steps: data.steps || [],
                            cached: !!data.cached
                        });
                    }
                })
                .catch(function () {
                    if (cfg.onRoute) {
                        cfg.onRoute(ordered, directKm, { mode: 'direct', reason: 'offline' });
                    }
                });
        }

        function addStop(id) {
            if (selected.indexOf(id) === -1) selected.push(id);
            highlight();
            drawRoute();
        }

        /** Add if absent, remove if present - what a tick control should do. */
        function toggleStop(id) {
            if (selected.indexOf(id) === -1) {
                selected.push(id);
            } else {
                selected = selected.filter(function (x) { return x !== id; });
            }
            highlight();
            drawRoute();
        }

        function removeStop(id) {
            selected = selected.filter(function (x) { return x !== id; });
            highlight();
            drawRoute();
        }

        function clearRoute() {
            selected = [];
            highlight();
            drawRoute();
        }

        function highlight() {
            churches.forEach(function (c) {
                var m = markers[c.id];
                if (m) m.setIcon(churchIcon(c, { selected: selected.indexOf(c.id) !== -1 }));
            });
        }

        /**
         * Show only the churches whose ids are given, and frame them.
         *
         * The map should answer the same question the list is answering. With
         * every pin left on screen, searching "san" narrows the list to three
         * while the map still shows nine - so the map contradicts the list.
         */
        function showOnly(ids) {
            var shown = [];

            churches.forEach(function (c) {
                var m = markers[c.id];
                if (!m) return;

                if (ids.indexOf(c.id) !== -1) {
                    if (!map.hasLayer(m)) m.addTo(group);
                    shown.push([c.lat, c.lng]);
                } else if (map.hasLayer(m)) {
                    group.removeLayer(m);
                }
            });

            if (!shown.length) return;

            // One match reads better centred than filling the screen.
            if (shown.length === 1) {
                map.setView(shown[0], 15, { animate: true });
            } else {
                map.fitBounds(L.latLngBounds(shown).pad(0.22), { animate: true });
            }
        }

        function focus(id) {
            var m = markers[id];
            if (!m) return;
            map.setView(m.getLatLng(), 16, { animate: true });
            m.openPopup();
        }

        /** Hand the ordered stops to Google Maps for turn-by-turn (needs data). */
        function externalDirections() {
            var stops = selected.map(function (id) {
                return churches.filter(function (c) { return c.id === id; })[0];
            }).filter(Boolean);

            if (!stops.length) return null;

            var ordered = orderStops(me || { lat: stops[0].lat, lng: stops[0].lng }, stops);
            var pts = ordered.map(function (s) { return s.lat + ',' + s.lng; });
            var origin = me ? me.lat + ',' + me.lng : pts.shift();
            var dest = pts.pop();

            return 'https://www.google.com/maps/dir/?api=1' +
                   '&origin=' + origin + '&destination=' + dest +
                   (pts.length ? '&waypoints=' + pts.join('|') : '') +
                   '&travelmode=driving';
        }

        // Size first, then frame - fitBounds against a stale size picks the
        // wrong centre and zoom.
        setTimeout(function () { map.invalidateSize(); frameAll(); }, 200);
        window.addEventListener('resize', function () { map.invalidateSize(); });

        return {
            map: map, locate: locate, focus: focus, frameAll: frameAll,
            showOnly: showOnly,
            addStop: addStop, toggleStop: toggleStop,
            removeStop: removeStop, clearRoute: clearRoute,
            externalDirections: externalDirections,
            selected: function () { return selected.slice(); },
            distanceTo: function (c) { return me ? km(me, c) : null; }
        };
    }

    /* ============================================================= ADMIN MAP */

    function picker(cfg) {
        var map = L.map(cfg.element, {
            center: [CEBU.lat, CEBU.lng],
            zoom: cfg.zoom || 12
        });

        tileLayer(cfg).addTo(map);

        var existing = L.layerGroup().addTo(map);
        var pin = null;

        function report(latlng) {
            var lat = Math.round(latlng.lat * 1e8) / 1e8;
            var lng = Math.round(latlng.lng * 1e8) / 1e8;
            if (cfg.latInput) document.querySelector(cfg.latInput).value = lat.toFixed(8);
            if (cfg.lngInput) document.querySelector(cfg.lngInput).value = lng.toFixed(8);
            if (cfg.onPin) cfg.onPin(lat, lng);
        }

        function place(lat, lng, silent) {
            var ll = L.latLng(lat, lng);
            if (!pin) {
                pin = L.marker(ll, {
                    icon: churchIcon({ name: 'New', color: '#D7A94A' }, { selected: true, size: 44 }),
                    draggable: true, zIndexOffset: 1000
                }).addTo(map);
                pin.on('dragend', function (e) { report(e.target.getLatLng()); });
            } else {
                pin.setLatLng(ll);
            }
            if (!silent) report(ll);
        }

        map.on('click', function (e) { place(e.latlng.lat, e.latlng.lng); });

        function draw(churches) {
            existing.clearLayers();
            (churches || []).forEach(function (c) {
                if (!c.lat || !c.lng) return;

                L.marker([c.lat, c.lng], {
                    icon: churchIcon(c, { size: 42 }),
                    opacity: c.active ? 1 : 0.55,
                    title: c.name
                })
                    .on('click', function () { if (cfg.onChurchClick) cfg.onChurchClick(c); })
                    .bindTooltip(c.name, { direction: 'top', offset: [0, -50] })
                    .addTo(existing);
            });
        }

        draw(cfg.churches);

        setTimeout(function () { map.invalidateSize(); }, 200);
        window.addEventListener('resize', function () { map.invalidateSize(); });

        return {
            map: map, place: place, draw: draw,
            clear: function () { if (pin) { map.removeLayer(pin); pin = null; } },
            focus: function (lat, lng) { map.setView([lat, lng], 16); }
        };
    }

    /* =========================================================== PILGRIMAGE
       A numbered, ordered route. Pins carry their stop number and recolour as
       stops are marked visited. Same road-following logic as browse().
       --------------------------------------------------------------------- */
    function pilgrimage(cfg) {
        var map = L.map(cfg.element, { center: [CEBU.lat, CEBU.lng], zoom: 12 });
        tileLayer(cfg).addTo(map);

        var pins = {};
        var line = null;
        var stops = (cfg.stops || []).filter(function (s) { return s.lat && s.lng; });

        function numberedIcon(stop, state) {
            var fill = state === 'visited' ? '#6B9B5A'
                     : state === 'current' ? '#D7A94A' : '#8E3B2F';
            var text = state === 'visited' ? '&#10003;' : stop.order;

            return L.divIcon({
                html: '<div class="giya-numpin' + (state === 'current' ? ' is-current' : '') + '">' +
                        '<span class="giya-numpin-body" style="background:' + fill + '">' + text + '</span>' +
                        '<span class="giya-numpin-tail" style="border-top-color:' + fill + '"></span>' +
                      '</div>',
                className: 'giya-pin-wrap',
                iconSize: [34, 44],
                iconAnchor: [17, 44],
                popupAnchor: [0, -40]
            });
        }

        /* The popup is where a stop is marked visited: the devotee is looking
           at the pin for the church they are standing outside, so the action
           belongs there rather than on a button in the corner. */
        function popupFor(s) {
            return '<div class="giya-popup">' +
                (s.image ? '<img src="' + s.image + '" alt="">' : '') +
                '<strong>' + escapeHtml(s.name) + '</strong>' +
                '<span>Stop ' + s.order + ' &middot; ' + escapeHtml(s.location || '') + '</span>' +
                (s.visited
                    ? '<span class="giya-popup-done">Visited</span>'
                    : '<button type="button" class="giya-popup-btn" ' +
                          'onclick="GiyaActive.mark(' + s.id + ')">Mark visited</button>') +
                '</div>';
        }

        function refreshPopup(id) {
            var s = stops.filter(function (x) { return x.id === id; })[0];
            if (s && pins[id]) pins[id].setPopupContent(popupFor(s));
        }

        function stateOf(stop, currentId) {
            if (stop.visited) return 'visited';
            return stop.id === currentId ? 'current' : 'pending';
        }

        function draw(currentId) {
            stops.forEach(function (s) {
                var state = stateOf(s, currentId);

                if (pins[s.id]) {
                    pins[s.id].setIcon(numberedIcon(s, state));
                    return;
                }

                pins[s.id] = L.marker([s.lat, s.lng], {
                    icon: numberedIcon(s, state),
                    zIndexOffset: state === 'current' ? 800 : 0
                })
                    .bindPopup(popupFor(s))
                    .addTo(map);
            });
        }

        function route() {
            var path = stops.map(function (s) { return [s.lat, s.lng]; });
            if (path.length < 2) {
                if (path.length === 1) map.setView(path[0], 15);
                return;
            }

            paint(path, true);
            askRoads(path);
        }

        function paint(path, dashed) {
            if (line) map.removeLayer(line);

            line = L.layerGroup().addTo(map);

            L.polyline(path, {
                color: '#8E3B2F', weight: dashed ? 3 : 6, opacity: .8,
                dashArray: dashed ? '2 9' : null, lineCap: 'round', lineJoin: 'round'
            }).addTo(line);

            if (!dashed) {
                L.polyline(path, { color: '#D7A94A', weight: 2, opacity: .9 }).addTo(line);
            }

            map.fitBounds(L.polyline(path).getBounds().pad(0.18));
        }

        function askRoads(path) {
            var token = document.querySelector('meta[name="csrf-token"]');
            if (!token) return;

            fetch('/api/route', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token.content },
                body: JSON.stringify({
                    stops: path.map(function (p) { return { lat: p[0], lng: p[1] }; })
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok && d.geometry && d.geometry.length) {
                        paint(d.geometry, false);
                        if (cfg.onRoads) cfg.onRoads(d);
                    }
                })
                .catch(function () { /* keep the dashed line */ });
        }

        draw(cfg.currentId);
        route();

        setTimeout(function () { map.invalidateSize(); }, 200);
        window.addEventListener('resize', function () { map.invalidateSize(); });

        /* Show where the devotee is, and how far the next stop is. */
        var meMarker = null, meRing = null;

        function showMe(here, accuracy) {
            if (meMarker) map.removeLayer(meMarker);
            if (meRing) map.removeLayer(meRing);

            meRing = L.circle([here.lat, here.lng], {
                radius: accuracy || 30,
                color: '#2563EB', weight: 1,
                fillColor: '#2563EB', fillOpacity: .08
            }).addTo(map);

            meMarker = L.marker([here.lat, here.lng], {
                icon: userIcon(), zIndexOffset: 900
            }).bindPopup('You are here').addTo(map);
        }

        function locate(done) {
            if (!navigator.geolocation) {
                if (cfg.onStatus) cfg.onStatus('This browser cannot share a location.', 'error');
                if (done) done();
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    var here = { lat: pos.coords.latitude, lng: pos.coords.longitude };

                    if (meMarker) map.removeLayer(meMarker);
                    if (meRing) map.removeLayer(meRing);

                    meRing = L.circle([here.lat, here.lng], {
                        radius: pos.coords.accuracy,
                        color: '#2563EB', weight: 1,
                        fillColor: '#2563EB', fillOpacity: .08
                    }).addTo(map);

                    meMarker = L.marker([here.lat, here.lng], {
                        icon: userIcon(), zIndexOffset: 900
                    }).bindPopup('You are here').addTo(map);

                    map.setView([here.lat, here.lng], 15);

                    // Distance to the first stop still to be visited.
                    var next = stops.filter(function (st) { return !st.visited; })[0];
                    if (next && cfg.onLocated) {
                        cfg.onLocated(here, next, km(here, { lat: next.lat, lng: next.lng }));
                    }
                    if (done) done();
                },
                function (err) {
                    if (cfg.onStatus) {
                        cfg.onStatus(err.code === 1
                            ? 'Location permission was denied.'
                            : 'Could not get a location fix.', 'error');
                    }
                    if (done) done();
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
            );
        }

        /*
           Arrival detection.

           A pilgrim standing outside a church should not have to tell the app
           they are there. watchPosition runs for the length of the pilgrimage,
           and when the devotee comes within ARRIVE_M of an unvisited stop the
           page is told so it can record the visit.

           75 m is chosen deliberately: phone GPS in a built-up area is good to
           roughly 20-40 m, and a church occupies a fair footprint, so a tighter
           radius would miss arrivals at the door. Wider would fire while
           walking past on the far side of the street.
        */
        var ARRIVE_M = 75;
        var watchId = null;
        var announced = {};

        function track() {
            if (!navigator.geolocation || watchId !== null) return;

            watchId = navigator.geolocation.watchPosition(
                function (pos) {
                    var here = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                    showMe(here, pos.coords.accuracy);

                    var next = stops.filter(function (st) { return !st.visited; })[0];
                    if (next && cfg.onLocated) {
                        cfg.onLocated(here, next, km(here, { lat: next.lat, lng: next.lng }));
                    }

                    stops.forEach(function (st) {
                        if (st.visited || announced[st.id]) return;

                        var metres = km(here, { lat: st.lat, lng: st.lng }) * 1000;

                        if (metres <= ARRIVE_M) {
                            announced[st.id] = true;      // announce once per session
                            if (cfg.onArrive) cfg.onArrive(st, Math.round(metres));
                        }
                    });
                },
                function (err) {
                    if (cfg.onStatus) {
                        cfg.onStatus(err.code === 1
                            ? 'Location is off, so stops will not tick themselves. Turn it on to check in automatically.'
                            : 'Could not follow your location.', 'error');
                    }
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
            );
        }

        function untrack() {
            if (watchId !== null) navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        return {
            map: map,
            locate: locate,
            track: track,
            untrack: untrack,
            refreshPopup: refreshPopup,
            frameAll: function () { route(); },
            refresh: function (visitedIds, currentId) {
                stops.forEach(function (s) {
                    s.visited = visitedIds.indexOf(s.id) !== -1;
                    refreshPopup(s.id);
                });
                draw(currentId);
            },
            focus: function (id) {
                var m = pins[id];
                if (m) { map.setView(m.getLatLng(), 16); m.openPopup(); }
            }
        };
    }

    /* Popup buttons call through this; the page assigns the live instance. */
    var current = null;

    return {
        browse: function (cfg) { current = browse(cfg); return current; },
        picker: picker,
        pilgrimage: pilgrimage,
        addStop: function (id) { if (current) current.addStop(id); },
        churchIcon: churchIcon,
        km: km
    };
})();
