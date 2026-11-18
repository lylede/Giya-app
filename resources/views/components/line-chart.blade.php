{{--
    Line chart: the line stretches to fit the card, the text does not.

    It used to be one SVG scaled to the card's width, which scaled its text
    as well: in a wide card the month names and numbers came out bigger than
    the card's title. Now the line and its shading are an SVG that stretches
    (the stroke keeps its thickness), and the axis numbers, month names and
    dots are ordinary page elements at the page's own size.

    @param  array   $labels  x-axis labels
    @param  array   $data    numeric series, same length as $labels
    @param  string  $aria    what the chart shows, for screen readers
--}}
@props(['labels' => [], 'data' => [], 'aria' => 'Line chart of monthly visits'])

@php
    $count = max(count($data), 1);
    $max   = max(max($data ?: [0]), 1);
    // Round the axis ceiling up to a friendly number.
    $step  = (int) max(1, ceil($max / 4));
    $top   = $step * 4;

    // Positions in percent of the plot (0 = left/top, 100 = right/bottom).
    $x = fn ($i) => $count > 1 ? round($i / ($count - 1) * 100, 3) : 50;
    $y = fn ($v) => round((1 - $v / $top) * 100, 3);

    $points = collect($data)->map(fn ($v, $i) => $x($i).','.$y($v))->implode(' ');
    $area   = $points ? $x(0).',100 '.$points.' '.$x($count - 1).',100' : '';

    // Many points (a month of days): label every few, counted back from the
    // newest, so the latest one is always named and none run into each other.
    $every = max(1, (int) ceil(count($labels) / 8));
    $last  = count($labels) - 1;
@endphp

<div class="lchart" role="img" aria-label="{{ $aria }}">
    <div class="lchart-y" aria-hidden="true">
        @for ($g = 0; $g <= 4; $g++)
            <span style="top: {{ $g * 25 }}%">{{ $top - $g * $step }}</span>
        @endfor
    </div>

    <div class="lchart-plot">
        <svg class="lchart-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            @for ($g = 0; $g <= 4; $g++)
                <line class="chart-grid-line" x1="0" y1="{{ $g * 25 }}" x2="100" y2="{{ $g * 25 }}" vector-effect="non-scaling-stroke"/>
            @endfor
            @if ($area)
                <polygon class="chart-area" points="{{ $area }}"/>
                <polyline class="chart-line" points="{{ $points }}" vector-effect="non-scaling-stroke"/>
            @endif
        </svg>
        @foreach ($data as $i => $value)
            <span class="lchart-dot" style="left: {{ $x($i) }}%; top: {{ $y($value) }}%"
                  title="{{ $labels[$i] ?? '' }}: {{ $value }}"></span>
        @endforeach
    </div>

    <div class="lchart-x" aria-hidden="true">
        @foreach ($labels as $i => $label)
            @if (($last - $i) % $every === 0)
                <span style="left: {{ $x($i) }}%">{{ $label }}</span>
            @endif
        @endforeach
    </div>
</div>
