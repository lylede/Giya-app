@extends('layouts.admin')

@section('title', 'Reports')

@section('page-title', 'Reports & Analytics')

@section(
    'page-subtitle',
    'Review system performance and download administrative reports'
)

@section('content')

{{-- =========================================================
     DATE FILTER
========================================================= --}}

<form
    method="GET"
    action="{{ route('admin.reports') }}"
    class="card card-body"
    style="margin-bottom:20px"
>
    <div
        class="d-flex align-items-end gap-3 report-filter-row"
    >

        <div style="flex:1;min-width:180px">
            <label
                class="form-label"
                for="from"
            >
                From
            </label>

            <input
                class="form-control"
                type="date"
                id="from"
                name="from"
                value="{{ $filters['from'] }}"
            >
        </div>


        <div style="flex:1;min-width:180px">
            <label
                class="form-label"
                for="to"
            >
                To
            </label>

            <input
                class="form-control"
                type="date"
                id="to"
                name="to"
                value="{{ $filters['to'] }}"
            >
        </div>


        <button
            type="submit"
            class="btn btn-primary"
        >
            <i class="bi bi-funnel"></i>

            Apply Filter
        </button>


        @if ($filters['from'] || $filters['to'])

            <a
                href="{{ route('admin.reports') }}"
                class="btn btn-outline"
            >
                Clear
            </a>

        @endif

    </div>


    @error('from')

        <div
            class="text-danger"
            style="
                font-size:.75rem;
                margin-top:8px
            "
        >
            {{ $message }}
        </div>

    @enderror


    @error('to')

        <div
            class="text-danger"
            style="
                font-size:.75rem;
                margin-top:8px
            "
        >
            {{ $message }}
        </div>

    @enderror

</form>


{{-- =========================================================
     SUMMARY CARDS
========================================================= --}}

<div
    class="report-stat-grid"
    style="
        display:grid;
        grid-template-columns:repeat(5,1fr);
        gap:16px;
        margin-bottom:24px
    "
>

    {{-- Users --}}
    <div class="card card-body report-stat">

        <span class="report-stat-icon">
            <i class="bi bi-people-fill"></i>
        </span>

        <strong>
            {{ number_format($summary['users']) }}
        </strong>

        <span>
            Users
        </span>

    </div>


    {{-- Itineraries --}}
    <div class="card card-body report-stat">

        <span class="report-stat-icon">
            <i class="bi bi-journal-text"></i>
        </span>

        <strong>
            {{ number_format($summary['itineraries']) }}
        </strong>

        <span>
            Itineraries
        </span>

    </div>


    {{-- Visits --}}
    <div class="card card-body report-stat">

        <span class="report-stat-icon">
            <i class="bi bi-geo-alt-fill"></i>
        </span>

        <strong>
            {{ number_format($summary['visits']) }}
        </strong>

        <span>
            Pilgrimage Visits
        </span>

    </div>


    {{-- Feedback --}}
    <div class="card card-body report-stat">

        <span class="report-stat-icon">
            <i class="bi bi-chat-dots-fill"></i>
        </span>

        <strong>
            {{ number_format($summary['feedback']) }}
        </strong>

        <span>
            Feedback ·
            {{ number_format(
                $summary['average_rating'],
                2
            ) }}★ avg
        </span>

    </div>


    {{-- Revenue --}}
    <div class="card card-body report-stat">

        <span class="report-stat-icon">
            <i class="bi bi-cash-stack"></i>
        </span>

        <strong>
            ₱{{ number_format(
                $summary['revenue'],
                2
            ) }}
        </strong>

        <span>
            Paid Revenue ·
            {{ number_format(
                $summary['transactions']
            ) }}
            transactions
        </span>

    </div>

</div>


{{-- =========================================================
     DOWNLOADABLE REPORTS
========================================================= --}}

<div
    class="card card-body"
    style="margin-bottom:24px"
