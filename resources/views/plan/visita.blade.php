@extends('layouts.app')
@section('title', 'Visita Iglesia')

@section('content')
<div class="page-wrap">

    <a href="{{ route('plan.hub') }}" class="back-link">
        <i class="bi bi-chevron-left"></i> Back to Plan Hub
    </a>

    <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
        <h1 style="font-family:var(--font-display);font-size: 1.75rem;margin:0">Visita Iglesia Planner</h1>
        <span class="badge badge-brown">Traditionally 7 churches</span>
    </div>
    <p style="color:var(--text-muted);font-size: 0.875rem;margin:0 0 32px">
        The traditional multi-church pilgrimage - adjust the number of stops to suit your devotion and schedule.
    </p>

    @if ($atLimit)
        <div class="alert alert-warning">
            <i class="bi bi-lock-fill"></i>
            <span>You have reached the free limit of {{ \App\Http\Controllers\ItineraryController::FREE_LIMIT }} saved itineraries.
                  <a href="{{ route('upgrade') }}" style="font-weight:700;color:inherit;text-decoration:underline">Go Premium</a> for unlimited routes.</span>
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
                <div class="upgrade-card mb-3">
                    <div style="position:relative">
                        <div style="color:rgba(255,255,255,0.7);font-size: 0.75rem;margin-bottom:4px">Route Progress</div>
                        <div style="font-family:var(--font-display);color:var(--gold);font-size: 1.875rem;font-weight:700;line-height:1">
                            <span id="visitedCount">0</span> of <span id="totalCountA">7</span>
                        </div>
                        <div style="color:rgba(255,255,255,0.8);font-size: 0.8125rem;margin-top:2px">churches marked visited</div>
                        <div class="progress-track" style="margin-top:14px">
                            <div class="progress-fill" id="progressBar" style="width:0%"></div>
                        </div>
                        <div id="progressLabel" style="color:var(--gold);font-size: 0.75rem;font-weight:700;margin-top:6px">0% complete</div>
                    </div>
                </div>

                <div class="card card-body mb-3">
                    <div class="card-title">Itinerary Details</div>
                    <div class="field">
                        <label class="form-label-sm" for="vi-name">Route Name</label>
                        <input id="vi-name" type="text" name="name" class="giya-input"
                               value="{{ old('name', 'Visita Iglesia Route') }}" required maxlength="200">
                    </div>
                    <div class="field" style="margin-bottom:0">
                        <label class="form-label-sm" for="vi-date">Date</label>
                        <input id="vi-date" type="date" name="scheduled_date" class="giya-input"
                               value="{{ old('scheduled_date') }}" min="{{ now()->toDateString() }}">
                    </div>
                </div>

                <div class="card card-body mb-3">
                    <div class="card-title">Adjust Church Count</div>
                    <div class="d-flex align-items-center gap-4 mb-3">
                        <button type="button" onclick="GiyaVisita.step(-1)"
                                style="width:40px;height:40px;border-radius:12px;border:none;cursor:pointer;font-size: 1.25rem;font-weight:700;background:var(--gold-bg);color:var(--primary)">−</button>
                        <div class="text-center">
                            <div id="countValue" style="font-family:var(--font-display);font-size: 1.875rem;font-weight:700;color:var(--primary);line-height:1">7</div>
                            <div style="font-size: 0.6875rem;color:var(--text-muted)">churches</div>
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

                <div class="card card-body mb-3 d-none" id="addPicker">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span style="font-size: 0.875rem;font-weight:700;color:var(--primary)">Add a Church</span>
                        <button type="button" class="route-btn" onclick="document.getElementById('addPicker').classList.add('d-none')">
                            <i class="bi bi-x" style="font-size: 1.125rem;color:var(--text-muted)"></i>
                        </button>
                    </div>
                    <div id="pickerList" style="display:flex;flex-direction:column;gap:6px;max-height:220px;overflow-y:auto"></div>
                </div>

                <button type="button" class="btn btn-primary btn-w-full" onclick="GiyaVisita.submit()" @disabled($atLimit)>
                    <i class="bi bi-person-walking"></i> Start Pilgrimage
                </button>
            </div>

            {{-- Right --}}
            <div>
                <div class="card card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="card-title" style="margin:0">Your Visita Iglesia Route</div>
                        <span id="countLabel" style="font-size: 0.75rem;color:var(--text-muted)">7 churches</span>
                    </div>

                    <div id="churchList" style="display:flex;flex-direction:column;gap:10px"></div>

                    <button type="button" onclick="GiyaVisita.openPicker()"
                            style="width:100%;margin-top:12px;padding:12px;border-radius:13px;border:2px dashed rgba(215,169,74,.45);color:var(--primary);font-weight:600;font-size: 0.8125rem;background:rgba(215,169,74,.05);cursor:pointer;font-family:var(--font-body)">
                        <i class="bi bi-plus-lg"></i> Add Church
                    </button>
                </div>
            </div>
        </div>
    </form>
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
    @media (max-width: 950px) { .visita-grid { grid-template-columns: 1fr !important; } }
</style>
@endpush

