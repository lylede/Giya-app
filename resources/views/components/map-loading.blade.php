{{--
    The panel over a map while its tiles are still arriving.

    It sits inside the map's own wrapper and is removed by the class the
    engine puts on that wrapper when Leaflet says every visible tile has
    loaded - not by a timer, so it is honest on a slow connection and does
    not linger on a fast one.

    GIYA's pin falls like a drop of water: it squashes as it lands, bounces
    twice, and a gold ripple spreads out under it. Inlined rather than an
    <img> so the colours are exactly the logo's.
--}}
@props(['text' => null])

@php
    $pin = <<<'SVG'
        <svg viewBox="12 6 32 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M28 44.4C24.2 40.4 13 31.6 13 21.6A15 15 0 0 1 43 21.6C43 31.6 31.8 40.4 28 44.4Z" fill="#8E3B2F"/>
            <path d="M28 30.2C25.8 27.6 19.3 24 19.3 18.6A8.7 8.7 0 0 1 36.7 18.6C36.7 24 30.2 27.6 28 30.2Z" fill="#F8E3C6"/>
            <rect x="26.6" y="14" width="2.8" height="11.4" rx=".4" fill="#E3B043"/>
            <rect x="23.2" y="17.6" width="9.6" height="2.6" rx=".4" fill="#E3B043"/>
        </svg>
        SVG;
@endphp

<div {{ $attributes->merge(['class' => 'giya-loading']) }} role="status" aria-live="polite">
    <div class="giya-loading-scene">
        <span class="giya-loading-ripple"></span>
        <span class="giya-loading-ripple is-late"></span>
        <span class="giya-loading-shadow"></span>
        <span class="giya-loading-pin">{!! $pin !!}</span>
    </div>

    <p class="giya-loading-text">{{ $text ?? __('giya.map.loading') }}</p>
</div>
