<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\Schedule;
use App\Services\GiyaAssistant;
use App\Support\Recurrence;
use Illuminate\Console\Command;

/**
 * Gives every church without Mass times the usual Cebu parish pattern,
 * clearly marked as not yet confirmed, so the Schedule tab and Next Mass
 * are never empty during testing.
 *
 *   php artisan giya:add-typical-mass-times --dry-run
 *   php artisan giya:add-typical-mass-times
 *
 * Each time is saved with is_estimate = true. The church page shows it as
 * "usual time, please confirm with the parish", Giya AI says it is not
 * confirmed, and giya:import-mass-times replaces it with the real times.
 * A church that already has any Mass time, and heritage sites that are not
 * parishes (Magellan's Cross), are left alone.
 */
class AddTypicalMassTimes extends Command
{
    protected $signature = 'giya:add-typical-mass-times {--dry-run}';

    protected $description = 'Add the usual Cebu parish Mass times (marked unconfirmed) to churches without any';

    /** Sunday 6, 8 and 5 PM; Saturday anticipated 5:30 PM; weekdays 6 AM. */
    public const PATTERN = [
        ['Sunday Mass',   [0],             ['06:00', '08:00', '17:00']],
        ['Saturday Mass', [6],             ['17:30']],
        ['Weekday Mass',  [1, 2, 3, 4, 5], ['06:00']],
    ];

    public const NOTE = 'Usual Cebu parish time, not yet confirmed by this parish.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $churches = Church::with('churchCategory')
            ->whereDoesntHave('schedules', fn ($q) => $q->where('event_type', 'Mass'))
            ->orderBy('name')
            ->get()
            ->reject(fn (Church $c) => strcasecmp((string) $c->category, 'Heritage') === 0);

        foreach ($churches as $church) {
            if ($dry) {
                continue;
            }
            foreach (self::PATTERN as [$title, $days, $times]) {
                $rule = ['type' => 'weekly', 'days' => $days];
                foreach ($times as $t) {
                    Schedule::create([
                        'church_id' => $church->id, 'event_name' => $title, 'event_type' => 'Mass',
                        'start_time' => $t, 'is_recurring' => true, 'is_estimate' => true,
                        'recurrence' => $days === [1, 2, 3, 4, 5] ? 'Weekdays (Monday to Friday)' : Recurrence::label($rule), 'recurrence_rule' => $rule,
                        'notes' => self::NOTE, 'created_at' => now(),
                    ]);
                }
            }
        }

        $this->info(($dry ? 'Would add' : 'Added').' usual Mass times to '.$churches->count().' churches (5 each, marked unconfirmed).');
        $dry || GiyaAssistant::forget();

        return self::SUCCESS;
    }
}
