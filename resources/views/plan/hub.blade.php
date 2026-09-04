@extends('layouts.app')
@section('title', 'Plan Hub')

@section('content')
<div class="page-wrap">

    <div class="eyebrow">
        <span class="eyebrow-bar"></span>
        <span class="eyebrow-text">{{ __('giya.plan.plan_journey') }}</span>
    </div>
    <h1 style="font-family:var(--font-display);font-size: 2rem;margin:0 0 10px">{{ __('giya.plan.hub') }}</h1>
    <p style="color:var(--text-muted);font-size: 0.9375rem;line-height:1.7;max-width:580px;margin:0 0 40px">
        {{ __('giya.plan.hub_lead') }}
    </p>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:48px" class="plan-grid">
        @php
            $cards = [
                [
                    'icon' => 'giya-route', 'title' => __('giya.hub.c1_title'), 'badge' => __('giya.hub.c1_badge'),
                    'desc' => __('giya.hub.c1_desc'),
                    'points' => [__('giya.hub.c1_p1'), __('giya.hub.c1_p2'), __('giya.hub.c1_p3'), __('giya.hub.c1_p4')],
                    'cta' => __('giya.hub.c1_cta'), 'route' => route('plan.create'), 'accent' => 'var(--primary)', 'featured' => false,
                ],
                [
                    'icon' => 'giya-seven', 'title' => __('giya.plan.card_visita_title'), 'badge' => __('giya.hub.c2_badge'),
                    'desc' => __('giya.hub.c2_desc'),
                    'points' => [__('giya.hub.c2_p1'), __('giya.hub.c2_p2'), __('giya.hub.c2_p3'), __('giya.hub.c2_p4')],
                    'cta' => __('giya.hub.c2_cta'), 'route' => route('plan.visita'), 'accent' => '#6B4C2A', 'featured' => false,
                ],
                [
                    'icon' => 'giya-saved', 'title' => __('giya.plan.my_title'), 'badge' => __('giya.hub.c3_badge'),
                    'desc' => __('giya.hub.c3_desc'),
                    'points' => [__('giya.hub.c3_p1'), __('giya.hub.c3_p2'), __('giya.hub.c3_p3'), __('giya.hub.c3_p4')],
                    'cta' => __('giya.hub.c3_cta'), 'route' => route('plan.index'), 'accent' => '#5A3E28', 'featured' => false,
                ],
                [
                    'icon' => 'giya-pilgrim', 'title' => __('giya.hub.c4_title'),
                    'badge' => $activeItinerary ? __('giya.hub.c4_badge_on') : __('giya.hub.c4_badge_off'),
                    'desc' => $activeItinerary
                        ? __('giya.hub.c4_desc_on', ['name' => $activeItinerary->name])
                        : __('giya.hub.c4_desc_off'),
                    'points' => [__('giya.hub.c4_p1'), __('giya.hub.c4_p2'), __('giya.hub.c4_p3'), __('giya.hub.c4_p4')],
                    'cta' => $activeItinerary ? __('giya.hub.c4_cta_on') : __('giya.hub.c4_cta_off'),
                    'route' => $activeItinerary ? route('plan.show', $activeItinerary) : route('plan.create'),
                    'accent' => 'var(--gold)', 'featured' => (bool) $activeItinerary,
                ],
            ];
        @endphp

        @foreach ($cards as $card)
            <a href="{{ $card['route'] }}" @class(['plan-card', 'featured' => $card['featured']])
               style="text-decoration:none">
                <span class="plan-card-accent" style="background:{{ $card['accent'] }}"></span>
                <span class="plan-card-body">
                    <span class="d-flex align-items-start justify-content-between mb-3">
                        <span style="width:48px;height:48px;border-radius:16px;background:var(--gold-bg);display:flex;align-items:center;justify-content:center">
                            <i class="bi bi-{{ $card['icon'] }}" style="font-size: 1.375rem;color:var(--primary)"></i>
                        </span>
                        <span @class(['badge', 'badge-primary' => $card['featured'], 'badge-brown' => ! $card['featured']])>
                            {{ $card['badge'] }}
                        </span>
                    </span>

                    <span style="display:block;font-size: 0.9375rem;font-weight:700;color:var(--text);line-height:1.3;margin-bottom:8px">
                        {{ $card['title'] }}
                    </span>
                    <span style="display:block;font-size: 0.75rem;color:var(--text-muted);line-height:1.7;margin-bottom:14px">
                        {{ $card['desc'] }}
                    </span>

                    <span style="display:flex;flex-direction:column;gap:6px;margin-bottom:16px">
                        @foreach ($card['points'] as $point)
                            <span class="d-flex align-items-center gap-2">
                                <span style="width:6px;height:6px;border-radius:50%;background:var(--gold);flex-shrink:0"></span>
                                <span style="font-size: 0.6875rem;color:var(--text)">{{ $point }}</span>
                            </span>
                        @endforeach
                    </span>

                    <span @class(['btn', 'btn-primary' => $card['featured'], 'btn-outline-gold' => ! $card['featured'], 'btn-w-full'])
                          style="margin-top:auto;justify-content:center">
                        {{ $card['cta'] }}
                    </span>
                </span>
            </a>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:32px;border-top:1px solid var(--border-light);padding-top:40px" class="hub-bottom">
        <div>
            <h2 class="section-title" style="font-size: 1.25rem">{{ __('giya.plan.recent') }}</h2>

            @forelse ($itineraries as $itinerary)
                <a href="{{ route('plan.show', $itinerary) }}" class="history-item" style="text-decoration:none">
                    <span class="history-icon">
                        <i class="bi bi-{{ $itinerary->type === 'Visita Iglesia' ? 'giya-seven' : 'giya-route' }}"
                           style="font-size: 1.125rem;color:var(--primary)"></i>
                    </span>
                    <span style="flex:1;min-width:0">
                        <span style="display:block;font-size: 0.875rem;font-weight:700;color:var(--text)">{{ $itinerary->name }}</span>
                        <span style="display:block;font-size: 0.75rem;color:var(--text-muted);margin-top:2px">
                            {{ $itinerary->total_stops }} stops
                            @if ($itinerary->scheduled_date) · {{ $itinerary->scheduled_date->format('M j, Y') }} @endif
                        </span>
                    </span>
                    <span class="badge status-{{ $itinerary->status }}">{{ $itinerary->status }}</span>
                    <i class="bi bi-chevron-right" style="color:var(--text-muted)"></i>
                </a>
            @empty
                <x-empty-state icon="giya-route" :title="__('giya.plan.no_itineraries')"
                               :desc="__('giya.plan.no_itin_desc')">
                    <a href="{{ route('plan.create') }}" class="btn btn-primary btn-sm mt-3">{{ __('giya.plan.create_first') }}</a>
                </x-empty-state>
            @endforelse
        </div>

        <div>
            <h2 class="section-title" style="font-size: 1.25rem">{{ __('giya.plan.tips') }}</h2>
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

@push('head')
<style>
    @media (max-width: 1024px) { .plan-grid { grid-template-columns: repeat(2, 1fr) !important; } }
    @media (max-width: 620px)  { .plan-grid { grid-template-columns: 1fr !important; } }
    @media (max-width: 900px)  { .hub-bottom { grid-template-columns: 1fr !important; } }
</style>
@endpush
@endsection
