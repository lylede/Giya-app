{{--
    Horizontal bar chart, drawn as inline SVG.

    @param  array  $labels  category names (drawn to the left of each bar)
    @param  array  $data    numeric values, same length as $labels
--}}
@props(['labels' => [], 'data' => [], 'aria' => 'Bar chart of most visited destinations'])

@php
    $rows  = count($data);
    $rowH  = 30;
    $W     = 520;
    $H     = max($rows * $rowH + 20, 60);
    $padL  = 150; $padR = 40;
    $plotW = $W - $padL - $padR;
    $max   = max(max($data ?: [0]), 1);
@endphp

<svg class="chart-svg" viewBox="0 0 {{ $W }} {{ $H }}" role="img"
     aria-label="{{ $aria }}">

    @foreach ($data as $i => $value)
        @php
            $y  = 10 + $i * $rowH;
            $bw = round(($value / $max) * $plotW, 2);
        @endphp

        <text class="chart-axis-text" x="{{ $padL - 10 }}" y="{{ $y + 14 }}" text-anchor="end">
            {{ \Illuminate\Support\Str::limit($labels[$i] ?? '', 22) }}
        </text>

        <rect class="chart-bar" x="{{ $padL }}" y="{{ $y }}" width="{{ max($bw, 2) }}"
              height="18" rx="4">
            <title>{{ $labels[$i] ?? '' }}: {{ $value }} visits</title>
        </rect>

        <text class="chart-axis-text" x="{{ $padL + max($bw, 2) + 8 }}" y="{{ $y + 14 }}">
            {{ $value }}
        </text>
    @endforeach
</svg>
