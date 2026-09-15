@extends('layouts.app')
@section('title', __('giya.nav.home'))

@section('content')

@php
    /* Built here rather than inside a @json directive: Blade matches a
       directive's brackets textually, and an array literal inside a closure
       defeats that parser. */
    $featuredData = $featured->map(function ($c) {
        return [
            'name'     => $c->name,
            'category' => $c->category,
            'location' => $c->location,
            'desc'     => \Illuminate\Support\Str::limit($c->description, 120),
            'image'    => $c->imagePath(),
            'url'      => route('churches.show', $c),
        ];
    })->values()->toJson();
@endphp

{{-- ─────────────────────────────── Hero ─────────────────────────────── --}}
<section class="hero">
    <img src="{{ asset('images/backgrounds/hero-basilica.svg') }}" alt="" class="hero-bg">
    <div class="hero-scrim"></div>

    <div class="hero-mark" aria-hidden="true">
        <svg width="120" height="120" viewBox="0 0 120 120" fill="none" aria-hidden="true">
            <rect x="48" y="8" width="24" height="104" rx="8" fill="#D7A94A"/>
            <rect x="8" y="44" width="104" height="24" rx="8" fill="#D7A94A"/>
        </svg>
    </div>

    <div class="hero-inner">
        <div class="hero-copy">
            <div class="eyebrow">
                <span class="eyebrow-bar"></span>
                <span class="eyebrow-text is-gold">{{ __('giya.home.eyebrow') }}</span>
            </div>

            <h1 class="hero-title">{{ __('giya.home.title') }}</h1>

            <p class="hero-lead">{{ __('giya.home.lead') }}</p>

            <div class="d-flex flex-wrap gap-3 mb-4">
                <a href="{{ route('map') }}" class="btn btn-gold">
                    <i class="bi bi-map-fill"></i> {{ __('giya.home.explore_map') }}
                </a>
                <a href="{{ auth()->check() ? route('plan.hub') : route('login') }}" class="btn btn-ghost btn-ghost-inverse">
                    <i class="bi bi-journal-text"></i> {{ auth()->check() ? __('giya.home.plan_pilgrimage') : __('giya.home.sign_in_plan') }}
                </a>
            </div>

            <div class="hero-stats">
                @foreach ([
                    [$stats['churches'] . '+', __('giya.home.stat_churches')],
                    [auth()->check() ? number_format(auth()->user()->total_churches_visited) : $stats['churches'], auth()->check() ? __('giya.home.stat_visits') : __('giya.home.stat_places')],
                    [$stats['cities'], __('giya.home.stat_cities')],
                ] as [$value, $label])
                    <div class="hero-stat">
                        <span class="hero-stat-value">{{ $value }}</span>
                        <span class="hero-stat-label">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ────────────────────────── Search / filters ──────────────────────── --}}

