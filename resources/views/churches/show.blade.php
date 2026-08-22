@extends('layouts.app')
@section('title', $church->name)

@section('content')

{{-- ─────────────────────────────── Hero ─────────────────────────────── --}}
<section style="position:relative;overflow:hidden;min-height:480px;display:flex;flex-direction:column;justify-content:space-between;background-color:#63331b;padding-bottom:48px;">
    @if ($church->imagePath())
        <img src="{{ $church->imagePath() }}" alt="{{ $church->name }}"
             style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;">
    @endif
    {{-- Warm brown tinted transparent gradient overlay matching the brand design --}}
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(99,51,27,0.55) 0%,rgba(53,27,14,0.92) 100%);z-index:1;"></div>

    <div style="position:relative;z-index:2;padding:24px">
        <a href="{{ url()->previous() ?: route('map') }}"
           aria-label="Back to map"
           style="width:42px;height:42px;border-radius:50%;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.5);color:#fff;display:inline-flex;align-items:center;justify-content:center;text-decoration:none">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </a>
    </div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;padding:0 24px;width:100%">
        @if ($church->category)
            <span style="background:var(--gold);color:#fff;font-weight:600;font-size:0.75rem;padding:6px 14px;border-radius:999px;display:inline-block;margin-bottom:12px">
                {{ $church->category }}
            </span>
        @endif

        <h1 style="font-family:var(--font-display);color:#fff;font-size:clamp(28px,5vw,44px);line-height:1.2;font-weight:700;margin:0 0 8px">
            {{ $church->name }}
        </h1>

        @if ($church->address || $church->location)
            <p style="color:rgba(255,255,255,0.9);display:flex;align-items:center;gap:6px;margin:0 0 20px">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                {{ $church->address ?? $church->location }}
            </p>
        @endif

        <div class="d-flex flex-wrap gap-3">
            <a href="{{ route('map') }}?church={{ $church->id }}" class="btn btn-ghost btn-ghost-inverse">
                <i class="bi bi-map" aria-hidden="true"></i> View Map
            </a>

            @auth
                <a href="{{ route('plan.create', ['church' => $church->id]) }}" class="btn btn-ghost btn-ghost-inverse">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Add to Itinerary
                </a>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost btn-ghost-inverse">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Add to Itinerary
                </a>
            @endauth
        </div>
    </div>
</section>

{{-- ─────────────────── Stat strip — overlaps hero bottom edge cleanly ────────────────── --}}
<div style="max-width:540px;margin:24px auto 0;position:relative;z-index:3;padding:0 20px">
    <div class="card" style="display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:28px;padding:14px 20px;box-shadow:0 4px 12px rgba(0,0,0,0.08);background:#fff;border-radius:999px;">
        @if (!is_null($church->rating))
            <div style="text-align:center;">
                <div style="font-weight:700;font-size:0.875rem;color:var(--text);display:flex;align-items:center;gap:3px;justify-content:center;">
                    <i class="bi bi-star-fill" style="color:var(--gold);font-size:0.75rem;" aria-hidden="true"></i> {{ number_format($church->rating, 1) }}
                </div>
                <div style="font-size:0.6875rem;color:var(--text-muted)">Rating</div>
            </div>
        @endif

        @if ($church->hours_label)
            <div style="text-align:center;">
                <div style="font-weight:700;font-size:0.875rem;color:var(--text)">{{ $church->hours_label }}</div>
                <div style="font-size:0.6875rem;color:var(--text-muted)">Open</div>
            </div>
        @endif

        @if (!is_null($church->daily_visits))
            <div style="text-align:center;">
                <div style="font-weight:700;font-size:0.875rem;color:var(--text)">{{ $church->daily_visits }}</div>
                <div style="font-size:0.6875rem;color:var(--text-muted)">Visitors</div>
            </div>
        @endif

        <div style="text-align:center;">
            <div style="font-weight:700;font-size:0.875rem;color:var(--text);display:flex;align-items:center;gap:3px;justify-content:center;">
                <i class="bi bi-file-earmark-fill" style="color:#0d6efd;font-size:0.75rem;" aria-hidden="true"></i> {{ $church->access ?? 'Yes' }}
            </div>
            <div style="font-size:0.6875rem;color:var(--text-muted)">Access</div>
        </div>
    </div>
</div>

