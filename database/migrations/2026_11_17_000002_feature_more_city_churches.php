<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * More featured destinations for the Home slideshow: well-known churches of
 * Metro Cebu that already have a photo. Matched by name and town, so a church
 * that is not in a database is simply skipped. Admins can still switch any
 * church on or off in Destinations.
 */
return new class extends Migration
{
    /** [part of the name, town] - both matched case-insensitively. */
    private const CHURCHES = [
        ['Our Lady of Guadalupe', 'cebu city'],
        ['Redemptorist', 'cebu city'],
        ['San Pedro Calungsod', 'cebu city'],
        ['San Agustin', 'cebu city'],
        ['Sacred Heart', 'mandaue'],
        ['National Shrine of St. Joseph', 'mandaue'],
        ['Sto. Rosario', 'cebu city'],
        ['St. Therese', 'lahug'],
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
