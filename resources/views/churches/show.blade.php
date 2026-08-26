@extends('layouts.app')
@section('title', $church->name)

@section('content')

{{-- ─────────────────────────────── Hero ─────────────────────────────── --}}
<section class="church-hero">
    @if ($church->imagePath())
        <img src="{{ $church->imagePath() }}" alt="{{ $church->name }}"
             class="church-hero-image">
    @endif
    {{-- Warm brown tinted transparent gradient overlay matching the brand design --}}
    <div class="church-hero-overlay"></div>

    <div class="church-hero-top">
        <a href="{{ url()->previous() ?: route('map') }}"
           aria-label="Back to map"
           class="church-back-link">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </a>
    </div>

    <div class="church-hero-content">
        @if ($church->category)
            <span class="church-hero-category">
                {{ $church->category }}
            </span>
        @endif

        <h1>
            {{ $church->name }}
        </h1>

        @if ($church->address || $church->location)
            <p class="church-hero-address">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                {{ $church->address ?? $church->location }}
            </p>
        @endif

        <div class="church-hero-actions d-flex flex-wrap gap-3">
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
<div class="church-stat-wrap">
    <div class="church-stat-strip card">
        @if (!is_null($church->rating))
            <div class="church-stat-item">
                <div class="church-stat-value is-inline">
                    <i class="bi bi-star-fill" aria-hidden="true"></i> {{ number_format($church->rating, 1) }}
                </div>
                <div class="church-stat-label">Rating</div>
            </div>
        @endif

        @if ($church->hours_label)
            <div class="church-stat-item">
                <div class="church-stat-value">{{ $church->hours_label }}</div>
                <div class="church-stat-label">Open</div>
            </div>
        @endif

        @if (!is_null($church->daily_visits))
            <div class="church-stat-item">
                <div class="church-stat-value">{{ $church->daily_visits }}</div>
                <div class="church-stat-label">Visitors</div>
            </div>
        @endif

        <div class="church-stat-item">
            <div class="church-stat-value is-inline">
                <i class="bi bi-file-earmark-fill" aria-hidden="true"></i> {{ $church->access ?? 'Yes' }}
            </div>
            <div class="church-stat-label">Access</div>
        </div>
    </div>
</div>

