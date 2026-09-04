@extends('layouts.app')
@section('title', 'Home')

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
<section style="position:relative;overflow:hidden;min-height:520px;display:flex;align-items:center">
    <img src="{{ asset('images/backgrounds/hero-basilica.svg') }}" alt=""
         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(142,59,47,0.9) 0%,rgba(36,28,24,0.82) 100%)"></div>

    <div style="position:absolute;top:32px;right:64px;opacity:.1">
        <svg width="120" height="120" viewBox="0 0 120 120" fill="none" aria-hidden="true">
            <rect x="48" y="8" width="24" height="104" rx="8" fill="#D7A94A"/>
            <rect x="8" y="44" width="104" height="24" rx="8" fill="#D7A94A"/>
        </svg>
    </div>

    <div class="hero-inner">
        <div style="max-width:680px">
            <div class="eyebrow">
                <span class="eyebrow-bar"></span>
                <span class="eyebrow-text" style="color:var(--gold)">Metro Cebu Religious Tourism</span>
            </div>

            <h1 style="font-family:var(--font-display);color:#fff;font-size:clamp(32px,5vw,54px);line-height:1.15;font-weight:700;margin:0 0 16px">
                Discover the Sacred Heart of Cebu
            </h1>

            <p style="color:rgba(255,255,255,0.8);font-size: 1rem;line-height:1.75;max-width:560px;margin:0 0 24px">
                Giya is your companion for pilgrimage and religious tourism across Metro Cebu 
                find churches, plan routes, and walk in faith through the Philippines' oldest diocese.
            </p>

            <div class="d-flex flex-wrap gap-3 mb-4">
                <a href="{{ route('map') }}" class="btn btn-gold">
                    <i class="bi bi-map-fill"></i> Explore the Map
                </a>
                <a href="{{ auth()->check() ? route('plan.hub') : route('login') }}" class="btn btn-ghost btn-ghost-inverse">
                    <i class="bi bi-journal-text"></i> {{ auth()->check() ? 'Plan a Pilgrimage' : 'Sign in to plan' }}
                </a>
            </div>

            <div class="hero-stats">
                @foreach ([
                    [$stats['churches'] . '+', 'Churches & Shrines'],
                    [auth()->check() ? number_format(auth()->user()->total_churches_visited) : $stats['churches'], auth()->check() ? 'Your Visits' : 'Places to Discover'],
                    [$stats['cities'], 'Cities Covered'],
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
    <section style="margin-bottom:56px">
        <div class="section-header">
            <div>
                <h2 class="section-title">Start Your Journey</h2>
                <p class="section-subtitle">Choose how you want to explore Metro Cebu's sacred places</p>
            </div>
        </div>

        <div class="home-grid home-grid-sm">
            @foreach ([
                /* GIYA's own icons: a church inside a place marker, a route
                   with its stops and a flag, the seven churches, and Giya's
                   own head - rather than a map, a notebook, an office block
                   and a speech bubble standing in for them. */
                ['giya-nearby',    'Find Nearby Churches', 'Discover religious destinations close to your current location.', 'map'],
                ['giya-route',     'Plan Pilgrimage',      'Create a custom itinerary tailored to your time and devotion.',   auth()->check() ? 'plan.create' : 'login'],
                ['giya-seven',     'Visita Iglesia Route', 'Plan the traditional multi-church route with progress tracking.', auth()->check() ? 'plan.visita' : 'login'],
                ['giya-assistant', 'Ask Giya AI',          'Get answers about churches, schedules, and pilgrimage routes.',   auth()->check() ? 'chatbot' : 'login'],
            ] as [$icon, $title, $desc, $route])
                <a href="{{ route($route) }}" class="card card-hover"
                   style="padding:20px;display:flex;flex-direction:column;gap:12px;text-decoration:none">
                    <span style="width:48px;height:48px;border-radius:16px;background:var(--gold-bg);display:flex;align-items:center;justify-content:center">
                        <i class="bi bi-{{ $icon }}" style="font-size: 1.375rem;color:var(--primary)"></i>
                    </span>
                    <span>
                        <span style="display:block;font-size: 0.9375rem;font-weight:700;color:var(--text)">{{ $title }}</span>
                        <span style="display:block;font-size: 0.75rem;color:var(--text-muted);margin-top:4px;line-height:1.6">{{ $desc }}</span>
                    </span>
                    <span style="margin-top:auto;font-size: 0.75rem;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:4px">
                        {{ $route === 'login' ? 'Sign in to continue' : 'Get started' }} <i class="bi bi-chevron-right" style="font-size: 0.6875rem"></i>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ──────────────────── Featured destinations ───────────────────── --}}
    <section style="margin-bottom:56px;border-top:1px solid var(--border-light);padding-top:48px">
        <div class="section-header">
            <div>
                <h2 class="section-title">Featured Destinations</h2>
                <p class="section-subtitle">Sacred places awaiting your visit in Metro Cebu</p>
            </div>
            <a href="{{ route('map') }}" class="section-link">View all on map →</a>
        </div>

        @if ($featured->isEmpty())
            <x-empty-state icon="building" title="No featured destinations yet"
                           desc="An administrator can feature destinations from the admin panel." />
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
                    <a class="btn btn-gold btn-sm" id="procLink">See More</a>
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
    <section style="border-top:1px solid var(--border-light);padding-top:48px">
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:32px" class="home-bottom-grid">

            <div>
                <h2 class="section-title" style="margin-bottom:4px">Upcoming Religious Activities</h2>
                <p class="section-subtitle" style="margin-bottom:20px">Masses, feast days, and celebrations across Metro Cebu</p>

                @forelse ($upcoming as $i => $event)
                    <div class="d-flex align-items-center gap-3 card"
                         style="padding:14px;margin-bottom:10px;box-shadow:var(--shadow-sm)">
                        <span style="width:56px;height:56px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center;text-align:center;padding:4px;
                                     background:{{ $i === 0 ? 'var(--primary)' : 'var(--gold-bg)' }}">
                            <span style="font-size: 0.5625rem;font-weight:700;text-transform:uppercase;line-height:1.2;
                                         color:{{ $i === 0 ? 'var(--gold)' : 'var(--primary)' }}">{{ $event->event_type }}</span>
                        </span>
                        <div style="flex:1;min-width:0">
                            <div style="font-size: 0.875rem;font-weight:700;color:var(--text)">{{ $event->event_name }}</div>
                            <div style="font-size: 0.75rem;color:var(--text-muted)">
                                <i class="bi bi-building" style="font-size: 0.6875rem"></i>
                                {{ $event->church->name ?? 'Metro Cebu' }}
                            </div>
                        </div>
                        <div style="font-size: 0.75rem;font-weight:700;color:var(--primary);white-space:nowrap">
                            {{ $event->schedule_date?->format('M j, Y') ?? ($event->recurrence ?? 'Recurring') }}
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="calendar-event" title="No upcoming activities"
                                   desc="Schedules added in the admin panel will appear here." />
                @endforelse
            </div>

            <div class="upgrade-card d-flex flex-column" style="padding:28px;min-height:320px">
                <div style="position:relative">
                    <span style="width:48px;height:48px;border-radius:16px;background:rgba(215,169,74,0.2);display:flex;align-items:center;justify-content:center;margin-bottom:16px">
                        <i class="bi bi-stars" style="font-size: 1.375rem;color:var(--gold)"></i>
                    </span>
                    <h3 style="font-family:var(--font-display);color:#fff;font-size: 1.375rem;line-height:1.3;margin:0 0 12px">
                        Ready to begin your pilgrimage?
                    </h3>
                    <p style="color:rgba(255,255,255,0.7);font-size: 0.8125rem;line-height:1.7;margin:0">
                        Build a personalised route based on your available time, location, and devotion.
                    </p>
                </div>

                <div class="d-flex flex-column gap-2 mt-auto pt-4" style="position:relative">
                    <a href="{{ auth()->check() ? route('plan.create') : route('login') }}" class="btn btn-gold btn-w-full">{{ auth()->check() ? 'Plan My Pilgrimage' : 'Sign in to plan' }}</a>
                    <a href="{{ auth()->check() ? route('chatbot') : route('login') }}" class="btn btn-ghost btn-ghost-inverse btn-w-full">{{ auth()->check() ? 'Ask Giya AI' : 'Create an account' }}</a>
                </div>
            </div>

        </div>
    </section>
</div>

@push('head')
<style>
    @media (max-width: 900px) {
        .home-bottom-grid { grid-template-columns: 1fr !important; }
    }
</style>
@endpush
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