>

    <div
        class="
            d-flex
            align-items-center
            justify-content-between
            gap-3
        "
        style="margin-bottom:16px"
    >

        <div>

            <div
                class="card-title"
                style="margin-bottom:3px"
            >
                Download Reports
            </div>

            <div
                style="
                    font-size:.75rem;
                    color:var(--text-muted)
                "
            >
                CSV files can be opened directly
                in Microsoft Excel or Google Sheets.
            </div>

        </div>


        {{-- System Summary --}}
        <a
            href="{{
                route(
                    'admin.reports.download',
                    ['report' => 'system-summary']
                    + array_filter($filters)
                )
            }}"
            class="btn btn-primary"
        >

            <i class="bi bi-download"></i>

            System Summary

        </a>

    </div>


    @php

        $reports = [

            [
                'type' => 'users',
                'icon' => 'people-fill',
                'title' => 'User Report',
                'desc' =>
                    'Accounts, roles, status, favorites, and itineraries.',
            ],

            [
                'type' => 'transactions',
                'icon' => 'credit-card-fill',
                'title' => 'Transaction Report',
                'desc' =>
                    'Payments, plans, references, status, and amounts.',
            ],

            [
                'type' => 'feedback',
                'icon' => 'chat-square-text-fill',
                'title' => 'Feedback Report',
                'desc' =>
                    'Ratings, comments, moderation status, and destinations.',
            ],

            [
                'type' => 'visits',
                'icon' => 'geo-alt-fill',
                'title' => 'Pilgrimage Visit Report',
                'desc' =>
                    'Completed visits, destinations, itineraries, and visit dates.',
            ],

            [
                'type' => 'itineraries',
                'icon' => 'journal-text',
                'title' => 'Itinerary Report',
                'desc' =>
                    'Created plans, itinerary types, schedules, stops, and progress.',
            ],

        ];

    @endphp


    <div class="report-download-grid">

        @foreach ($reports as $report)

            <div class="report-download-card">

                <span class="report-download-icon">

                    <i
                        class="
                            bi
                            bi-{{ $report['icon'] }}
                        "
                    ></i>

                </span>


                <div
                    style="
                        min-width:0;
                        flex:1
                    "
                >

                    <div
                        style="
                            font-weight:700;
                            color:var(--text);
                            font-size:.875rem
                        "
                    >
                        {{ $report['title'] }}
                    </div>


                    <div
                        style="
                            font-size:.72rem;
                            color:var(--text-muted);
                            margin-top:3px
                        "
                    >
                        {{ $report['desc'] }}
                    </div>

                </div>


                <a
                    href="{{
                        route(
                            'admin.reports.download',
                            ['report' => $report['type']]
                            + array_filter($filters)
                        )
                    }}"
                    class="btn btn-outline"
                    title="Download {{ $report['title'] }}"
                >

                    <i class="bi bi-download"></i>

                    CSV

                </a>

            </div>

        @endforeach

    </div>

</div>


{{-- =========================================================
     TRANSACTIONS + FEEDBACK PREVIEW
========================================================= --}}

<div
    class="report-preview-grid"
    style="
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:20px;
        margin-bottom:24px
    "
>

    {{-- Recent Transactions --}}
    <div class="card card-body">

        <div class="card-title">
            Recent Transactions
        </div>


        <div style="overflow-x:auto">

            <table
                class="table"
                style="min-width:520px"
            >

                <thead>

                    <tr>
                        <th>Reference</th>

                        <th>User</th>

                        <th>Status</th>

                        <th style="text-align:right">
                            Amount
                        </th>
                    </tr>

                </thead>


                <tbody>

                    @forelse (
                        $recentTransactions
                        as $transaction
                    )

                        <tr>

                            <td>
                                {{
                                    $transaction
                                        ->transaction_id
                                }}
                            </td>


                            <td>
                                {{
                                    $transaction
                                        ->user
                                        ?->name
                                        ?? 'Unknown user'
                                }}
                            </td>


                            <td>

                                <span class="badge">
                                    {{
                                        $transaction
                                            ->status
                                    }}
                                </span>

                            </td>


                            <td style="text-align:right">

                                ₱{{
                                    number_format(
                                        (float)
                                        $transaction
                                            ->amount,
                                        2
                                    )
                                }}

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="4"
                                style="
                                    text-align:center;
                                    color:var(--text-muted);
                                    padding:28px
                                "
                            >
                                No transaction records
                                for this period.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- Recent Feedback --}}
    <div class="card card-body">

        <div class="card-title">
            Recent Feedback
        </div>


        <div style="overflow-x:auto">

            <table
                class="table"
                style="min-width:520px"
            >

                <thead>

                    <tr>

                        <th>User</th>

                        <th>Destination</th>

                        <th>Rating</th>

                        <th>Status</th>

                    </tr>

                </thead>


                <tbody>

                    @forelse (
                        $recentFeedback
                        as $feedback
                    )

                        <tr>

                            <td>
                                {{
                                    $feedback
                                        ->user
                                        ?->name
                                        ?? 'Unknown user'
                                }}
                            </td>


                            <td>
                                {{
                                    $feedback
                                        ->church
                                        ?->name
                                        ?? 'Unknown destination'
                                }}
                            </td>


                            <td>
                                {{ $feedback->rating }}★
                            </td>


                            <td>

                                <span class="badge">
                                    {{ $feedback->status }}
                                </span>

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="4"
                                style="
                                    text-align:center;
                                    color:var(--text-muted);
                                    padding:28px
                                "
                            >
                                No feedback records
                                for this period.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>