<div class="church-detail-page page-wrap">

    {{-- ───────────────────────────── Tabs ─────────────────────────────── --}}
    <div class="church-tabs" role="tablist" aria-label="Church details">
        <a href="#church-info" class="church-tab is-active" id="church-info-tab" role="tab" aria-selected="true" aria-controls="church-info">Info</a>
        <a href="#church-schedule" class="church-tab" id="church-schedule-tab" role="tab" aria-selected="false" aria-controls="church-schedule" tabindex="-1">Schedule</a>
        <a href="#church-reviews" class="church-tab" id="church-reviews-tab" role="tab" aria-selected="false" aria-controls="church-reviews" tabindex="-1">Reviews</a>
        <a href="#church-guidelines" class="church-tab" id="church-guidelines-tab" role="tab" aria-selected="false" aria-controls="church-guidelines" tabindex="-1">Guidelines</a>
    </div>

    {{-- ─────────────────────────── Info section ───────────────────────── --}}
    <section id="church-info" role="tabpanel" aria-labelledby="church-info-tab" data-church-panel class="church-detail-section">
        <h2 class="section-title church-section-title">About {{ $church->name }}</h2>

        @if ($church->description)
            <p class="church-description">{{ $church->description }}</p>
        @else
            <p class="church-description is-empty">No description is available for this church yet.</p>
        @endif

        <p class="church-info-meta is-address">
            <i class="bi bi-geo-alt" aria-hidden="true"></i>
            {{ $church->address ?? $church->location ?? 'Address not available' }}
        </p>

        <p class="church-info-meta is-hours">
            <i class="bi bi-clock" aria-hidden="true"></i>
            {{ $church->hours_label ?? 'Time not available' }}
        </p>
    </section>

    {{-- ──────────────────── Schedule + Upcoming events ─────────────────── --}}
    <div id="church-schedule" role="tabpanel" aria-labelledby="church-schedule-tab" data-church-panel hidden class="church-detail-grid">

        <section>
            <h2 class="section-title church-section-title">Mass Schedule</h2>

            @php
                $massSchedules = $church->schedules
                    ->filter(fn ($schedule) => strcasecmp($schedule->event_type, 'Mass') === 0)
                    ->groupBy('day_label');
            @endphp

            <div class="card church-schedule-card">
                @forelse ($massSchedules as $dayLabel => $schedulesForDay)
                    <div class="church-schedule-day">
                        <div class="church-schedule-day-label">{{ $dayLabel }}</div>
                        <div>
                            @foreach ($schedulesForDay as $schedule)
                                <span class="church-schedule-time">
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
            <h2 class="section-title church-section-title">Upcoming Events</h2>

            @php
                $upcomingEvents = $church->schedules
                    ->filter(fn ($schedule) => strcasecmp($schedule->event_type, 'Mass') !== 0)
                    ->filter(fn ($schedule) => $schedule->is_recurring
                        || ! $schedule->schedule_date
                        || $schedule->schedule_date->isToday()
                        || $schedule->schedule_date->isFuture());
            @endphp

            @forelse ($upcomingEvents as $event)
                <div class="church-event-row d-flex align-items-center gap-3 card">
                    <span class="church-event-badge">
                        <i class="bi bi-star-fill"></i>
                        <span>{{ $event->event_type }}</span>
                    </span>
                    <div class="church-event-details">
                        <div>{{ $event->event_name }}</div>
                        <div>
                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                            {{ $event->location ?? $church->name }}
                        </div>
                    </div>
                    <div class="church-event-meta">
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

    <section id="church-reviews" role="tabpanel" aria-labelledby="church-reviews-tab" data-church-panel hidden class="church-detail-section">
        @php
            $approvedFeedback = $church->feedback;
            $ratedFeedback = $approvedFeedback->filter(fn ($feedback) => !is_null($feedback->rating));
            $averageRating = $ratedFeedback->avg('rating');
            $reviewCount = $approvedFeedback->count();
        @endphp

        <h2 class="section-title church-section-title">Reviews</h2>

        @if ($reviewCount)
            <div class="church-reviews-layout">
                <div class="church-review-summary card">
                    <div class="church-review-average">
                        <strong>{{ $averageRating ? number_format($averageRating, 1) : '—' }}</strong>
                        @if ($averageRating)
                            <x-stars :rating="round($averageRating)" :size="16" />
                        @endif
                        <span>{{ number_format($reviewCount) }} {{ $reviewCount === 1 ? 'review' : 'reviews' }}</span>
                    </div>

                    <div class="church-rating-breakdown" aria-label="Rating breakdown">
                        @for ($rating = 5; $rating >= 1; $rating--)
                            @php
                                $ratingCount = $ratedFeedback->where('rating', $rating)->count();
                                $ratingPercent = $ratedFeedback->count()
                                    ? ($ratingCount / $ratedFeedback->count()) * 100
                                    : 0;
                            @endphp
                            <div class="church-rating-row">
                                <span>{{ $rating }}</span>
                                <div class="church-rating-track" aria-hidden="true">
                                    <span style="width:{{ $ratingPercent }}%"></span>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>

                <div class="church-review-list">
                    @foreach ($approvedFeedback as $feedback)
                        <article class="church-review-card">
                            <div class="church-review-card-header">
                                <div>
                                    <div class="church-review-author">{{ $feedback->user->name ?? 'Anonymous pilgrim' }}</div>
                                    @if (!is_null($feedback->rating))
                                        <x-stars :rating="$feedback->rating" :size="12" />
                                    @endif
                                </div>
                                <time datetime="{{ $feedback->created_at?->toDateString() }}">
                                    {{ $feedback->created_at?->format('F j, Y') ?? 'Date unavailable' }}
                                </time>
                            </div>

                            @if ($feedback->comment)
                                <p>{{ $feedback->comment }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        @else
            <x-empty-state icon="chat-square-text" title="No reviews yet"
                           desc="Approved reviews for this church will appear here." />
        @endif
    </section>

    <section id="church-guidelines" role="tabpanel" aria-labelledby="church-guidelines-tab" data-church-panel hidden class="church-detail-section">
        <x-empty-state icon="info-circle" title="Guidelines coming soon"
                       desc="Visit guidelines for this church will appear here when available." />
    </section>
</div>

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/church-show.css') }}?v={{ filemtime(public_path('assets/css/church-show.css')) }}">
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