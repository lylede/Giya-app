<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\Schedule;
use App\Support\Recurrence;
use Illuminate\Console\Command;

/**
 * Adds the Mass times the team collected (the "GIYA Mass times" sheet,
 * downloaded as CSV) to each destination.
 *
 *   php artisan giya:import-mass-times mass-times.csv --dry-run
 *   php artisan giya:import-mass-times mass-times.csv
 *   php artisan giya:import-mass-times mass-times.csv --replace
 *
 * Columns (first row = headings): Church, Town, Sunday, Saturday, Weekdays,
 * Source, Checked by. A time cell holds one or more times: "6:00 AM, 8:00 AM"
 * or "06:00, 17:30". Rows with no times are skipped.
 *
 * Only rows with a Source are imported, so no unchecked time reaches a
 * pilgrim. Usual times added by giya:add-typical-mass-times are always
 * replaced. A church with confirmed Mass times is left alone unless
 * --replace is given (then its old Mass times are swapped for the sheet's).
 */
class ImportMassTimes extends Command
{
    protected $signature = 'giya:import-mass-times {file} {--dry-run} {--replace}';

    protected $description = 'Import collected Mass times from the Mass times sheet (CSV)';

    private const COLUMNS = [
        'sunday'   => ['Sunday Mass',  [0]],
        'saturday' => ['Saturday Mass', [6]],
        'weekdays' => ['Weekday Mass', [1, 2, 3, 4, 5]],
    ];

    public function handle(): int
    {
        $fh = @fopen($this->argument('file'), 'r');
        if (! $fh) {
            $this->error('Could not open '.$this->argument('file'));

            return self::FAILURE;
        }

        $head = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($fh) ?: []);
        $col = fn (array $row, string $name) => trim((string) ($row[array_search($name, $head, true)] ?? ''));
        if (! in_array('church', $head, true)) {
            $this->error('The first row must be the headings: Church, Town, Sunday, Saturday, Weekdays, Source, Checked by.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $added = 0;
        $report = [];

        while (($row = fgetcsv($fh)) !== false) {
            $name = $col($row, 'church');
            $times = array_filter(array_map(fn ($k) => $this->times($col($row, $k)), array_combine(array_keys(self::COLUMNS), array_keys(self::COLUMNS))));
            if ($name === '' || ! $times) {
                continue;
            }

            if ($col($row, 'source') === '') {
                $report[] = [$name, $col($row, 'town'), 'skipped: no Source'];
                continue;
            }

            $church = $this->church($name, $col($row, 'town'));
            if (! $church) {
                $report[] = [$name, $col($row, 'town'), 'not found in GIYA'];
                continue;
            }

            // Usual times GIYA filled in are always swapped for real ones.
            $existing = $church->schedules()->where('event_type', 'Mass')->where('is_estimate', false);
            if ($existing->exists() && ! $this->option('replace')) {
                $report[] = [$name, $col($row, 'town'), 'already has Mass times (use --replace)'];
                continue;
            }

            $count = array_sum(array_map('count', $times));
            if (! $dry) {
                $church->schedules()->where('event_type', 'Mass')->where('is_estimate', true)->delete();
                $this->option('replace') && $existing->delete();
                foreach ($times as $key => $list) {
                    [$title, $days] = self::COLUMNS[$key];
                    $rule = ['type' => 'weekly', 'days' => $days];
                    foreach ($list as $t) {
                        Schedule::create([
                            'church_id' => $church->id, 'event_name' => $title, 'event_type' => 'Mass',
                            'start_time' => $t, 'is_recurring' => true,
                            'recurrence' => $days === [1, 2, 3, 4, 5] ? 'Weekdays (Monday to Friday)' : Recurrence::label($rule), 'recurrence_rule' => $rule,
                            'notes' => 'Source: '.$col($row, 'source'), 'created_at' => now(),
                        ]);
                    }
                }
            }
            $added += $count;
            $report[] = [$name, $col($row, 'town'), "$count Mass times"];
        }

        $report && $this->table(['Church', 'Town', 'Result'], $report);
        $this->info(($dry ? 'Would add ' : 'Added ')."$added Mass times.");

        return self::SUCCESS;
    }

    /** "6:00 AM, 8 am / 17:30" -> ['06:00', '08:00', '17:30'] */
    private function times(string $cell): array
    {
        $out = [];
        foreach (preg_split('/[,;\/|]+|\s{2,}/', $cell) as $part) {
            $part = trim($part);
            if ($part === '' || ($ts = strtotime($part)) === false) {
                continue;
            }
            $out[] = date('H:i', $ts);
        }

        return array_values(array_unique($out));
    }

    private function church(string $name, string $town): ?Church
    {
        $town = trim(preg_replace('/\b(city of|city)\b/i', '', $town));
        $matches = Church::whereRaw('lower(name) = ?', [mb_strtolower($name)])->get();

        return $matches->count() === 1 ? $matches->first() : $matches->first(fn ($c) => $town !== ''
            && str_contains(mb_strtolower($c->municipality.' '.$c->location.' '.$c->address), mb_strtolower($town)));
    }
}
