@extends('layouts.app')

@section('title', __('giya.nav.map'))

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/leaflet.css') }}?v={{ filemtime(public_path('assets/css/leaflet.css')) }}">
@endpush

@section('content')
@php
    /* Built here rather than inside @json(...): Blade matches a directive's
       brackets textually, and a multi-line array literal defeats that parser -
       the same trap the home page carries a note about.

       giya-leaflet.js is a plain script and cannot reach the translator, so
       the phrases it puts on screen are handed to it already translated. */
    $mapLabels = [
        'seeDetails' => __('giya.church.see_details'),
        'directions' => __('giya.church.directions'),
        'addToRoute' => __('giya.church.add_to_route'),
        'youAreHere' => __('giya.map.you_are_here'),
        'finding'    => __('giya.map.finding'),
        'noGeo'      => __('giya.map.no_geo'),
        'denied'     => __('giya.map.denied'),
        'noFix'      => __('giya.map.no_fix'),
    ];

    /* The phrases the page's own script writes, already translated. It counts
       things, so two of them are plural forms and go through trans_choice. */
    $mapStrings = [
        'results'        => trans_choice('giya.church.results', 2, ['count' => ':count']),
        'near_unlocated' => trans_choice('giya.map.near_unlocated', 2, ['count' => ':count']),
        'near_unsorted'  => __('giya.map.near_unsorted'),
        'none_within'    => __('giya.map.none_within', ['km' => ':km']),
        'within'         => trans_choice('giya.map.within', 2, ['count' => ':count', 'km' => ':km']),
        'select_route'   => __('giya.map.select_route', ['church' => ':church']),
        'selected'       => __('giya.map.selected', ['count' => ':count']),
        'following'      => __('giya.map.following'),
        'add_another'    => __('giya.map.add_another'),
        'no_match'       => __('giya.map.no_match'),
        'exit_full'      => __('giya.common.exit_full'),
        'fullscreen'     => __('giya.map.fullscreen'),
        'signin_title'   => __('giya.map.signin_title'),
        'signin_body'    => __('giya.map.signin_body', ['what' => ':what']),
        'sign_in'        => __('giya.nav.sign_in'),
        'not_now'        => __('giya.common.not_now'),
        'this_church'    => __('giya.map.this_church'),
        'act_directions' => __('giya.map.act_directions', ['church' => ':church']),
        'act_add'        => __('giya.map.act_add', ['church' => ':church']),
        'act_details'    => __('giya.map.act_details', ['church' => ':church']),
        'act_plan'       => __('giya.map.act_plan'),
        'pick_first'     => __('giya.map.pick_first'),
    ];

    /* Typing "open" or "mass" filters, so the two toggle chips could go. The
       words are listed for all three languages at once rather than for the
       current one: a devotee reading Cebuano may still type "open", and one
       reading English may type "misa", and neither should come up empty. */
    $searchKeywords = [
        'open'   => ['open', 'now', 'abli', 'bukas', 'ablihan'],
        'masses' => ['mass', 'masses', 'misa', 'schedule', 'iskedyul'],
    ];
@endphp
<div style="max-width:1280px;margin:0 auto;padding:24px 20px 48px">

    <header class="mx-head">
        <span class="eyebrow">{{ __('giya.map.eyebrow') }}</span>
        <h1>{{ __('giya.map.title') }}</h1>
        <p>{{ __('giya.map.lead') }}</p>
    </header>

    <div id="mapNote" class="map-note" style="display:none">
        <span id="mapNoteText"></span>
        <button type="button" class="map-note-close" aria-label="{{ __('giya.common.dismiss') }}">&times;</button>
    </div>

    <div class="map-grid">

        <aside class="map-sidebar card">

            <div class="mx-controls">
                <h2 class="mx-title">{{ __('giya.map.explore') }}</h2>

                <label class="mx-search-field">
                    <i class="bi bi-search"></i>
                    <input type="search" id="mapSearch" placeholder="{{ __('giya.map.search_ph') }}"
                           aria-label="{{ __('giya.nav.search_label') }}">
                </label>

