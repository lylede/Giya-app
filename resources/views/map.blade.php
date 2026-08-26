@extends('layouts.app')

@section('title', 'Map')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/leaflet.css') }}?v={{ filemtime(public_path('assets/css/leaflet.css')) }}">
@endpush

@section('content')
<div style="max-width:1280px;margin:0 auto;padding:24px 20px 48px">

    <header class="mx-head">
        <span class="eyebrow">EXPLORE</span>
        <h1>Map of Metro Cebu</h1>
        <p>Find churches near you, then build a route through the ones you want to visit.</p>
    </header>

    <div id="mapNote" class="map-note" style="display:none">
        <span id="mapNoteText"></span>
        <button type="button" class="map-note-close" aria-label="Dismiss">&times;</button>
    </div>

    <div class="map-grid">

        <aside class="map-sidebar card">

            <div class="mx-controls">
                <h2 class="mx-title">Explore Churches</h2>

                <label class="mx-search-field">
                    <i class="bi bi-search"></i>
                    <input type="search" id="mapSearch" placeholder="Search churches..."
                           aria-label="Search churches">
                </label>

                <div class="mx-chips" role="group" aria-label="Filters">
                    <button type="button" class="cat-chip is-active" data-cat="Near">Near</button>
                    @foreach ($categories as $category)
                        <button type="button" class="cat-chip" data-cat="{{ $category }}">{{ $category }}</button>
                    @endforeach
                    <button type="button" class="cat-chip is-toggle" data-flag="open">Open Now</button>
                    <button type="button" class="cat-chip is-toggle" data-flag="masses">Has Mass Schedule</button>
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
                    <a href="#" id="btnDirections" class="btn btn-primary mx-plan">
                        <i class="bi bi-signpost-fill"></i> Plan Route
                    </a>
                    <button type="button" id="btnClearRoute" class="btn btn-ghost btn-sm">Clear</button>
                </div>
            </section>
        </aside>

        <div class="giya-map-shell">
            <div class="giya-map-canvas" id="giyaMap"></div>

            <div class="map-tools">
                <button type="button" class="map-tool" id="btnFullscreen"
                        title="Fullscreen" aria-label="Toggle fullscreen">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>

                <button type="button" class="map-tool" id="btnLocate"
                        title="Find my location" aria-label="Find my location">
                    <i class="bi bi-geo-alt-fill"></i>
                </button>

                <div class="map-tool-pair">
                    <button type="button" class="map-tool" id="btnZoomIn"
                            title="Zoom in" aria-label="Zoom in">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                    <button type="button" class="map-tool" id="btnZoomOut"
                            title="Zoom out" aria-label="Zoom out">
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
    // A search from the home page arrives as ?q= — start from it.
    let query = new URLSearchParams(window.location.search).get('q') || '';
    query = query.trim().toLowerCase();
    let distances = {};

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

    const map = GiyaLeaflet.browse({
        element: 'giyaMap',
        churches: churches,
        onStatus: showNote,
        onFallback: function () {
            // Offline tiles are a deployment concern, not something a devotee
            // can act on. The map works either way, so say nothing.
        },
        onLocated: function (me, nearest) {
            distances = {};
    const initialChurchId = Number(new URLSearchParams(window.location.search).get('church'));
    if (initialChurchId) {
        setTimeout(function () { map.focus(initialChurchId); }, 0);
    }
            nearest.forEach(function (n) { distances[n.church.id] = n.km; });
            renderList();
            showNote('Showing your location. The nearest destinations are listed first.', 'info');
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

            const url = map.externalDirections();
            document.getElementById('btnDirections').href = url || '#';
        }
    });
    let flags = { open: false, masses: false };

    function filtered() {
        return churches
            .filter(function (c) { return category === 'All' || category === 'Near' || c.category === category; })
            .filter(function (c) { return !flags.open   || c.open; })
            .filter(function (c) { return !flags.masses || c.masses; })
            .filter(function (c) { return !query || (c.name + ' ' + c.location).toLowerCase().indexOf(query) !== -1; })
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
                            ? '<a href="' + c.details + '" data-details="' + c.details + '">' + c.name + '</a>'
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

                // "Near" only means anything once we know where the devotee is.
                if (category === 'Near' && !Object.keys(distances).length) map.locate();
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

    renderList();
})();
</script>
@endpush
