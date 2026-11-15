@extends('layouts.admin')
@section('title', 'App Feedback')
@section('page-title', 'App Feedback')
@section('page-subtitle', 'What pilgrims say about GIYA itself')

@section('content')

@unless ($ready)
    <div class="alert alert-warning mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>The database is missing the app feedback table. Run <strong>php artisan migrate</strong> once, then come back.</span>
    </div>
@endunless

@if ($summary)
    <div class="stat-row">
        <x-stat-tile icon="giya-star" label="Average rating" :value="$summary['total'] ? number_format($summary['average'], 2).' / 5' : '-'" tone="gold" />
        <x-stat-tile icon="chat-dots-fill" label="All feedback" :value="$summary['total']" tone="blue" />
        <x-stat-tile icon="calendar-event" label="This month" :value="$summary['month']" tone="green" />
        <x-stat-tile icon="flag-fill" label="1 or 2 stars" :value="$summary['low']" tone="primary" />
    </div>

    @if ($byTopic->isNotEmpty())
        <div class="card card-body mb-3">
            <div class="card-title">By topic</div>
            <ul class="appfb-topics">
                @foreach (\App\Models\AppFeedback::TOPICS as $topic)
                    @php $row = $byTopic->get($topic); @endphp
                    <li>
                        <span class="appfb-topic-name">{{ __('giya.app_feedback.topic_'.$topic) }}</span>
                        <span class="appfb-topic-num">{{ $row ? number_format((float) $row->average, 1).' / 5' : '-' }}</span>
                        <span class="appfb-topic-count">{{ $row ? $row->total.' '.($row->total == 1 ? 'response' : 'responses') : 'none yet' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endif

<form method="GET" class="log-filters mb-3">
    <div class="field">
        <label class="dm-label" for="af-topic">Topic</label>
        <select id="af-topic" name="topic" class="giya-input">
            <option value="">All topics</option>
            @foreach (\App\Models\AppFeedback::TOPICS as $topic)
                <option value="{{ $topic }}" @selected(($filters['topic'] ?? '') === $topic)>{{ __('giya.app_feedback.topic_'.$topic) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label class="dm-label" for="af-rating">Rating</label>
        <select id="af-rating" name="rating" class="giya-input">
            <option value="">Any rating</option>
            @for ($i = 5; $i >= 1; $i--)
                <option value="{{ $i }}" @selected((int) ($filters['rating'] ?? 0) === $i)>{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}</option>
            @endfor
        </select>
    </div>
    <div class="field log-filter-actions">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="{{ route('admin.app-feedback') }}" class="btn btn-outline">Clear</a>
        @if ($ready)
            <a href="{{ route('admin.app-feedback.download', array_filter($filters)) }}" class="btn btn-outline"><i class="bi bi-download"></i> Download</a>
        @endif
    </div>
</form>

<div class="card adm-scroller">
    <div>
        <table class="giya-table sm-table">
            <thead>
                <tr><th>When</th><th>Pilgrim</th><th>Rating</th><th>Topic</th><th>Comment</th></tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="log-when">{{ $item->created_at?->format('M j, Y') }}<br><span class="log-muted">{{ $item->created_at?->format('g:i A') }}</span></td>
                        <td>{{ $item->user->name ?? 'Deleted account' }}<br><span class="log-muted">{{ $item->user->email ?? '' }}</span></td>
                        <td class="appfb-stars" aria-label="{{ $item->rating }} out of 5">{{ str_repeat('★', $item->rating) }}<span class="appfb-stars-off">{{ str_repeat('★', 5 - $item->rating) }}</span></td>
                        <td>{{ $item->topicLabel() }}</td>
                        <td class="appfb-comment">{{ $item->comment ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="chat-dots-fill" title="No app feedback yet" desc="What pilgrims send from the Send feedback page shows here." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($ready)
    <div class="mt-3"><x-pagination :paginator="$items" /></div>
@endif
@endsection
