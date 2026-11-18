@extends('layouts.app')
@section('title', __('giya.plan.visita'))

@section('content')
<div class="page-wrap">


    <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
        <h1 style="font-family:var(--font-title);font-size: 1.75rem;margin:0">{{ __('giya.plan.visita_title') }}</h1>
        <span class="badge badge-brown">{{ __('giya.plan.traditionally') }}</span>
    </div>
    <p style="color:var(--text-muted);font-size: 0.875rem;margin:0 0 32px">
        {{ __('giya.plan.visita_lead') }}
    </p>

    @if ($atLimit)
        <div class="alert alert-warning">
            <i class="bi bi-lock-fill"></i>
            <span>You have reached the free limit of {{ \App\Http\Controllers\ItineraryController::FREE_LIMIT }} saved itineraries.
                  <a href="{{ route('upgrade') }}" style="font-weight:700;color:inherit;text-decoration:underline">{{ __('giya.plan.go_premium') }}</a> {{ __('giya.plan.premium_short') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form id="visitaForm" method="POST" action="{{ route('plan.store') }}">
        @csrf
        <input type="hidden" name="type" value="Visita Iglesia">
        <div id="stopsInputs"></div>

        <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:28px" class="visita-grid">

            {{-- Left --}}
            <div>
                {{-- Shown once the route has churches: "0 of 0" told a new
                     pilgrim nothing except that something was empty. --}}
                <div class="upgrade-card mb-3" id="viProgress" hidden>
                    <div style="position:relative">
                        <div style="color:rgba(255,255,255,0.7);font-size: 0.75rem;margin-bottom:4px">{{ __('giya.plan.route_progress') }}</div>
                        <div style="font-family:var(--font-display);color:var(--gold);font-size: 1.875rem;font-weight:700;line-height:1">
                            <span id="visitedCount">0</span> of <span id="totalCountA">7</span>
                        </div>
                        <div style="color:rgba(255,255,255,0.8);font-size: 0.8125rem;margin-top:2px">{{ __('giya.plan.marked_visited') }}</div>
                        {{-- The bar is gone. Seven candles say the same thing
                             and say it as the thing the route actually is. --}}
                        <x-candle-progress :total="7" :done="0" id="candleRow" class="mt-3" />
                        <div id="progressLabel" style="color:var(--gold);font-size: 0.75rem;font-weight:700;margin-top:6px">{{ __('giya.plan.complete', ['percent' => 0]) }}</div>
                    </div>
                </div>

                <div class="card card-body mb-3">
                    <div class="card-title">{{ __('giya.plan.itin_details') }}</div>
                    <div class="field">
                        <label class="form-label-sm" for="vi-name">{{ __('giya.plan.route_name') }}</label>
                        <input id="vi-name" type="text" name="name" class="giya-input"
                               value="{{ old('name', __('giya.plan.card_visita_title')) }}" required maxlength="200">
                    </div>
                    <div class="field" style="margin-bottom:0">
                        <label class="form-label-sm" for="vi-date">{{ __('giya.plan.date') }}</label>
                        <input id="vi-date" type="date" name="scheduled_date" class="giya-input"
                               value="{{ old('scheduled_date') }}" min="{{ now()->toDateString() }}">
                    </div>
                </div>

                <div class="card card-body mb-3">
                    <div class="card-title">{{ __('giya.plan.adjust_count') }}</div>
                    <div class="d-flex align-items-center gap-4 mb-3">
                        <button type="button" onclick="GiyaVisita.step(-1)"
                                style="width:40px;height:40px;border-radius:12px;border:none;cursor:pointer;font-size: 1.25rem;font-weight:700;background:var(--gold-bg);color:var(--primary)">−</button>
                        <div class="text-center">
                            <div id="countValue" style="font-family:var(--font-display);font-size: 1.875rem;font-weight:700;color:var(--primary);line-height:1">7</div>
                            <div style="font-size: .75rem;color:var(--text-muted)">{{ __('giya.plan.churches_word') }}</div>
                        </div>
                        <button type="button" onclick="GiyaVisita.step(1)"
                                style="width:40px;height:40px;border-radius:12px;border:none;cursor:pointer;font-size: 1.25rem;font-weight:700;background:var(--primary);color:#fff">+</button>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach ([5, 7, 9, 12] as $n)
                            <button type="button" @class(['preset-btn', 'active' => $n === 7])
                                    onclick="GiyaVisita.preset({{ $n }}, this)">{{ $n }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary" style="flex:1;min-width:170px"
                            onclick="GiyaVisita.submit()" @disabled($atLimit)>
                        <i class="bi bi-person-walking"></i> {{ __('giya.plan.start') }}
                    </button>

                    {{-- The same trip on the map, before committing to it. The
                         planner lists names; the map answers "how far apart are
                         these actually", which is the question a seven-church
                         route raises. Not disabled at the free limit: looking is
                         not saving. --}}
                    <button type="button" class="btn btn-outline" onclick="GiyaVisita.viewOnMap()">
                        <i class="bi bi-map-fill"></i> {{ __('giya.plan.view_on_map') }}
                    </button>
                </div>
            </div>

            {{-- Right --}}
            <div>
                <div class="card card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="card-title" style="margin:0">{{ __('giya.plan.your_visita') }}</div>
                        <span id="countLabel" style="font-size: 0.75rem;color:var(--text-muted)">{{ __('giya.plan.stops_count', ['count' => 7]) }}</span>
                    </div>

                    {{-- The suggestion: the main churches of the towns nearest
                         the devotee, in driving order. Says where it measured
                         from, and asks for a town when the phone will not say. --}}
                    <div class="vi-suggest" id="viSuggest">
                        <p class="vi-suggest-note" id="viNote" role="status" aria-live="polite"></p>
                        <div class="vi-town" id="viTownPick" hidden>
                            <label for="viTown" class="form-label-sm">{{ __('giya.plan.visita_start_from') }}</label>
                            <select id="viTown" class="giya-input">
                                <option value="">{{ __('giya.plan.visita_choose_town') }}</option>
                                @foreach ($startTowns as $t)
                                    <option value="{{ $t['id'] }}">{{ $t['town'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm vi-again" id="viAgain" hidden>
                            <i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('giya.plan.visita_suggest_again') }}
                        </button>
                    </div>

                    <div id="churchList" style="display:flex;flex-direction:column;gap:10px"></div>

                    <button type="button" onclick="GiyaVisita.openPicker()"
                            style="width:100%;margin-top:12px;padding:12px;border-radius:13px;border:2px dashed rgba(215,169,74,.45);color:var(--primary);font-weight:600;font-size: 0.8125rem;background:rgba(215,169,74,.05);cursor:pointer;font-family:var(--font-body)">
                        <i class="bi bi-plus-lg"></i> {{ __('giya.plan.add_church') }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Add Church: a sheet that slides up over the page (a centred pop-up on
         a computer), right where the finger is, so it cannot be missed. It
         stays open to add several; Done shows where they went. --}}
    <div class="modal vi-sheet" id="addPicker" tabindex="-1" aria-hidden="true" role="dialog" aria-labelledby="addPickerTitle">
        <div class="modal-dialog">
            <div class="modal-content vi-sheet-body" id="addPickerBody">
                {{-- The handle: drag it (or the title row) down to close. --}}
                <span class="vi-sheet-grab" aria-hidden="true"></span>
                <div class="vi-sheet-head">
                    <h2 class="vi-sheet-title" id="addPickerTitle">{{ __('giya.plan.add_a_church') }}</h2>
                    <button type="button" class="vi-sheet-x" data-modal-close aria-label="{{ __('giya.common.close') }}"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="vi-sheet-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="pickerSearch" class="giya-input" autocomplete="off"
                           placeholder="{{ __('giya.plan.picker_search') }}" aria-label="{{ __('giya.plan.picker_search') }}">
                </div>
                <p class="vi-sheet-note" id="pickerNote" aria-live="polite"></p>
                <div id="pickerList" class="vi-pick-list"></div>
                <button type="button" class="btn btn-primary btn-w-full vi-sheet-done" data-modal-close>{{ __('giya.plan.picker_done') }}</button>
            </div>
        </div>
    </div>
</div>

@push('head')
<style>
    .preset-btn { padding:6px 14px; border-radius:999px; font-size: 0.75rem; border:1.5px solid var(--border);
                  color:var(--text-muted); background:var(--bg); cursor:pointer; transition:all .18s;
                  font-family:var(--font-body); }
    .preset-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
    .vi-row { display:flex; align-items:center; gap:10px; padding:13px; border-radius:15px;
              border:1.5px solid rgba(142,59,47,.1); transition:all .18s; }
    .vi-row.done    { background:#F5E8D0; border-color:rgba(215,169,74,.35); }
    .vi-row.current { background:rgba(142,59,47,.04); border:2px solid var(--primary); }
    .vi-row.todo    { background:var(--bg); }
    .route-btn { background:none; border:none; cursor:pointer; padding:2px; line-height:1; }
    /* minmax(0, 1fr), not 1fr: a grid column's default min-width is auto,
       so it refuses to shrink below the widest thing inside it. On a phone
       that pushed this column to 397px inside a 358px grid and scrolled the
       whole page sideways - in English, and 7px further in Cebuano, where
       the words are longer. */
    /* The drag handle: six dots, a finger's width wide. */
    .vi-grip { flex-shrink: 0; width: 28px; height: 42px; margin-left: -4px; display: inline-flex; align-items: center;
               justify-content: center; border: 0; border-radius: 8px; background: none; color: var(--text-muted);
               cursor: grab; touch-action: none; -webkit-user-select: none; user-select: none; }
    .vi-grip svg { width: 10px; height: 16px; fill: currentColor; }
    .vi-grip:hover { color: var(--primary); background: rgba(142,59,47,.06); }
    .vi-grip:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
    #churchList.is-sorting { -webkit-user-select: none; user-select: none; }
    #churchList.is-sorting .vi-grip { cursor: grabbing; }
    .vi-row.is-dragging { position: relative; z-index: 2; box-shadow: 0 14px 30px rgba(60, 25, 15, .22);
                          border-color: var(--primary); cursor: grabbing; }
    html[data-theme="dark"] .vi-row.is-dragging { box-shadow: 0 10px 24px rgba(0, 0, 0, .5); }

    /* Add a Church: the church icon, and type sized to be read on a phone. */
    .vi-pick-list { display: flex; flex-direction: column; gap: 8px; max-height: min(360px, 55vh); overflow-y: auto; padding-right: 2px; }
    .vi-pick { display: flex; align-items: center; gap: 12px; width: 100%; padding: 11px 12px; border-radius: 12px;
               background: var(--bg); border: 1px solid var(--border); cursor: pointer; text-align: left; font-family: var(--font-body); }
    .vi-pick:hover { border-color: var(--primary); }
    .vi-pick:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
    .vi-pick-icon { width: 38px; height: 38px; flex-shrink: 0; border-radius: 10px; display: inline-flex; align-items: center;
                    justify-content: center; background: var(--gold-bg); font-size: 1.125rem; }
    .vi-pick-text { min-width: 0; }
    .vi-pick-name { display: block; font-size: .9375rem; font-weight: 600; line-height: 1.35; color: var(--text); }
    .vi-pick-place { display: block; margin-top: 2px; font-size: .8125rem; line-height: 1.4; color: var(--text-muted); }
    .vi-pick-empty { margin: 0; padding: 8px; font-size: .875rem; color: var(--text-muted); }
    html[data-theme="dark"] .vi-pick { background: var(--surface); }
    html[data-theme="dark"] .vi-pick-icon { background: rgba(215,169,74,.14); }
    .vi-suggest { display: flex; flex-direction: column; gap: 10px; margin: 0 0 14px; padding: 12px 14px;
                  border-radius: 13px; background: var(--gold-bg); }
    .vi-suggest-note { margin: 0; font-size: .8125rem; line-height: 1.5; color: var(--text); }
    .vi-suggest-note:empty { display: none; }
    .vi-town { display: flex; flex-direction: column; gap: 4px; }
    .vi-again { align-self: flex-start; }
    html[data-theme="dark"] .vi-suggest { background: var(--surface-2); }
    @media (max-width: 950px) {
        .visita-grid { grid-template-columns: minmax(0, 1fr) !important; }
        /* On a phone the route comes first: it is what the page is for, and
           where the town picker appears when location is off. */
        .visita-grid > div:last-child { order: -1; }
    }
</style>
@endpush

@php
    // Built here, not inside @json(...): Blade matches the directive's
    // brackets textually and a closure in there defeats it.
    $visitaChurches = $churches->map(fn ($c) => [
        'id'       => $c->id,
        'name'     => $c->name,
        'location' => $c->location,
        'color'    => $c->color(),
        'lat'      => $c->latitude !== null ? (float) $c->latitude : null,
        'lng'      => $c->longitude !== null ? (float) $c->longitude : null,
        // The mother church of its municipality: what the suggestion is built from.
        'major'    => (bool) $c->is_major,
        'town'     => \App\Services\RoutePlanner::townName((string) ($c->municipality ?: $c->location)),
    ])->values();

@endphp
@push('scripts')
<script>
const GiyaVisita = (function () {
        const esc = window.giyaEsc;
        // Encoded with Blade's json directive rather than a raw toJson(): a
        // plain toJson() leaves a closing script tag in a church name intact,
        // which would end this script block early.
        const all = @json($visitaChurches);
    /* Seven churches is the tradition, and the starting point. A route
       coming back from the map replaces it - the devotee has already chosen
       there, and re-imposing seven would throw their choice away. */
    const returning = @json($preset);

    let list = returning.map(c => Object.assign({ visited: false }, c));

    /* ---- Suggesting the churches ----
       The main church (is_major) of each of the nearest towns, one per town,
       measured from the phone's position or a town the devotee picks. A
       main church more than NEAR_KM away does not count as near: the nearest
       other churches fill those places instead. Then the route is put in
       driving order, nearest first and each next one closest to the last. */
    const NEAR_KM = 40;
    const T = {
        locating:  @json(__('giya.plan.visita_locating')),
        nearYou:   @json(__('giya.plan.visita_near_you')),
        nearTown:  @json(__('giya.plan.visita_near_town')),
        plusYou:   @json(__('giya.plan.visita_near_you_plus')),
        plusTown:  @json(__('giya.plan.visita_near_town_plus')),
        noLoc:     @json(__('giya.plan.visita_no_location')),
        dragLabel: @json(__('giya.plan.drag_label')),
        dragHint:  @json(__('giya.plan.drag_hint')),
    };
    let origin = null;      // { lat, lng, town } once known
    let target = list.length || 7;

    function km(a, b) {
        const r = Math.PI / 180, dLat = (b.lat - a.lat) * r, dLng = (b.lng - a.lng) * r;
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin(dLng / 2) ** 2;
        return 12742 * Math.asin(Math.sqrt(h));
    }

    /** Every church in the order it should be suggested, and whether the top n needed filling. */
    function ranked(n, skip) {
        const placed = all.filter(c => c.lat != null && c.lng != null && !(skip || []).includes(c.id))
            .map(c => ({ c: c, d: km(origin, c) }))
            .sort((x, y) => x.d - y.d);
        const out = [], towns = new Set();
        placed.forEach(function (x) {
            if (!x.c.major || x.d > NEAR_KM) return;
            const town = (x.c.town || '').toLowerCase() || ('#' + x.c.id);
            if (towns.has(town)) return;
            towns.add(town);
            out.push(x.c);
        });
        placed.forEach(function (x) { if (!out.includes(x.c)) out.push(x.c); });
        return { churches: out };
    }

    function drivingOrder(churches) {
        const rest = churches.slice(), ordered = [];
        let here = origin;
        while (rest.length) {
            let best = 0, bestKm = Infinity;
            rest.forEach(function (c, i) { const d = km(here, c); if (d < bestKm) { bestKm = d; best = i; } });
            here = rest.splice(best, 1)[0];
            ordered.push(here);
        }
        return ordered;
    }

    function note(text) { document.getElementById('viNote').textContent = text; }

    function suggest(n) {
        if (!origin) return false;
        target = n;
        const r = ranked(n);
        list = drivingOrder(r.churches.slice(0, n)).map(c => Object.assign({ visited: false }, c));
        // Say exactly what was picked: how many towns' main churches, and how
        // many other churches made up the number when there were not enough.
        const mains = list.filter(c => c.major && km(origin, c) <= NEAR_KM).length;
        const extra = list.length - mains;
        const tpl = extra > 0
            ? (origin.town ? T.plusTown : T.plusYou)
            : (origin.town ? T.nearTown : T.nearYou);
        note(tpl.replace(':count', mains).replace(':total', list.length).replace(':town', origin.town || ''));
        document.getElementById('viAgain').hidden = false;
        render(); refreshPicker();
        return true;
    }

    function askForTown() {
        note(T.noLoc);
        document.getElementById('viTownPick').hidden = false;
    }

    function locate() {
        if (!navigator.geolocation) { askForTown(); return; }
        note(T.locating);
        navigator.geolocation.getCurrentPosition(function (pos) {
            origin = { lat: pos.coords.latitude, lng: pos.coords.longitude, town: null };
            document.getElementById('viTownPick').hidden = true;
            suggest(target);
        }, askForTown, { enableHighAccuracy: false, timeout: 9000, maximumAge: 600000 });
    }

    document.getElementById('viTown').addEventListener('change', function () {
        const c = all.find(x => x.id === Number(this.value));
        if (!c) return;
        origin = { lat: c.lat, lng: c.lng, town: c.town };
        suggest(target);
    });
    document.getElementById('viAgain').addEventListener('click', function () {
        if (origin) suggest(list.length || target); else locate();
    });

    function render() {
        const done  = list.filter(c => c.visited).length;
        const total = list.length;
        const pct   = total ? Math.round((done / total) * 100) : 0;
        const current = list.find(c => !c.visited);

        document.getElementById('viProgress').hidden = total === 0;
        document.getElementById('visitedCount').textContent = done;
        document.getElementById('totalCountA').textContent  = total;
        document.getElementById('countValue').textContent   = total;
        document.getElementById('countLabel').textContent   = @json(__('giya.plan.stops_count', ['count' => ':count'])).replace(':count', total);
        /* The row can change length here: the devotee adds and removes
           churches before starting, so it is regrown before it is lit. */
        const row = document.getElementById('candleRow');
        window.GiyaCandles.grow(row, total);
        window.GiyaCandles.set(row, done);
        document.getElementById('progressLabel').textContent = @json(__('giya.plan.complete', ['percent' => ':percent'])).replace(':percent', pct);

        document.getElementById('churchList').innerHTML = list.map(function (c, i) {
            const isCurrent = current && current.id === c.id;
            const cls = c.visited ? 'done' : (isCurrent ? 'current' : 'todo');
            const badgeBg = c.visited ? 'var(--primary)' : (isCurrent ? 'var(--gold-bg)' : '#fff');
            const inner = c.visited
                ? '<i class="bi bi-check-lg" style="color:var(--gold);font-size: 1.0625rem"></i>'
                : '<span style="font-size: 0.75rem;font-weight:700;color:' + (isCurrent ? 'var(--primary)' : 'var(--text-muted)') + '">' + (i + 1) + '</span>';

            return '<div class="vi-row ' + cls + '" data-i="' + i + '">' +
                '<button type="button" class="vi-grip" aria-label="' + esc(T.dragLabel.replace(':name', c.name)) + '" title="' + esc(T.dragHint) + '">' +
                  '<svg viewBox="0 0 10 16" aria-hidden="true" focusable="false"><circle cx="2.5" cy="2.5" r="1.5"/><circle cx="7.5" cy="2.5" r="1.5"/><circle cx="2.5" cy="8" r="1.5"/><circle cx="7.5" cy="8" r="1.5"/><circle cx="2.5" cy="13.5" r="1.5"/><circle cx="7.5" cy="13.5" r="1.5"/></svg>' +
                '</button>' +
                '<span style="width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(142,59,47,.15);background:' + badgeBg + '">' + inner + '</span>' +
                '<span class="vi-info" style="flex:1;min-width:0">' +
                  '<span style="display:block;font-size: 0.875rem;font-weight:600;color:var(--text)' + (c.visited ? ';text-decoration:line-through;opacity:.65' : '') + '">' + esc(c.name) + '</span>' +
                  '<span style="display:block;font-size: .75rem;color:var(--text-muted);margin-top:2px">' + esc(c.location) + '</span>' +
                  (isCurrent ? '<span class="badge badge-primary" style="margin-top:4px">Next stop</span>' : '') +
                '</span>' +
                '<span class="vi-actions" style="display:flex;align-items:center;gap:8px;flex-shrink:0">' +
                  '<button type="button" class="vi-mark" onclick="GiyaVisita.toggle(' + i + ')" style="padding:8px 13px;border-radius:10px;font-size: 0.75rem;font-weight:700;border:none;cursor:pointer;min-width:100px;font-family:var(--font-body);background:' + (c.visited ? 'var(--gold-bg)' : 'var(--primary)') + ';color:' + (c.visited ? 'var(--primary)' : '#fff') + '">' + (c.visited ? @json(__('giya.plan.visited_b')) : @json(__('giya.plan.mark_visited_b'))) + '</button>' +
                  '<button type="button" class="vi-drop" aria-label="Remove" onclick="GiyaVisita.drop(' + i + ')" style="width:28px;height:28px;border-radius:8px;background:rgba(212,24,61,.08);color:#D4183D;border:none;cursor:pointer;font-size: 0.9375rem">×</button>' +
                '</span></div>';
        }).join('');
    }

    /* ---- Dragging to reorder ----
       Pointer events, so one piece of code serves a mouse, a finger and a
       pen. The row moves in the page while it is dragged; the route is
       re-ordered from the rows when it is let go. The handle has
       touch-action: none so dragging it does not scroll the page; near the
       top or bottom of the screen the page scrolls along. On a keyboard the
       handle moves its church with the up and down arrow keys. */
    const listEl = document.getElementById('churchList');
    let drag = null;

    /* The whole box lifts and follows the finger; the boxes it passes slide
       up or down to make room, and on letting go it glides into its new
       place. Nothing is moved in the page until then, so the motion is
       smooth rather than jumping from slot to slot. */
    const SLIDE = 'transform .2s cubic-bezier(.2, .8, .2, 1)';
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    listEl.addEventListener('pointerdown', function (e) {
        const grip = e.target.closest('.vi-grip');
        if (!grip || (e.pointerType === 'mouse' && e.button !== 0)) return;
        e.preventDefault();
        const row = grip.closest('.vi-row');
        const rows = Array.from(listEl.querySelectorAll('.vi-row'));
        const rects = rows.map(r => r.getBoundingClientRect());
        const from = rows.indexOf(row);
        const gap = rows.length > 1 ? Math.max(0, rects[1].top - rects[0].bottom) : 10;
        drag = { row: row, grip: grip, pointer: e.pointerId, rows: rows, rects: rects, from: from, to: from,
                 gap: gap, y0: e.clientY, s0: window.scrollY, dy: 0 };
        grip.setPointerCapture(e.pointerId);
        rows.forEach(r => { r.style.transition = (r === row || still) ? 'none' : SLIDE; });
        row.classList.add('is-dragging');
        listEl.classList.add('is-sorting');
    });

    function moveTo(e) {
        const d = drag;
        d.dy = e.clientY - d.y0 + (window.scrollY - d.s0);
        d.row.style.transform = 'translateY(' + d.dy + 'px) scale(1.02)';

        // Where the box's middle is now, against where the others' middles were.
        const mid = d.rects[d.from].top + d.rects[d.from].height / 2 + d.dy;
        let to = 0;
        d.rows.forEach((r, i) => { if (i !== d.from && d.rects[i].top + d.rects[i].height / 2 < mid) to++; });
        d.to = to;

        const step = d.rects[d.from].height + d.gap;
        d.rows.forEach((r, i) => {
            if (i === d.from) return;
            let shift = 0;
            if (d.from < to && i > d.from && i <= to) shift = -step;
            if (d.from > to && i >= to && i < d.from) shift = step;
            r.style.transform = shift ? 'translateY(' + shift + 'px)' : '';
        });
    }

    listEl.addEventListener('pointermove', function (e) {
        if (!drag || e.pointerId !== drag.pointer) return;
        e.preventDefault();
        moveTo(e);
        const edge = 70;
        if (e.clientY < edge) window.scrollBy(0, -14);
        else if (e.clientY > window.innerHeight - edge) window.scrollBy(0, 14);
    });

    function finishDrag(e) {
        if (!drag || (e && e.pointerId !== drag.pointer)) return;
        const d = drag;
        drag = null;
        listEl.classList.remove('is-sorting');

        // Glide into the slot it was dropped on, then re-order for real.
        let land = 0;
        if (d.to > d.from) for (let i = d.from + 1; i <= d.to; i++) land += d.rects[i].height + d.gap;
        if (d.to < d.from) for (let i = d.to; i < d.from; i++) land -= d.rects[i].height + d.gap;
        d.row.style.transition = still ? 'none' : SLIDE;
        d.row.style.transform = 'translateY(' + land + 'px)';

        setTimeout(function () {
            d.row.classList.remove('is-dragging');
            d.rows.forEach(r => { r.style.transition = ''; r.style.transform = ''; });
            if (d.to !== d.from) {
                const moved = list.splice(d.from, 1)[0];
                list.splice(d.to, 0, moved);
            }
            render();
        }, still ? 0 : 200);
    }
    listEl.addEventListener('pointerup', finishDrag);
    listEl.addEventListener('pointercancel', finishDrag);

    listEl.addEventListener('keydown', function (e) {
        const grip = e.target.closest('.vi-grip');
        if (!grip || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
        e.preventDefault();
        const i = Number(grip.closest('.vi-row').dataset.i), t = i + (e.key === 'ArrowUp' ? -1 : 1);
        if (t < 0 || t >= list.length) return;
        [list[i], list[t]] = [list[t], list[i]];
        render();
        const again = listEl.querySelector('.vi-row[data-i="' + t + '"] .vi-grip');
        if (again) again.focus();
    });

    /* ---- Add Church sheet ----
       Nearest first: measured from the last church in the route, so the next
       stop is close to where the devotee will be. Search by name or town.
       Churches added while the sheet is open stay in the list as "Added". */
    let justAdded = [];
    const P = {
        added:   @json(__('giya.plan.picker_added')),
        add:     @json(__('giya.plan.picker_add')),
        none:    @json(__('giya.plan.picker_none')),
        allIn:   @json(__('giya.plan.picker_all_in')),
        count:   @json(__('giya.plan.picker_count')),
        seven:   @json(__('giya.plan.picker_seven')),
    };

    function refreshPicker() {
        const box = document.getElementById('pickerList');
        const q = (document.getElementById('pickerSearch').value || '').trim().toLowerCase();
        // From the last church that was in the route before this sheet opened, so
        // the distances do not jump about while churches are being added.
        const from = [...list].reverse().find(c => c.lat != null && c.lng != null && !justAdded.includes(c.id));
        let shown = all.filter(c => !list.some(x => x.id === c.id) || justAdded.includes(c.id));
        if (q) shown = shown.filter(c => (c.name + ' ' + (c.town || '') + ' ' + (c.location || '')).toLowerCase().includes(q));
        const dist = c => (from && c.lat != null) ? km(from, c) : null;
        shown.sort((a, b) => {
            const da = dist(a), db = dist(b);
            if (da !== null && db !== null) return da - db;
            if (da !== null) return -1;
            if (db !== null) return 1;
            return a.name.localeCompare(b.name);
        });

        box.innerHTML = shown.length
            ? shown.map(c => {
                const done = justAdded.includes(c.id), d = dist(c);
                // A switch, like the add-to-route circle on the Map page: tap to
                // add, tap again to take it back out.
                return '<button type="button" class="vi-pick' + (done ? ' is-added' : '') + '"' +
                    ' role="switch" aria-checked="' + (done ? 'true' : 'false') + '"' +
                    ' onclick="GiyaVisita.pick(' + c.id + ')">' +
                    '<span class="vi-pick-icon" style="color:' + esc(c.color) + '"><i class="bi bi-giya-spires" aria-hidden="true"></i></span>' +
                    '<span class="vi-pick-text"><span class="vi-pick-name">' + esc(c.name) + '</span>' +
                    '<span class="vi-pick-place">' + esc(c.location || c.town || '') +
                    (d !== null ? ' · ' + (d < 10 ? d.toFixed(1) : Math.round(d)) + ' km' : '') + '</span></span>' +
                    '<span class="vi-pick-tick' + (done ? ' is-on' : '') + '" aria-hidden="true"><i class="bi bi-check-lg"></i></span>' +
                    '<span class="visually-hidden">' + esc(done ? P.added : P.add) + '</span>' +
                    '</button>';
            }).join('')
            : '<p class="vi-pick-empty">' + esc(q ? P.none : P.allIn) + '</p>';

        // How many so far, and a gentle word past the traditional seven.
        const n = list.length;
        document.getElementById('pickerNote').innerHTML =
            esc(P.count.replace(':n', n)) + (n > 7 ? ' <span class="vi-sheet-seven">' + esc(P.seven) + '</span>' : '');
    }

    // Closing the sheet (Done, ✕, the backdrop or Escape) shows where the new
    // churches went: the list scrolls to the last one and it glows briefly.
    (function () {
        const sheet = document.getElementById('addPicker');
        let wasOpen = false;
        new MutationObserver(function () {
            const open = sheet.classList.contains('is-open');
            if (wasOpen && !open && justAdded.length) {
                const last = justAdded[justAdded.length - 1];
                const i = list.findIndex(c => c.id === last);
                const row = document.querySelector('#churchList .vi-row[data-i="' + i + '"]');
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('is-new');
                    setTimeout(() => row.classList.remove('is-new'), 2200);
                }
                justAdded = [];
            }
            wasOpen = open;
        }).observe(sheet, { attributes: true, attributeFilter: ['class'] });
        document.getElementById('pickerSearch').addEventListener('input', refreshPicker);

        /* Swipe down to close, as on any phone sheet: drag the handle or the
           title row. It follows the finger; let go past 90px (or with a quick
           flick) and it closes, otherwise it springs back. */
        const body = document.getElementById('addPickerBody');
        let y0 = null, dy = 0, t0 = 0;
        function startDrag(e) {
            if (e.target.closest('button')) return;
            y0 = e.clientY; dy = 0; t0 = Date.now();
            body.style.transition = 'none';
            if (body.setPointerCapture) body.setPointerCapture(e.pointerId);
        }
        body.querySelector('.vi-sheet-grab').addEventListener('pointerdown', startDrag);
        body.querySelector('.vi-sheet-head').addEventListener('pointerdown', startDrag);
        body.addEventListener('pointermove', function (e) {
            if (y0 === null) return;
            dy = Math.max(0, e.clientY - y0);
            body.style.transform = 'translateY(' + dy + 'px)';
        });
        function endDrag() {
            if (y0 === null) return;
            const quick = dy > 30 && (dy / Math.max(1, Date.now() - t0)) > 0.6;
            y0 = null;
            body.style.transition = 'transform .2s ease';
            if (dy > 90 || quick) {
                body.style.transform = 'translateY(100%)';
                setTimeout(function () {
                    GiyaUI.Modal.close('addPicker');
                    body.style.transition = ''; body.style.transform = '';
                }, 180);
            } else {
                body.style.transform = '';
            }
        }
        body.addEventListener('pointerup', endDrag);
        body.addEventListener('pointercancel', endDrag);
    })();

    render();

    // A route coming back from the map is the devotee's own; leave it, and
    // offer Suggest again instead of replacing it.
    if (returning.length) {
        document.getElementById('viAgain').hidden = false;
    } else {
        locate();
    }

    return {
        toggle: function (i) { list[i].visited = !list[i].visited; render(); },
        move:   function (i, d) {
            const t = i + d;
            if (t < 0 || t >= list.length) return;
            [list[i], list[t]] = [list[t], list[i]];
            render();
        },
        drop: function (i) { if (list.length > 1) { list.splice(i, 1); render(); refreshPicker(); } },
        step: function (d) {
            if (d > 0) {
                // With a start point, the next church is the next nearest town's;
                // without one, the next church not already in the route.
                const next = origin
                    ? ranked(list.length + 1, list.map(c => c.id)).churches[0]
                    : all.find(c => !list.some(x => x.id === c.id));
                if (next) list.push(Object.assign({ visited: false }, next));
            } else if (list.length > 1) {
                list.pop();
            }
            render(); refreshPicker();
        },
        preset: function (n, btn) {
            document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (!suggest(n)) {
                target = n;
                list = all.slice(0, Math.min(n, all.length)).map(c => Object.assign({ visited: false }, c));
                render(); refreshPicker();
            }
        },
        openPicker: function () {
            justAdded = [];
            document.getElementById('pickerSearch').value = '';
            refreshPicker();
            GiyaUI.Modal.open('addPicker');
            document.getElementById('pickerList').scrollTop = 0;
            // On a phone, do not throw the keyboard up over the list at once.
            if (window.matchMedia('(pointer: coarse)').matches) document.activeElement.blur();
        },

        /* Hand the route to the map by id. The map already knows how to read
           ?stops= - it ticks each one, opens the tray and draws the line - so
           this needs to do nothing more than name them, and the two screens
           cannot drift apart because only one of them implements it. */
        viewOnMap: function () {
            /* plan=visita, so the map plans THIS trip rather than a Custom
               one. It keeps the name, saves as a Visita Iglesia itinerary,
               and offers a way back here - which it could not do when all it
               received was a list of ids. */
            const base = @json(route('map', ['plan' => 'visita']));
            const ids  = list.map(c => c.id).filter(id => id != null);

            window.location.href = ids.length ? base + '&stops=' + ids.join(',') : base;
        },
        pick: function (id) {
            const c = all.find(x => x.id === id);
            if (!c) return;
            if (justAdded.includes(id)) {
                // Added by mistake: tapping it again takes it back out.
                list = list.filter(x => x.id !== id);
                justAdded = justAdded.filter(x => x !== id);
            } else if (!list.some(x => x.id === id)) {
                list.push(Object.assign({ visited: false }, c));
                justAdded.push(id);
            }
            render();
            refreshPicker();
        },
        submit: function () {
            if (!list.length) { GiyaConfirm.ask({ title: @json(__('giya.plan.no_churches_t')), message: @json(__('giya.plan.no_churches_b')), ok: @json(__('giya.common.got_it')), cancel: @json(__('giya.common.close')), tone: 'primary' }); return; }
            document.getElementById('stopsInputs').innerHTML = list
                .map(c => '<input type="hidden" name="stops[]" value="' + esc(c.name) + '">')
                .join('') +
                // Ids too, so two churches with the same name stay distinct.
                '<input type="hidden" name="stop_ids" value="' + esc(list.map(c => c.id).join(',')) + '">';
            document.getElementById('visitaForm').submit();
        },
    };
})();
</script>
@endpush
@endsection