<div class="page-wrap">

    {{-- ───────────────────────── Quick actions ──────────────────────── --}}
    <section class="home-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">{{ __('giya.home.journey') }}</h2>
                <p class="section-subtitle">{{ __('giya.home.journey_lead') }}</p>
            </div>
        </div>

        <div class="home-grid home-grid-sm" data-reveal>
            @foreach ([
                /* GIYA's own icons: a church inside a place marker, a route
                   with its stops and a flag, the seven churches, and Giya's
                   own head - rather than a map, a notebook, an office block
                   and a speech bubble standing in for them. */
                /* Each card carries its own parameters, because two of them
                   now go to the same route and differ only by them: the map
                   to browse, and the map in planning mode. */
                ['giya-nearby',    __('giya.home.card_nearby'), __('giya.home.card_nearby_d'), 'map', []],
                ['giya-route',     __('giya.home.card_plan'),   __('giya.home.card_plan_d'),   auth()->check() ? 'map' : 'login', auth()->check() ? ['plan' => 1] : []],
                ['giya-seven',     __('giya.home.card_visita'), __('giya.home.card_visita_d'), auth()->check() ? 'plan.visita' : 'login', []],
                ['giya-assistant', __('giya.home.card_ask'),    __('giya.home.card_ask_d'),    auth()->check() ? 'chatbot' : 'login', []],
            ] as [$icon, $title, $desc, $route, $params])
                <a href="{{ route($route, $params) }}" class="card card-hover home-card">
                    {{-- The same wave as the plan hub: a pale shape behind the
                         solid accent, both anchored off the top-right corner
                         and clipped to the card. Two of these cards lead to
                         the same screens the hub does, so giving them a
                         different hover would say they were different
                         places. --}}
                    <span class="liquid-wave" aria-hidden="true">
                        <i class="b1"></i><i class="b2"></i>
                    </span>

                    <span class="home-card-icon">
                        <i class="bi bi-{{ $icon }}"></i>
                    </span>
                    <span>
                        <span class="home-card-title">{{ $title }}</span>
                        <span class="home-card-desc">{{ $desc }}</span>
                    </span>
                    <span class="home-card-go">
                        {{ $route === 'login' ? __('giya.home.sign_in_go') : __('giya.home.get_started') }}
                        <i class="bi bi-chevron-right"></i>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ──────────────────── Featured destinations ───────────────────── --}}
    <section class="home-section-ruled">
        <div class="section-header">
            <div>
                <h2 class="section-title">{{ __('giya.home.featured') }}</h2>
                <p class="section-subtitle">{{ __('giya.home.featured_lead') }}</p>
            </div>
            <a href="{{ route('map') }}" class="section-link">{{ __('giya.home.view_all_map') }} →</a>
        </div>

        @if ($featured->isEmpty())
            <x-empty-state icon="building" :title="__('giya.home.no_featured')"
                           :desc="__('giya.home.no_featured_d')" />
        @else
            {{--
                A procession: one church at a time, filling the frame, advancing
                on its own every six seconds.

                A church deserves the whole frame - the facade, the scale, the
                sky behind it. A grid of four cards gave each one a thumbnail and
                a truncated paragraph, which is the least interesting way to show
                a building.
            --}}
            <div class="proc" id="proc" tabindex="0" aria-roledescription="carousel">
                @foreach ($featured as $i => $church)
                    <figure @class(['proc-slide', 'is-live' => $i === 0])
                            style="background-image:url('{{ $church->imagePath() }}')"
                            aria-hidden="{{ $i === 0 ? 'false' : 'true' }}"></figure>
                @endforeach

                <div class="proc-veil"></div>

                <div class="proc-copy" id="procCopy">
                    <span class="proc-cat" id="procCat"></span>
                    <h3 id="procName"></h3>
                    <p class="proc-loc" id="procLoc"></p>
                    <p class="proc-desc" id="procDesc"></p>
                    <a class="btn btn-gold btn-sm" id="procLink">{{ __('giya.common.see_more') }}</a>
                </div>

                {{-- One bar per destination: position, remaining time, and a
                     jump control in a single row. --}}
                <div class="proc-bars" role="tablist">
                    @foreach ($featured as $i => $church)
                        <button type="button" class="proc-bar" data-go="{{ $i }}"
                                role="tab" aria-label="{{ $church->name }}"><span></span></button>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- ─────────────── Upcoming activities + CTA card ───────────────── --}}
    <section class="home-section-ruled">
        <div class="home-bottom-grid">

            <div>
                <h2 class="section-title event-heading">{{ __('giya.home.events') }}</h2>
                <p class="section-subtitle event-lead">{{ __('giya.home.events_lead') }}</p>

                <div data-reveal>
                @forelse ($upcoming as $i => $event)
                    {{-- The soonest one is the only one in the primary colour.
                         Everything after it is the same weight, because a list
                         where each row shouts equally has no next. --}}
                    <div class="card event-row">
                        <span @class(['event-date', 'is-soonest' => $i === 0])>
                            <span class="event-kind">{{ $event->event_type }}</span>
                        </span>
                        <div class="event-body">
                            <div class="event-name">{{ $event->event_name }}</div>
                            <div class="event-where">
                                <i class="bi bi-building"></i>
                                {{ $event->church->name ?? 'Metro Cebu' }}
                            </div>
                        </div>
                        <div class="event-when">
                            {{ $event->schedule_date?->format('M j, Y') ?? ($event->recurrence ?? __('giya.home.recurring')) }}
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="calendar-event" :title="__('giya.home.no_events')"
                                   :desc="__('giya.home.no_events_d')" />
                @endforelse
                </div>
            </div>

            <div class="upgrade-card d-flex flex-column home-cta">
                <div class="home-cta-copy">
                    <span class="home-cta-icon">
                        <i class="bi bi-stars"></i>
                    </span>
                    <h3 class="home-cta-title">{{ __('giya.home.cta_title') }}</h3>
                    <p class="home-cta-lead">{{ __('giya.home.cta_lead') }}</p>
                </div>

                <div class="d-flex flex-column gap-2 mt-auto pt-4 home-cta-actions">
                    <a href="{{ auth()->check() ? route('map', ['plan' => 1]) : route('login') }}" class="btn btn-gold btn-w-full">{{ auth()->check() ? __('giya.home.plan_mine') : __('giya.home.sign_in_plan') }}</a>
                    <a href="{{ auth()->check() ? route('chatbot') : route('login') }}" class="btn btn-ghost btn-ghost-inverse btn-w-full">{{ auth()->check() ? __('giya.home.card_ask') : __('giya.home.create_acct') }}</a>
                </div>
            </div>

        </div>
    </section>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const proc = document.getElementById('proc');
    if (!proc) return;

    const items  = {!! $featuredData !!};
    const slides = Array.from(proc.querySelectorAll('.proc-slide'));
    const bars   = Array.from(proc.querySelectorAll('.proc-bar'));
    const copy   = document.getElementById('procCopy');
    if (!items.length) return;

    const HOLD = 6000;
    let live = 0, timer = null, paused = false;

    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function show(index) {
        live = (index + items.length) % items.length;
        const it = items[live];

        slides.forEach(function (s, i) {
            s.classList.toggle('is-live', i === live);
            s.setAttribute('aria-hidden', i === live ? 'false' : 'true');
        });
        bars.forEach(function (b, i) {
            b.classList.toggle('is-live', i === live);
            b.classList.toggle('is-done', i < live);
            b.setAttribute('aria-selected', i === live ? 'true' : 'false');
        });

        document.getElementById('procCat').textContent  = it.category || '';
        document.getElementById('procName').textContent  = it.name;
        document.getElementById('procLoc').innerHTML     =
            '<i class="bi bi-geo-alt-fill"></i> ' + (it.location || '');
        document.getElementById('procDesc').textContent  = it.desc || '';
        document.getElementById('procLink').href         = it.url;

        copy.classList.remove('is-in');
        void copy.offsetWidth;
        copy.classList.add('is-in');

        restart();
    }

    /* No automatic advance. Nothing moves unless the devotee moves it - the
       bars, the arrow keys and a swipe are the controls.

       The pause and resume handlers below are left in place: they cost
       nothing, and turning the timer back on is a one-line change. */
    function restart() {}

    /* Pausing on hover or focus is the difference between a slideshow that
       helps and one that snatches the page away mid-sentence. */
    function pause() { paused = true; clearTimeout(timer); proc.classList.add('is-paused'); }
    function resume() { paused = false; proc.classList.remove('is-paused'); restart(); }

    proc.addEventListener('mouseenter', pause);
    proc.addEventListener('mouseleave', resume);
    proc.addEventListener('focusin', pause);
    proc.addEventListener('focusout', resume);

    bars.forEach(function (b) {
        b.addEventListener('click', function () { show(Number(b.dataset.go)); });
    });

    proc.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { show(live + 1); e.preventDefault(); }
        if (e.key === 'ArrowLeft')  { show(live - 1); e.preventDefault(); }
    });

    let startX = null;
    proc.addEventListener('touchstart', function (e) {
        startX = e.touches[0].clientX; pause();
    }, { passive: true });
    proc.addEventListener('touchend', function (e) {
        if (startX !== null) {
            const dx = e.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 40) show(live + (dx < 0 ? 1 : -1));
            startX = null;
        }
        resume();
    });

    // A background tab should not run through five destinations unwatched.
    document.addEventListener('visibilitychange', function () {
        document.hidden ? pause() : resume();
    });

    show(0);
})();
</script>
@endpush
