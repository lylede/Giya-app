@extends('layouts.app')
@section('title', $church->name)

@section('content')

{{-- ─────────────────────────────── Hero ─────────────────────────────── --}}
{{-- No shimmer on the hero. Its image is absolutely positioned inside a
     section that already has a solid brown behind it and a fixed minimum
     height, so there is no white flash and nothing can shift when the photo
     lands - a shimmer here would be decoration, not a fix. --}}
<section class="church-hero">
    @if ($church->imagePath())
        <img src="{{ $church->imagePath() }}" alt="{{ $church->name }}"
             class="church-hero-image">
    @endif
    {{-- Warm brown tinted transparent gradient overlay matching the brand design --}}
    <div class="church-hero-overlay"></div>

    {{-- Free-licensed photos must name their author and licence. --}}
    @if ($credit = $church->photoCredit())
        <a href="{{ $credit['url'] }}" class="church-hero-credit" target="_blank" rel="noopener">{{ $credit['text'] }}</a>
    @endif

    <div class="church-hero-top">
        @php
            [$backUrl, $backLabel] = \App\Support\BackLink::previous(request(), route('map'), __('giya.back.map'));
        @endphp
        <x-back-link :href="$backUrl" :label="$backLabel" tone="dark" />
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
                <i class="bi bi-map" aria-hidden="true"></i> {{ __('giya.church.view_map') }}
            </a>

            @auth
                <a href="{{ route('map', ['plan' => 1, 'stops' => $church->id]) }}" class="btn btn-ghost btn-ghost-inverse">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('giya.church.add_itinerary') }}
                </a>

                {{-- Saving a destination belongs beside the other actions, not
                     buried in a card elsewhere in the app. --}}
                <button type="button"
                        @class(['btn', 'btn-ghost', 'btn-ghost-inverse', 'church-fav', 'is-saved' => $church->isFavorited()])
                        data-church="{{ $church->id }}"
                        aria-pressed="{{ $church->isFavorited() ? 'true' : 'false' }}"
                        onclick="GiyaChurchFav.toggle(this)">
                    <i class="bi bi-heart-fill" aria-hidden="true"></i>
                    <span class="church-fav-text">{{ $church->isFavorited() ? __('giya.church.saved') : __('giya.common.save') }}</span>
                </button>

                {{-- Notify me: feast days, novenas and schedule changes here,
                     plus a reminder a week before each dated event. --}}
                @php
                    $notified = \App\Models\ChurchFollow::isNotified((int) auth()->id(), $church->id);
                @endphp
                <button type="button"
                        @class(['btn', 'btn-ghost', 'btn-ghost-inverse', 'church-notify', 'is-on' => $notified])
                        data-url="{{ route('churches.notify', $church) }}"
                        aria-pressed="{{ $notified ? 'true' : 'false' }}"
                        title="{{ __('giya.church.notify_hint') }}"
                        onclick="GiyaChurchNotify.toggle(this)">
                    <i class="bi bi-bell-fill" aria-hidden="true"></i>
                    <span class="church-notify-text">{{ $notified ? __('giya.church.notifying') : __('giya.church.notify_me') }}</span>
                </button>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost btn-ghost-inverse">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('giya.church.add_itinerary') }}
                </a>
                <a href="{{ route('login') }}" class="btn btn-ghost btn-ghost-inverse">
                    <i class="bi bi-heart" aria-hidden="true"></i> {{ __('giya.common.save') }}
                </a>
            @endauth
        </div>
    </div>
</section>

{{-- ─────────────────── Stat strip - overlaps hero bottom edge cleanly ────────────────── --}}
<div class="church-stat-wrap">
    <div class="church-stat-strip card">
        {{-- A rating of 0.0 means "not rated yet", not "rated zero". Say so. --}}
        <div class="church-stat-item">
            <div class="church-stat-value is-inline">
                <i class="bi bi-star-fill" style="color:var(--gold)" aria-hidden="true"></i>
                {{ $church->rating > 0 ? number_format($church->rating, 1) : '-' }}
            </div>
            <div class="church-stat-label">
                {{ $church->rating > 0 ? 'Rating' : 'No ratings yet' }}
            </div>
        </div>

        @if ($church->hours_label)
            <div class="church-stat-item">
                <div class="church-stat-value is-inline">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    {{ $church->hours_label }}
                </div>
                <div class="church-stat-label">
                    {{ $church->isOpenNow() ? 'Open now' : 'Opening hours' }}
                </div>
            </div>
        @endif

        @if (!is_null($church->daily_visits))
            <div class="church-stat-item">
                <div class="church-stat-value is-inline">
                    <i class="bi bi-people-fill" aria-hidden="true"></i>
                    {{ $church->daily_visits }}
                </div>
                <div class="church-stat-label">{{ __('giya.church.visits_month') }}</div>
            </div>
        @endif

    </div>
