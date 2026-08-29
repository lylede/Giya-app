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

        $plans = [
            [
                'name'          => 'Free',
                'description'   => 'One active itinerary at a time, full destination browsing.',
                'price'         => 0.00,
                'duration_days' => 0,
            ],
            [
                'name'          => 'Additional Itinerary Access',
                'description'   => 'Unlimited active itineraries for 30 days.',
                'price'         => 99.00,
                'duration_days' => 30,
            ],
            [
                'name'          => 'Pilgrim Annual',
                'description'   => 'Unlimited itineraries and priority chat assistance for one year.',
                'price'         => 899.00,
                'duration_days' => 365,
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
