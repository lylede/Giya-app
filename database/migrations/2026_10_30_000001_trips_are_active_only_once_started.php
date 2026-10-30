<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A trip used to become "Active" the moment it was opened, so lists were full
 * of "Active" trips nobody had started. "Active" (In progress) now means a
 * church on it has been visited. This puts the opened-but-not-started ones
 * back to "Upcoming". Only the status changes; nothing is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('itineraries')
            ->where('status', 'Active')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('itinerary_stops')
                ->whereColumn('itinerary_stops.itinerary_id', 'itineraries.id')
                ->where('itinerary_stops.is_visited', true))
            ->update(['status' => 'Upcoming']);
    }

    public function down(): void
    {
        // Nothing to undo: which trips had merely been opened is not recorded.
    }
};
