@extends('layouts.admin')
@section('title', 'Insights')
@section('page-title', 'Insights')
@section('page-subtitle', 'How pilgrims use GIYA · Giya AI over the last 30 days, visits over the last 90')

@section('content')

<div class="stat-row">
    <x-stat-tile icon="chat-dots-fill"  label="Giya AI questions (30 days)" :value="$ai['questions']" tone="gold" />
    <x-stat-tile icon="people-fill"     label="People who asked"            :value="$ai['people']"    tone="blue" />
    <x-stat-tile icon="signpost-2-fill" label="Routes planned by Giya AI"   :value="$ai['routes']"    tone="green" />
</div>

<div class="chart-grid">
    <div class="card card-body">
        <div class="card-title">Giya AI questions per day</div>
        <x-line-chart :labels="$perDay['labels']" :data="$perDay['data']" />
    </div>

    <div class="card card-body">
        <div class="card-title">What pilgrims ask about</div>
        @if (empty($topics['labels']))
            <x-empty-state icon="chat-dots" title="No questions yet" desc="This fills in as pilgrims use Giya AI." />
        @else
            <x-bar-chart :labels="$topics['labels']" :data="$topics['data']" aria="Bar chart of Giya AI question topics" />
        @endif
    </div>

    <div class="card card-body">
        <div class="card-title">Parts of Cebu asked about</div>
        @if (empty($regions['labels']))
            <x-empty-state icon="map" title="No area asked yet" desc="Questions like &quot;churches in south Cebu&quot; show up here." />
        @else
            <x-bar-chart :labels="$regions['labels']" :data="$regions['data']" aria="Bar chart of parts of Cebu asked about" />
        @endif
    </div>

    <div class="card card-body">
        <div class="card-title">Busiest days for church visits</div>
        <x-bar-chart :labels="$weekdays['labels']" :data="$weekdays['data']" aria="Bar chart of church visits by day of the week" />
    </div>

    <div class="card card-body">
        <div class="card-title">Most followed churches</div>
        @if (empty($follows['labels']))
            <x-empty-state icon="bell" title="No follows yet" desc="Pilgrims follow a church to get its Mass reminders." />
        @else
            <x-bar-chart :labels="$follows['labels']" :data="$follows['data']" aria="Bar chart of most followed churches" />
        @endif
    </div>

    <div class="card card-body">
        <div class="card-title">New sign-ups per week</div>
        <x-line-chart :labels="$signups['labels']" :data="$signups['data']" />
    </div>
</div>

<p class="insights-note">Counts only: no names, emails or chat text are shown on this page.</p>
@endsection
