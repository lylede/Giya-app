@extends('layouts.admin')
@section('title', 'Activity Log')
@section('page-title', 'Activity Log')
@section('page-subtitle', 'What each admin changed in the admin panel, and when')

@section('content')

@unless ($ready)
    <div class="alert alert-warning mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>The database is missing the activity log table. Run <strong>php artisan migrate</strong> once, then come back.</span>
    </div>
@endunless

<form method="GET" class="log-filters">
    <div class="field">
        <label class="dm-label" for="l-q">Search</label>
        <input id="l-q" type="search" name="q" class="giya-input" value="{{ $filters['q'] ?? '' }}" placeholder="A name, a church, an action">
    </div>
    <div class="field">
        <label class="dm-label" for="l-admin">Admin</label>
        <select id="l-admin" name="admin" class="giya-input">
            <option value="">Everyone</option>
            @foreach ($admins as $name)
                <option value="{{ $name }}" @selected(($filters['admin'] ?? '') === $name)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label class="dm-label" for="l-from">From</label>
        <input id="l-from" type="date" name="from" class="giya-input" value="{{ $filters['from'] ?? '' }}">
    </div>
    <div class="field">
        <label class="dm-label" for="l-to">To</label>
        <input id="l-to" type="date" name="to" class="giya-input" value="{{ $filters['to'] ?? '' }}">
    </div>
    <div class="field log-filter-actions">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="{{ route('admin.activity') }}" class="btn btn-outline">Clear</a>
    </div>
</form>

<div class="card adm-scroller mt-3">
    <div>
        <table class="giya-table sm-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>About</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="log-when" title="{{ $entry->created_at?->format('M j, Y g:i:s A') }}">
                            {{ $entry->created_at?->format('M j, Y') }}<br>
                            <span class="log-muted">{{ $entry->created_at?->format('g:i A') }}</span>
                        </td>
                        <td>{{ $entry->admin_name }}</td>
                        <td><strong>{{ $entry->action }}</strong></td>
                        <td>{{ $entry->subject ?? '-' }}</td>
                        <td class="log-muted">{{ $entry->details ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state icon="clock-history" title="Nothing logged yet"
                                           desc="Changes made in the admin panel appear here from now on." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($ready)
    <div class="mt-3"><x-pagination :paginator="$entries" /></div>
@endif

<p class="insights-note">Entries are written automatically and cannot be edited or deleted from the admin panel. Looking at a page is not logged; changes and downloads are.</p>
@endsection
