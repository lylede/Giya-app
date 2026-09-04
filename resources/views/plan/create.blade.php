@extends('layouts.app')
@section('title', __('giya.plan.title_create'))

@section('content')
<div class="page-wrap">

    <a href="{{ route('plan.hub') }}" class="back-link">
        <i class="bi bi-chevron-left"></i> {{ __('giya.plan.back_hub') }}
    </a>

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 style="font-family:var(--font-display);font-size: 1.75rem;margin:0 0 4px">{{ __('giya.plan.custom_title') }}</h1>
            <p style="color:var(--text-muted);font-size: 0.875rem;margin:0">{{ __('giya.plan.custom_lead') }}</p>
        </div>
    </div>

    @if ($atLimit)
        <div class="alert alert-warning">
            <i class="bi bi-lock-fill"></i>
            <span>You have reached the free limit of {{ \App\Http\Controllers\ItineraryController::FREE_LIMIT }} saved itineraries.
                  <a href="{{ route('upgrade') }}" style="font-weight:700;color:inherit;text-decoration:underline">{{ __('giya.plan.go_premium') }}</a>
                  {{ __('giya.plan.premium_note') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form id="planForm" method="POST" action="{{ route('plan.store') }}">
        @csrf
        <input type="hidden" name="type" value="Custom">
        <div id="stopsInputs"></div>

        {{--
            The route is the thing being built, so it gets the wide column on
            the left and the destinations to pick from sit on the right - the
            workspace-and-palette arrangement, rather than the route being the
            far column you glance across to.
        --}}
        <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:28px" class="create-grid">

            {{-- 1. Trip details --}}
            <div class="card card-body pane-details">
                    <div class="card-title" style="color:var(--primary)">{{ __('giya.plan.trip_details') }}</div>

                    <div class="field">
                        <label class="form-label-sm" for="pl-name">{{ __('giya.plan.itinerary_name') }}</label>
                        <input id="pl-name" type="text" name="name" class="giya-input"
                               value="{{ old('name') }}" placeholder="{{ __('giya.plan.name_ph') }}" required maxlength="200">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="field">
                            <label class="form-label-sm" for="pl-date">{{ __('giya.plan.date') }}</label>
                            <input id="pl-date" type="date" name="scheduled_date" class="giya-input"
                                   value="{{ old('scheduled_date') }}" min="{{ now()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label class="form-label-sm" for="pl-time">{{ __('giya.plan.start_time') }}</label>
                            <input id="pl-time" type="time" class="giya-input" value="08:00"
                                   onchange="GiyaPlanner.render()">
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="form-label-sm" for="pl-notes">Notes (optional)</label>
                        <textarea id="pl-notes" name="notes" class="giya-input" rows="2"
                                  placeholder="{{ __('giya.plan.notes_ph') }}">{{ old('notes') }}</textarea>
                    </div>
            </div>

            {{-- 2. The route being built --}}
            <div class="pane-route">
                <div class="card card-body mb-3">
                    {{--
                        Title, estimate and the map note used to sit in one
                        un-wrapping flex row. On a phone that squeezed the
                        title into a three-line column beside the note. They
                        are now a wrapping row with the note on its own line.
                    --}}
                    <div class="route-head mb-3">
                        <div class="route-head-top">
                            <div class="card-title" style="margin:0">Route (<span id="stopCount">0</span> stops)</div>
                            <div id="routeEstimate" class="route-est" style="display:none"></div>
                        </div>
                        <p id="presetNote" class="plan-preset-note" hidden>
                            <i class="bi bi-check-circle-fill"></i>
                            {{ __('giya.plan.from_map') }}
                        </p>
                    </div>

                    <div id="routeEmpty" class="empty-state" style="padding:56px 20px">
                        <div class="empty-icon"><i class="bi bi-signpost-2" style="color:var(--gold)"></i></div>
                        <div class="empty-title" style="font-size: 0.9375rem">{{ __('giya.plan.no_stops') }}</div>
                        <div class="empty-desc">{{ __('giya.plan.no_stops_lead') }}</div>
                    </div>

                    <div id="routeList" class="d-none"></div>
                </div>

                <div id="routeActions" class="d-none gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary" style="flex:1;min-width:180px"
                            onclick="GiyaPlanner.submit()" @disabled($atLimit)>
                        <i class="bi bi-person-walking"></i> {{ __('giya.plan.start') }}
                    </button>
                    <button type="button" class="btn btn-outline" id="planViewMap">
                        <i class="bi bi-map-fill"></i> {{ __('giya.plan.view_on_map') }}
                    </button>
                </div>
            </div>

            {{-- 3. The destinations to choose from --}}
            <div class="pane-picker">
                <div class="card card-body picker-card">
                    <div class="card-title" style="color:var(--primary)">{{ __('giya.plan.destinations') }}</div>
                    <div class="dest-list">
                        @foreach ($churches as $church)
                            <button type="button" class="dest-item" id="dest-{{ $church->id }}"
                                    data-name="{{ $church->name }}" data-location="{{ $church->location }}"
                                    data-image="{{ $church->imagePath() }}"
                                    onclick="GiyaPlanner.add({{ $church->id }})">
                                {{-- A photograph identifies a church far faster than the
                                     generic building glyph that was here. imagePath()
                                     falls back to a local placeholder, so it never
                                     points at a remote URL and never renders broken. --}}
                                <img class="dest-thumb" src="{{ $church->imagePath() }}" alt="" loading="lazy">
                                <span style="flex:1;min-width:0;text-align:left">
                                    <span style="display:block;font-size: 0.75rem;font-weight:600;color:var(--text)">{{ $church->name }}</span>
                                    <span style="display:block;font-size: 0.625rem;color:var(--text-muted)">{{ $church->location }}</span>
                                </span>
                                <span class="dest-mark" style="color:var(--primary);font-size: 1.0625rem;font-weight:700">+</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('head')
<style>
    .dest-item { display:flex; align-items:center; gap:10px; padding:9px 11px; border-radius:11px;
                 background:var(--bg-input); border:1.5px solid var(--border); cursor:pointer;
                 transition:all .18s; width:100%; }
    .dest-item:hover:not(.added) { border-color:var(--primary); background:rgba(142,59,47,0.04); }
    .dest-item.added { background:var(--gold-bg); border-color:var(--gold); opacity:.65; cursor:default; }
    .route-row { display:flex; gap:12px; }
    .route-node { display:flex; flex-direction:column; align-items:center; }
    .route-dot  { width:32px; height:32px; border-radius:50%; display:flex; align-items:center;
                  justify-content:center; flex-shrink:0; font-size: 0.6875rem; font-weight:700; color:#fff;
                  background:var(--primary); border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.18); }
    .route-line { width:2px; flex:1; min-height:16px; background:rgba(215,169,74,.4); margin:2px auto; }
    .route-body { flex:1; padding-bottom:12px; }
    .route-card { display:flex; align-items:flex-start; gap:10px; padding:12px; border-radius:13px;
                  background:var(--bg); border:1px solid var(--border); }
    .route-btn  { background:none; border:none; cursor:pointer; font-size: 0.8125rem; line-height:1; padding:2px; }

    /* Set by the renderer once the route passes ROUTE_VISIBLE stops. The
       height itself is measured from the last visible row rather than
       hard-coded, so it stays exactly N rows on any viewport - a route row is
       98px wide-screen and 119px on a phone, where the name wraps. */
    .route-list-scrolls { overflow-y: auto; padding-right: 8px; scrollbar-gutter: stable; }

    /* Church photographs, in the picker and in the route. object-fit keeps
       portrait and landscape shots square without squashing either. */
    .dest-thumb, .route-thumb {
        border-radius:9px; object-fit:cover; flex-shrink:0;
        background:var(--gold-bg); border:1px solid var(--border);
    }
    .dest-thumb  { width:40px; height:40px; }
    .route-thumb { width:44px; height:44px; }

    .dest-list { display:flex; flex-direction:column; gap:8px; max-height:420px; overflow-y:auto; }

    /* The header wraps instead of crushing the title into a narrow column. */
    .route-head     { display:flex; flex-direction:column; gap:6px; }
    .route-head-top { display:flex; align-items:baseline; justify-content:space-between;
                      gap:10px; flex-wrap:wrap; }
    .route-est      { font-size: 0.75rem; color:var(--text-muted); white-space:nowrap; }

    /* Three panes, placed explicitly. Trip Details and the Route stack down
       the wide left column; the picker fills the narrow right one beside
       them both, so it stays in view while stops are being added. */
    .pane-details { grid-column: 1; grid-row: 1; align-self: start; }
    .pane-route   { grid-column: 1; grid-row: 2; }

    /* The picker runs the full height of the two panes beside it, so the
       column ends level instead of stopping short.
       The grid item is an empty positioned box and the card is absolutely
       placed inside it. That is what keeps a long church list from sizing
       the rows: min-height:0 alone was not enough - the list still counted
       towards the tracks it spans, which grew row 1 and left a gap between
       Trip Details and the Route. With the card out of flow the rows are
       sized by those two panes alone, and the card fills whatever they
       come to, scrolling inside itself. */
    .pane-picker { grid-column: 2; grid-row: 1 / span 2; position: relative; }
    .picker-card {
        position: absolute; inset: 0;
        display: flex; flex-direction: column; min-height: 0;
    }
    .picker-card .dest-list { flex: 1 1 auto; min-height: 0; max-height: none; }

    @media (max-width: 950px) {
        .create-grid { grid-template-columns: 1fr !important; }
        /* Stacked, the order is the order of the work: name the trip, pick
           the churches, then look at the route they made. Placing them by
           grid-area rather than reordering whole columns is what lets the
           picker sit between the other two here but beside them above. */
        .pane-details, .pane-route, .pane-picker { grid-column: 1; }
        .pane-details { grid-row: 1; }
        .pane-picker  { grid-row: 2; }
        .pane-route   { grid-row: 3; }

        /* Stacked there is nothing to match heights with, so the card returns
           to normal flow and the list to a fixed scrolling height. */
        .picker-card { position: static; }
        .picker-card .dest-list { flex: 0 1 auto; max-height: 300px; }
    }
</style>
@endpush

@push('scripts')
<script>
const GiyaPlanner = (function () {
    let stops = [];

    /* Past this many stops the route scrolls instead of growing. That also
       stops the whole left column growing, which is what keeps the Add
       Destinations panel beside it from stretching any further. */
    const ROUTE_VISIBLE = 7;

    /* Church names reach innerHTML below. They come from our own database, but
       an apostrophe in "Our Lady's" is enough to break the markup on its own,
       so everything interpolated is escaped. */
    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* Stops chosen on the map arrive already ordered. Adding them through the
       same add() the buttons use means the list, the counter and the hidden
       inputs are all built by one code path - nothing special-cased. */
    const PRESET = @json($preset ?? []);

    function add(id) {
        if (stops.some(s => s.id === id)) return;
        const el = document.getElementById('dest-' + id);
        stops.push({ id: id, name: el.dataset.name, location: el.dataset.location,
                     image: el.dataset.image });
        el.classList.add('added');
        el.querySelector('.dest-mark').textContent = '✓';
        el.querySelector('.dest-mark').style.color = 'var(--gold)';
        el.onclick = null;
        render();
    }

    function remove(id) {
        stops = stops.filter(s => s.id !== id);
        const el = document.getElementById('dest-' + id);
        if (el) {
            el.classList.remove('added');
            el.querySelector('.dest-mark').textContent = '+';
            el.querySelector('.dest-mark').style.color = 'var(--primary)';
            el.onclick = function () { add(id); };
        }
        render();
    }

    function move(index, delta) {
        const target = index + delta;
        if (target < 0 || target >= stops.length) return;
        [stops[index], stops[target]] = [stops[target], stops[index]];
        render();
    }

    /*
       Show the first ROUTE_VISIBLE stops and scroll the rest.

       The cap is measured from the bottom of the last row that should be
       visible, not calculated from a row height. Rows are not all the same
       height - the final one has no connector beneath it - and they grow when
       a long church name wraps on a narrow screen, so any fixed number would
       cut a row in half on some device.
    */
    function capRouteHeight(list) {
        list.classList.remove('route-list-scrolls');
        list.style.maxHeight = '';

        if (stops.length <= ROUTE_VISIBLE) return;

        /*
           Reserve the scrollbar's width BEFORE measuring. Measuring first and
           adding the scrollbar afterwards narrows the rows, which rewraps a
           long church name onto a second line and makes every row taller than
           the height just measured - enough that the seventh row ended up
           half cut off on a phone. scrollbar-gutter holds the space open, so
           what is measured is the width the rows will actually have.
        */
        list.classList.add('route-list-scrolls');
        list.style.maxHeight = 'none';

        const lastVisible = list.children[ROUTE_VISIBLE - 1];

        if (!lastVisible) {
            list.classList.remove('route-list-scrolls');
            return;
        }

        const height = lastVisible.getBoundingClientRect().bottom
                     - list.getBoundingClientRect().top;

        list.style.maxHeight = Math.ceil(height) + 'px';
    }

    function render() {
        document.getElementById('stopCount').textContent = stops.length;
        const empty   = document.getElementById('routeEmpty');
        const list    = document.getElementById('routeList');
        const actions = document.getElementById('routeActions');

        if (!stops.length) {
            empty.classList.remove('d-none');
            list.classList.add('d-none');
            actions.classList.add('d-none');
            document.getElementById('routeEstimate').style.display = 'none';
            return;
        }

        empty.classList.add('d-none');
        list.classList.remove('d-none');
        actions.classList.remove('d-none');
        actions.classList.add('d-flex');

        const start = (document.getElementById('pl-time').value || '08:00').split(':').map(Number);
        let html = '';

        stops.forEach(function (stop, i) {
            const minutes = start[0] * 60 + start[1] + i * 40;
            const hh = String(Math.floor(minutes / 60) % 24).padStart(2, '0');
            const mm = String(minutes % 60).padStart(2, '0');
            const last = i === stops.length - 1;

            html +=
              '<div class="route-row">' +
                '<div class="route-node"><div class="route-dot">' + (i + 1) + '</div>' +
                  (last ? '' : '<div class="route-line"></div>') +
                '</div>' +
                '<div class="route-body"><div class="route-card">' +
                  '<img class="route-thumb" src="' + esc(stop.image) + '" alt="" loading="lazy">' +
                  '<div style="flex:1;min-width:0">' +
                    '<div style="font-size: 0.8125rem;font-weight:700;color:var(--text)">' + esc(stop.name) + '</div>' +
                    '<div style="font-size: 0.6875rem;color:var(--text-muted)">' + esc(stop.location) + '</div>' +
                    '<div style="font-size: 0.6875rem;color:var(--primary);font-weight:700;margin-top:4px">Arrive ~' + hh + ':' + mm + '</div>' +
                  '</div>' +
                  '<div style="display:flex;flex-direction:column;gap:3px">' +
                    '<button type="button" class="route-btn" onclick="GiyaPlanner.move(' + i + ',-1)" style="color:' + (i === 0 ? '#D8C4BC' : 'var(--primary)') + '">▲</button>' +
                    '<button type="button" class="route-btn" onclick="GiyaPlanner.move(' + i + ',1)" style="color:' + (last ? '#D8C4BC' : 'var(--primary)') + '">▼</button>' +
                    '<button type="button" class="route-btn" onclick="GiyaPlanner.remove(' + stop.id + ')" style="color:#D4183D;font-size: 0.9375rem">×</button>' +
                  '</div>' +
                '</div></div>' +
              '</div>';
        });

        list.innerHTML = html;
        capRouteHeight(list);

        const total = stops.length * 30 + (stops.length - 1) * 10;
        const est   = document.getElementById('routeEstimate');
        est.textContent = 'Estimated ' + Math.floor(total / 60) + 'h ' + (total % 60) + 'min';
        est.style.display = 'block';
    }

    function loadPreset() {
        if (!PRESET.length) return;

        PRESET.forEach(function (c) {
            if (document.getElementById('dest-' + c.id)) {
                add(c.id);
            }
        });

        const note = document.getElementById('presetNote');
        if (note) note.hidden = false;
    }

    document.addEventListener('DOMContentLoaded', loadPreset);

    /* Show what is in the route, not a fresh map. The ids go back the same way
       they came, so the map arrives ticked and framed on those churches. */
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('planViewMap');
        if (!btn) return;

        btn.addEventListener('click', function () {
            const base = @json(route('map'));

            if (!stops.length) {
                window.location.href = base;
                return;
            }

            const ids = stops.map(function (s) { return s.id; }).join(',');
            window.location.href = base + '?stops=' + ids;
        });
    });


    return {
        add: add, remove: remove, move: move, render: render,
        submit: function () {
            if (!stops.length) { GiyaConfirm.ask({ title: @json(__('giya.plan.no_dest_title')), message: @json(__('giya.plan.no_dest_body')), ok: @json(__('giya.common.got_it')), cancel: @json(__('giya.common.close')), tone: 'primary', icon: 'signpost-2-fill' }); return; }

            /* The names are what the server saves. The ids ride along so that
               if the server does reject the form, the route can be rebuilt
               from them rather than the devotee having to add every church
               again - see $preset in ItineraryController::create(). */
            document.getElementById('stopsInputs').innerHTML = stops
                .map(s => '<input type="hidden" name="stops[]" value="' + esc(s.name) + '">')
                .join('')
                + '<input type="hidden" name="stop_ids" value="' + stops.map(s => s.id).join(',') + '">';

            const form = document.getElementById('planForm');

            /*
               requestSubmit(), not submit().

               form.submit() posts without running the browser's own checks, so
               leaving the itinerary name empty sent the form anyway; the server
               rejected it, the page reloaded, and the route was gone.
               requestSubmit() validates first, so the name field is flagged in
               place and nothing is lost. Older browsers fall back to asking for
               the same check explicitly.
            */
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else if (form.reportValidity()) {
                form.submit();
            }
        },
    };
})();
</script>
@endpush
@endsection