</div>

<div class="church-detail-page page-wrap">

    {{-- The first thing most visitors want: when is the next Mass. --}}
    @php $nextMass = $church->nextMass(); @endphp
    @if ($nextMass)
        @php
            $at = $nextMass['at'];
            $day = $at->isToday() ? __('giya.church.today')
                : ($at->isTomorrow() ? __('giya.church.tomorrow')
                : ($at->diffInDays(now()->startOfDay(), true) < 7 ? $at->format('l') : $at->format('M j')));
        @endphp
        <a href="#church-schedule" class="church-next-mass" data-open-tab="church-schedule-tab">
            <i class="bi bi-bell-fill" aria-hidden="true"></i>
            <span class="church-next-mass-label">{{ $nextMass['schedule']->is_estimate ? __('giya.church.usual_mass') : __('giya.church.next_mass') }}</span>
            <strong>{{ $day }}, {{ $at->format('g:i A') }}</strong>
            @if ($nextMass['schedule']->is_estimate)
                <span class="church-next-mass-confirm">{{ __('giya.church.please_confirm') }}</span>
            @endif
            <span class="church-next-mass-link">{{ __('giya.church.see_schedule') }} <i class="bi bi-chevron-right" aria-hidden="true"></i></span>
        </a>
    @endif

    {{-- ───────────────────────────── Tabs ─────────────────────────────── --}}
    <div class="church-tabs" role="tablist" aria-label="{{ __('giya.church.details_aria') }}">
        <a href="#church-info" class="church-tab is-active" id="church-info-tab" role="tab" aria-selected="true" aria-controls="church-info">{{ __('giya.church.info') }}</a>
        <a href="#church-schedule" class="church-tab" id="church-schedule-tab" role="tab" aria-selected="false" aria-controls="church-schedule" tabindex="-1">{{ __('giya.church.schedule') }}</a>
        <a href="#church-reviews" class="church-tab" id="church-reviews-tab" role="tab" aria-selected="false" aria-controls="church-reviews" tabindex="-1">{{ __('giya.church.reviews') }}</a>
        <a href="#church-guidelines" class="church-tab" id="church-guidelines-tab" role="tab" aria-selected="false" aria-controls="church-guidelines" tabindex="-1">{{ __('giya.church.guidelines') }}</a>
    </div>

    {{-- ─────────────────────────── Info section ───────────────────────── --}}
    <section id="church-info" role="tabpanel" aria-labelledby="church-info-tab" data-church-panel class="church-detail-section">
        <h2 class="section-title church-section-title">About {{ $church->name }}</h2>

        @if ($church->description)
            <p class="church-description">{{ $church->description }}</p>
        @else
            <p class="church-description is-empty">{{ __('giya.church.no_desc') }}</p>
        @endif

        @if ($church->history)
            <h3 class="church-history-title">{{ __('giya.church.history') }}</h3>
            <div class="church-history">{!! nl2br(e($church->history)) !!}</div>
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
            <h2 class="section-title church-section-title">{{ __('giya.church.mass_schedule') }}</h2>

            @php
                $massSchedules = $church->schedules
                    ->filter(fn ($schedule) => strcasecmp($schedule->event_type, 'Mass') === 0)
                    ->groupBy('day_label');
            @endphp

            <div class="card church-schedule-card">
                @if ($church->schedules->where('event_type', 'Mass')->contains('is_estimate', true))
                    <p class="church-schedule-estimate">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        {{ __('giya.church.usual_times') }}
                    </p>
                @endif
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
                    <x-empty-state icon="calendar-x" :title="__('giya.church.no_schedule')"
                                    :desc="__('giya.church.no_schedule_d')" />
                @endforelse
            </div>
        </section>

        <section id="church-events">
            <h2 class="section-title church-section-title">{{ __('giya.church.events') }}</h2>

            @php
                $upcomingEvents = $church->schedules
                    ->filter(fn ($schedule) => strcasecmp($schedule->event_type, 'Mass') !== 0)
                    ->filter(fn ($schedule) => $schedule->is_recurring
                        || ! $schedule->schedule_date
                        || $schedule->schedule_date->isToday()
                        || $schedule->schedule_date->isFuture())
                    // Soonest first; events without a date last.
                    ->sortBy(fn ($schedule) => $schedule->schedule_date?->format('Y-m-d') ?? '9999-12-31')
                    ->values();
            @endphp

            @forelse ($upcomingEvents as $event)
                {{-- Name first, then when and where on their own lines. The date
                     used to sit in a third column; a long one ("Yearly - 3rd Sunday
                     of January (Jan 17, 2027)") squeezed the name down to one
                     letter per line. --}}
                <div class="church-event-row card">
                    <span class="church-event-badge">
                        <i class="bi bi-star-fill" aria-hidden="true"></i>
                        <span>{{ $event->event_type }}</span>
                    </span>
                    <div class="church-event-details">
                        <div class="church-event-name">{{ $event->event_name }}</div>
                        <div class="church-event-when">
                            <i class="bi bi-calendar-event" aria-hidden="true"></i>
                            {{ $event->day_label }}@if ($event->start_time) &middot; {{ $event->timeLabel($event->start_time) }}@endif
                        </div>
                        <div class="church-event-where">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                            {{ $event->location ?? $church->name }}
                        </div>
                    </div>
                </div>
            @empty
                <x-empty-state icon="calendar-x" :title="__('giya.church.no_events')"
                                :desc="__('giya.church.no_events_d')" />
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

        <h2 class="section-title church-section-title">{{ __('giya.church.reviews') }}</h2>

        @if ($reviewCount)
            <div class="church-reviews-layout">
                <div class="church-review-summary card">
                    <div class="church-review-average">
                        <strong>{{ $averageRating ? number_format($averageRating, 1) : '-' }}</strong>
                        @if ($averageRating)
                            <x-stars :rating="round($averageRating)" :size="16" />
                        @endif
                        <span>{{ number_format($reviewCount) }} {{ $reviewCount === 1 ? 'review' : 'reviews' }}</span>
                    </div>

                    <div class="church-rating-breakdown" aria-label="{{ __('giya.church.rating_aria') }}">
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
            <x-empty-state icon="chat-square-text" :title="__('giya.church.no_reviews')"
                           :desc="__('giya.church.no_reviews_d')" />
        @endif
    </section>

    <section id="church-guidelines" role="tabpanel" aria-labelledby="church-guidelines-tab" data-church-panel hidden class="church-detail-section">
        <h2 class="section-title church-section-title">{{ __('giya.church.guidelines') }}</h2>
        <p class="church-guide-intro">{{ __('giya.church.guide_intro') }}</p>
        <ul class="church-guide-list">
            @foreach (__('giya.church.guide_list') as [$icon, $title, $text])
                <li class="church-guide-item">
                    <span class="church-guide-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
                    <div>
                        <strong>{{ $title }}</strong>
                        <p>{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
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

    // "Next Mass" opens the Schedule tab and scrolls to it.
    document.querySelectorAll('[data-open-tab]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const tab = document.getElementById(link.dataset.openTab);
            if (!tab) return;
            tab.click();
            tab.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
</script>
<script>
window.GiyaChurchNotify = {
    toggle(btn) {
        const on = !btn.classList.contains('is-on');
        const label = (state) => state ? @json(__('giya.church.notifying')) : @json(__('giya.church.notify_me'));
        const paint = (state) => {
            btn.classList.toggle('is-on', state);
            btn.setAttribute('aria-pressed', String(state));
            btn.querySelector('.church-notify-text').textContent = label(state);
        };

        paint(on);   // flip first; put it back if the save fails

        fetch(btn.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
            .then(r => r.json())
            .then(d => { if (!d.ok) throw new Error('rejected'); paint(d.notify); })
            .catch(() => paint(!on));
    },
};

window.GiyaChurchFav = {
    toggle(btn) {
        const saved = !btn.classList.contains('is-saved');

        /* Flip first, then persist. A save that waits on the network feels
           broken; if the request fails the button is put back. */
        btn.classList.toggle('is-saved', saved);
        btn.setAttribute('aria-pressed', String(saved));
        btn.querySelector('.church-fav-text').textContent = saved ? @json(__('giya.church.saved')) : @json(__('giya.common.save'));

        fetch('{{ route('favorites.toggle') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ church_id: btn.dataset.church }),
        })
            .then(r => r.json())
            .then(d => {
                if (d.ok) return;
                throw new Error('rejected');
            })
            .catch(() => {
                btn.classList.toggle('is-saved', !saved);
                btn.setAttribute('aria-pressed', String(!saved));
                btn.querySelector('.church-fav-text').textContent = !saved ? @json(__('giya.church.saved')) : @json(__('giya.common.save'));
            });
    },
};
</script>
@endpush
@endsection