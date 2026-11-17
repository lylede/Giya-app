<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Featured destinations: add the best-known churches of Cebu province to the
 * four already featured (Basilica del Santo Niño, the Cathedral, Magellan's
 * Cross and Simala). Matched by name and town, so a church that is not in a
 * database is simply skipped. Admins can still switch any church on or off
 * in Destinations.
 */
return new class extends Migration
{
    /** [part of the name, town] - both matched case-insensitively. */
    private const CHURCHES = [
        ['St. Catherine of Alexandria', 'carcar'],
        ['St. Michael Archangel', 'argao'],
        ['Patrocinio de Maria', 'boljoon'],
        ['Immaculate Conception', 'oslob'],
        ['Our Lady of the Rule', 'lapu-lapu'],
        ['Sts. Peter and Paul', 'bantayan'],
        ['Sto. Tomas de Villanueva', 'danao'],
        ['Our Lady of the Pillar', 'sibonga'],
    ];

    public function up(): void
    {
        $this->set(true);
    }

    public function down(): void
    {
        $this->set(false);
    }

    private function set(bool $featured): void
    {
        foreach (self::CHURCHES as [$name, $town]) {
            DB::table('churches')
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%'])
                ->where(fn ($q) => $q->whereRaw('LOWER(COALESCE(municipality, \'\')) LIKE ?', ['%'.$town.'%'])
                    ->orWhereRaw('LOWER(COALESCE(location, \'\')) LIKE ?', ['%'.$town.'%']))
                ->update(['is_featured' => $featured]);
        }
    }
};