{{-- =========================================================
     MOST VISITED DESTINATIONS
========================================================= --}}

<div class="card card-body">

    <div class="card-title">
        Most Visited Destinations
    </div>


    @if ($topDestinations->isEmpty())

        <x-empty-state
            icon="geo-alt"
            title="No visit data for this period"
            desc="Destination rankings will appear after pilgrimage visits are recorded."
        />


    @else

        <div class="top-destination-list">

            @foreach (
                $topDestinations
                as $index => $destination
            )

                <div class="top-destination-row">

                    <span class="top-rank">
                        {{ $index + 1 }}
                    </span>


                    <span
                        style="
                            flex:1;
                            font-size:.8125rem;
                            color:var(--text)
                        "
                    >
                        {{ $destination->name }}
                    </span>


                    <strong
                        style="
                            font-size:.8125rem;
                            color:var(--primary)
                        "
                    >

                        {{
                            number_format(
                                $destination
                                    ->total_visits
                            )
                        }}
                        visits

                    </strong>

                </div>

            @endforeach

        </div>

    @endif

</div>


{{-- =========================================================
     REPORT PAGE STYLES
========================================================= --}}

@push('head')

<style>

    .report-stat {
        min-width: 0;
    }


    .report-stat-icon,
    .report-download-icon {

        width: 40px;
        height: 40px;

        border-radius: 12px;

        background: var(--gold-bg);

        display: flex;

        align-items: center;
        justify-content: center;

        flex-shrink: 0;

        color: var(--primary);
    }


    .report-stat strong {

        font-family: var(--font-display);

        font-size: 1.35rem;

        color: var(--text);

        line-height: 1.1;

        margin-top: 10px;
    }


    .report-stat > span:last-child {

        font-size: .6875rem;

        color: var(--text-muted);

        margin-top: 4px;
    }


    .report-download-grid {

        display: grid;

        grid-template-columns:
            1fr
            1fr;

        gap: 12px;
    }


    .report-download-card {

        display: flex;

        align-items: center;

        gap: 12px;

        border:
            1px solid
            var(--border-light);

        border-radius: 12px;

        padding: 14px;

        background: var(--surface);
    }


    .top-destination-row {

        display: flex;

        align-items: center;

        gap: 12px;

        padding: 11px 0;

        border-bottom:
            1px solid
            var(--border-light);
    }


    .top-destination-row:last-child {
        border-bottom: 0;
    }


    .top-rank {

        width: 28px;
        height: 28px;

        border-radius: 50%;

        background: var(--gold-bg);

        display: flex;

        align-items: center;

        justify-content: center;

        font-weight: 700;

        font-size: .75rem;

        color: var(--primary);
    }


    /*
    |--------------------------------------------------------------------------
    | Tablet
    |--------------------------------------------------------------------------
    */

    @media (max-width: 1100px) {

        .report-stat-grid {

            grid-template-columns:
                repeat(3, 1fr) !important;
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Mobile / Tablet
    |--------------------------------------------------------------------------
    */

    @media (max-width: 800px) {

        .report-download-grid,
        .report-preview-grid {

            grid-template-columns:
                1fr !important;
        }


        .report-filter-row {
            flex-wrap: wrap;
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Small Mobile
    |--------------------------------------------------------------------------
    */

    @media (max-width: 600px) {

        .report-stat-grid {

            grid-template-columns:
                1fr 1fr !important;
        }


        .report-download-card {

            align-items: flex-start;

            flex-wrap: wrap;
        }


        .report-download-card .btn {

            margin-left: 52px;
        }

    }

</style>

@endpush

@endsection