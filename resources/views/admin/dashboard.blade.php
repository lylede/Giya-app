@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard Overview')
@section('page-subtitle', 'System activity across GIYA · ' . now()->format('F j, Y'))

@section('content')

<div class="stat-row">
    @foreach ($stats as $stat)
        <x-stat-tile :icon="$stat['icon']" :value="$stat['value']"
                     :label="$stat['label']" :tone="$stat['tone'] ?? 'gold'" />
    @endforeach
</div>

{{-- What needs doing: each line opens the place where it is done. --}}
<section class="card card-body todo-card" aria-labelledby="todoTitle">
    <div class="card-title" id="todoTitle">To do</div>
    @if (empty($todo))
        <p class="todo-done"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> All caught up. Nothing is waiting.</p>
    @else
        <ul class="todo-list">
            @foreach ($todo as $t)
                <li>
                    <a href="{{ $t['url'] }}" class="todo-item">
                        <span @class(['todo-icon', 'is-'.$t['tone']])><i class="bi bi-{{ $t['icon'] }}" aria-hidden="true"></i></span>
                        <span class="todo-text"><b>{{ number_format($t['n']) }}</b> {{ $t['n'] === 1 ? $t['one'] : $t['many'] }}</span>
                        <i class="bi bi-chevron-right todo-go" aria-hidden="true"></i>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>

<div class="chart-grid">
    <div class="card card-body">
        <div class="card-title">Visits - Last 6 Months</div>
        <x-line-chart :labels="$monthlyVisits['labels']" :data="$monthlyVisits['data']" />
    </div>

    <div class="card card-body">
        <div class="card-title">Most Visited Destinations</div>
        @if (empty($popularChurches['labels']))
            <x-empty-state icon="bar-chart" title="No visit data yet"
                           desc="This chart fills in once pilgrims start logging visits." />
        @else
            <x-bar-chart :labels="$popularChurches['labels']" :data="$popularChurches['data']" />
        @endif
    </div>
</div>

<div class="card card-body">
    <div class="card-title">Recent Activity</div>
    @forelse ($recentActivity as $i => $item)
        <div @class(['activity-row', 'is-last' => $i === count($recentActivity) - 1])>
            <span class="activity-icon">
                <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
            </span>
            <span class="activity-text">{{ $item['text'] }}</span>
            <span class="activity-when">{{ $item['at']?->diffForHumans() }}</span>
        </div>
    @empty
        <x-empty-state icon="activity" title="No recent activity" />
    @endforelse
</div>

@endsection
