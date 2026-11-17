{{--
    The panel over a map while its tiles are still arriving.

    It sits inside the map's own wrapper and is removed by the class the
    engine puts on that wrapper when Leaflet says every visible tile has
    loaded - not by a timer, so it is honest on a slow connection and does
    not linger on a fast one.

    The pin is GIYA's own mark, inlined rather than an <img> because it has
    to turn in three dimensions, and an image cannot be three planes.

    Three, not two: a face at the front, a face at the back, and the edge
    between them. With a single face the edge sat at the same depth and,
    seen exactly end-on, was drawn as a hairline down the middle of the
    logo - a plane with no width still gets a pixel. Pushed half a
    thickness forward, the face covers it; the back face does the same from
    behind, and each hides its own reverse so only one is ever in view.
--}}
@props(['text' => null])

@php
    // One copy in the source, two on the page - the faces must be identical
    // or the pin would change as it turned.
    $pinFace = <<<'SVG'
        <svg viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="28" cy="28" r="28" fill="#F8E3C6"/>
            <ellipse cx="28" cy="45" rx="11.5" ry="2.1" fill="#E3B043"/>
            <path d="M28 44.4C24.2 40.4 13 31.6 13 21.6A15 15 0 0 1 43 21.6C43 31.6 31.8 40.4 28 44.4Z" fill="#8E3B2F"/>
            <path d="M28 30.2C25.8 27.6 19.3 24 19.3 18.6A8.7 8.7 0 0 1 36.7 18.6C36.7 24 30.2 27.6 28 30.2Z" fill="#F8E3C6"/>
            <rect x="26.6" y="14" width="2.8" height="11.4" rx=".4" fill="#E3B043"/>
            <rect x="23.2" y="17.6" width="9.6" height="2.6" rx=".4" fill="#E3B043"/>
        </svg>
        SVG;
@endphp

<div {{ $attributes->merge(['class' => 'giya-loading']) }} role="status" aria-live="polite">
    <div class="giya-loading-scene">
        <div class="giya-loading-pin">
            <span class="giya-loading-face is-front">{!! $pinFace !!}</span>
            <span class="giya-loading-edge"></span>
            <span class="giya-loading-face is-back">{!! $pinFace !!}</span>
        </div>
        <span class="giya-loading-shadow"></span>
    </div>

    <p class="giya-loading-text">{{ $text ?? __('giya.map.loading') }}</p>
</div>
