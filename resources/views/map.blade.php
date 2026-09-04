@extends('layouts.app')

@section('title', 'Map')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/leaflet.css') }}?v={{ filemtime(public_path('assets/css/leaflet.css')) }}">
@endpush

@section('content')
<div style="max-width:1280px;margin:0 auto;padding:24px 20px 48px">

    <header class="mx-head">
        <span class="eyebrow">{{ __('giya.map.eyebrow') }}</span>
        <h1>{{ __('giya.map.title') }}</h1>
        <p>{{ __('giya.map.lead') }}</p>
    </header>

    <div id="mapNote" class="map-note" style="display:none">
        <span id="mapNoteText"></span>
        <button type="button" class="map-note-close" aria-label="Dismiss">&times;</button>
    </div>

    <div class="map-grid">

        <aside class="map-sidebar card">

            <div class="mx-controls">
                <h2 class="mx-title">{{ __('giya.map.explore') }}</h2>

                <label class="mx-search-field">
                    <i class="bi bi-search"></i>
                    <input type="search" id="mapSearch" placeholder="Search, or try &quot;open&quot; or &quot;mass&quot;"
                           aria-label="{{ __('giya.nav.search_label') }}">
                </label>

                <div class="mx-chips" role="group" aria-label="Filters">
                    <button type="button" class="cat-chip is-active" data-cat="Near">Near</button>

                    {{-- Chapel and Heritage are hidden: chapels are not
                         destinations a pilgrim travels to, and Heritage
                         overlapped every other category. --}}
                    @foreach ($categories as $category)
                        @continue (in_array($category, ['Chapel', 'Heritage']))
                        <button type="button" class="cat-chip" data-cat="{{ $category }}">{{ $category }}</button>
                    @endforeach

                </div>
            </div>

            <div class="mx-list-head" id="listHeading">{{ count($markers) }} results</div>
            <div id="churchList" class="mx-list"></div>

            {{-- Selection tray: rises from the bottom once churches are picked --}}
            <section id="routeBox" class="mx-tray" style="display:none" aria-label="Selected churches">
                <div class="mx-tray-head">
                    <span id="traySummary">0 churches selected</span>
                    <span id="routeDistance" class="mx-tray-distance"></span>
                </div>

                <ol id="routeStops" class="mx-tray-list"></ol>
                <p id="routeMode" class="route-mode"></p>

                <div class="mx-tray-actions">
                    <button type="button" id="btnDirections" class="btn btn-primary mx-plan">
                        <i class="bi bi-signpost-fill"></i> {{ __('giya.map.plan_route') }}
                    </button>
                    <button type="button" id="btnClearRoute" class="btn btn-ghost btn-sm">{{ __('giya.common.clear') }}</button>
                </div>
            </section>
        </aside>

        <div class="giya-map-shell">
            <div class="giya-map-canvas" id="giyaMap"></div>

            <div class="map-tools">
                <button type="button" class="map-tool" id="btnFullscreen"
                        title="{{ __('giya.map.fullscreen') }}" aria-label="{{ __('giya.map.fullscreen') }}">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>

                <button type="button" class="map-tool" id="btnLocate"
                        title="{{ __('giya.map.locate') }}" aria-label="{{ __('giya.map.locate') }}">
                    <i class="bi bi-geo-alt-fill"></i>
                </button>

                <div class="map-tool-pair">
                    <button type="button" class="map-tool" id="btnZoomIn"
                            title="{{ __('giya.map.zoom_in') }}" aria-label="{{ __('giya.map.zoom_in') }}">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                    <button type="button" class="map-tool" id="btnZoomOut"
                            title="{{ __('giya.map.zoom_out') }}" aria-label="{{ __('giya.map.zoom_out') }}">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/leaflet.js') }}?v={{ filemtime(public_path('assets/js/leaflet.js')) }}"></script>
