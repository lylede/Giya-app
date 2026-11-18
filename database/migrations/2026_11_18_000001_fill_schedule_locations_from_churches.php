<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schedules with a church but no Location get the church's address from the
 * map (its location, else its address, else its town), the same address
 * the schedule form now fills in by itself.
 *
 * Only blanks are filled: a Location an admin typed ("Main Church",
 * "Chapel") is never touched, and town festivals (no church) are left as
 * they are. Safe to run once on Neon with "php artisan migrate"; running it
 * again changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $places = DB::table('churches')
            ->select('id', 'location', 'address', 'municipality')
            ->get()
            ->mapWithKeys(function ($c) {
                $place = trim((string) ($c->location ?: $c->address ?: $c->municipality));

                return [$c->id => $place === '' ? null : mb_substr($place, 0, 150)];
            })
            ->filter();

        DB::table('schedules')
            ->whereNotNull('church_id')
            ->where(fn ($q) => $q->whereNull('location')->orWhere('location', ''))
            ->orderBy('id')
            ->select('id', 'church_id')
            ->chunkById(500, function ($rows) use ($places) {
                foreach ($rows->groupBy('church_id') as $churchId => $group) {
                    if ($place = $places[$churchId] ?? null) {
                        DB::table('schedules')->whereIn('id', $group->pluck('id'))->update(['location' => $place]);
                    }
                }
            });
    }

    /** The filled addresses look the same as typed ones, so nothing is undone. */
    public function down(): void
    {
    }
};
