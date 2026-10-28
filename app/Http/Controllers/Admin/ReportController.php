<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Itinerary;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VisitHistory;
use App\Reports\FeedbackReport;
use App\Reports\ItinerariesReport;
use App\Reports\Report;
use App\Reports\SystemSummaryReport;
use App\Reports\TransactionsReport;
use App\Reports\UsersReport;
use App\Reports\VisitsReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /admin/reports - the overview, a chosen report as a table, and its CSV.
 *
 * Each report lives in its own class under app/Reports; this controller only
 * picks one and hands it the date filters. To add a report, write the class
 * and add one line to REPORTS.
 */
class ReportController extends Controller
{
    /** @var array<string, class-string<Report>> */
    private const REPORTS = [
        'users'          => UsersReport::class,
        'transactions'   => TransactionsReport::class,
        'feedback'       => FeedbackReport::class,
        'visits'         => VisitsReport::class,
        'itineraries'    => ItinerariesReport::class,
        'system-summary' => SystemSummaryReport::class,
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $selectedReport = $request->query('report_type') ?: null;

        if ($selectedReport !== null) {
            abort_unless(array_key_exists($selectedReport, self::REPORTS), 404);
        }

        $users        = Report::range(User::query(), $filters, 'created_at');
        $transactions = Report::range(Transaction::query(), $filters, 'created_at');
        $feedback     = Report::range(Feedback::query(), $filters, 'created_at');
        $visits       = Report::range(VisitHistory::query(), $filters, 'visited_at');
        $itineraries  = Report::range(Itinerary::query(), $filters, 'created_at');

        $feedbackCount = (clone $feedback)->count();

        return view('admin.reports', [
            'filters'        => $filters,
            'selectedReport' => $selectedReport,

            // Only built once a report type is chosen.
            'reportTable' => $selectedReport
                ? $this->report($selectedReport, $filters)->table()
                : null,

            'summary' => [
                'users'          => (clone $users)->count(),
                'transactions'   => (clone $transactions)->count(),
                'revenue'        => (float) (clone $transactions)->where('status', 'Paid')->sum('amount'),
                'feedback'       => $feedbackCount,
                'average_rating' => $feedbackCount ? round((float) (clone $feedback)->avg('rating'), 2) : 0,
                'visits'         => (clone $visits)->count(),
                'itineraries'    => (clone $itineraries)->count(),
            ],

            'recentTransactions' => (clone $transactions)
                ->with(['user', 'subscriptionPlan'])
                ->orderByDesc('created_at')
                ->take(5)
                ->get(),

            'recentFeedback' => (clone $feedback)
                ->with(['user', 'church'])
                ->orderByDesc('created_at')
                ->take(5)
                ->get(),

            'topDestinations' => (clone $visits)
                ->join('churches', 'churches.id', '=', 'visit_history.church_id')
                ->selectRaw('churches.id, churches.name, COUNT(*) as total_visits')
                ->groupBy('churches.id', 'churches.name')
                ->orderByDesc('total_visits')
                ->take(5)
                ->get(),
        ]);
    }

    public function download(Request $request, string $report): StreamedResponse
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        $instance = $this->report($report, $this->filters($request));
        $filename = 'giya-'.$report.'-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($instance) {
            $out = fopen('php://output', 'w');

            // UTF-8 byte-order mark, so Excel shows ₱ and ñ correctly.
            fwrite($out, "\xEF\xBB\xBF");

            $instance->writeCsv($out);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function report(string $key, array $filters): Report
    {
        $class = self::REPORTS[$key];

        return new $class($filters);
    }

    /** @return array{from: ?string, to: ?string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'from' => $validated['from'] ?? null,
            'to'   => $validated['to'] ?? null,
        ];
    }
}