<script src="{{ asset('assets/js/giya-leaflet.js') }}?v={{ filemtime(public_path('assets/js/giya-leaflet.js')) }}"></script>
<script>
(function () {
    const churches = @json($markers);

    /* Everyone may browse the map. Opening a church's own page - schedules,
       reviews, visit history - needs an account, so the list marks those links
       and explains before sending anyone to a login form. */
    const GUEST = @json(! auth()->check());

    /*
       Everything a guest cannot do yet goes through here, so the wording and
       the behaviour are the same wherever they hit it - the list, a marker
       popup, or the Plan Route button.

       `next` is where they are sent when they choose Sign in, and it is always
       the page they were trying to reach rather than /login. Those pages are
       behind auth, so the middleware records them as the intended page and
       returns the devotee there after signing in - which for Plan Route means
       arriving in the planner with the churches they had already picked.
    */
    function askToSignIn(what, next) {
        GiyaConfirm.ask({
            title:   'Sign in to continue',
            message: what + ' is for members. Creating an account is free, and you can keep browsing the map without one.',
            ok:      'Sign in',
            cancel:  'Not now',
            tone:    'primary',
            icon:    'person-plus-fill',
        }).then(function (ok) {
            if (ok && next) window.location.href = next;
        });

        return false;
    }

    function churchName(id) {
        const match = churches.filter(function (c) { return c.id === id; })[0];
        return match ? match.name : 'this church';
    }

    function churchUrl(id) {
        const match = churches.filter(function (c) { return c.id === id; })[0];
        return match ? match.details : null;
    }

    /* A rotating reminder. It advances each time the devotee dismisses it, so
       it stays worth reading instead of becoming wallpaper, and it remembers
       where it left off between visits. */
    const REMINDERS = [
        'Travel safely today, and may every church you enter bring you a little more peace than the last.',
        'Mass schedules change without notice, so it is always worth calling the parish before you set out.',
        'Dress modestly when you visit, with shoulders and knees covered, and remember that some chapels ask for silence.',
        'Start early and carry water with you. The midday heat in Cebu is unforgiving, especially on foot.',
        'Keep your belongings close in crowded churches, particularly during fiesta and on feast days.',
    ];

    let reminderIndex = Number(localStorage.getItem('giya_reminder') || 0) % REMINDERS.length;

    function showReminder() {
        showNote(REMINDERS[reminderIndex], 'info');
    }

    function nextReminder() {
        reminderIndex = (reminderIndex + 1) % REMINDERS.length;
        localStorage.setItem('giya_reminder', reminderIndex);
    }
    const note     = document.getElementById('mapNote');
    const listBox  = document.getElementById('churchList');
    const routeBox = document.getElementById('routeBox');

    let category = 'All';
    // A search from the home page arrives as ?q= - start from it.
    let query = new URLSearchParams(window.location.search).get('q') || '';
    query = query.trim().toLowerCase();
    let distances = {};
    let nearbyIds = [];

    /* Near is a radius, not a count. Five kilometres is a reasonable walk or a
       short ride in Metro Cebu, and wide enough that a devotee in the city
       centre still sees several destinations. */
    const NEAR_KM = 5;

    /** Great-circle distance in kilometres. */
    function haversineKm(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }
    let hasLocation = false;

    function showNote(message, kind) {
        if (!message || kind === 'clear') { note.style.display = 'none'; return; }
        document.getElementById('mapNoteText').textContent = message;
        note.className = 'map-note' + (kind === 'error' ? ' is-error' : '');
        note.style.display = 'flex';
    }

    note.querySelector('.map-note-close').addEventListener('click', function () {
        note.style.display = 'none';
        nextReminder();
    });

    /* Directions and Add to route come from the marker popups, which the map
       engine builds. It asks this before either one runs. */
    if (GUEST) {
        GiyaLeaflet.requireAccess(function (action, id) {
            return askToSignIn(
                action === 'directions'
                    ? 'Directions to ' + churchName(id)
                    : 'Adding ' + churchName(id) + ' to a route',
                churchUrl(id)
            );
        });
    }

    const map = GiyaLeaflet.browse({
        element: 'giyaMap',
        churches: churches,
        onStatus: showNote,
        onFallback: function () {
            // Offline tiles are a deployment concern, not something a devotee
            // can act on. The map works either way, so say nothing.
        },
        onLocated: function (me) {
            /* Distance to EVERY church, not just the handful the map returns as
               "nearest". Near is a radius, so it needs them all - otherwise a
               church 200 m away is excluded because it fell outside an
               arbitrary top-eight. */
            distances = {};
            hasLocation = true;

            churches.forEach(function (c) {
                distances[c.id] = haversineKm(me.lat, me.lng, c.lat, c.lng);
            });

            nearbyIds = churches
                .filter(function (c) { return distances[c.id] <= NEAR_KM; })
                .map(function (c) { return c.id; });

            renderList();

            showNote(nearbyIds.length
                ? nearbyIds.length + ' destination' + (nearbyIds.length === 1 ? '' : 's')
                    + ' within ' + NEAR_KM + ' km'
                : 'No destinations within ' + NEAR_KM + ' km. Showing the closest instead.',
                'info');
        },
        onSelect: function (id) {
            const row = document.querySelector('[data-church="' + id + '"]');
            if (row) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
        onRoute: function (stops, totalKm, meta) {
            const wrap = document.getElementById('routeStops');
            const note = document.getElementById('routeMode');
            document.getElementById('btnClearRoute').style.display = stops.length ? '' : 'none';

            // Redraw the list BEFORE returning, or the last tick stays filled
            // even though nothing is selected any more.
            if (!stops.length) {
                routeBox.style.display = 'none';
                renderList();
                return;
            }

            routeBox.style.display = 'block';
            document.getElementById('traySummary').textContent =
                stops.length + ' church' + (stops.length === 1 ? '' : 'es') + ' selected';
            document.getElementById('routeDistance').textContent =
                stops.length < 2 ? '' : totalKm.toFixed(1) + ' km';

            meta = meta || {};

            if (meta.mode === 'road') {
                note.textContent = 'Following roads' +
                    (meta.minutes ? ' \u00b7 about ' + meta.minutes + ' min by car' : '');
                note.className = 'route-mode is-road';
            } else if (stops.length < 2) {
                note.textContent = 'Add another church to build a route.';
                note.className = 'route-mode';
            } else if (meta.pending) {
                note.textContent = 'Straight-line estimate \u2014 checking roads\u2026';
                note.className = 'route-mode';
            } else {
                const why = {
                    no_key:   'no routing key configured',
                    quota:    'daily routing limit reached',
                    offline:  'no connection',
                    upstream: 'routing service unavailable',
                    empty:    'no road route found'
                }[meta.reason] || 'roads unavailable';

                note.textContent = 'Straight-line distance \u2014 ' + why;
                note.className = 'route-mode is-direct';
            }
            wrap.innerHTML = stops.map(function (s, i) {
                return '<li class="mx-tray-item">' +
                    '<span class="mx-tray-n">' + (i + 1) + '.</span>' +
                    '<span class="mx-tray-name">' + s.name + '</span>' +
                    '<button type="button" class="mx-stop-drop" data-drop="' + s.id + '" ' +
                            'aria-label="Remove ' + s.name + '">&times;</button>' +
                '</li>';
            }).join('');

            renderList();

            // Plan Route carries the selection into the planner - see below.
        }
    });

    /**
     * Search across name, location, category - and the words the chips used to
     * stand for.
     *
     * "open now" and "mass" were toggles taking permanent space for something
     * asked occasionally. Typing them is one action instead of finding and
     * pressing a chip, and it combines: "open basilica" narrows twice.
     */
    function matchesQuery(c) {
        if (!query) return true;

        const words = query.split(/\s+/).filter(Boolean);
        const haystack = (c.name + ' ' + (c.location || '') + ' ' + (c.category || '')).toLowerCase();

        return words.every(function (w) {
            if (w === 'open' || w === 'now' || w === 'bukas') return c.open;
            if (w === 'mass' || w === 'masses' || w === 'misa' || w === 'schedule') return c.masses;
            return haystack.indexOf(w) !== -1;
        });
    }

    function filtered() {
        return churches
            .filter(function (c) {
                if (category === 'Near') {
                    // No position yet: show everything rather than an empty
                    // page. Once located, only what is inside the radius.
                    if (!hasLocation) return true;
                    return distances[c.id] != null && distances[c.id] <= NEAR_KM;
                }
                return category === 'All' || c.category === category;
            })
            .filter(function (c) { return matchesQuery(c); })
            .sort(function (a, b) {
                const da = distances[a.id], db = distances[b.id];
                if (da != null && db != null) return da - db;
                if (da != null) return -1;
                if (db != null) return 1;
                return a.name.localeCompare(b.name);
            });
    }

    function renderList() {
        const list = filtered();
        const chosen = map.selected();

        // Say plainly when Near is showing everything because no position is
        // known yet - a count with no explanation reads as a failed filter.
        if (category === 'Near' && !nearbyIds.length) {
            document.getElementById('listHeading').textContent =
                list.length + ' destinations - turn on location to sort by distance';
        } else
        document.getElementById('listHeading').textContent =
            list.length + ' result' + (list.length === 1 ? '' : 's');

        // Keep the map showing exactly what the list shows.
        if (map.showOnly) {
            map.showOnly(list.map(function (c) { return c.id; }));
        }

        if (!list.length) {
            listBox.innerHTML = '<p class="mx-empty">No churches match these filters.</p>';
            return;
        }

        listBox.innerHTML = list.map(function (c) {
            const picked = chosen.indexOf(c.id) !== -1;

            return '<article class="mx-row' + (picked ? ' is-picked' : '') + '" data-church="' + c.id + '">' +
                '<span class="mx-thumb">' +
                    (c.image
                        ? '<img src="' + c.image + '" alt="" loading="lazy" onerror="this.parentNode.classList.add(\'is-empty\')">'
                        : '<i class="bi bi-building"></i>') +
                '</span>' +
                '<div class="mx-row-body">' +
                    '<h3>' +
                        (c.details
                            ? '<a href="' + c.details + '" data-details="' + c.details + '" data-church="' + c.name + '">' +
                                  c.name + (GUEST ? ' <i class="bi bi-lock-fill mx-lock" title="Sign in to view"></i>' : '') +
                              '</a>'
                            : c.name) +
                    '</h3>' +
                    '<p class="mx-row-place">' +
                        '<i class="bi bi-geo-alt-fill"></i>' + c.location +
                        (distances[c.id] != null ? ' &middot; ' + distances[c.id].toFixed(1) + ' km' : '') +
                    '</p>' +
                    '<p class="mx-row-tags">' +
                        (c.rating > 0 ? '<span class="mx-star"><i class="bi bi-star-fill"></i>' + c.rating.toFixed(1) + '</span>' : '') +
                        (c.open ? '<span class="mx-tag is-open">Open</span>' : '') +
                        '<span class="mx-tag">' + c.category + '</span>' +
                    '</p>' +
                '</div>' +
                '<button type="button" class="mx-pick' + (picked ? ' is-on' : '') + '" ' +
                        'data-add="' + c.id + '" role="switch" aria-checked="' + picked + '" ' +
                        'aria-label="Select ' + c.name + ' for the route">' +
                    '<i class="bi bi-check-lg"></i>' +
                '</button>' +
            '</article>';
        }).join('');
    }

    /* ---- zoom ---- */
    document.getElementById('btnZoomIn').addEventListener('click', function () { map.map.zoomIn(); });
    document.getElementById('btnZoomOut').addEventListener('click', function () { map.map.zoomOut(); });

    /* ---- fullscreen ---- */
    const shell = document.querySelector('.map-grid');
    const fsBtn = document.getElementById('btnFullscreen');

    fsBtn.addEventListener('click', function () {
        const on = shell.classList.toggle('is-fullscreen');

        fsBtn.innerHTML = on
            ? '<i class="bi bi-fullscreen-exit"></i>'
            : '<i class="bi bi-arrows-fullscreen"></i>';
        fsBtn.title = on ? 'Exit fullscreen' : 'Fullscreen';

        // Stop the page behind scrolling while the map covers it.
        document.body.style.overflow = on ? 'hidden' : '';

        // Leaflet has to be told its container changed size.
        setTimeout(function () { map.map.invalidateSize(); }, 120);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && shell.classList.contains('is-fullscreen')) {
            fsBtn.click();
        }
    });

    const locBtn = document.getElementById('btnLocate');
    locBtn.addEventListener('click', function () {
        locBtn.classList.add('is-busy');
        map.locate(function () { locBtn.classList.remove('is-busy'); });
        // Release the button even if the fix fails or is denied.
        setTimeout(function () { locBtn.classList.remove('is-busy'); }, 13000);
    });
    document.getElementById('btnClearRoute').addEventListener('click', function () { map.clearRoute(); });

    /* Plan Route hands the selection to the itinerary planner, in the order the
       map worked out - so the planner receives a route, not a bag of churches. */
    document.getElementById('btnDirections').addEventListener('click', function () {
        const ordered = (map.orderedStops ? map.orderedStops() : [])
            .map(function (s) { return s.id; })
            .filter(Boolean);

        const ids = ordered.length ? ordered : map.selected();

        if (!ids.length) {
            showNote(@json(__('giya.map.pick_first')), 'error');
            return;
        }

        const planner = @json(route('plan.create')) + '?stops=' + ids.join(',');

        // Sending a guest to the planner URL rather than to /login is what
        // saves their selection: the planner is behind auth, so it becomes
        // the intended page and they land there with these churches ready.
        if (GUEST) { askToSignIn('Planning a route', planner); return; }

        window.location.href = planner;
    });

    document.getElementById('mapSearch').addEventListener('input', function () {
        query = this.value.trim().toLowerCase();
        renderList();
    });

    document.querySelectorAll('.cat-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (this.dataset.flag) {
                flags[this.dataset.flag] = !flags[this.dataset.flag];
                this.classList.toggle('is-active', flags[this.dataset.flag]);
            } else {
                document.querySelectorAll('.cat-chip:not(.is-toggle)').forEach(function (b) {
                    b.classList.remove('is-active');
                });
                this.classList.add('is-active');
                category = this.dataset.cat;

                if (category === 'Near') {
                    if (!hasLocation) {
                        showNote('Finding churches near you...', 'info');
                    }
                    map.locate();
                }
            }
            renderList();
        });
    });

    document.addEventListener('click', function (e) {
        // The tick toggles: a second press deselects, no Clear needed.
        const pick = e.target.closest('[data-add]');
        if (pick) {
            e.preventDefault();
            e.stopPropagation();

            if (GUEST) {
                const id = Number(pick.dataset.add);
                askToSignIn('Adding ' + churchName(id) + ' to a route', churchUrl(id));
                return;
            }

            // Flip the control immediately, so it responds even if the route
            // callback takes a different path afterwards.
            const on = !pick.classList.contains('is-on');
            pick.classList.toggle('is-on', on);
            pick.setAttribute('aria-checked', String(on));
            pick.closest('.mx-row')?.classList.toggle('is-picked', on);

            map.toggleStop(Number(pick.dataset.add));
            return;
        }

        const drop = e.target.closest('[data-drop]');
        if (drop) { map.removeStop(Number(drop.dataset.drop)); return; }

        // The name opens the church's own page.
        const link = e.target.closest('[data-details]');
        if (link) {
            e.stopPropagation();

            /* The name is a real <a href>, so the browser would follow it
               before any of this ran. Both branches below navigate for
               themselves, and the guest branch has a dialog to show first. */
            e.preventDefault();

            /* A guest may browse the map freely, but a church's own page -
               its schedules, reviews and visit history - needs an account.
               Saying so here beats bouncing them to a login form with no
               explanation of what they clicked or why. */
            if (GUEST) {
                askToSignIn(
                    'Mass schedules, reviews and photos for ' + (link.dataset.church || 'this church'),
                    link.dataset.details
                );
                return;
            }

            window.location.href = link.dataset.details;
            return;
        }

        // Anywhere else on the row centres the map on it.
        const row = e.target.closest('[data-church]');
        if (row) map.focus(Number(row.dataset.church));
    });

    showReminder();
    if (query) {
        document.getElementById('mapSearch').value = query;
    }

    /* Stops sent back from the planner arrive as ?stops=3,7,1 - tick them and
       frame the map on them, so the devotee sees their route rather than a
       fresh map they have to rebuild. */
    (function () {
        const raw = new URLSearchParams(window.location.search).get('stops');
        if (!raw) return;

        const ids = raw.split(',')
            .map(function (n) { return parseInt(n, 10); })
            .filter(function (n) { return !isNaN(n); });

        const known = ids.filter(function (id) {
            return churches.some(function (c) { return c.id === id; });
        });

        if (!known.length) return;

        known.forEach(function (id) { map.addStop(id); });
        renderList();
    })();

    /* Focus a church passed as ?church=. This used to sit inside onLocated,
       so it only ran when geolocation succeeded - a link to a church did
       nothing if location was refused. */
    const initialChurchId = Number(new URLSearchParams(window.location.search).get('church'));
    if (initialChurchId) {
        setTimeout(function () { map.focus(initialChurchId); }, 0);
    }

    renderList();
})();
</script>
@endpush
