<?php

namespace App\Reports;

use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;

/**
 * A report that lists records - one row per user, transaction, visit...
 *
 * query() is written once and read by both rows() (the screen) and
 * writeCsv() (the download).
 */
abstract class RecordReport extends Report
{
    /** The records both formats are built from. */
    abstract protected function query(): Builder;

    /** Column headings for the CSV. */
    abstract protected function csvHeader(): array;

    /** One CSV row for one record; $i is its 0-based position. */
    abstract protected function csvRow(mixed $record, int $i): array;

    /** @param  resource  $out */
    public function writeCsv($out): void
    {
        Csv::put($out, $this->csvHeader());

        foreach ($this->query()->get() as $i => $record) {
            Csv::put($out, $this->csvRow($record, $i));
        }
    }
}
