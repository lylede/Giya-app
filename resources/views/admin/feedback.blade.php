@extends('layouts.admin')
@section('title', 'Feedback')
@section('page-title', 'Feedback Management')
@section('page-subtitle', 'Reviews and ratings submitted by pilgrims')

@section('content')

<div class="stat-row">
    @foreach ([
        ['giya-star',        'Total',    $summary['total'],    'gold'],
        ['hourglass-split',  'Pending',  $summary['pending'],  'blue'],
        ['check-circle-fill','Approved', $summary['approved'], 'green'],
        ['flag-fill',        'Flagged',  $summary['flagged'],  'primary'],
    ] as [$icon, $label, $value, $tone])
        <x-stat-tile :icon="$icon" :label="$label" :value="$value" :tone="$tone" />
    @endforeach
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
    @foreach (['All', 'Pending', 'Approved', 'Flagged'] as $s)
        <a href="{{ route('admin.feedback', ['status' => $s]) }}"
           @class(['btn', 'btn-sm', 'btn-primary' => $status === $s, 'btn-outline' => $status !== $s])>{{ $s }}</a>
    @endforeach
</div>

@forelse ($feedback as $item)
    <div class="card card-body mb-2">
        <div class="adm-item">
            <span class="nav-avatar adm-item-avatar">
                {{ strtoupper(substr($item->user->name ?? 'A', 0, 1)) }}
            </span>
            <div class="adm-item-main">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="adm-item-name">{{ $item->user->name ?? 'Deleted user' }}</span>
                    <x-stars :rating="$item->rating ?? 0" />
                    <span class="badge status-{{ $item->status === 'Approved' ? 'Completed' : ($item->status === 'Flagged' ? 'Draft' : 'Upcoming') }}">
                        {{ $item->status }}
                    </span>
                </div>
                <div class="adm-item-meta">
                    {{ $item->church->name ?? 'Unknown destination' }} · {{ $item->created_at?->diffForHumans() }}
                </div>
                @if ($item->comment)
                    <p class="adm-item-body">{{ $item->comment }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.feedback.update', $item) }}" class="d-flex gap-2">
                @csrf @method('PATCH')
                <select name="status" class="giya-input is-compact">
                    @foreach (['Pending', 'Approved', 'Flagged'] as $s)
                        <option value="{{ $s }}" @selected($item->status === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </form>
        </div>
    </div>
@empty
    <div class="card"><x-empty-state icon="chat-dots" title="No feedback yet" /></div>
@endforelse

<div class="mt-3"><x-pagination :paginator="$feedback" /></div>
@endsection
