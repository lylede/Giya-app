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
        'users'          => 'people-fill',
        'transactions'   => 'credit-card-fill',
        'feedback'       => 'chat-dots-fill',
        'visits'         => 'person-walking',
        'itineraries'    => 'journal-text',
        'system-summary' => 'bar-chart',
    ];

    $currentIcon = $reportIcons[$selectedReport] ?? 'compass-fill';
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
                <h3 style="margin: 0 0 3px; font-family: var(--font-display); font-size: 1rem; color: var(--text);">
                    Report Generator
                </h3>

                <p style="margin: 0; font-size: .72rem; color: var(--text-muted);">
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
            <ul style="margin: 0; padding-left: 18px;">
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

                <strong style="font-size: .78rem; color: var(--text);">
                    {{ $reportTable['title'] }}
                </strong>

                <span style="font-size: .67rem; color: var(--text-muted);">
                    {{ number_format($reportTable['rows']->count()) }}
                    {{ $reportTable['rows']->count() === 1 ? 'record' : 'records' }}
                </span>
            </div>


            <div class="report-date-range">
                <i class="bi bi-calendar3" style="color: var(--primary);"></i>

                @if ($filters['from'] || $filters['to'])

                    {{ $filters['from']
                        ? \Illuminate\Support\Carbon::parse($filters['from'])->format('M d, Y h:i A')
                        : 'Beginning'
                    }}

                    <span>—</span>

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
                                <td>{{ filled($value) ? $value : '—' }}</td>
                            @endforeach
                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="{{ count($reportTable['columns']) }}"
                                style="text-align: center; padding: 30px 15px;"
                            >
                                <i
                                    class="bi bi-inbox"
                                    style="display: block; font-size: 1.5rem; color: #aaa; margin-bottom: 7px;"
                                ></i>

                                <strong style="display: block; font-size: .78rem; margin-bottom: 3px;">
                                    No records found
                                </strong>

                                <span style="font-size: .69rem; color: var(--text-muted);">
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

            <i class="bi bi-compass-fill"></i>

            <div>
                <strong style="display: block; font-size: .76rem; color: var(--text); margin-bottom: 2px;">
                    No Report Generated Yet
                </strong>

                <p style="margin: 0; font-size: .69rem; color: var(--text-muted);">
                    Select a report type and optional date/time range,
                    then click Generate Report.
                </p>
            </div>

        </div>

    @endif

</div>


{{-- SUMMARY --}}
<div class="report-summary-grid">

    <div class="report-summary-card">
        <div class="report-summary-icon is-users">
            <i class="bi bi-people-fill"></i>
        </div>

        <div>
            <strong style="display: block; font-family: var(--font-display); font-size: 1rem;">
                {{ number_format($summary['users']) }}
            </strong>

            <span style="font-size: .65rem; color: var(--text-muted);">
                Users
            </span>
        </div>
    </div>


    <div class="report-summary-card">
        <div class="report-summary-icon is-itineraries">
            <i class="bi bi-journal-text"></i>
        </div>

        <div>
            <strong style="display: block; font-family: var(--font-display); font-size: 1rem;">
                {{ number_format($summary['itineraries']) }}
            </strong>

            <span style="font-size: .65rem; color: var(--text-muted);">
                Itineraries
            </span>
        </div>
    </div>


    <div class="report-summary-card">
        <div class="report-summary-icon is-visits">
            <i class="bi bi-person-walking"></i>
        </div>

        <div>
            <strong style="display: block; font-family: var(--font-display); font-size: 1rem;">
                {{ number_format($summary['visits']) }}
            </strong>

            <span style="font-size: .65rem; color: var(--text-muted);">
                Pilgrimage Visits
            </span>
        </div>
    </div>


    <div class="report-summary-card">
        <div class="report-summary-icon is-feedback">
            <i class="bi bi-chat-dots-fill"></i>
        </div>

        <div>
            <strong style="display: block; font-family: var(--font-display); font-size: 1rem;">
                {{ number_format($summary['feedback']) }}
            </strong>

            <span style="font-size: .65rem; color: var(--text-muted);">
                Feedback

                @if ($summary['feedback'] > 0)
                    · {{ number_format($summary['average_rating'], 2) }}★
                @endif
            </span>
        </div>
    </div>


    <div class="report-summary-card">
        <div class="report-summary-icon is-revenue">
            <i class="bi bi-credit-card-fill"></i>
        </div>

        <div>
            <strong style="display: block; font-family: var(--font-display); font-size: 1rem;">
                ₱{{ number_format($summary['revenue'], 2) }}
            </strong>

            <span style="font-size: .65rem; color: var(--text-muted);">
                Paid Revenue ·
                {{ number_format($summary['transactions']) }}
                transactions
            </span>
        </div>
    </div>

</div>


{{-- RECENT TRANSACTIONS + FEEDBACK --}}
<div class="report-preview-grid">

    {{-- TRANSACTIONS --}}
    <div class="report-section-card">

        <div style="padding: 12px 16px; border-bottom: 1px solid var(--border-light, #eee);">
            <h3 class="report-section-title">
                <i class="bi bi-credit-card-fill"></i>
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
                        <th style="text-align: right;">Amount</th>
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

                            <td style="text-align: right;">
                                ₱{{ number_format((float) $transaction->amount, 2) }}
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="4"
                                style="height: 110px; text-align: center; color: var(--text-muted);"
                            >
                                <i
                                    class="bi bi-credit-card"
                                    style="display: block; font-size: 1.2rem; margin-bottom: 5px;"
                                ></i>

                                <span style="font-size: .68rem;">
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

        <div style="padding: 12px 16px; border-bottom: 1px solid var(--border-light, #eee);">
            <h3 class="report-section-title">
                <i class="bi bi-chat-dots-fill"></i>
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

                            <td style="color: #c99000; font-weight: 700;">
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
                            <td
                                colspan="4"
                                style="height: 110px; text-align: center; color: var(--text-muted);"
                            >
                                <i
                                    class="bi bi-chat-dots"
                                    style="display: block; font-size: 1.2rem; margin-bottom: 5px;"
                                ></i>

                                <span style="font-size: .68rem;">
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

    <div style="padding: 12px 16px; border-bottom: 1px solid var(--border-light, #eee);">
        <h3 class="report-section-title">
            <i class="bi bi-building"></i>
            Most Visited Destinations
        </h3>
    </div>


    @if ($topDestinations->isEmpty())

        <div class="report-placeholder">

            <i class="bi bi-building"></i>

            <div>
                <strong style="display: block; font-size: .76rem; color: var(--text);">
                    No Visit Data
                </strong>

                <p style="margin: 2px 0 0; font-size: .69rem; color: var(--text-muted);">
                    Destination rankings will appear after pilgrimage visits are recorded.
                </p>
            </div>

        </div>

    @else

        <div style="padding: 4px 16px;">

            @foreach ($topDestinations as $index => $destination)

                <div class="report-destination-item">

                    <span class="report-rank">
                        {{ $index + 1 }}
                    </span>

                    <span style="flex: 1; font-size: .72rem; font-weight: 500; color: var(--text);">
                        {{ $destination->name }}
                    </span>

                    <span style="font-size: .68rem; font-weight: 600; color: var(--primary);">
                        {{ number_format($destination->total_visits) }}
                        {{ $destination->total_visits == 1 ? 'visit' : 'visits' }}
                    </span>

                </div>

            @endforeach

        </div>

    @endif

</div>

@endsection