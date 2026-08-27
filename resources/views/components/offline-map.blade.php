{{--
    Offline map.

    Renders destinations as an SVG using an equirectangular projection of the
    supplied coordinates. No tile server, no network - the map draws identically
    with the machine disconnected.

    @param  \Illuminate\Support\Collection  $points   each: id, name, lat, lng, color, label, state
    @param  bool                            $route    draw a connecting path between points in order
--}}
@props(['points' => collect(), 'route' => false, 'height' => 520])

@php
    $pts = collect($points)->filter(fn ($p) => !empty($p['lat']) && !empty($p['lng']))->values();

    // Bounding box with padding, falling back to Metro Cebu when empty.
    if ($pts->isEmpty()) {
        $minLat = 9.95; $maxLat = 10.45; $minLng = 123.55; $maxLng = 124.05;
    } else {
        $minLat = $pts->min('lat'); $maxLat = $pts->max('lat');
        $minLng = $pts->min('lng'); $maxLng = $pts->max('lng');
        $padLat = max(($maxLat - $minLat) * 0.18, 0.02);
        $padLng = max(($maxLng - $minLng) * 0.18, 0.02);
        $minLat -= $padLat; $maxLat += $padLat;
        $minLng -= $padLng; $maxLng += $padLng;
    }

    $W = 1000; $H = 640;
    $spanLat = max($maxLat - $minLat, 0.0001);
    $spanLng = max($maxLng - $minLng, 0.0001);

    // Equirectangular projection: longitude → x, latitude → y (inverted).
    $project = function (float $lat, float $lng) use ($minLat, $minLng, $spanLat, $spanLng, $W, $H) {
        return [
            round((($lng - $minLng) / $spanLng) * $W, 2),
            round((1 - (($lat - $minLat) / $spanLat)) * $H, 2),
        ];
    };

    $placed = $pts->map(function ($p) use ($project) {
        [$x, $y] = $project((float) $p['lat'], (float) $p['lng']);
        return array_merge($p, ['x' => $x, 'y' => $y]);
    });
@endphp

<svg class="offline-map" viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid slice"
     role="img" aria-label="Map of pilgrimage destinations in Metro Cebu">

    <rect width="{{ $W }}" height="{{ $H }}" class="om-water"/>

    {{-- Stylised landmass so the plot reads as a map rather than a scatter chart --}}
    <path class="om-land" d="M120 60 L360 20 L620 70 L820 40 L960 130 L930 330 L980 470
                             L820 600 L560 630 L300 590 L140 480 L60 300 Z"/>

    {{-- Graticule --}}
    @for ($i = 1; $i < 8; $i++)
        <line class="om-grid" x1="{{ $i * ($W / 8) }}" y1="0" x2="{{ $i * ($W / 8) }}" y2="{{ $H }}"/>
    @endfor
    @for ($i = 1; $i < 5; $i++)
        <line class="om-grid" x1="0" y1="{{ $i * ($H / 5) }}" x2="{{ $W }}" y2="{{ $i * ($H / 5) }}"/>
    @endfor

    {{-- Route --}}
    @if ($route && $placed->count() > 1)
        <polyline class="om-route"
                  points="{{ $placed->map(fn ($p) => $p['x'] . ',' . $p['y'])->implode(' ') }}"/>
    @endif

    {{-- Destination pins --}}
    @foreach ($placed as $p)
        <g class="om-pin" data-point-id="{{ $p['id'] ?? '' }}"
           transform="translate({{ $p['x'] }},{{ $p['y'] }})"
           tabindex="0" role="button" aria-label="{{ $p['name'] }}">
            <ellipse cx="0" cy="2" rx="9" ry="3" fill="rgba(36,28,24,.18)"/>
            <path d="M0 0 C-9 -10 -13 -16 -13 -22 A13 13 0 1 1 13 -22 C13 -16 9 -10 0 0 Z"
                  fill="{{ $p['color'] ?? '#8E3B2F' }}" stroke="#fff" stroke-width="2.5"/>
            <text class="om-pin-label" y="-18">{{ $p['label'] ?? '' }}</text>
            <title>{{ $p['name'] }}</title>
        </g>
        <text class="om-name" x="{{ $p['x'] }}" y="{{ $p['y'] + 18 }}">
            {{ \Illuminate\Support\Str::limit($p['name'], 22) }}
        </text>
    @endforeach

    {{-- Current position, injected by script when geolocation is permitted --}}
    <circle id="omMe" class="om-me" r="8" cx="-100" cy="-100" style="display:none"/>

    <text class="om-attrib" x="12" y="{{ $H - 12 }}">
        Offline schematic map · coordinates projected from the GIYA database
    </text>
</svg>
