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
use Illuminate\Support\Carbon;
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

        $selectedReport = $request->query('report_type') ?: null;

        if ($selectedReport !== null) {
            abort_unless(
                in_array($selectedReport, self::REPORT_TYPES, true),
                404
            );
        }

        // Queries used for dashboard statistics
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

            'selectedReport' => $selectedReport,

            // Only load report table after a report type is selected.
            'reportTable' => $selectedReport
                ? $this->reportTable(
                    $selectedReport,
                    $filters
                )
                : null,

            // Dashboard summary
            'summary' => [

                'users' =>
                    (clone $userQuery)->count(),

                'transactions' =>
                    $totalTransactions,

                'revenue' =>
                    (float) (clone $paidTransactions)
                        ->sum('amount'),

                'feedback' =>
                    $feedbackCount,

                'average_rating' =>
                    $feedbackCount
                        ? round(
                            (float) (clone $feedbackQuery)
                                ->avg('rating'),
                            2
                        )
                        : 0,

                'visits' =>
                    (clone $visitQuery)->count(),

                'itineraries' =>
                    (clone $itineraryQuery)->count(),
            ],

            // Recent transactions
            'recentTransactions' =>
                (clone $transactionQuery)
                    ->with([
                        'user',
                        'subscriptionPlan',
                    ])
                    ->orderByDesc('created_at')
                    ->take(5)
                    ->get(),

            // Recent feedback
            'recentFeedback' =>
                (clone $feedbackQuery)
                    ->with([
                        'user',
                        'church',
                    ])
                    ->orderByDesc('created_at')
                    ->take(5)
                    ->get(),

            // Most visited destinations
            'topDestinations' =>
                (clone $visitQuery)
                    ->join(
                        'churches',
                        'churches.id',
                        '=',
                        'visit_history.church_id'
                    )
                    ->selectRaw(
                        '
                        churches.id,
                        churches.name,
                        COUNT(*) as total_visits
                        '
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


    /*
    |--------------------------------------------------------------------------
    | Generate Report Table
    |--------------------------------------------------------------------------
    */

    private function reportTable(
        string $report,
        array $filters
    ): array {

        return match ($report) {

            /*
            |--------------------------------------------------------------------------
            | USER REPORT
            |--------------------------------------------------------------------------
            */

            'users' => [

                'title' => 'User Report',

                'columns' => [
                    'Name',
                    'Email',
                    'Role',
                    'Status',
                    'Joined',
                    'Saved Destinations',
                    'Itineraries',
                ],

                'rows' => $this->dateRange(
                    User::query(),
                    $filters,
                    'created_at'
                )
                    ->withCount([
                        'favorites',
                        'itineraries',
                    ])
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(
                        fn ($user) => [

                            $user->name,

                            $user->email,

                            ucfirst(
                                $user->role
                            ),

                            $user->status,

                            $user
                                ->created_at
                                ?->format(
                                    'M d, Y h:i A'
                                ),

                            $user->favorites_count,

                            $user->itineraries_count,
                        ]
                    ),
            ],


            /*
            |--------------------------------------------------------------------------
            | TRANSACTION REPORT
            |--------------------------------------------------------------------------
            */

            'transactions' => [

                'title' =>
                    'Transaction Report',

                'columns' => [
                    'Transaction ID',
                    'User',
                    'Plan',
                    'Amount',
                    'Method',
                    'Status',
                    'Reference',
                    'Created At',
                ],

                'rows' => $this->dateRange(
                    Transaction::query(),
                    $filters,
                    'created_at'
                )
                    ->with([
                        'user',
                        'subscriptionPlan',
                    ])
                    ->orderByDesc(
                        'created_at'
                    )
                    ->get()
                    ->map(
                        fn ($transaction) => [

                            $transaction
                                ->transaction_id,

                            $transaction
                                ->user
                                ?->name
                                ?? 'Unknown user',

                            $transaction
                                ->subscriptionPlan
                                ?->name
                                ?? 'Unknown plan',

                            '₱' .
                            number_format(
                                (float)
                                $transaction->amount,
                                2
                            ),

                            $transaction->method,

                            $transaction->status,

                            $transaction
                                ->reference_no,

                            $transaction
                                ->created_at
                                ?->format(
                                    'M d, Y h:i A'
                                ),
                        ]
                    ),
            ],


            /*
            |--------------------------------------------------------------------------
            | FEEDBACK REPORT
            |--------------------------------------------------------------------------
            */

            'feedback' => [

                'title' =>
                    'Feedback Report',

                'columns' => [
                    'User',
                    'Destination',
                    'Rating',
                    'Status',
                    'Comment',
                    'Submitted At',
                ],

                'rows' => $this->dateRange(
                    Feedback::query(),
                    $filters,
                    'created_at'
                )
                    ->with([
                        'user',
                        'church',
                    ])
                    ->orderByDesc(
                        'created_at'
                    )
                    ->get()
                    ->map(
                        fn ($feedback) => [

                            $feedback
                                ->user
                                ?->name
                                ?? 'Unknown user',

                            $feedback
                                ->church
                                ?->name
                                ?? 'Unknown destination',

                            $feedback->rating
                                . '★',

                            $feedback->status,

                            $feedback->comment,

                            $feedback
                                ->created_at
                                ?->format(
                                    'M d, Y h:i A'
                                ),
                        ]
                    ),
            ],


            /*
            |--------------------------------------------------------------------------
            | PILGRIMAGE VISIT REPORT
            |--------------------------------------------------------------------------
            */

            'visits' => [

                'title' =>
                    'Pilgrimage Visit Report',

                'columns' => [
                    'User',
                    'Destination',
                    'Itinerary',
                    'Completion Status',
                    'Visited At',
                    'Notes',
                ],

                'rows' => $this->dateRange(
                    VisitHistory::query(),
                    $filters,
                    'visited_at'
                )
                    ->with([
                        'user',
                        'church',
                        'itinerary',
                    ])
                    ->orderByDesc(
                        'visited_at'
                    )
                    ->get()
                    ->map(
                        fn ($visit) => [

                            $visit
                                ->user
                                ?->name
                                ?? 'Unknown user',

                            $visit
                                ->church
                                ?->name
                                ?? 'Unknown destination',

                            $visit
                                ->itinerary
                                ?->name
                                ?? 'Independent visit',

                            $visit
                                ->completion_status,

                            $visit
                                ->visited_at
                                ?->format(
                                    'M d, Y h:i A'
                                ),

                            $visit->notes,
                        ]
                    ),
            ],


            /*
            |--------------------------------------------------------------------------
            | ITINERARY REPORT
            |--------------------------------------------------------------------------
            */

            'itineraries' => [

                'title' =>
                    'Itinerary Report',

                'columns' => [
                    'User',
                    'Itinerary Name',
                    'Type',
                    'Scheduled Date',
                    'Status',
                    'Total Stops',
                    'Visited Stops',
                    'Created At',
                ],

                'rows' => $this->dateRange(
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
                    ->orderByDesc(
                        'created_at'
                    )
                    ->get()
                    ->map(
                        fn ($itinerary) => [

                            $itinerary
                                ->user
                                ?->name
                                ?? 'Unknown user',

                            $itinerary->name,

                            $itinerary
                                ->itineraryType
                                ?->name
                                ?? 'Custom',

                            $itinerary
                                ->schedule_date
                                ?->format(
                                    'M d, Y'
                                ),

                            $itinerary->status,

                            $itinerary
                                ->stops_count,

                            $itinerary
                                ->visited_stops_count,

                            $itinerary
                                ->created_at
                                ?->format(
                                    'M d, Y h:i A'
                                ),
                        ]
                    ),
            ],


            /*
            |--------------------------------------------------------------------------
            | SYSTEM SUMMARY
            |--------------------------------------------------------------------------
            */

            'system-summary' =>
                $this->systemSummaryTable(
                    $filters
                ),
        };
    }


    /*
    |--------------------------------------------------------------------------
    | System Summary Table
    |--------------------------------------------------------------------------
    */

    private function systemSummaryTable(
        array $filters
    ): array {

        $users = $this->dateRange(
            User::query(),
            $filters,
            'created_at'
        );

        $transactions = $this->dateRange(
            Transaction::query(),
            $filters,
            'created_at'
        );

        $feedback = $this->dateRange(
            Feedback::query(),
            $filters,
            'created_at'
        );

        $visits = $this->dateRange(
            VisitHistory::query(),
            $filters,
            'visited_at'
        );

        $itineraries = $this->dateRange(
            Itinerary::query(),
            $filters,
            'created_at'
        );

        return [

            'title' =>
                'System Summary Report',

            'columns' => [
                'Metric',
                'Value',
            ],

            'rows' => collect([

                [
                    'Users',
                    (clone $users)->count(),
                ],

                [
                    'Itineraries Created',
                    (clone $itineraries)
                        ->count(),
                ],

                [
                    'Pilgrimage Visits',
                    (clone $visits)
                        ->count(),
                ],

                [
                    'Feedback Submitted',
                    (clone $feedback)
                        ->count(),
                ],

                [
                    'Average Rating',

                    number_format(
                        (float)
                        (clone $feedback)
                            ->avg('rating'),
                        2
                    ) . '★',
                ],

                [
                    'Transactions',
                    (clone $transactions)
                        ->count(),
                ],

                [
                    'Paid Transactions',

                    (clone $transactions)
                        ->where(
                            'status',
                            'Paid'
                        )
                        ->count(),
                ],

                [
                    'Revenue',

                    '₱' .
                    number_format(
                        (float)
                        (clone $transactions)
                            ->where(
                                'status',
                                'Paid'
                            )
                            ->sum('amount'),
                        2
                    ),
                ],
            ]),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Download Report
    |--------------------------------------------------------------------------
    */

    public function download(
        Request $request,
        string $report
    ): StreamedResponse {

        abort_unless(
            in_array(
                $report,
                self::REPORT_TYPES,
                true
            ),
            404
        );

        $filters =
            $this->filters($request);

        $filename =
            'giya-' .
            $report .
            '-report-' .
            now()->format('Y-m-d') .
            '.csv';

        return response()->streamDownload(

            function () use (
                $report,
                $filters
            ) {

                $out = fopen(
                    'php://output',
                    'w'
                );

                /*
                 * UTF-8 BOM
                 * Helps Microsoft Excel
                 * display special characters.
                 */
                fwrite(
                    $out,
                    "\xEF\xBB\xBF"
                );

                match ($report) {

                    'users' =>
                        $this->writeUsers(
                            $out,
                            $filters
                        ),

                    'transactions' =>
                        $this->writeTransactions(
                            $out,
                            $filters
                        ),

                    'feedback' =>
                        $this->writeFeedback(
                            $out,
                            $filters
                        ),

                    'visits' =>
                        $this->writeVisits(
                            $out,
                            $filters
                        ),

                    'itineraries' =>
                        $this->writeItineraries(
                            $out,
                            $filters
                        ),

                    'system-summary' =>
                        $this->writeSystemSummary(
                            $out,
                            $filters
                        ),
                };

                fclose($out);
            },

            $filename,

            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | USER CSV
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
            ->orderByDesc(
                'created_at'
            )
            ->get();

        foreach ($rows as $i => $user) {

            fputcsv($out, [

                $i + 1,

                $user->name,

                $user->email,

                ucfirst($user->role),

                $user->status,

                $user
                    ->created_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

                $user->favorites_count,

                $user->itineraries_count,
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TRANSACTION CSV
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
            ->orderByDesc(
                'created_at'
            )
            ->get();

        foreach ($rows as $transaction) {

            fputcsv($out, [

                $transaction
                    ->transaction_id,

                $transaction
                    ->user
                    ?->name
                    ?? 'Unknown user',

                $transaction
                    ->user
                    ?->email
                    ?? '',

                $transaction
                    ->subscriptionPlan
                    ?->name
                    ?? 'Unknown plan',

                number_format(
                    (float)
                    $transaction->amount,
                    2,
                    '.',
                    ''
                ),

                $transaction->currency,

                $transaction->method,

                $transaction->status,

                $transaction
                    ->reference_no,

                $transaction
                    ->processed_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

                $transaction
                    ->created_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FEEDBACK CSV
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
            ->orderByDesc(
                'created_at'
            )
            ->get();

        foreach ($rows as $i => $feedback) {

            fputcsv($out, [

                $i + 1,

                $feedback
                    ->user
                    ?->name
                    ?? 'Unknown user',

                $feedback
                    ->church
                    ?->name
                    ?? 'Unknown destination',

                $feedback->rating,

                $feedback->status,

                $feedback->comment,

                $feedback
                    ->created_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | VISIT CSV
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
            ->orderByDesc(
                'visited_at'
            )
            ->get();

        foreach ($rows as $i => $visit) {

            fputcsv($out, [

                $i + 1,

                $visit
                    ->user
                    ?->name
                    ?? 'Unknown user',

                $visit
                    ->church
                    ?->name
                    ?? 'Unknown destination',

                $visit
                    ->itinerary
                    ?->name
                    ?? 'Independent visit',

                $visit
                    ->completion_status,

                $visit
                    ->visited_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

                $visit->notes,
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ITINERARY CSV
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
            ->orderByDesc(
                'created_at'
            )
            ->get();

        foreach ($rows as $i => $itinerary) {

            fputcsv($out, [

                $i + 1,

                $itinerary
                    ->user
                    ?->name
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

                $itinerary
                    ->stops_count,

                $itinerary
                    ->visited_stops_count,

                $itinerary
                    ->created_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SYSTEM SUMMARY CSV
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

        $transactionQuery =
            $this->dateRange(
                Transaction::query(),
                $filters,
                'created_at'
            );

        $feedbackQuery =
            $this->dateRange(
                Feedback::query(),
                $filters,
                'created_at'
            );

        $visitQuery =
            $this->dateRange(
                VisitHistory::query(),
                $filters,
                'visited_at'
            );

        $itineraryQuery =
            $this->dateRange(
                Itinerary::query(),
                $filters,
                'created_at'
            );

        fputcsv($out, [
            'GIYA System Summary Report'
        ]);

        fputcsv($out, [
            'Generated At',
            now()->format(
                'Y-m-d H:i:s'
            ),
        ]);

        fputcsv($out, [
            'Period From',
            $filters['from']
                ?: 'All time',
        ]);

        fputcsv($out, [
            'Period To',
            $filters['to']
                ?: 'All time',
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
            (clone $itineraryQuery)
                ->count(),
        ]);

        fputcsv($out, [
            'Pilgrimage Visits',
            (clone $visitQuery)
                ->count(),
        ]);

        fputcsv($out, [
            'Feedback Submitted',
            (clone $feedbackQuery)
                ->count(),
        ]);

        fputcsv($out, [
            'Average Rating',
            round(
                (float)
                (clone $feedbackQuery)
                    ->avg('rating'),
                2
            ),
        ]);

        fputcsv($out, [
            'Transactions',
            (clone $transactionQuery)
                ->count(),
        ]);

        fputcsv($out, [
            'Paid Transactions',

            (clone $transactionQuery)
                ->where(
                    'status',
                    'Paid'
                )
                ->count(),
        ]);

        fputcsv($out, [
            'Revenue (PHP)',

            number_format(

                (float)
                (clone $transactionQuery)
                    ->where(
                        'status',
                        'Paid'
                    )
                    ->sum('amount'),

                2,
                '.',
                ''
            ),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DATE/TIME FILTER
    |--------------------------------------------------------------------------
    */

    private function filters(
        Request $request
    ): array {

        $validated =
            $request->validate([

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

            'from' =>
                $validated['from']
                ?? null,

            'to' =>
                $validated['to']
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
                ) =>

                    $q->where(
                        $column,
                        '>=',
                        Carbon::parse($date)
                    )
            )

            ->when(
                $filters['to'],

                fn (
                    Builder $q,
                    string $date
                ) =>

                    $q->where(
                        $column,
                        '<=',
                        Carbon::parse($date)
                    )
            );
    }
}