<div class="mx-chips" role="group" aria-label="{{ __('giya.map.filters') }}">
                    {{-- No is-active: the script starts at 'All', so a lit
                         Near chip claims a filter that is not running. --}}
                    <button type="button" class="cat-chip" data-cat="Near">{{ __('giya.church.near') }}</button>

                    {{-- Chapel and Heritage do not get a chip. The list comes
                         from MapController::CHIPS_HIDDEN so the reason lives in
                         one place rather than being repeated here. --}}
                    @foreach ($categories as $category)
                        <button type="button" class="cat-chip" data-cat="{{ $category }}">{{ $category }}</button>
                    @endforeach
                </div>
            </div>

            <div class="mx-list-head" id="listHeading">{{ __('giya.church.results', ['count' => count($markers)]) }}</div>
            <div id="churchList" class="mx-list"></div>

            {{-- Selection tray: rises from the bottom once churches are picked --}}
            <section id="routeBox" class="mx-tray" style="display:none" aria-label="{{ __('giya.map.selected_aria') }}">
                <div class="mx-tray-head">
                    <span id="traySummary">{{ __('giya.map.selected_none') }}</span>
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

    /* Every phrase this script writes into the page, already translated.
       Placeholders are :name style, the same as Laravel's, so the strings in
       lang/ read the same whether PHP or JavaScript substitutes them. */
    const T = @json($mapStrings);

    function trans(key, values) {
        let out = T[key] || '';
        for (const k in (values || {})) out = out.split(':' + k).join(values[k]);
        return out;
    }

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
            title:   trans('signin_title'),
            message: trans('signin_body', { what: what }),
            ok:      trans('sign_in'),
            cancel:  trans('not_now'),
            tone:    'primary',
            icon:    'person-plus-fill',
        }).then(function (ok) {
            if (ok && next) window.location.href = next;
        });

        return false;
    }

    function churchName(id) {
        const match = churches.filter(function (c) { return c.id === id; })[0];
        return match ? match.name : trans('this_church');
    }

    function churchUrl(id) {
        const match = churches.filter(function (c) { return c.id === id; })[0];
        return match ? match.details : null;
    }

    /* A rotating reminder. It advances each time the devotee dismisses it, so
       it stays worth reading instead of becoming wallpaper, and it remembers
       where it left off between visits. */
    const REMINDERS = @json(array_values(__('giya.reminders')));

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
                trans(action === 'directions' ? 'act_directions' : 'act_add',
                      { church: churchName(id) }),
                churchUrl(id)
            );
        });
    }

    const map = GiyaLeaflet.browse({
        element: 'giyaMap',
        churches: churches,

        labels: @json($mapLabels),

        /* A refused location is only an error if it left the devotee with
           nothing. Near falls back to the whole list, so when the prompt is
           denied - or the browser never asks, which is what happens over
           plain http from a phone - we say what is missing instead of
           throwing a red banner at a screenful of churches. */
        onStatus: function (message, kind) {
            if (kind === 'error' && category === 'Near') {
                showNote(trans('near_unsorted'), 'info');
                return;
            }
            showNote(message, kind);
        },
        onFallback: function () {
            // Offline tiles are a deployment concern, not something a devotee
            // can act on. The map works either way, so say nothing.
        },
onLocated: function (me) {
            /* Distance to EVERY church, not just the handful the map returns
               as "nearest". Near is a radius, so it needs them all - otherwise
               a church 200 m away is excluded because it fell outside an
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
                ? trans('within', { count: nearbyIds.length, km: NEAR_KM })
                : trans('none_within', { km: NEAR_KM }), 'info');
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
                trans('selected', { count: stops.length });
            document.getElementById('routeDistance').textContent =
                stops.length < 2 ? '' : totalKm.toFixed(1) + ' km';

            meta = meta || {};

            if (meta.mode === 'road') {
                note.textContent = trans('following') +
                    (meta.minutes ? ' \u00b7 about ' + meta.minutes + ' min by car' : '');
                note.className = 'route-mode is-road';
            } else if (stops.length < 2) {
                note.textContent = trans('add_another');
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
     *
     * The keywords are listed per language rather than hardcoded in English,
     * so a devotee reading Cebuano can type "bukas" or "misa" and a devotee
     * reading English can type "open" or "mass" - and either works whichever
     * language the interface happens to be in, because all three lists are
     * loaded at once.
     */
    const KEYWORDS = @json($searchKeywords);

    function matchesQuery(c) {
        if (!query) return true;

        const words = query.split(/\s+/).filter(Boolean);
        const haystack = (c.name + ' ' + (c.location || '') + ' ' + (c.category || '')).toLowerCase();

        return words.every(function (w) {
            if (KEYWORDS.open.indexOf(w) !== -1)   return c.open;
            if (KEYWORDS.masses.indexOf(w) !== -1) return c.masses;
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

        /* Say plainly when Near is showing everything because no position is
           known yet - a count with no explanation reads as a failed filter. */
        const unlocated = category === 'Near' && !hasLocation;

        document.getElementById('listHeading').textContent = unlocated
            ? trans('near_unlocated', { count: list.length })
            : trans('results', { count: list.length });

        // Keep the map showing exactly what the list shows.
        if (map.showOnly) {
            map.showOnly(list.map(function (c) { return c.id; }));
        }

        if (!list.length) {
            listBox.innerHTML = '<p class="mx-empty">' + trans('no_match') + '</p>';
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
                        /* A bare "12.3 km" reads as "from you". It only is
                           when we have the devotee's own position; measured
                           from the middle of the map it would be a false
                           claim, so the order stands and the number waits. */
                        (hasLocation && distances[c.id] != null
                            ? ' &middot; ' + distances[c.id].toFixed(1) + ' km' : '') +
                    '</p>' +
                    '<p class="mx-row-tags">' +
                        (c.rating > 0 ? '<span class="mx-star"><i class="bi bi-star-fill"></i>' + c.rating.toFixed(1) + '</span>' : '') +
                        (c.open ? '<span class="mx-tag is-open">Open</span>' : '') +
                        '<span class="mx-tag">' + c.category + '</span>' +
                    '</p>' +
                '</div>' +
                '<button type="button" class="mx-pick' + (picked ? ' is-on' : '') + '" ' +
                        'data-add="' + c.id + '" role="switch" aria-checked="' + picked + '" ' +
                        'aria-label="' + trans('select_route', { church: c.name }) + '">' +
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
        fsBtn.title = on ? trans('exit_full') : trans('fullscreen');

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
            showNote(trans('pick_first'), 'error');
            return;
        }

        const planner = @json(route('plan.create')) + '?stops=' + ids.join(',');

        // Sending a guest to the planner URL rather than to /login is what
        // saves their selection: the planner is behind auth, so it becomes
        // the intended page and they land there with these churches ready.
        if (GUEST) { askToSignIn(trans('act_plan'), planner); return; }

        window.location.href = planner;
    });

    document.getElementById('mapSearch').addEventListener('input', function () {
        query = this.value.trim().toLowerCase();
        renderList();
    });

    /* Open Now and Mass Schedule are gone - the search box answers both, so
       every chip left is a category and this handler no longer needs the
       toggle branch. */
    document.querySelectorAll('.cat-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cat-chip').forEach(function (b) {
                b.classList.remove('is-active');
            });
            this.classList.add('is-active');
            category = this.dataset.cat;

            if (category === 'Near') {
                map.locate();

                /* Said AFTER locate(), which posts a "finding your location"
                   note of its own. On a phone over plain http the prompt is
                   never shown and never answered, so that note would sit there
                   forever describing something that is not happening - while
                   the list below it is perfectly usable, just unsorted. If a
                   fix does arrive, onLocated replaces this with the count. */
                if (!hasLocation) showNote(trans('near_unsorted'), 'info');
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
                askToSignIn(trans('act_add', { church: churchName(id) }), churchUrl(id));
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
                    trans('act_details', { church: link.dataset.church || trans('this_church') }),
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

    /* Arriving from ?church=<id> - a "View Details" in Favorites, a link in
       the planner. It used to sit inside onLocated, so the church was only
       focused if geolocation succeeded: deny the prompt, or open the page
       over plain http on a phone, and the deep link did nothing at all. */
    const initialChurchId = Number(new URLSearchParams(window.location.search).get('church'));
    if (initialChurchId) {
        setTimeout(function () { map.focus(initialChurchId); }, 0);
    }

    renderList();
})();
</script>
@endpush
