@extends('layouts.admin')

@section('title', 'Reports')
@section('page-title', 'Reports & Analytics')
@section('page-subtitle', 'Generate, review, and download administrative reports')

@php
    /*
        One icon per report, named once so the select, the result header and
        the placeholder cannot drift apart. These follow the vocabulary the
        rest of GIYA already uses - a walking pilgrim for visits, a building
        for a church, the same card and chat glyphs the admin sidebar shows -
        rather than generic document icons, so a report reads as belonging to
        this app rather than to any admin panel.
    */
    $reportIcons = [
        'users'          => 'giya-pilgrim',
        'transactions'   => 'giya-payment',
        'feedback'       => 'giya-star',
        'visits'         => 'giya-pilgrim',
        'itineraries'    => 'giya-route',
        'system-summary' => 'giya-tally',
    ];

    $currentIcon = $reportIcons[$selectedReport] ?? 'giya-magellan';
@endphp

@section('content')

{{-- REPORT GENERATOR --}}
<div class="report-card">

    <div class="report-header">
        <div class="report-header-title">
            {{-- Giya means guide, and a guide carries a compass. It becomes
                 the chosen report's own icon once one is picked. --}}
            <span class="report-header-icon">
                <i class="bi bi-{{ $currentIcon }}"></i>
            </span>

            <div>
                <h3 class="report-card-title">
                    Report Generator
                </h3>

                <p class="report-card-sub">
                    Select a report type and date/time range, then generate your report.
                </p>
            </div>
        </div>

        @if ($selectedReport)
            <a
                href="{{ route('admin.reports.download', ['report' => $selectedReport] + array_filter($filters)) }}"
                class="report-download-btn"
            >
                <i class="bi bi-download"></i>
                Download CSV
            </a>
        @else
            <button
                type="button"
                class="report-download-btn report-download-disabled"
                disabled
            >
                <i class="bi bi-download"></i>
                Download CSV
            </button>
        @endif
    </div>


    {{-- FILTER --}}
    <form method="GET" action="{{ route('admin.reports') }}" class="report-filter">

        <div class="report-field">
            <label for="report_type">Report Type</label>

            <select id="report_type" name="report_type" required>
                <option value="">Select Report Type</option>

                <option value="users" @selected($selectedReport === 'users')>
                    User Report
                </option>

                <option value="transactions" @selected($selectedReport === 'transactions')>
                    Transaction Report
                </option>

                <option value="feedback" @selected($selectedReport === 'feedback')>
                    Feedback Report
                </option>

                <option value="visits" @selected($selectedReport === 'visits')>
                    Pilgrimage Visit Report
                </option>

                <option value="itineraries" @selected($selectedReport === 'itineraries')>
                    Itinerary Report
                </option>

                <option value="system-summary" @selected($selectedReport === 'system-summary')>
                    System Summary
                </option>
            </select>
        </div>


        <div class="report-field">
            <label for="from">From Date & Time</label>

            <input
                type="datetime-local"
                id="from"
                name="from"
                value="{{ $filters['from'] }}"
            >
        </div>


        <div class="report-field">
            <label for="to">To Date & Time</label>

            <input
                type="datetime-local"
                id="to"
                name="to"
                value="{{ $filters['to'] }}"
            >
        </div>


        <div class="report-actions">
            <button type="submit" class="report-generate-btn">
                <i class="bi bi-bar-chart"></i>
                Generate Report
            </button>

            @if ($selectedReport || $filters['from'] || $filters['to'])
                <a href="{{ route('admin.reports') }}" class="report-clear-btn">
                    Clear
                </a>
            @endif
        </div>

    </form>


    {{-- ERRORS --}}
    @if ($errors->any())
        <div class="report-error">
            <ul class="report-error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    {{-- GENERATED REPORT --}}
    @if ($reportTable)

        <div class="report-result-header">

            <div class="report-result-info">
                <i class="bi bi-{{ $currentIcon }} report-result-icon"></i>

                <strong class="report-result-title">
                    {{ $reportTable['title'] }}
                </strong>

                <span class="report-result-count giya-num">
                    {{ number_format($reportTable['rows']->count()) }}
                    {{ $reportTable['rows']->count() === 1 ? 'record' : 'records' }}
                </span>
            </div>


            <div class="report-date-range">
                <i class="bi bi-calendar3"></i>

                @if ($filters['from'] || $filters['to'])

                    {{ $filters['from']
                        ? \Illuminate\Support\Carbon::parse($filters['from'])->format('M d, Y h:i A')
                        : 'Beginning'
                    }}

                    <span>-</span>

                    {{ $filters['to']
                        ? \Illuminate\Support\Carbon::parse($filters['to'])->format('M d, Y h:i A')
                        : 'Present'
                    }}

                @else
                    All Time
                @endif
            </div>

        </div>


        <div class="report-table-wrapper">

            <table class="report-table">

                <thead>
                    <tr>
                        @foreach ($reportTable['columns'] as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($reportTable['rows'] as $row)

                        <tr>
                            @foreach ($row as $value)
                                <td>{{ filled($value) ? $value : '-' }}</td>
                            @endforeach
                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="{{ count($reportTable['columns']) }}"
                                class="report-empty-cell"
                            >
                                <i class="bi bi-inbox report-empty-icon"></i>

                                <strong class="report-empty-title">
                                    No records found
                                </strong>

                                <span class="report-empty-sub">
                                    No records match the selected report and date/time range.
                                </span>
                            </td>
                        </tr>

                    @endforelse
                </tbody>

            </table>

        </div>

    @else

        <div class="report-placeholder">

            <i class="bi bi-giya-magellan"></i>

            <div>
                <strong class="report-placeholder-title">
                    No Report Generated Yet
                </strong>

                <p class="report-placeholder-sub">
                    Select a report type and optional date/time range,
                    then click Generate Report.
                </p>
            </div>

        </div>

    @endif

</div>


{{-- SUMMARY --}}
{{-- The same tile every other module uses. These were five hand-written
     cards with the number in the display serif, which is the one face a
     figure should never be set in. --}}
<div class="stat-row">
    <x-stat-tile icon="giya-pilgrim" label="Users" :value="$summary['users']" tone="primary" />
    <x-stat-tile icon="giya-route" label="Itineraries" :value="$summary['itineraries']" tone="gold" />
    <x-stat-tile icon="giya-nearby" label="Pilgrimage Visits" :value="$summary['visits']" tone="green" />

    <x-stat-tile icon="giya-star" label="Feedback" :value="$summary['feedback']" tone="blue">
        @if ($summary['feedback'] > 0)
            <x-slot:sub>{{ number_format($summary['average_rating'], 2) }}★ average</x-slot:sub>
        @endif
    </x-stat-tile>

    <x-stat-tile icon="giya-payment" label="Paid Revenue"
                 value="₱{{ number_format($summary['revenue'], 2) }}" tone="primary">
        <x-slot:sub>{{ number_format($summary['transactions']) }} transactions</x-slot:sub>
    </x-stat-tile>
</div>


{{-- RECENT TRANSACTIONS + FEEDBACK --}}
<div class="report-preview-grid">

    {{-- TRANSACTIONS --}}
    <div class="report-section-card">

        <div class="report-section-head">
            <h3 class="report-section-title">
                <i class="bi bi-giya-payment"></i>
                Recent Transactions
            </h3>
        </div>

        <div class="report-table-wrapper">

            <table class="report-table report-small-table">

                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>User</th>
                        <th>Status</th>
                        <th class="is-right">Amount</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($recentTransactions as $transaction)

                        @php
                            $status = strtolower($transaction->status ?? '');
                        @endphp

                        <tr>
                            <td>{{ $transaction->transaction_id }}</td>

                            <td>
                                {{ $transaction->user?->name ?? 'Unknown user' }}
                            </td>

                            <td>
                                <span class="
                                    report-status
                                    @if (in_array($status, ['paid', 'approved', 'completed']))
                                        report-status-success
                                    @elseif ($status === 'pending')
                                        report-status-warning
                                    @elseif (in_array($status, ['failed', 'rejected', 'cancelled']))
                                        report-status-danger
                                    @else
                                        report-status-neutral
                                    @endif
                                ">
                                    {{ $transaction->status }}
                                </span>
                            </td>

                            <td class="is-right giya-num">
                                ₱{{ number_format((float) $transaction->amount, 2) }}
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="4" class="report-blank-cell">
                                <i class="bi bi-giya-payment report-blank-icon"></i>

                                <span class="report-blank-text">
                                    No transaction records for this period.
                                </span>
                            </td>
                        </tr>

                    @endforelse
                </tbody>

            </table>

        </div>
    </div>


    {{-- FEEDBACK --}}
    <div class="report-section-card">

        <div class="report-section-head">
            <h3 class="report-section-title">
                <i class="bi bi-giya-star"></i>
                Recent Feedback
            </h3>
        </div>

        <div class="report-table-wrapper">

            <table class="report-table report-small-table">

                <thead>
                    <tr>
                        <th>User</th>
                        <th>Destination</th>
                        <th>Rating</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($recentFeedback as $feedback)

                        @php
                            $status = strtolower($feedback->status ?? '');
                        @endphp

                        <tr>
                            <td>
                                {{ $feedback->user?->name ?? 'Unknown user' }}
                            </td>

                            <td>
                                {{ $feedback->church?->name ?? 'Unknown destination' }}
                            </td>

                            <td class="report-rating giya-num">
                                {{ $feedback->rating }}★
                            </td>

                            <td>
                                <span class="
                                    report-status
                                    @if ($status === 'approved')
                                        report-status-success
                                    @elseif ($status === 'pending')
                                        report-status-warning
                                    @elseif ($status === 'rejected')
                                        report-status-danger
                                    @else
                                        report-status-neutral
                                    @endif
                                ">
                                    {{ $feedback->status }}
                                </span>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="4" class="report-blank-cell">
                                <i class="bi bi-giya-star report-blank-icon"></i>

                                <span class="report-blank-text">
                                    No feedback records for this period.
                                </span>
                            </td>
                        </tr>

                    @endforelse
                </tbody>

            </table>

        </div>
    </div>

</div>


{{-- MOST VISITED DESTINATIONS --}}
<div class="report-section-card">

    <div class="report-section-head">
        <h3 class="report-section-title">
            <i class="bi bi-giya-spires"></i>
            Most Visited Destinations
        </h3>
    </div>


    @if ($topDestinations->isEmpty())

        <div class="report-placeholder">

            <i class="bi bi-giya-spires"></i>

            <div>
                <strong class="report-placeholder-title">
                    No Visit Data
                </strong>

                <p class="report-placeholder-sub">
                    Destination rankings will appear after pilgrimage visits are recorded.
                </p>
            </div>

        </div>

    @else

        <div class="report-rank-list">

            @foreach ($topDestinations as $index => $destination)

                <div class="report-destination-item">

                    <span class="report-rank">
                        {{ $index + 1 }}
                    </span>

                    <span class="report-rank-name">
                        {{ $destination->name }}
                    </span>

                    <span class="report-rank-count giya-num">
                        {{ number_format($destination->total_visits) }}
                        {{ $destination->total_visits == 1 ? 'visit' : 'visits' }}
                    </span>

                </div>

            @endforeach

        </div>

    @endif

</div>

@endsection