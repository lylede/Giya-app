<?php

namespace App\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One admin report: the table shown on /admin/reports and the CSV download.
 *
 * Before this, ReportController held all six reports twice over - once as a
 * screen table and once as a CSV writer - each with its own copy of the
 * query. Now each report is one class (see RecordReport for the five that
 * list records), so a change to what a report includes cannot reach the
 * screen and miss the download, or the other way round.
 *
 * The screen and the CSV deliberately format differently: the screen is for
 * reading ("Oct 28, 2026 03:15 PM", "₱899.00", "4★"), the CSV is for a
 * spreadsheet ("2026-10-28 15:15:00", "899.00", 4) and numbers its rows.
 */
abstract class Report
{
    /** @param  array{from: ?string, to: ?string}  $filters */
    public function __construct(protected array $filters)
    {
    }

    abstract public function title(): string;

    /** Column headings for the on-screen table. */
    abstract public function columns(): array;

    /** Rows for the on-screen table. */
    abstract public function rows(): Collection;

    /** The shape resources/views/admin/reports.blade.php expects. */
    public function table(): array
    {
        return [
            'title'   => $this->title(),
            'columns' => $this->columns(),
            'rows'    => $this->rows(),
        ];
    }

    /** @param  resource  $out */
    abstract public function writeCsv($out): void;

    /** Limit a query to the chosen from/to window on the given column. */
    public static function range(Builder $query, array $filters, string $column): Builder
    {
        return $query
            ->when($filters['from'] ?? null, fn (Builder $q, string $date) => $q->where($column, '>=', Carbon::parse($date)))
            ->when($filters['to'] ?? null, fn (Builder $q, string $date) => $q->where($column, '<=', Carbon::parse($date)));
    }

    protected function inRange(Builder $query, string $column): Builder
    {
        return static::range($query, $this->filters, $column);
    }

    /** "Oct 28, 2026 03:15 PM" - the on-screen timestamp format. */
    protected static function screenTime(?\DateTimeInterface $at): ?string
    {
        return $at?->format('M d, Y h:i A');
    }

    /** "2026-10-28 15:15:00" - the CSV timestamp format. */
    protected static function csvTime(?\DateTimeInterface $at): ?string
    {
        return $at?->format('Y-m-d H:i:s');
    }

    protected static function peso(float|string|null $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    /** 899.00 with no thousands separator, so a spreadsheet reads a number. */
    protected static function plainAmount(float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
