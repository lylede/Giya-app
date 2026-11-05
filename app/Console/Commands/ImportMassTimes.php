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
 * Source, Checked by. Optional day columns (Monday ... Friday) hold a day
 * whose times differ from the rest of the week. A time cell holds one or more times: "6:00 AM, 8:00 AM"
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

    /** Day columns the sheet may have; 0 = Sunday. "Weekdays" fills Monday to Friday. */
    private const DAYS = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                          'thursday' => 4, 'friday' => 5, 'saturday' => 6];

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
            $times = $this->byTime($row, $col);
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

            $count = array_sum(array_map(fn ($d) => count($d), $times));
            if (! $dry) {
                $church->schedules()->where('event_type', 'Mass')->where('is_estimate', true)->delete();
                $this->option('replace') && $existing->delete();
                foreach ($times as $t => $days) {
                    $rule = ['type' => 'weekly', 'days' => $days];
                    Schedule::create([
                        'church_id' => $church->id, 'event_name' => self::title($days), 'event_type' => 'Mass',
                        'start_time' => $t, 'is_recurring' => true,
                        'recurrence' => self::label($days), 'recurrence_rule' => $rule,
                        'notes' => 'Source: '.$col($row, 'source'), 'created_at' => now(),
                    ]);
                }
            }
            $added += $count;
            $report[] = [$name, $col($row, 'town'), "$count Mass times"];
        }

        $report && $this->table(['Church', 'Town', 'Result'], $report);
        $this->info(($dry ? 'Would add ' : 'Added ')."$added Mass times.");

        return self::SUCCESS;
    }

    /**
     * The row's Mass times as time => weekdays, so a time said on several
     * days is one schedule: ['06:00' => [1, 2, 3, 4, 5], '09:00' => [0]].
     * A day's own column wins over "Weekdays" for that day.
     */
    private function byTime(array $row, \Closure $col): array
    {
        $perDay = [];
        foreach ([1, 2, 3, 4, 5] as $d) {
            $perDay[$d] = $this->times($col($row, 'weekdays'));
        }
        foreach (self::DAYS as $name => $d) {
            $own = $this->times($col($row, $name));
            if ($own || ! isset($perDay[$d])) {
                $perDay[$d] = $own;
            }
        }

        $byTime = [];
        foreach ($perDay as $d => $list) {
            foreach ($list as $t) {
                $byTime[$t][] = $d;
            }
        }
        ksort($byTime);
        foreach ($byTime as &$days) {
            sort($days);
        }

        return $byTime;
    }

    private static function title(array $days): string
    {
        return match (true) {
            $days === [0]                           => 'Sunday Mass',
            $days === [6]                           => 'Saturday Mass',
            ! array_diff($days, [1, 2, 3, 4, 5])    => 'Weekday Mass',
            default                                 => 'Mass',
        };
    }

    private static function label(array $days): string
    {
        return match ($days) {
            [1, 2, 3, 4, 5]          => 'Weekdays (Monday to Friday)',
            [1, 2, 3, 4, 5, 6]       => 'Monday to Saturday',
            [0, 1, 2, 3, 4, 5, 6]    => 'Daily',
            default                  => Recurrence::label(['type' => 'weekly', 'days' => $days]),
        };
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
