@extends('layouts.app')
@section('title', 'Active Pilgrimage')
@section('no-footer', true)

@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/leaflet.css') }}?v={{ filemtime(public_path('assets/css/leaflet.css')) }}">
<style>
    body { overflow: hidden; }
    .active-layout { height: calc(100vh - 64px); }
    .ap-head { background: linear-gradient(135deg, var(--primary), var(--primary-dark));
               padding: 20px; position: relative; overflow: hidden; flex-shrink: 0; }
    .ap-head::after { content:''; position:absolute; top:-15px; right:-15px; width:96px; height:96px;
                      border-radius:50%; background:var(--gold); opacity:.15; }
    .ap-banner { padding: 14px 16px; background: var(--gold-bg);
                 border-bottom: 1px solid rgba(142,59,47,.12); flex-shrink: 0; }
    .ap-list { flex: 1; overflow-y: auto; padding: 8px 0; }
    .ap-list::-webkit-scrollbar { width: 4px; }
    .ap-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }
    .ap-foot { padding: 14px; border-top: 1px solid var(--border);
               display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }
    .ap-toolbar { position: absolute; bottom: 24px; right: 24px; z-index: 10;
                  display: flex; flex-direction: column; gap: 8px; }
</style>
@endpush

@section('content')
<div class="active-layout">

    <aside class="active-sidebar">
        <div class="ap-head">
            <div style="position:relative">
                <a href="{{ route('plan.index') }}"
                   style="display:inline-flex;align-items:center;gap:4px;color:rgba(255,255,255,.7);font-size: 0.75rem;margin-bottom:10px">
                    <i class="bi bi-chevron-left"></i> All itineraries
                </a>
                <h1 style="font-family:var(--font-display);color:#fff;font-size: 1.1875rem;margin:0 0 2px">{{ $itinerary->name }}</h1>
                <p style="color:rgba(255,255,255,.7);font-size: 0.75rem;margin:0">
                    <span id="visitedCount">{{ $stops->where('is_visited', true)->count() }}</span>
                    of {{ $stops->count() }} stops visited
                </p>
                <div class="progress-track" style="margin-top:12px">
                    <div class="progress-fill" id="progressBar" style="width:{{ $itinerary->progressPercent() }}%"></div>
                </div>
                <div id="progressLabel" style="color:var(--gold);font-size: 0.75rem;font-weight:700;margin-top:4px">
                    {{ $itinerary->progressPercent() }}% complete
                </div>
            </div>
        </div>

        <div class="ap-banner" id="currentBanner">
            <div style="font-size: 0.625rem;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Current stop</div>
            <div id="currentName" style="font-size: 0.875rem;font-weight:700;color:var(--text)"></div>
            <div id="currentNext" style="font-size: 0.6875rem;color:var(--primary);margin-top:4px"></div>
        </div>

        <div class="ap-banner d-none" id="doneBanner" style="text-align:center">
            <i class="bi bi-award-fill" style="font-size: 1.625rem;color:var(--gold)"></i>
            <div style="font-size: 0.875rem;font-weight:700;color:var(--text);margin-top:4px">Pilgrimage complete</div>
            <div style="font-size: 0.75rem;color:var(--text-muted)">All {{ $stops->count() }} churches visited</div>
        </div>

        <div class="ap-list" id="stopList"></div>

        <div class="ap-foot">
            <button type="button" class="btn btn-primary btn-w-full" id="markBtn" onclick="GiyaActive.markCurrent()">
                <i class="bi bi-check-lg"></i> Mark Current Visited
            </button>
            <form method="POST" action="{{ route('plan.destroy', $itinerary) }}"
                  data-confirm-title="End this pilgrimage?"
                  data-confirm="Your progress and this itinerary are deleted. Visits you already recorded stay in your history."
                  data-confirm-ok="End pilgrimage">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-w-full">End Pilgrimage</button>
            </form>
        </div>
    </aside>

    <div class="map-wrap">
        @php
            $points = $stops
                ->filter(fn ($s) => $s->church && $s->church->latitude && $s->church->longitude)
                ->map(fn ($s) => [
                    'id'       => $s->id,
                    'name'     => $s->church_name,
                    'lat'      => (float) $s->church->latitude,
                    'lng'      => (float) $s->church->longitude,
                    'image'    => $s->church->imagePath(),
                    'color'    => $s->is_visited ? '#6B9B5A' : $s->church->color(),
                    'location' => $s->church->location,
                    'order'    => $s->stop_order,
                    'visited'  => (bool) $s->is_visited,
                ])->values();
        @endphp

        <div class="giya-map-canvas" id="activeMap" style="height:100%;border-radius:0;border:0"></div>

        <div class="map-tools">
            <button type="button" class="map-tool" id="apLocate"
                    title="Find my location" aria-label="Find my location">
                <i class="bi bi-geo-alt-fill"></i>
            </button>
            <button type="button" class="map-tool" id="apFullscreen"
                    title="Fullscreen" aria-label="Toggle fullscreen">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>
            <button type="button" class="map-tool" id="apRecenter"
                    title="Show whole route" aria-label="Show the whole route">
                <i class="bi bi-map"></i>
            </button>
        </div>

        <div class="ap-summary" id="routeSummary"></div>

        <div class="ap-toolbar">
            <button type="button" class="btn btn-gold btn-sm" onclick="GiyaActive.markCurrent()">
                <i class="bi bi-check-lg"></i> Mark Visited
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/leaflet.js') }}?v={{ filemtime(public_path('assets/js/leaflet.js')) }}"></script>
<script src="{{ asset('assets/js/giya-leaflet.js') }}?v={{ filemtime(public_path('assets/js/giya-leaflet.js')) }}"></script>
<script>
/**
 * Active pilgrimage controller.
 *
 * Progress is persisted through the local Laravel API. The map is Leaflet,
 * so it shows real streets and the route follows roads when a routing key is
 * configured.
 */