<div class="page-wrap" style="padding-top:28px">

    {{-- ───────────────────────────── Tabs ─────────────────────────────── --}}
    <div class="church-tabs" role="tablist" aria-label="Church details">
        <a href="#church-info" class="church-tab is-active" id="church-info-tab" role="tab" aria-selected="true" aria-controls="church-info">Info</a>
        <a href="#church-schedule" class="church-tab" id="church-schedule-tab" role="tab" aria-selected="false" aria-controls="church-schedule" tabindex="-1">Schedule</a>
        <a href="#church-reviews" class="church-tab" id="church-reviews-tab" role="tab" aria-selected="false" aria-controls="church-reviews" tabindex="-1">Reviews</a>
        <a href="#church-guidelines" class="church-tab" id="church-guidelines-tab" role="tab" aria-selected="false" aria-controls="church-guidelines" tabindex="-1">Guidelines</a>
    </div>

    {{-- ─────────────────────────── Info section ───────────────────────── --}}
    <section id="church-info" role="tabpanel" aria-labelledby="church-info-tab" data-church-panel style="margin-bottom:40px">
        <h2 class="section-title" style="margin-bottom:16px">About {{ $church->name }}</h2>

        @if ($church->description)
            <p style="color:var(--text);line-height:1.75;margin-bottom:12px">{{ $church->description }}</p>
        @else
            <p style="color:var(--text-muted);line-height:1.75;margin-bottom:12px">No description is available for this church yet.</p>
        @endif

        <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:4px">
            <i class="bi bi-geo-alt" aria-hidden="true"></i>
            {{ $church->address ?? $church->location ?? 'Address not available' }}
        </p>

        <p style="color:var(--text-muted);font-size:0.875rem;margin:0">
            <i class="bi bi-clock" aria-hidden="true"></i>
            {{ $church->hours_label ?? 'Time not available' }}
        </p>
    </section>

    {{-- ──────────────────── Schedule + Upcoming events ─────────────────── --}}
    <div id="church-schedule" role="tabpanel" aria-labelledby="church-schedule-tab" data-church-panel hidden class="church-detail-grid">

        <section>
            <h2 class="section-title" style="margin-bottom:16px">Mass Schedule</h2>

            @php
                $massSchedules = $church->schedules
                    ->filter(fn ($schedule) => strcasecmp($schedule->event_type, 'Mass') === 0)
                    ->groupBy('day_label');
            @endphp

            <div class="card" style="padding:20px 24px">
                @forelse ($massSchedules as $dayLabel => $schedulesForDay)
                    <div style="margin-bottom:20px">
                        <div style="font-weight:700;color:var(--text);margin-bottom:10px">{{ $dayLabel }}</div>
                        <div>
                            @foreach ($schedulesForDay as $schedule)
                                <span style="display:inline-block;background:var(--gold-bg);border:1px solid var(--border);border-radius:999px;padding:6px 14px;font-size:0.8125rem;color:var(--text);margin:0 6px 6px 0">
                                    {{ $schedule->timeLabel($schedule->start_time) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="calendar-x" title="No schedule available yet"
                                    desc="Mass schedules added in the admin panel will appear here." />
                @endforelse
            </div>
        </section>

        <section id="church-events">
            <h2 class="section-title" style="margin-bottom:16px">Upcoming Events</h2>

            @php
                $upcomingEvents = $church->schedules
                    ->filter(fn ($schedule) => strcasecmp($schedule->event_type, 'Mass') !== 0)
                    ->filter(fn ($schedule) => $schedule->is_recurring
                        || ! $schedule->schedule_date
                        || $schedule->schedule_date->isToday()
                        || $schedule->schedule_date->isFuture());
            @endphp

            @forelse ($upcomingEvents as $event)
                <div class="d-flex align-items-center gap-3 card" style="padding:14px;margin-bottom:10px;box-shadow:var(--shadow-sm)">
                    <span style="width:52px;height:52px;border-radius:12px;flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:4px;background:#63331b;color:var(--gold)">
                        <i class="bi bi-star-fill" style="font-size:0.75rem;margin-bottom:2px"></i>
                        <span style="font-size:0.5rem;font-weight:700;text-transform:uppercase;line-height:1.1;">{{ $event->event_type }}</span>
                    </span>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:0.875rem;font-weight:700;color:var(--text)">{{ $event->event_name }}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted)">
                            <i class="bi bi-geo-alt" style="font-size:0.6875rem" aria-hidden="true"></i>
                            {{ $event->location ?? $church->name }}
                        </div>
                    </div>
                    <div style="font-size:0.75rem;font-weight:700;color:var(--text-muted);white-space:nowrap;margin-left:auto;text-align:right">
                        <div>{{ $event->day_label }}</div>
                        @if ($event->start_time)
                            <div>{{ $event->timeLabel($event->start_time) }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <x-empty-state icon="calendar-x" title="No upcoming events"
                                desc="Events added in the admin panel will appear here." />
            @endforelse
        </section>
    </div>

    <section id="church-reviews" role="tabpanel" aria-labelledby="church-reviews-tab" data-church-panel hidden style="margin-bottom:40px">
        <x-empty-state icon="chat-square-text" title="Reviews coming soon"
                       desc="Reviews for this church will appear here when available." />
    </section>

    <section id="church-guidelines" role="tabpanel" aria-labelledby="church-guidelines-tab" data-church-panel hidden style="margin-bottom:40px">
        <x-empty-state icon="info-circle" title="Guidelines coming soon"
                       desc="Visit guidelines for this church will appear here when available." />
    </section>
</div>

@push('head')
<style>
    @media (max-width: 900px) {
        .church-detail-grid { grid-template-columns: 1fr !important; }
    }

    .church-detail-grid {
        display:grid;
        grid-template-columns:2fr 1fr;
        gap:32px;
    }

    [data-church-panel][hidden] {
        display:none !important;
    }

    .church-tabs {
        display:flex;
        justify-content:center;
        gap:36px;
        border-bottom:1px solid var(--border-light);
        margin-bottom:24px;
        flex-wrap:wrap;
    }

    .church-tab {
        color:var(--text-muted);
        font-weight:600;
        font-size:0.9375rem;
        text-decoration:none;
        padding-bottom:10px;
        border-bottom:2px solid transparent;
    }

    .church-tab.is-active,
    .church-tab:hover,
    .church-tab:focus-visible {
        color:var(--primary);
    }

    .church-tab.is-active {
        border-bottom-color:var(--primary);
    }
</style>
@endpush

@push('scripts')
<script>
    document.querySelectorAll('.church-tab').forEach((tab) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();

            document.querySelectorAll('.church-tab').forEach((item) => {
                const active = item === tab;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
                item.tabIndex = active ? 0 : -1;
            });

            document.querySelectorAll('[data-church-panel]').forEach((panel) => {
                panel.hidden = panel.id !== tab.getAttribute('aria-controls');
            });
        });
    });
</script>
@endpush
@endsection