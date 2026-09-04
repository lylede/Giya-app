<?php

namespace Database\Seeders;

use App\Models\ChurchCategory;
use App\Models\ItineraryType;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Fills the three new lookup tables the ERD introduced. Run this BEFORE
 * ChurchSeeder, since churches now need a category_id.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Basilica',  'description' => 'A church granted special privileges by the Pope.'],
            ['name' => 'Cathedral', 'description' => 'The principal church of a diocese, seat of the bishop.'],
            ['name' => 'Shrine',    'description' => 'A holy place devoted to a saint or a sacred image.'],
            ['name' => 'Church',    'description' => 'A parish church open for regular worship.'],
        ];

        foreach ($categories as $row) {
            ChurchCategory::firstOrCreate(['name' => $row['name']], $row + [
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $types = [
            ['name' => 'Custom',         'description' => 'A route the devotee builds themselves.'],
            ['name' => 'Visita Iglesia', 'description' => 'The traditional visit to seven churches during Holy Week.'],
            ['name' => 'Heritage Tour',  'description' => 'A route focused on historic and declared heritage churches.'],
            ['name' => 'Feast Day',      'description' => 'A route built around a specific feast day celebration.'],
        ];

        foreach ($types as $row) {
            ItineraryType::firstOrCreate(['name' => $row['name']], $row + [
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        /*
         * The paid tiers, straight from the business model canvas. Free is not
         * one of them: a free account is simply an account with no valid paid
         * transaction, capped at ItineraryController::FREE_LIMIT itineraries.
         * There is deliberately no row for it - a plan row is something a
         * devotee can buy, and a PHP 0.00 plan would show up on the upgrade
         * page with a Pay button.
         *
         * Both paid tiers unlock every feature; they differ only in how long
         * they last.
         */
        $plans = [
            [
                'name'          => 'Pilgrim Weekly',
                'description'   => 'Every GIYA feature, unlimited itineraries, for one week.',
                'price'         => 49.00,
                'duration_days' => 7,
            ],
            [
                'name'          => 'Pilgrim Monthly',
                'description'   => 'Every GIYA feature, unlimited itineraries, for one month.',
                'price'         => 99.00,
                'duration_days' => 30,
            ],
        ];

        foreach ($plans as $row) {
            SubscriptionPlan::firstOrCreate(['name' => $row['name']], $row + [
                'currency' => 'PHP', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
