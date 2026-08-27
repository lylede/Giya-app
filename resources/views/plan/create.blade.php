@extends('layouts.app')
@section('title', 'Create Itinerary')

@section('content')
<div class="page-wrap">

    <a href="{{ route('plan.hub') }}" class="back-link">
        <i class="bi bi-chevron-left"></i> Back to Plan Hub
    </a>

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 style="font-family:var(--font-display);font-size: 1.75rem;margin:0 0 4px">Custom Itinerary Planner</h1>
            <p style="color:var(--text-muted);font-size: 0.875rem;margin:0">Build a personalised pilgrimage route across Metro Cebu</p>
        </div>
    </div>

    @if ($atLimit)
        <div class="alert alert-warning">
            <i class="bi bi-lock-fill"></i>
            <span>You have reached the free limit of {{ \App\Http\Controllers\ItineraryController::FREE_LIMIT }} saved itineraries.
                  Delete one from <a href="{{ route('plan.index') }}" style="font-weight:700;color:inherit;text-decoration:underline">My Itineraries</a> to create another.</span>
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

        <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:28px" class="create-grid">

            {{-- Left column --}}
            <div>
                <div class="card card-body mb-3">
                    <div class="card-title" style="color:var(--primary)">Trip Details</div>

                    <div class="field">
                        <label class="form-label-sm" for="pl-name">Itinerary Name</label>
                        <input id="pl-name" type="text" name="name" class="giya-input"
                               value="{{ old('name') }}" placeholder="e.g. Cebu City Pilgrimage" required maxlength="200">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="field">
                            <label class="form-label-sm" for="pl-date">Date</label>
                            <input id="pl-date" type="date" name="scheduled_date" class="giya-input"
                                   value="{{ old('scheduled_date') }}" min="{{ now()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label class="form-label-sm" for="pl-time">Start Time</label>
                            <input id="pl-time" type="time" class="giya-input" value="08:00"
                                   onchange="GiyaPlanner.render()">
                        </div>
                    </div>

                    <div class="field">
                        <label class="form-label-sm">Transport</label>
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach (['Walk', 'Jeepney', 'Taxi', 'Private Car'] as $i => $mode)
                                <button type="button" @class(['transport-btn', 'active' => $i === 0])
                                        onclick="GiyaPlanner.setTransport(this)">{{ $mode }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label class="form-label-sm" for="pl-notes">Notes (optional)</label>
                        <textarea id="pl-notes" name="notes" class="giya-input" rows="2"
                                  placeholder="Anything to remember for this trip…">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="card card-body mb-3">
                    <div class="card-title" style="color:var(--primary)">Add Destinations</div>
                    <div style="display:flex;flex-direction:column;gap:8px;max-height:320px;overflow-y:auto">
                        @foreach ($churches as $church)
                            <button type="button" class="dest-item" id="dest-{{ $church->id }}"
                                    data-name="{{ $church->name }}" data-location="{{ $church->location }}"
                                    onclick="GiyaPlanner.add({{ $church->id }})">
                                <span style="width:34px;height:34px;border-radius:9px;background:var(--gold-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <i class="bi bi-building" style="font-size: 0.9375rem;color:{{ $church->color() }}"></i>
                                </span>
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

            {{-- Right column --}}
            <div>
                <div class="card card-body mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="card-title" style="margin:0">Route (<span id="stopCount">0</span> stops)</div>
                        <p id="presetNote" class="plan-preset-note" hidden>
                            <i class="bi bi-check-circle-fill"></i>
                            Brought over from the map - add or remove any stop before saving.
                        </p>
                        <div id="routeEstimate" style="font-size: 0.75rem;color:var(--text-muted);display:none"></div>
                    </div>

                    <div id="routeEmpty" class="empty-state" style="padding:56px 20px">
                        <div class="empty-icon"><i class="bi bi-signpost-2" style="color:var(--gold)"></i></div>
                        <div class="empty-title" style="font-size: 0.9375rem">No stops yet</div>
                        <div class="empty-desc">Add destinations from the panel on the left.</div>
                    </div>

                    <div id="routeList" class="d-none"></div>
                </div>

                <div id="routeActions" class="d-none gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary" style="flex:1;min-width:180px"
                            onclick="GiyaPlanner.submit()" @disabled($atLimit)>
                        <i class="bi bi-person-walking"></i> Start Pilgrimage
                    </button>
                    <button type="button" class="btn btn-outline" id="planViewMap">
                        <i class="bi bi-map-fill"></i> View on Map
                    </button>
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
    .transport-btn { padding:8px 14px; border-radius:10px; font-size: 0.75rem; border:1.5px solid var(--border);
                     color:var(--text-muted); background:var(--bg); cursor:pointer; transition:all .18s;
                     font-family:var(--font-body); }
    .transport-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
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
    @media (max-width: 950px) { .create-grid { grid-template-columns: 1fr !important; } }
</style>
@endpush

@push('scripts')
<script>
const GiyaPlanner = (function () {
    let stops = [];

    /* Stops chosen on the map arrive already ordered. Adding them through the
       same add() the buttons use means the list, the counter and the hidden
       inputs are all built by one code path - nothing special-cased. */
    const PRESET = @json($preset ?? []);

    function add(id) {
        if (stops.some(s => s.id === id)) return;
        const el = document.getElementById('dest-' + id);
        stops.push({ id: id, name: el.dataset.name, location: el.dataset.location });
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
                  '<div style="flex:1;min-width:0">' +
                    '<div style="font-size: 0.8125rem;font-weight:700;color:var(--text)">' + stop.name + '</div>' +
                    '<div style="font-size: 0.6875rem;color:var(--text-muted)">' + stop.location + '</div>' +
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
        setTransport: function (btn) {
            document.querySelectorAll('.transport-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        },
        submit: function () {
            if (!stops.length) { GiyaConfirm.ask({ title: 'No destinations yet', message: 'Add at least one destination to this itinerary before starting it.', ok: 'Got it', cancel: 'Close', tone: 'primary' }); return; }
            document.getElementById('stopsInputs').innerHTML = stops
                .map(s => '<input type="hidden" name="stops[]" value="' + s.name.replace(/"/g, '&quot;') + '">')
                .join('');
            document.getElementById('planForm').submit();
        },
    };
})();
</script>
@endpush
@endsection
