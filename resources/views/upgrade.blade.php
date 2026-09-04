@extends('layouts.app')
@section('title', __('giya.profile.go_premium'))

@section('content')
<div class="page-wrap">

    <div class="eyebrow">
        <span class="eyebrow-bar"></span>
        <span class="eyebrow-text">{{ __('giya.upgrade.premium') }}</span>
    </div>

    <h1 style="font-family:var(--font-display);font-size:2rem;margin:0 0 10px">
        {{ __('giya.upgrade.title') }}
    </h1>
    <p style="color:var(--text-muted);font-size:0.9375rem;line-height:1.7;max-width:580px;margin:0 0 32px">
        {{ __('giya.upgrade.lead', ['limit' => \App\Http\Controllers\ItineraryController::FREE_LIMIT]) }}
    </p>

    @if ($user->is_premium)
        <div class="card card-body" style="display:flex;gap:14px;align-items:center;margin-bottom:32px;
                    border-color:rgba(215,169,74,.45);background:rgba(215,169,74,.08)">
            <i class="bi bi-gem" style="font-size:1.5rem;color:var(--gold)"></i>
            <div>
                <div style="font-weight:700;color:var(--text)">{{ __('giya.upgrade.already') }}</div>
                <div style="font-size:.8125rem;color:var(--text-muted)">
                    {{ __('giya.upgrade.already_note') }}
                </div>
            </div>
        </div>
    @endif

    @unless ($ready)
        <div class="alert alert-warning" style="margin-bottom:28px">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>{{ __('giya.upgrade.unavailable') }}</span>
        </div>
    @endunless

    {{-- ── Plans ──────────────────────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:40px">
        @foreach ($plans as $plan)
            @php
                /*
                 * Best value = the lowest cost per day among what is offered,
                 * worked out rather than hard-coded. This used to test for a
                 * 365-day plan, which quietly badged nothing once the annual
                 * tier was retired.
                 */
                $perDay   = fn ($p) => $p->duration_days > 0 ? $p->price / $p->duration_days : INF;
                $featured = $plans->count() > 1 && $plans->sortBy($perDay)->first()->is($plan);
            @endphp

            <div class="card card-body" style="display:flex;flex-direction:column;
                 {{ $featured ? 'border-color:rgba(215,169,74,.5);box-shadow:0 6px 24px rgba(215,169,74,.12)' : '' }}">

                {{--
                    The badge row is rendered on every card, empty where there
                    is no badge. Only the featured card having one pushed its
                    title 50px below the other card's, and two plan names at
                    different heights read as a mistake rather than emphasis.
                --}}
                <div style="height:24px;margin-bottom:12px;display:flex;align-items:center">
                    @if ($featured)
                        <span style="font-size:.6875rem;font-weight:700;letter-spacing:.06em;
                                     text-transform:uppercase;color:#7a5c00;background:rgba(215,169,74,.18);
                                     padding:4px 10px;border-radius:999px">{{ __('giya.upgrade.best_value') }}</span>
                    @endif
                </div>

                <div style="display:flex;align-items:center;gap:9px;margin-bottom:6px">
                    <i class="bi {{ $featured ? 'bi-gem' : 'bi-stars' }}"
                       style="font-size:1.125rem;color:var(--gold)"></i>
                    <span style="font-family:var(--font-display);font-size:1.125rem;color:var(--text)">
                        {{ $plan->name }}
                    </span>
                </div>

                <div style="display:flex;align-items:baseline;gap:6px;margin-bottom:10px">
                    <span style="font-size:2rem;font-weight:700;color:var(--text)">
                        ₱{{ number_format((float) $plan->price, 0) }}
                    </span>
                    <span style="font-size:.8125rem;color:var(--text-muted)">
                        @php
                            $per = match (true) {
                                $plan->duration_days === 7  => 'week',
                                $plan->duration_days === 30 => 'month',
                                $plan->duration_days % 7 === 0 => ($plan->duration_days / 7).' weeks',
                                default => $plan->duration_days.' days',
                            };
                        @endphp
                        / {{ $per }}
                    </span>
                </div>

                <p style="font-size:.8125rem;color:var(--text-muted);line-height:1.65;margin:0 0 18px">
                    {{ $plan->description }}
                </p>

                {{-- Identical on both tiers on purpose: they differ only in
                     how long they last, not in what they unlock. --}}
                <ul style="list-style:none;padding:0;margin:0 0 22px;display:grid;gap:9px">
                    @foreach ([
                        __('giya.upgrade.perk_unl'),
                        __('giya.upgrade.perk_visita'),
                        __('giya.upgrade.perk_all'),
                    ] as $perk)
                        <li style="display:flex;gap:9px;align-items:flex-start;font-size:.8125rem;color:var(--text)">
                            <i class="bi bi-check-lg" style="color:var(--gold);flex:none;margin-top:2px"></i>
                            <span>{{ $perk }}</span>
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('upgrade.checkout', $plan) }}" style="margin-top:auto">
                    @csrf
                    <button type="submit" class="btn {{ $featured ? 'btn-primary' : 'btn-outline' }}"
                            style="width:100%;justify-content:center"
                            @disabled($user->is_premium || ! $ready)>
                        <i class="bi bi-credit-card-fill"></i>
                        <span>{{ $user->is_premium ? 'Already active' : 'Pay with Maya' }}</span>
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="card card-body" style="display:flex;gap:12px;align-items:flex-start;margin-bottom:32px">
        <i class="bi bi-shield-lock-fill" style="color:var(--gold);flex:none;margin-top:2px"></i>
        <p style="font-size:.8125rem;color:var(--text-muted);line-height:1.7;margin:0">
            {{ __('giya.upgrade.maya_note') }}
        </p>
    </div>

    {{-- ── Their own transactions ──────────────────────────────────────── --}}
    @if ($history->isNotEmpty())
        <div class="card card-body">
            <div class="form-label-sm" style="margin-bottom:14px">{{ __('giya.upgrade.transactions') }}</div>

            @foreach ($history as $t)
                @php
                    $tone = match ($t->status) {
                        'Paid'     => ['bi-check-circle-fill',        'var(--gold)'],
                        'Failed'   => ['bi-exclamation-circle-fill',  '#8e3b2f'],
                        'Refunded' => ['bi-arrow-repeat',             'var(--text-muted)'],
                        default    => ['bi-hourglass-split',          'var(--text-muted)'],
                    };
                @endphp
                <div style="display:flex;align-items:center;gap:12px;padding:11px 0;
                            {{ ! $loop->last ? 'border-bottom:1px solid var(--border-light)' : '' }}">
                    <i class="bi {{ $tone[0] }}" style="color:{{ $tone[1] }};flex:none"></i>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.8125rem;font-weight:600;color:var(--text)">{{ $t->plan }}</div>
                        <div style="font-size:.75rem;color:var(--text-muted)">
                            {{ $t->reference_no }} · {{ $t->created_at?->format('d M Y, g:i A') }}
                        </div>
                    </div>
                    <div style="text-align:right;flex:none">
                        <div style="font-size:.8125rem;font-weight:600;color:var(--text)">{{ $t->amountLabel() }}</div>
                        <div style="font-size:.75rem;color:{{ $tone[1] }}">{{ $t->status }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
