<?php

namespace App\Reports;

use App\Models\Feedback;
use App\Models\Itinerary;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VisitHistory;
use App\Support\Csv;
use Illuminate\Support\Collection;

/**
 * Headline numbers for the chosen period, as a Metric / Value table.
 *
 * Each figure is counted once in metrics() and then labelled two ways: the
 * screen shows "3.67★" and "₱899.00", the CSV the bare 3.67 and 899.00.
 */
class SystemSummaryReport extends Report
{
    public function title(): string
    {
        return 'System Summary Report';
    }

    public function columns(): array
    {
        return ['Metric', 'Value'];
    }

    /** @return array<string, int|float> */
    protected function metrics(): array
    {
        $users        = $this->inRange(User::query(), 'created_at');
        $transactions = $this->inRange(Transaction::query(), 'created_at');
        $feedback     = $this->inRange(Feedback::query(), 'created_at');
        $visits       = $this->inRange(VisitHistory::query(), 'visited_at');
        $itineraries  = $this->inRange(Itinerary::query(), 'created_at');

        $paid = (clone $transactions)->where('status', 'Paid');

        return [
            'users'        => (clone $users)->count(),
            'itineraries'  => (clone $itineraries)->count(),
            'visits'       => (clone $visits)->count(),
            'feedback'     => (clone $feedback)->count(),
            'rating'       => (float) (clone $feedback)->avg('rating'),
            'transactions' => (clone $transactions)->count(),
            'paid'         => (clone $paid)->count(),
            'revenue'      => (float) (clone $paid)->sum('amount'),
        ];
    }

    public function rows(): Collection
    {
        $m = $this->metrics();

        return collect([
            ['Users', $m['users']],
            ['Itineraries Created', $m['itineraries']],
            ['Pilgrimage Visits', $m['visits']],
            ['Feedback Submitted', $m['feedback']],
            ['Average Rating', number_format($m['rating'], 2).'★'],
            ['Transactions', $m['transactions']],
            ['Paid Transactions', $m['paid']],
            ['Revenue', self::peso($m['revenue'])],
        ]);
    }

    /** @param  resource  $out */
    public function writeCsv($out): void
    {
        $m = $this->metrics();

        Csv::put($out, ['GIYA System Summary Report']);
        Csv::put($out, ['Generated At', now()->format('Y-m-d H:i:s')]);
        Csv::put($out, ['Period From', ($this->filters['from'] ?? null) ?: 'All time']);
        Csv::put($out, ['Period To', ($this->filters['to'] ?? null) ?: 'All time']);
        Csv::put($out, []);
        Csv::put($out, ['Metric', 'Value']);
        Csv::put($out, ['Users', $m['users']]);
        Csv::put($out, ['Itineraries Created', $m['itineraries']]);
        Csv::put($out, ['Pilgrimage Visits', $m['visits']]);
        Csv::put($out, ['Feedback Submitted', $m['feedback']]);
        Csv::put($out, ['Average Rating', round($m['rating'], 2)]);
        Csv::put($out, ['Transactions', $m['transactions']]);
        Csv::put($out, ['Paid Transactions', $m['paid']]);
        Csv::put($out, ['Revenue (PHP)', self::plainAmount($m['revenue'])]);
    }
}
