@extends('layouts.app')
@section('title', 'Plan Hub')

@section('content')
<div class="page-wrap">

    <div class="eyebrow">
        <span class="eyebrow-bar"></span>
        <span class="eyebrow-text">{{ __('giya.plan.plan_journey') }}</span>
    </div>
    <h1 class="hub-title">{{ __('giya.plan.hub') }}</h1>
    <p class="hub-lead">{{ __('giya.plan.hub_lead') }}</p>

    <div class="plan-grid" data-reveal>
        @php
            $cards = [
                [
                    'icon' => 'giya-route', 'title' => __('giya.hub.c1_title'), 'badge' => __('giya.hub.c1_badge'),
                    'desc' => __('giya.hub.c1_desc'),
                    'points' => [__('giya.hub.c1_p1'), __('giya.hub.c1_p2'), __('giya.hub.c1_p3'), __('giya.hub.c1_p4')],
                    /* The map, in planning mode. Choosing a destination is a
                       question about where things are, so it happens on the
                       map rather than on a separate screen with a list. */
                    'cta' => __('giya.hub.c1_cta'), 'route' => route('map', ['plan' => 1]), 'featured' => false,
                ],
                [
                    'icon' => 'giya-seven', 'title' => __('giya.plan.card_visita_title'), 'badge' => __('giya.hub.c2_badge'),
                    'desc' => __('giya.hub.c2_desc'),
                    'points' => [__('giya.hub.c2_p1'), __('giya.hub.c2_p2'), __('giya.hub.c2_p3'), __('giya.hub.c2_p4')],
                    'cta' => __('giya.hub.c2_cta'), 'route' => route('plan.visita'), 'featured' => false,
                ],
                [
                    'icon' => 'giya-saved', 'title' => __('giya.plan.my_title'), 'badge' => __('giya.hub.c3_badge'),
                    'desc' => __('giya.hub.c3_desc'),
                    'points' => [__('giya.hub.c3_p1'), __('giya.hub.c3_p2'), __('giya.hub.c3_p3'), __('giya.hub.c3_p4')],
                    'cta' => __('giya.hub.c3_cta'), 'route' => route('plan.index'), 'featured' => false,
                ],
                [
                    'icon' => 'giya-pilgrim', 'title' => __('giya.hub.c4_title'),
                    'badge' => $activeItinerary ? __('giya.hub.c4_badge_on') : __('giya.hub.c4_badge_off'),
                    'desc' => $activeItinerary
                        ? __('giya.hub.c4_desc_on', ['name' => $activeItinerary->name])
                        : __('giya.hub.c4_desc_off'),
                    'points' => [__('giya.hub.c4_p1'), __('giya.hub.c4_p2'), __('giya.hub.c4_p3'), __('giya.hub.c4_p4')],
                    'cta' => $activeItinerary ? __('giya.hub.c4_cta_on') : __('giya.hub.c4_cta_off'),
                    'route' => $activeItinerary ? route('plan.show', $activeItinerary) : route('map', ['plan' => 1]),
                    'featured' => (bool) $activeItinerary,
                ],
            ];
        @endphp

        @foreach ($cards as $i => $card)
            {{-- All four take the same accent. They are four ways into one
                 activity, not four categories, and four different browns
                 said they were unrelated. The colour lives in the stylesheet
                 now rather than being passed per card. --}}
            <a href="{{ $card['route'] }}" @class(['plan-card', 'featured' => $card['featured']])>

                {{-- Two shapes, not one. A pale wave behind the solid accent,
                     offset and scaled at different rates, so the moving edge
                     between them reads as liquid rather than as a circle. --}}
                <span class="liquid-wave" aria-hidden="true">
                    <i class="b1"></i><i class="b2"></i>
                </span>

                <span class="plan-card-accent"></span>

                <span class="plan-card-body">
                    <span class="plan-card-num" aria-hidden="true">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>

                    <span class="plan-card-icon">
                        <i class="bi bi-{{ $card['icon'] }}"></i>
                    </span>

                    {{-- Below the icon, not opposite it. The top-right corner
                         is the wave's and the number's; a badge put there
                         was being painted over. --}}
                    <span class="plan-card-badge-row">
                        <span @class(['badge', 'badge-primary' => $card['featured'], 'badge-brown' => ! $card['featured']])>
                            {{ $card['badge'] }}
                        </span>
                    </span>

                    <span class="plan-card-title">{{ $card['title'] }}</span>
                    <span class="plan-card-rule" aria-hidden="true"></span>
                    <span class="plan-card-desc">{{ $card['desc'] }}</span>

                    <span class="plan-card-points">
                        @foreach ($card['points'] as $point)
                            <span class="d-flex align-items-center gap-2">
                                <span class="plan-card-dot"></span>
                                <span class="plan-card-point">{{ $point }}</span>
                            </span>
                        @endforeach
                    </span>

                    <span @class(['btn', 'btn-primary' => $card['featured'], 'btn-outline-gold' => ! $card['featured'], 'btn-w-full', 'plan-card-cta'])>
                        {{ $card['cta'] }}
                    </span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="hub-bottom">
        <div>
            <h2 class="section-title hub-section-title">{{ __('giya.plan.recent') }}</h2>

            @forelse ($itineraries as $itinerary)
                <a href="{{ route('plan.show', $itinerary) }}" class="history-item">
                    <span class="history-icon">
                        <i class="bi bi-{{ $itinerary->type === 'Visita Iglesia' ? 'giya-seven' : 'giya-route' }}"></i>
                    </span>
                    <span class="history-body">
                        <span class="history-name">{{ $itinerary->name }}</span>
                        <span class="history-meta">
                            {{ $itinerary->total_stops }} stops
                            @if ($itinerary->scheduled_date) · {{ $itinerary->scheduled_date->format('M j, Y') }} @endif
                        </span>
                    </span>
                    <span class="badge status-{{ $itinerary->status }}">{{ $itinerary->status }}</span>
                    <i class="bi bi-chevron-right history-chevron"></i>
                </a>
            @empty
                <x-empty-state icon="giya-route" :title="__('giya.plan.no_itineraries')"
                               :desc="__('giya.plan.no_itin_desc')">
                    <a href="{{ route('map', ['plan' => 1]) }}" class="btn btn-primary btn-sm mt-3">{{ __('giya.plan.create_first') }}</a>
                </x-empty-state>
            @endforelse
        </div>

        <div>
            <h2 class="section-title hub-section-title">{{ __('giya.plan.tips') }}</h2>
            @foreach ([
                /* What each tip actually means here: a mantilla, a candle, a
                   jeepney and a water bottle - not the vcard, flame, coach and
                   droplet a general-purpose set had to offer. */
                ['giya-veil',    __('giya.tips.dress')],
                ['giya-candle',  __('giya.tips.candle')],
                ['giya-jeepney', __('giya.tips.transport')],
                ['giya-bottle',  __('giya.tips.water')],
            ] as [$icon, $tip])
                {{-- A two-column grid rather than a flex row. The icon and the
                     text sat on different baselines because the icon centred
                     itself against the whole wrapped paragraph; here it is
                     given a box the height of one line, so it lines up with
                     the first line whether the tip wraps or not. --}}
                <div class="card card-body plan-tip">
                    <i class="bi bi-{{ $icon }} plan-tip-icon"></i>
                    <p class="plan-tip-text">{{ $tip }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>

@endsection
