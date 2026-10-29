<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\Schedule;
use App\Services\GiyaAssistant;
use App\Support\Recurrence;
use Illuminate\Console\Command;

/**
 * Adds Cebu's festivals (database/data/cebu_festivals.php, from the Cebu
 * Provincial Tourism list) as yearly schedules.
 *
 *   php artisan giya:import-festivals --dry-run   show what would happen
 *   php artisan giya:import-festivals             add them
 *
 * Safe to run again: a festival already in GIYA (same name and town) is
 * skipped, so nothing is duplicated and an admin's later edits are kept.
 * Each festival is attached to a GIYA destination when exactly one church
 * in that town matches its patron; otherwise it is listed as a town
 * festival. No notifications are sent: the reminders a week before each
 * date reach followers in the usual way.
 */
class ImportFestivals extends Command
{
    protected $signature = 'giya:import-festivals {--dry-run : Show what would be added without saving}';

    protected $description = "Add Cebu's festivals (tourism.cebu.gov.ph) as yearly schedules";

    public function handle(): int
    {
        $festivals = require database_path('data/cebu_festivals.php');
        $dry = (bool) $this->option('dry-run');
        $rows = [];
        $added = $linked = $skipped = 0;

        foreach ($festivals as $f) {
            [$name, $town, $when, $type, $honors] = $f;

            $rule = match ($when[0]) {
                'date'   => ['type' => 'yearly', 'month' => $when[1], 'day' => $when[2]],
                'nth'    => ['type' => 'yearly', 'nth' => $when[1], 'weekday' => $when[2], 'month' => $when[3]],
                'easter' => ['type' => 'yearly', 'easter' => 0],
            };

            $church = isset($f['church']) ? $this->churchFor($town, $f['church']) : null;

            if (Schedule::where('event_name', $name)
                ->where(fn ($q) => $q->where('location', $town)->when($church, fn ($w) => $w->orWhere('church_id', $church->id)))
                ->exists()) {
                $skipped++;
                $rows[] = [$name, $town, Recurrence::label($rule), 'already in GIYA'];
                continue;
            }

            $notes = trim(($honors ? "Honors {$honors}. " : '').($f['note'] ?? '')
                ."\nSource: Cebu Provincial Tourism, tourism.cebu.gov.ph/festivals");

            if (! $dry) {
                Schedule::create([
                    'church_id'       => $church?->id,
                    'event_name'      => $name,
                    'event_type'      => $type,
                    'location'        => $town,
                    'is_whole_day'    => true,
                    'is_recurring'    => true,
                    'recurrence_rule' => $rule,
                    'recurrence'      => Recurrence::label($rule),
                    'schedule_date'   => Recurrence::next($rule)->toDateString(),
                    'status'          => 'Published',
                    'notes'           => $notes,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            $added++;
            $linked += $church ? 1 : 0;
            $rows[] = [$name, $town, Recurrence::label($rule), $church ? 'at '.$church->name : 'town festival'];
        }

        $this->table(['Festival', 'Town', 'When', 'Placed'], $rows);

        $verb = $dry ? 'Would add' : 'Added';
        $this->info("{$verb} {$added} festivals ({$linked} at a GIYA destination, ".($added - $linked)." as town festivals); {$skipped} already in GIYA.");

        if (! $dry && $added) {
            GiyaAssistant::forget();
        }

        return self::SUCCESS;
    }

    /** The one active destination in this town whose name has $word in it, if exactly one. */
    private function churchFor(string $town, string $word): ?Church
    {
        $place = trim(preg_replace('/^City of |\s+City$/i', '', $town));

        $matches = Church::where('is_active', true)
            ->where('name', 'like', '%'.$word.'%')
            ->where(fn ($q) => $q->where('location', 'like', '%'.$place.'%')
                ->orWhere('municipality', 'like', '%'.$place.'%')
                ->orWhere('address', 'like', '%'.$place.'%'))
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
