{{--
    Horizontal bar chart, as ordinary HTML.

    It used to be one SVG scaled to the card's width, which scaled its text
    too: in a wide card the labels came out bigger than the card's own title,
    and long church names were cut at 22 letters. Now the text stays at the
    page's size, names wrap to a second line instead of being cut, and only
    the bars stretch.

    @param  array   $labels  category names (to the left of each bar)
    @param  array   $data    numeric values, same length as $labels
    @param  string  $unit    what is counted, for the hover note ("visits", "follows")
--}}
@props(['labels' => [], 'data' => [], 'aria' => 'Bar chart of most visited destinations', 'unit' => 'visits'])

@php
    $max = max(max($data ?: [0]), 1);
@endphp

<div class="hbar" role="img" aria-label="{{ $aria }}">
    @foreach ($data as $i => $value)
        <div class="hbar-row" title="{{ $labels[$i] ?? '' }}: {{ $value }} {{ $unit }}">
            <span class="hbar-label">{{ $labels[$i] ?? '' }}</span>
            <span class="hbar-track"><span class="hbar-fill" style="width: {{ max(round($value / $max * 100, 2), 1.5) }}%"></span></span>
            <span class="hbar-val">{{ $value }}</span>
        </div>
    @endforeach
</div>