const GiyaActive = (function () {
    const points = {!! $points->toJson() !!};
    const stops  = {!! $stops->map(fn ($s) => ['id' => $s->id, 'name' => $s->church_name, 'order' => $s->stop_order, 'visited' => (bool) $s->is_visited, 'location' => $s->church?->location ?? 'Cebu'])->values()->toJson() !!};

    const csrf    = document.querySelector('meta[name="csrf-token"]').content;
    const markUrl = @js(route('plan.stop.visited'));
    const doneUrl = @js(route('plan.index'));

    const visited = new Set(stops.filter(s => s.visited).map(s => s.id));

    function current() {
        return stops.find(s => !visited.has(s.id)) || null;
    }

    const liveMap = GiyaLeaflet.pilgrimage({
        element: 'activeMap',
        stops: points,
        currentId: (stops.find(s => !s.visited) || {}).id,
        onStatus: function (message, kind) {
            const el = document.getElementById('routeSummary');
            if (el && kind === 'error') { el.textContent = message; }
        },
        onLocated: function (here, next, distanceKm) {
            const el = document.getElementById('routeSummary');
            if (el) {
                el.textContent = distanceKm.toFixed(1) + ' km to ' + next.name;
            }
        },
        onRoads: function (d) {
            const el = document.getElementById('routeSummary');
            if (el) {
                el.textContent = d.distance_km + ' km along roads'
                    + (d.duration_min ? ' \u00b7 about ' + d.duration_min + ' min by car' : '');
            }
        }
    });

    /* ---- map controls ---- */
    const apShell = document.querySelector('.active-layout');
    const apLocate = document.getElementById('apLocate');
    const apFull = document.getElementById('apFullscreen');

    apLocate.addEventListener('click', function () {
        apLocate.classList.add('is-busy');
        liveMap.locate(function () { apLocate.classList.remove('is-busy'); });
        setTimeout(function () { apLocate.classList.remove('is-busy'); }, 13000);
    });

    apFull.addEventListener('click', function () {
        const on = apShell.classList.toggle('is-fullscreen');
        apFull.innerHTML = on
            ? '<i class="bi bi-fullscreen-exit"></i>'
            : '<i class="bi bi-arrows-fullscreen"></i>';
        apFull.title = on ? 'Exit fullscreen' : 'Fullscreen';
        document.body.style.overflow = on ? 'hidden' : '';
        setTimeout(function () { liveMap.map.invalidateSize(); }, 120);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && apShell.classList.contains('is-fullscreen')) apFull.click();
    });

    document.getElementById('apRecenter').addEventListener('click', function () {
        liveMap.frameAll();
    });

    function paintPins() {
        const cur = current();
        liveMap.refresh(Array.from(visited), cur ? cur.id : null);
    }

    function render() {
        const total = stops.length;
        const done  = visited.size;
        const pct   = total ? Math.round((done / total) * 100) : 0;
        const cur   = current();
        const next  = cur ? stops[stops.indexOf(cur) + 1] : null;

        document.getElementById('visitedCount').textContent  = done;
        document.getElementById('progressBar').style.width   = pct + '%';
        document.getElementById('progressLabel').textContent = pct + '% complete';

        const banner  = document.getElementById('currentBanner');
        const doneB   = document.getElementById('doneBanner');
        const markBtn = document.getElementById('markBtn');

        if (cur) {
            banner.classList.remove('d-none');
            doneB.classList.add('d-none');
            markBtn.classList.remove('d-none');
            document.getElementById('currentName').textContent = cur.name;
            document.getElementById('currentNext').textContent =
                next ? 'Next → ' + next.name : 'Final stop of your route';
        } else {
            banner.classList.add('d-none');
            doneB.classList.remove('d-none');
            markBtn.classList.add('d-none');
        }

        document.getElementById('stopList').innerHTML = stops.map(function (s) {
            const isDone = visited.has(s.id);
            const isCur  = cur && cur.id === s.id;
            const dotBg  = isDone ? 'var(--primary)' : (isCur ? 'var(--gold-bg)' : '#F5E8D0');
            const inner  = isDone
                ? '<i class="bi bi-check-lg" style="color:var(--gold);font-size: 0.9375rem"></i>'
                : '<span style="font-size: 0.6875rem;font-weight:700;color:' +
                  (isCur ? 'var(--primary)' : 'var(--text-muted)') + '">' + s.order + '</span>';

            return '<div class="stop-item' + (isCur ? ' is-current' : '') + '">' +
                '<span style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;' +
                'justify-content:center;flex-shrink:0;background:' + dotBg + '">' + inner + '</span>' +
                '<span style="flex:1;min-width:0">' +
                  '<span style="display:block;font-size: 0.8125rem;color:' + (isDone ? 'var(--text-muted)' : 'var(--text)') +
                  ';font-weight:' + (isCur ? '700' : '500') + (isDone ? ';text-decoration:line-through' : '') + '">' +
                  s.name + '</span>' +
                  '<span style="display:block;font-size: 0.6875rem;color:var(--text-muted)">' + s.location + '</span>' +
                '</span>' +
                (isCur ? '<button type="button" onclick="GiyaActive.mark(' + s.id + ')" ' +
                         'style="padding:5px 11px;border-radius:8px;font-size: 0.6875rem;font-weight:700;border:none;' +
                         'cursor:pointer;background:var(--primary);color:#fff;font-family:var(--font-body);' +
                         'flex-shrink:0">Mark</button>' : '') +
                '</div>';
        }).join('');

        paintPins();
    }

    function mark(stopId) {
        if (visited.has(stopId)) return;

        fetch(markUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ stop_id: stopId }),
        })
        .then(response => response.ok ? response.json() : Promise.reject(response))
        .then(function (data) {
            visited.add(stopId);
            render();
            if (data.all_done) {
                setTimeout(function () {
                    GiyaConfirm.ask({
                        title: 'Pilgrimage complete',
                        message: 'You have visited every stop on this route. View your saved itineraries?',
                        ok: 'View itineraries',
                        cancel: 'Stay here',
                        tone: 'primary'
                    }).then(function (go) { if (go) window.location = doneUrl; });
                }, 350);
            }
        })
        .catch(function () {
            GiyaConfirm.ask({
                title: 'Could not save that stop',
                message: 'The change was not recorded. Check that the local server is running, then try again.',
                ok: 'Got it',
                cancel: 'Dismiss',
                tone: 'primary'
            });
        });
    }

    render();

    return {
        mark: mark,
        markCurrent: function () {
            const cur = current();
            if (cur) mark(cur.id);
        },
    };
})();
</script>
@endpush