@push('scripts')
<script>
const GiyaVisita = (function () {
        const all = {!! $churches->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 
        'location' => $c->location, 'color' => $c->color()])->values()->toJson() !!};
    let list = all.slice(0, Math.min(7, all.length)).map(c => Object.assign({ visited: false }, c));

    function render() {
        const done  = list.filter(c => c.visited).length;
        const total = list.length;
        const pct   = total ? Math.round((done / total) * 100) : 0;
        const current = list.find(c => !c.visited);

        document.getElementById('visitedCount').textContent = done;
        document.getElementById('totalCountA').textContent  = total;
        document.getElementById('countValue').textContent   = total;
        document.getElementById('countLabel').textContent   = total + (total === 1 ? ' church' : ' churches');
        document.getElementById('progressBar').style.width  = pct + '%';
        document.getElementById('progressLabel').textContent = pct + '% complete';

        document.getElementById('churchList').innerHTML = list.map(function (c, i) {
            const isCurrent = current && current.id === c.id;
            const cls = c.visited ? 'done' : (isCurrent ? 'current' : 'todo');
            const badgeBg = c.visited ? 'var(--primary)' : (isCurrent ? 'var(--gold-bg)' : '#fff');
            const inner = c.visited
                ? '<i class="bi bi-check-lg" style="color:var(--gold);font-size: 1.0625rem"></i>'
                : '<span style="font-size: 0.75rem;font-weight:700;color:' + (isCurrent ? 'var(--primary)' : 'var(--text-muted)') + '">' + (i + 1) + '</span>';

            return '<div class="vi-row ' + cls + '">' +
                '<span style="width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(142,59,47,.15);background:' + badgeBg + '">' + inner + '</span>' +
                '<span style="flex:1;min-width:0">' +
                  '<span style="display:block;font-size: 0.875rem;font-weight:600;color:var(--text)' + (c.visited ? ';text-decoration:line-through;opacity:.65' : '') + '">' + c.name + '</span>' +
                  '<span style="display:block;font-size: 0.6875rem;color:var(--text-muted);margin-top:2px">' + c.location + '</span>' +
                  (isCurrent ? '<span class="badge badge-primary" style="margin-top:4px">Next stop</span>' : '') +
                '</span>' +
                '<span style="display:flex;align-items:center;gap:8px;flex-shrink:0">' +
                  '<span style="display:flex;flex-direction:column;gap:2px">' +
                    '<button type="button" class="route-btn" onclick="GiyaVisita.move(' + i + ',-1)" style="color:' + (i === 0 ? '#D8C4BC' : 'var(--primary)') + ';font-size: 0.75rem">▲</button>' +
                    '<button type="button" class="route-btn" onclick="GiyaVisita.move(' + i + ',1)" style="color:' + (i === list.length - 1 ? '#D8C4BC' : 'var(--primary)') + ';font-size: 0.75rem">▼</button>' +
                  '</span>' +
                  '<button type="button" onclick="GiyaVisita.toggle(' + i + ')" style="padding:8px 13px;border-radius:10px;font-size: 0.75rem;font-weight:700;border:none;cursor:pointer;min-width:100px;font-family:var(--font-body);background:' + (c.visited ? 'var(--gold-bg)' : 'var(--primary)') + ';color:' + (c.visited ? 'var(--primary)' : '#fff') + '">' + (c.visited ? 'Visited' : 'Mark Visited') + '</button>' +
                  '<button type="button" onclick="GiyaVisita.drop(' + i + ')" style="width:28px;height:28px;border-radius:8px;background:rgba(212,24,61,.08);color:#D4183D;border:none;cursor:pointer;font-size: 0.9375rem">×</button>' +
                '</span></div>';
        }).join('');
    }

    function refreshPicker() {
        const available = all.filter(c => !list.some(x => x.id === c.id));
        const box = document.getElementById('pickerList');
        box.innerHTML = available.length
            ? available.map(c =>
                '<button type="button" onclick="GiyaVisita.pick(' + c.id + ')" style="display:flex;align-items:center;gap:8px;padding:9px 11px;border-radius:10px;background:var(--bg);border:1px solid var(--border);cursor:pointer;text-align:left;width:100%">' +
                '<i class="bi bi-building" style="color:' + c.color + '"></i>' +
                '<span><span style="display:block;font-size: 0.75rem;font-weight:600;color:var(--text)">' + c.name + '</span>' +
                '<span style="display:block;font-size: 0.625rem;color:var(--text-muted)">' + c.location + '</span></span></button>').join('')
            : '<p style="font-size: 0.75rem;color:var(--text-muted);padding:8px;margin:0">Every destination is already in your route.</p>';
    }

    render();

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
                const next = all.find(c => !list.some(x => x.id === c.id));
                if (next) list.push(Object.assign({ visited: false }, next));
            } else if (list.length > 1) {
                list.pop();
            }
            render(); refreshPicker();
        },
        preset: function (n, btn) {
            document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            list = all.slice(0, Math.min(n, all.length)).map(c => Object.assign({ visited: false }, c));
            render(); refreshPicker();
        },
        openPicker: function () { refreshPicker(); document.getElementById('addPicker').classList.remove('d-none'); },
        pick: function (id) {
            const c = all.find(x => x.id === id);
            if (c) { list.push(Object.assign({ visited: false }, c)); render(); refreshPicker(); }
            document.getElementById('addPicker').classList.add('d-none');
        },
        submit: function () {
            if (!list.length) { GiyaConfirm.ask({ title: 'No churches selected', message: 'Add at least one church to your Visita Iglesia route before saving it.', ok: 'Got it', cancel: 'Close', tone: 'primary' }); return; }
            document.getElementById('stopsInputs').innerHTML = list
                .map(c => '<input type="hidden" name="stops[]" value="' + c.name.replace(/"/g, '&quot;') + '">')
                .join('');
            document.getElementById('visitaForm').submit();
        },
    };
})();
</script>
@endpush
@endsection
