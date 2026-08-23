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
            ScheduleSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
