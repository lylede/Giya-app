@props(['icon' => 'giya-star', 'value' => 0, 'label' => '', 'tone' => 'gold', 'sub' => null])

{{--
    One tile, used by every module that shows a headline figure.

    It was written out by hand on the dashboard, again on feedback and again
    on transactions, each with its own inline sizes - which is why the three
    screens had three different tile heights and three different number sizes
    for the same kind of fact.

    The sub slot is for the line user management needs: a percentage, or a
    change against last week. It is optional, so a tile without one is exactly
    the tile it was before. It is a slot rather than a raw-HTML prop, so the
    markup is written by the template and anything passed as a plain string
    is still escaped.
--}}
<div {{ $attributes->merge(['class' => 'stat-tile card']) }}>
    <span @class(['stat-tile-icon', 'is-'.$tone])>
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    </span>

    <span class="stat-tile-value giya-num">{{ is_numeric($value) ? number_format($value) : $value }}</span>
    <span class="stat-tile-label">{{ $label }}</span>

    @if (filled($sub))
        <span class="stat-tile-sub">{{ $sub }}</span>
    @endif
</div>
