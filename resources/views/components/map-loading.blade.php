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
            <circle cx="28" cy="28" r="28" fill="#FFF8ED"/>
            <path d="M28 10C21.373 10 16 15.373 16 22C16 30 28 46 28 46C28 46 40 30 40 22C40 15.373 34.627 10 28 10Z" fill="#D7A94A"/>
            <circle cx="28" cy="22" r="6" fill="#8E3B2F"/>
            <rect x="26.5" y="18" width="3" height="8" rx="1.5" fill="#FFF8ED"/>
            <rect x="24" y="20.5" width="8" height="3" rx="1.5" fill="#FFF8ED"/>
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
