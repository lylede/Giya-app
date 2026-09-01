<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Itinerary;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VisitHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const REPORT_TYPES = [
        'users',
        'transactions',
        'feedback',
        'visits',
        'itineraries',
        'system-summary',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $userQuery = $this->dateRange(
            User::query(),
            $filters,
            'created_at'
        );

        $transactionQuery = $this->dateRange(
            Transaction::query(),
            $filters,
            'created_at'
        );

        $feedbackQuery = $this->dateRange(
            Feedback::query(),
            $filters,
            'created_at'
        );

        $visitQuery = $this->dateRange(
            VisitHistory::query(),
            $filters,
            'visited_at'
        );

        $itineraryQuery = $this->dateRange(
            Itinerary::query(),
            $filters,
            'created_at'
        );

        $totalTransactions = (clone $transactionQuery)->count();

        $paidTransactions = (clone $transactionQuery)
            ->where('status', 'Paid');

        $feedbackCount = (clone $feedbackQuery)->count();

        return view('admin.reports', [
            'filters' => $filters,

            'summary' => [
                'users' => (clone $userQuery)->count(),

                'transactions' => $totalTransactions,

                'revenue' => (float) (clone $paidTransactions)
                    ->sum('amount'),

                'feedback' => $feedbackCount,

                'average_rating' => $feedbackCount
                    ? round(
                        (float) (clone $feedbackQuery)->avg('rating'),
                        2
                    )
                    : 0,

                'visits' => (clone $visitQuery)->count(),

                'itineraries' => (clone $itineraryQuery)->count(),
            ],

            'recentTransactions' => (clone $transactionQuery)
                ->with([
                    'user',
                    'subscriptionPlan',
                ])
                ->orderByDesc('created_at')
                ->take(5)
                ->get(),

            'recentFeedback' => (clone $feedbackQuery)
                ->with([
                    'user',
                    'church',
                ])
                ->orderByDesc('created_at')
                ->take(5)
                ->get(),

            'topDestinations' => (clone $visitQuery)
                ->join(
                    'churches',
                    'churches.id',
                    '=',
                    'visit_history.church_id'
                )
                ->selectRaw(
                    'churches.id, churches.name, COUNT(*) as total_visits'
                )
                ->groupBy(
                    'churches.id',
                    'churches.name'
                )
                ->orderByDesc('total_visits')
                ->take(5)
                ->get(),
        ]);
    }

    public function download(
        Request $request,
        string $report
    ): StreamedResponse {
        abort_unless(
            in_array($report, self::REPORT_TYPES, true),
            404
        );

        $filters = $this->filters($request);

        $filename =
            'giya-' .
            $report .
            '-report-' .
            now()->format('Y-m-d') .
            '.csv';

        return response()->streamDownload(
            function () use ($report, $filters) {

                $out = fopen('php://output', 'w');

                /*
                 * UTF-8 BOM
                 * Helps Microsoft Excel display
                 * names and special characters correctly.
                 */
                fwrite($out, "\xEF\xBB\xBF");

                match ($report) {
                    'users' =>
                        $this->writeUsers($out, $filters),

                    'transactions' =>
                        $this->writeTransactions($out, $filters),

                    'feedback' =>
                        $this->writeFeedback($out, $filters),

                    'visits' =>
                        $this->writeVisits($out, $filters),

                    'itineraries' =>
                        $this->writeItineraries($out, $filters),

                    'system-summary' =>
                        $this->writeSystemSummary($out, $filters),
                };

                fclose($out);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | User Report
    |--------------------------------------------------------------------------
    */

    private function writeUsers(
        $out,
        array $filters
    ): void {
        fputcsv($out, [
            'No.',
            'Name',
            'Email',
            'Role',
            'Status',
            'Joined',
            'Saved Destinations',
            'Itineraries Created',
        ]);

        $rows = $this->dateRange(
            User::query(),
            $filters,
            'created_at'
        )
            ->withCount([
                'favorites',
                'itineraries',
            ])
            ->orderByDesc('created_at')
            ->get();

        foreach ($rows as $i => $user) {
            fputcsv($out, [
                $i + 1,
                $user->name,
                $user->email,
                ucfirst($user->role),
                $user->status,
                $user->created_at?->format(
                    'Y-m-d H:i:s'
                ),
                $user->favorites_count,
                $user->itineraries_count,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Report
    |--------------------------------------------------------------------------
    */

    private function writeTransactions(
        $out,
        array $filters
    ): void {
        fputcsv($out, [
            'Transaction ID',
            'User',
            'Email',
            'Plan',
            'Amount',
            'Currency',
            'Method',
            'Status',
            'Reference No.',
            'Processed At',
            'Created At',
        ]);

        $rows = $this->dateRange(
            Transaction::query(),
            $filters,
            'created_at'
        )
            ->with([
                'user',
                'subscriptionPlan',
            ])
            ->orderByDesc('created_at')
            ->get();

        foreach ($rows as $transaction) {
            fputcsv($out, [
                $transaction->transaction_id,

                $transaction->user?->name
                    ?? 'Unknown user',

                $transaction->user?->email
                    ?? '',

                $transaction
                    ->subscriptionPlan
                    ?->name
                    ?? 'Unknown plan',

                number_format(
                    (float) $transaction->amount,
                    2,
                    '.',
                    ''
                ),

                $transaction->currency,
                $transaction->method,
                $transaction->status,
                $transaction->reference_no,

                $transaction
                    ->processed_at
                    ?->format('Y-m-d H:i:s'),

                $transaction
                    ->created_at
                    ?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Feedback Report
    |--------------------------------------------------------------------------
    */

    private function writeFeedback(
        $out,
        array $filters
    ): void {
        fputcsv($out, [
            'No.',
            'User',
            'Destination',
            'Rating',
            'Status',
            'Comment',
            'Submitted At',
        ]);

        $rows = $this->dateRange(
            Feedback::query(),
            $filters,
            'created_at'
        )
            ->with([
                'user',
                'church',
            ])
            ->orderByDesc('created_at')
            ->get();

        foreach ($rows as $i => $feedback) {
            fputcsv($out, [
                $i + 1,

                $feedback->user?->name
                    ?? 'Unknown user',

                $feedback->church?->name
                    ?? 'Unknown destination',

                $feedback->rating,
                $feedback->status,
                $feedback->comment,

                $feedback
                    ->created_at
                    ?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Pilgrimage Visit Report
    |--------------------------------------------------------------------------
    */

    private function writeVisits(
        $out,
        array $filters
    ): void {
        fputcsv($out, [
            'No.',
            'User',
            'Destination',
            'Itinerary',
            'Completion Status',
            'Visited At',
            'Notes',
        ]);

        $rows = $this->dateRange(
            VisitHistory::query(),
            $filters,
            'visited_at'
        )
            ->with([
                'user',
                'church',
                'itinerary',
            ])
            ->orderByDesc('visited_at')
            ->get();

        foreach ($rows as $i => $visit) {
            fputcsv($out, [
                $i + 1,

                $visit->user?->name
                    ?? 'Unknown user',

                $visit->church?->name
                    ?? 'Unknown destination',

                $visit->itinerary?->name
                    ?? 'Independent visit',

                $visit->completion_status,

                $visit
                    ->visited_at
                    ?->format('Y-m-d H:i:s'),

                $visit->notes,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Itinerary Report
    |--------------------------------------------------------------------------
    */

    private function writeItineraries(
        $out,
        array $filters
    ): void {
        fputcsv($out, [
            'No.',
            'User',
            'Itinerary Name',
            'Type',
            'Scheduled Date',
            'Status',
            'Total Stops',
            'Visited Stops',
            'Created At',
        ]);

        $rows = $this->dateRange(
            Itinerary::query(),
            $filters,
            'created_at'
        )
            ->with([
                'user',
                'itineraryType',
            ])
            ->withCount([
                'stops',

                'stops as visited_stops_count'
                    => fn ($query) =>
                        $query->where(
                            'is_visited',
                            true
                        ),
            ])
            ->orderByDesc('created_at')
            ->get();

        foreach ($rows as $i => $itinerary) {
            fputcsv($out, [
                $i + 1,

                $itinerary->user?->name
                    ?? 'Unknown user',

                $itinerary->name,

                $itinerary
                    ->itineraryType
                    ?->name
                    ?? 'Custom',

                $itinerary
                    ->schedule_date
                    ?->format('Y-m-d'),

                $itinerary->status,

                $itinerary->stops_count,

                $itinerary->visited_stops_count,

                $itinerary
                    ->created_at
                    ?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | System Summary
    |--------------------------------------------------------------------------
    */

    private function writeSystemSummary(
        $out,
        array $filters
    ): void {
        $userQuery = $this->dateRange(
            User::query(),
            $filters,
            'created_at'
        );

        $transactionQuery = $this->dateRange(
            Transaction::query(),
            $filters,
            'created_at'
        );

        $feedbackQuery = $this->dateRange(
            Feedback::query(),
            $filters,
            'created_at'
        );

        $visitQuery = $this->dateRange(
            VisitHistory::query(),
            $filters,
            'visited_at'
        );

        $itineraryQuery = $this->dateRange(
            Itinerary::query(),
            $filters,
            'created_at'
        );

        fputcsv($out, [
            'GIYA System Summary Report',
        ]);

        fputcsv($out, [
            'Generated At',
            now()->format('Y-m-d H:i:s'),
        ]);

        fputcsv($out, [
            'Period From',
            $filters['from'] ?: 'All time',
        ]);

        fputcsv($out, [
            'Period To',
            $filters['to'] ?: 'All time',
        ]);

        fputcsv($out, []);

        fputcsv($out, [
            'Metric',
            'Value',
        ]);

        fputcsv($out, [
            'Users',
            (clone $userQuery)->count(),
        ]);

        fputcsv($out, [
            'Itineraries Created',
            (clone $itineraryQuery)->count(),
        ]);

        fputcsv($out, [
            'Pilgrimage Visits',
            (clone $visitQuery)->count(),
        ]);

        fputcsv($out, [
            'Feedback Submitted',
            (clone $feedbackQuery)->count(),
        ]);

        fputcsv($out, [
            'Average Rating',
            round(
                (float) (clone $feedbackQuery)
                    ->avg('rating'),
                2
            ),
        ]);

        fputcsv($out, [
            'Transactions',
            (clone $transactionQuery)->count(),
        ]);

        fputcsv($out, [
            'Paid Transactions',
            (clone $transactionQuery)
                ->where('status', 'Paid')
                ->count(),
        ]);

        fputcsv($out, [
            'Revenue (PHP)',

            number_format(
                (float) (clone $transactionQuery)
                    ->where('status', 'Paid')
                    ->sum('amount'),
                2,
                '.',
                ''
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Date Filter
    |--------------------------------------------------------------------------
    */

    private function filters(
        Request $request
    ): array {
        $validated = $request->validate([
            'from' => [
                'nullable',
                'date',
            ],

            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',
            ],
        ]);

        return [
            'from' => $validated['from']
                ?? null,

            'to' => $validated['to']
                ?? null,
        ];
    }

    private function dateRange(
        Builder $query,
        array $filters,
        string $column
    ): Builder {
        return $query

            ->when(
                $filters['from'],

                fn (
                    Builder $q,
                    string $date
                ) => $q->whereDate(
                    $column,
                    '>=',
                    $date
                )
            )

            ->when(
                $filters['to'],

                fn (
                    Builder $q,
                    string $date
                ) => $q->whereDate(
                    $column,
                    '<=',
                    $date
                )
            );
    }
}