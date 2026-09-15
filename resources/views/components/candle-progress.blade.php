@props(['total' => 7, 'done' => 0, 'id' => 'candleRow'])

{{--
    Progress as a row of candles, one per stop, lit as each church is visited.

    The bar this replaces was accurate and said nothing. Lighting a candle at
    each church is what Visita Iglesia IS, so the progress meter can be the
    act rather than a percentage of it - and a devotee who looks at the panel
    sees how far along they are without reading a number.

    The candle is drawn here rather than taken from the icon font: bi-giya-candle
    is a mask, which paints the whole glyph in one colour, and the flame has to
    be gold while the wax is not. Three elements instead, so the flame can be
    lit, tinted and flickered on its own.

    Each candle flexes rather than being a fixed width, because an itinerary is
    capped at twenty stops and twenty fixed-width candles would run off the
    side of the sidebar. Twenty share the row and come out thin; seven come out
    the width the design wants.

    aria-hidden, with the count beside it doing the talking: twenty list items
    that each say "lit" is worse for a screen reader than the sentence that is
    already there.
--}}
<div {{ $attributes->merge(['class' => 'candle-row']) }}
     id="{{ $id }}"
     data-total="{{ $total }}"
     data-done="{{ $done }}"
     aria-hidden="true">
    @for ($i = 0; $i < $total; $i++)
        <span @class(['candle', 'is-lit' => $i < $done, 'is-next' => $i === $done])>
            <span class="candle-glow"></span>
            <span class="candle-flame"></span>
            <span class="candle-wick"></span>
            <span class="candle-wax"></span>
        </span>
    @endfor
</div>
