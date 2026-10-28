<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,   // categories, itinerary types, plans
            UserSeeder::class,
            ChurchSeeder::class,
            ChurchHistorySeeder::class,   // researched history for the seeded churches
            ScheduleSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
