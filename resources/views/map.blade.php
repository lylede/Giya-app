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

        // Written by the engine when a Directions request comes back.
        'kmToRoads'  => __('giya.plan.km_roads_min_to', ['km' => ':km', 'name' => ':name', 'min' => ':min']),
    ];

    /* Whether the trip's details start open. ?plan= arrives that way, and so
       does a route handed over from a planner; so does a save the server sent
       back, wherever it was made from - the errors belong to that bar, and
       hiding it would hide them. Otherwise the bar is on the page but closed,
       and Plan Route opens it. */
    $openPlan = $planning || $errors->any();

    /* The phrases the page's own script writes, already translated. It counts
       things, so two of them are plural forms and go through trans_choice. */
    $mapStrings = [
        'results'        => trans_choice('giya.church.results', 2, ['count' => ':count']),
        'near_unlocated' => trans_choice('giya.map.near_unlocated', 2, ['count' => ':count']),
        'near_unsorted'  => __('giya.map.near_unsorted'),
        'none_within'    => __('giya.map.none_within', ['km' => ':km']),
        'within'         => trans_choice('giya.map.within', 2, ['count' => ':count', 'km' => ':km']),
        'select_route'   => __('giya.map.select_route', ['church' => ':church']),
        // Plural: "1 churches selected" is the kind of thing a panel notices.
        'selected'       => trans_choice('giya.map.selected', 2, ['count' => ':count']),
        'selected_one'   => trans_choice('giya.map.selected', 1, ['count' => ':count']),
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

    /* Written when Plan Route opens the trip's details in place - so only sent
       when there is a closed bar to open. A guest has none, and a bar that is
       already open never opens again; in both cases these would put wording on
       the page that nothing can ever show, including a Custom heading on a
       Visita Iglesia trip. */
    if ($canPlan && ! $openPlan) {
        $mapStrings += [
            'custom_title'     => __('giya.plan.custom_title'),
            'plan_lead'        => __('giya.map.plan_lead'),
            'start_pilgrimage' => __('giya.plan.start'),
            'limit_title'      => __('giya.plan.limit_title'),
        ];
    }

    /* Typing "open" or "mass" filters, so the two toggle chips could go. The
       words are listed for all three languages at once rather than for the
       current one: a devotee reading Cebuano may still type "open", and one
       reading English may type "misa", and neither should come up empty. */
    $searchKeywords = [
        'open'   => ['open', 'now', 'abli', 'bukas', 'ablihan'],
        'masses' => ['mass', 'masses', 'misa', 'schedule', 'iskedyul'],
    ];

    /* The route a rejected save is carrying back. Built here rather than
       inside @json(...), because Blade matches that directive's brackets
       textually and a call with its own brackets defeats the parser - the
       same trap the home page carries a note about. */
    $rejectedStops = (string) old('stop_ids', '');

@endphp
<div style="max-width:1280px;margin:0 auto;padding:24px 20px 48px">

    <header class="mx-head">
        {{-- Back to whichever planner sent us. For a Visita Iglesia route
             that is its own screen, and the link carries the current stops,
             so a church added or dropped here travels back with the devotee
             rather than being lost on the way. --}}
        <a href="{{ $isVisita ? route('plan.visita') : route('plan.hub') }}" class="back-link" id="planBack"
           style="margin-bottom:10px" @unless ($openPlan) hidden @endunless>
            <i class="bi bi-chevron-left"></i>
            {{ $isVisita ? __('giya.plan.back_visita') : __('giya.plan.back_hub') }}
        </a>
        <span class="eyebrow" id="mapEyebrow" @if ($openPlan) hidden @endif>{{ __('giya.map.eyebrow') }}</span>
        <h1 id="mapTitle">
            @if (! $openPlan) {{ __('giya.map.title') }}
            @elseif ($isVisita) {{ __('giya.plan.visita_title') }}
            @else {{ __('giya.plan.custom_title') }}
            @endif
        </h1>
        <p id="mapLead">{{ $openPlan ? __('giya.map.plan_lead') : __('giya.map.lead') }}</p>
    </header>

    @if ($canPlan)
        {{--
            The trip's own details, above the map that fills them.

            This used to be its own screen with a list of churches beside it.
            Choosing a destination is a question about where things are, so it
            belongs on the map; the name and the date are the only part that is
            not, and they fit in a bar. One screen instead of two.

            It is a real <form>: the fields post themselves, and the stops are
            written into it as hidden inputs the moment Start Pilgrimage is
            pressed, so a rejected save comes back with everything intact.
        --}}
        <form method="POST" action="{{ route('plan.store') }}" id="planForm" class="card card-body plan-bar"
              @unless ($openPlan) hidden @endunless>
            @csrf
            <input type="hidden" name="type" value="{{ $planType }}">
            <div id="planStops"></div>

            @if ($errors->any())
                <div class="alert alert-danger" style="grid-column:1/-1">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <label class="plan-field" style="flex:2 1 240px">
                <span class="form-label-sm">{{ __('giya.plan.itinerary_name') }}</span>
                <input type="text" name="name" class="giya-input" required maxlength="200"
                       value="{{ old('name') }}" placeholder="{{ __('giya.plan.name_ph') }}">
            </label>

            <label class="plan-field" style="flex:1 1 150px">
                <span class="form-label-sm">{{ __('giya.plan.date') }}</span>
                <input type="date" name="scheduled_date" class="giya-input"
                       value="{{ old('scheduled_date') }}" min="{{ now()->toDateString() }}">
            </label>

            <label class="plan-field" style="flex:2 1 220px">
                <span class="form-label-sm">{{ __('giya.plan.notes') }}</span>
                <input type="text" name="notes" class="giya-input" maxlength="2000"
                       value="{{ old('notes') }}" placeholder="{{ __('giya.plan.notes_ph') }}">
            </label>
        </form>
    @endif

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
                        {{-- data-cat stays the raw category, because the script
                             matches it against church.category. Only the label
                             is translated, and only "All" has one - the rest
                             are the church's own category name. Parish is folded
                             into Church because the app stores it as the same
                             destination type and we do not want a duplicate broad
                             filter. --}}
                        <button type="button" class="cat-chip" data-cat="{{ $category }}">{{ $category === 'All' ? __('giya.map.cat_all') : ($category === 'Church' ? __('giya.church.churches') : $category) }}</button>
                    @endforeach
                </div>
            </div>

            <div class="mx-list-head" id="listHeading">{{ __('giya.church.results', ['count' => count($markers)]) }}</div>
            {{-- The list is drawn by the script once the markers exist, so
                 until then it holds rows the size of the real ones. They
                 are replaced wholesale by the first render, which is why
                 nothing has to clear them. --}}
            <div id="churchList" class="mx-list">
                @for ($i = 0; $i < 4; $i++)
                    <div class="gs-row" aria-hidden="true">
                        <span class="gs gs-thumb"></span>
                        <span class="gs-row-body">
                            <span class="gs gs-line is-title" style="width:{{ [78, 64, 82, 70][$i] }}%"></span>
                            <span class="gs gs-line" style="width:{{ [46, 54, 40, 50][$i] }}%"></span>
                        </span>
                    </div>
                @endfor
            </div>

            {{-- Selection tray: rises from the bottom once churches are picked --}}
            <section id="routeBox" class="mx-tray" style="display:none" aria-label="{{ __('giya.map.selected_aria') }}">
                <div class="mx-tray-head">
                    <span id="traySummary">{{ __('giya.map.selected_none') }}</span>
                    <span id="routeDistance" class="mx-tray-distance"></span>
                </div>

                <ol id="routeStops" class="mx-tray-list"></ol>
                <p id="routeMode" class="route-mode"></p>

                <div class="mx-tray-actions">
                    {{-- Planning mode saves from here; browsing mode carries
                         the selection over to the planner, as it always did.
                         One button, because the tray only ever has one thing
                         worth doing with what is in it. --}}
                    <button type="button" id="btnDirections" class="btn btn-primary mx-plan"
                            @if ($openPlan && $atLimit) disabled title="{{ __('giya.plan.limit_title') }}" @endif>
                        @if ($openPlan)
                            <i class="bi bi-person-walking"></i> {{ __('giya.plan.start') }}
                        @else
                            <i class="bi bi-signpost-fill"></i> {{ __('giya.map.plan_route') }}
                        @endif
                    </button>
                    <button type="button" id="btnClearRoute" class="btn btn-ghost btn-sm">{{ __('giya.common.clear') }}</button>
                </div>
            </section>
        </aside>

        <div class="giya-map-shell">
            <div class="giya-map-canvas" id="giyaMap"></div>

            {{-- Removed by the engine when Leaflet says the tiles are on
                 screen, so what the devotee sees while waiting is GIYA's
                 pin turning rather than a grey checkerboard. --}}
            <x-map-loading />

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

    /* The map doubles as the custom itinerary planner: the details bar above
       it is a real form, and once it is open the tray's button saves rather
       than handing the selection to another screen.

       At the free limit there is nothing to save, so the button says so
       instead of posting something the controller would only refuse. */
    const AT_LIMIT = @json($atLimit);

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

    function escapeAttr(value) {
        return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                            .replace(/</g, '&lt;').replace(/>/g, '&gt;');
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

    let category = 'Near';
    // A search from the home page arrives as ?q= - start from it.
    let query = new URLSearchParams(window.location.search).get('q') || '';
    query = query.trim().toLowerCase();
    let distances = {};
    let nearbyIds = [];

    /* Near is a radius, not a count. Ten kilometres gives a slightly wider
       local area in Metro Cebu while still keeping the map focused and not
       city-wide. */
    const NEAR_KM = 10;

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
                hasLocation = false;
                distances = {};
                nearbyIds = [];
                renderList();
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
                rememberRoute([]);
                renderList();
                return;
            }

            routeBox.style.display = 'block';

            /* The way back keeps the route. A devotee who drops a church here
               and then returns to the planner they came from should find the
               six they have, not the seven they arrived with. */
            rememberRoute(stops);

            document.getElementById('traySummary').textContent =
                trans(stops.length === 1 ? 'selected_one' : 'selected', { count: stops.length });
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
    const FALLBACK_CATEGORIES = ['Basilica', 'Shrine', 'Church'];
    const FALLBACK_LIMIT = 12;

    function normalizeCategory(cat) {
        if (!cat) return cat;
        if (cat === 'Parish' || cat === 'Parishes') return 'Church';
        if (cat.toLowerCase().indexOf('shrine') !== -1) return 'Shrine';
        return cat;
    }

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
        const normalizedCategory = normalizeCategory(category);

        let list = churches.filter(function (c) {
            const normalizedChurchCategory = normalizeCategory(c.category);
            const inRadius = distances[c.id] != null && distances[c.id] <= NEAR_KM;

            if (!hasLocation) {
                if (category === 'Near') {
                    return FALLBACK_CATEGORIES.indexOf(normalizedChurchCategory) !== -1;
                }
                if (category === 'All') {
                    return FALLBACK_CATEGORIES.indexOf(normalizedChurchCategory) !== -1;
                }
                return normalizedChurchCategory === normalizedCategory;
            }

            if (category === 'Near') {
                return inRadius;
            }

            if (category === 'All') {
                return inRadius;
            }

            return inRadius && normalizedChurchCategory === normalizedCategory;
        });

        list = list.filter(function (c) { return matchesQuery(c); });

        if (!hasLocation && category !== 'Near' && category !== 'All') {
            list = list
                .sort(function (a, b) {
                    if (a.open !== b.open) return Number(b.open) - Number(a.open);
                    return (b.rating || 0) - (a.rating || 0) || a.name.localeCompare(b.name);
                })
                .slice(0, FALLBACK_LIMIT);
            return list;
        }

        return list.sort(function (a, b) {
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

    /* Plan Route opens the trip's details above the map rather than leaving for
       a second screen. The churches are already picked and the distances are
       already on screen; all that is missing is a name, so that is all that
       appears. The next press of the same button saves. */
    let planOpen = @json($openPlan);

    /* Where the back link points, kept in step with what is picked.

       BACK_BASE is the planner that sent us here; the stops are appended so
       the two screens stay one trip. Written on every route change rather
       than read at the moment of the click, because the link is a plain <a>
       and the browser follows it without asking us anything. */
    const BACK_BASE = @json($isVisita ? route('plan.visita') : route('plan.hub'));
    const BACK_KEEPS_STOPS = @json($isVisita);

    function rememberRoute(stops) {
        if (!BACK_KEEPS_STOPS) { return; }

        const link = document.getElementById('planBack');
        const ids  = (stops || []).map(function (s) { return s.id; }).filter(Boolean);

        link.href = ids.length ? BACK_BASE + '?stops=' + ids.join(',') : BACK_BASE;
    }

    function openPlanBar() {
        const form = document.getElementById('planForm');
        if (!form) { return false; }

        form.hidden = false;

        /* The link back to the hub stays hidden. Opening the details here is
           not arriving from the hub, and a back link pointing somewhere the
           devotee has not been is worse than none at all.

           Written without quoting the link's own wording, because the
           translation-coverage test reads the whole page - comments included -
           and cannot tell copy in a comment from copy on screen. It is right
           not to: a phrase worth writing twice is a phrase worth a key. */
        document.getElementById('mapEyebrow').hidden = true;
        document.getElementById('mapTitle').textContent = trans('custom_title');
        document.getElementById('mapLead').textContent  = trans('plan_lead');

        const btn = document.getElementById('btnDirections');
        btn.innerHTML = '<i class="bi bi-person-walking"></i> ';
        btn.appendChild(document.createTextNode(trans('start_pilgrimage')));

        // The allowance is only in the way once there is something to save.
        if (AT_LIMIT) {
            btn.disabled = true;
            btn.title = trans('limit_title');
        }

        planOpen = true;

        /* The button that opened this is at the bottom of the sidebar, and the
           bar is at the top of the page - without this the devotee presses
           Plan Route and, as far as they can see, nothing happens. */
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { form.querySelector('input[name=name]').focus(); }, 350);

        return true;
    }

    document.getElementById('btnDirections').addEventListener('click', function () {
        const ordered = (map.orderedStops ? map.orderedStops() : [])
            .map(function (s) { return s.id; })
            .filter(Boolean);

        const ids = ordered.length ? ordered : map.selected();

        if (!ids.length) {
            showNote(trans('pick_first'), 'error');
            return;
        }

        /* Where a guest is sent to sign in. The map in planning mode, not the
           old form: it is behind auth, so it becomes the intended page and
           they come back to it with these churches already picked. */
        const planner = @json(route('map', ['plan' => 1])) + '&stops=' + ids.join(',');

        if (GUEST) { askToSignIn(trans('act_plan'), planner); return; }

        // First press opens the details. Nothing is saved yet - there is no
        // name yet, and asking for one after saving is the wrong order.
        if (!planOpen) { openPlanBar(); return; }

        /* Second press saves. The stops are written into the details form as
           hidden inputs at the moment of submitting rather than kept in sync
           as they change - there is only one moment that matters, and syncing
           on every tick is a second source of truth waiting to disagree with
           the tray. */
        const form = document.getElementById('planForm');

        document.getElementById('planStops').innerHTML =
            ids.map(function (id) {
                const c = churches.filter(function (x) { return x.id === id; })[0];
                return '<input type="hidden" name="stops[]" value="' + escapeAttr(c ? c.name : '') + '">';
            }).join('') +
            '<input type="hidden" name="stop_ids" value="' + ids.join(',') + '">';

        // requestSubmit, not submit: submit() skips validation, so an empty
        // name would post and come back as a server error instead of the
        // browser saying so on the spot.
        form.requestSubmit();
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

    document.querySelector('[data-cat="Near"]').classList.add('is-active');
    map.locate();

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
        /* old('stop_ids') first: when a save is rejected - a missing name, a
           date in the past - Laravel sends the devotee back here, and without
           this the map would reload empty and every church would have to be
           picked again. The URL is the other way in, from a planner. */
        const raw = @json($rejectedStops) ||
                    new URLSearchParams(window.location.search).get('stops');
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
