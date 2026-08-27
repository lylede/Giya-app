@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard Overview')
@section('page-subtitle', 'System activity across GIYA · ' . now()->format('F j, Y'))

@section('content')

<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px" class="stat-grid">
    @foreach ($stats as $stat)
        <div class="card card-body">
            <span style="width:40px;height:40px;border-radius:12px;background:var(--gold-bg);display:flex;align-items:center;justify-content:center;margin-bottom:10px">
                <i class="bi bi-{{ $stat['icon'] }}" style="font-size: 1.125rem;color:var(--primary)"></i>
            </span>
            <div style="font-family:var(--font-display);font-size: 1.5rem;font-weight:700;color:var(--text);line-height:1">
                {{ number_format($stat['value']) }}
            </div>
            <div style="font-size: 0.6875rem;color:var(--text-muted);margin-top:4px">{{ $stat['label'] }}</div>
        </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px" class="chart-grid">
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
        <div class="d-flex align-items-center gap-3"
             style="padding:10px 0;{{ $i < count($recentActivity) - 1 ? 'border-bottom:1px solid var(--border-light)' : '' }}">
            <span style="width:30px;height:30px;border-radius:50%;background:var(--gold-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-{{ $item['icon'] }}" style="font-size: 0.8125rem;color:var(--primary)"></i>
            </span>
            <span style="flex:1;font-size: 0.8125rem;color:var(--text)">{{ $item['text'] }}</span>
            <span style="font-size: 0.6875rem;color:var(--text-muted);flex-shrink:0">
                {{ $item['at']?->diffForHumans() }}
            </span>
        </div>
    @empty
        <x-empty-state icon="activity" title="No recent activity" />
    @endforelse
</div>

@push('head')
<style>
    @media (max-width: 1100px) { .stat-grid { grid-template-columns: repeat(3, 1fr) !important; } }
    @media (max-width: 700px)  { .stat-grid  { grid-template-columns: repeat(2, 1fr) !important; }
                                 .chart-grid { grid-template-columns: 1fr !important; } }
</style>
@endpush
@endsection
