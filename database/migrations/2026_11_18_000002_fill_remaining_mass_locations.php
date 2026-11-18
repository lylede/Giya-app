<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A second pass after 2026_11_18_000001, for Mass schedules that still show
 * no place: a Location of only spaces or a "-" counts as empty here too.
 * Each gets its church's address (location, else address, else town).
 * Typed Locations and town festivals are untouched; running it again
 * changes nothing. Run on Neon with "php artisan migrate".
 */
return new class extends Migration
{
    public function up(): void
    {
        $places = DB::table('churches')->select('id', 'location', 'address', 'municipality')->get()
            ->mapWithKeys(function ($c) {
                $place = trim((string) ($c->location ?: $c->address ?: $c->municipality));

                return [$c->id => $place === '' ? null : mb_substr($place, 0, 150)];
            })
            ->filter();

        DB::table('schedules')
            ->whereNotNull('church_id')
            ->orderBy('id')
            ->select('id', 'church_id', 'location')
            ->chunkById(500, function ($rows) use ($places) {
                $empty = $rows->filter(fn ($r) => in_array(trim((string) $r->location), ['', '-'], true));
                foreach ($empty->groupBy('church_id') as $churchId => $group) {
                    if ($place = $places[$churchId] ?? null) {
                        DB::table('schedules')->whereIn('id', $group->pluck('id'))->update(['location' => $place]);
                    }
                }
            });
    }

    public function down(): void
    {
    }
};
