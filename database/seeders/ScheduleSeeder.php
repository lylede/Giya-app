<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

/**
 * Now also carries the recurring Mass times that used to live in the
 * churches.mass_schedule jsonb column, so $church->mass_schedule still
 * returns data through the Schedules relation.
 */
class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            ['Basilica del Santo Niño',      'Sinulog Grand Parade',         'Feast Day',  '2027-01-19'],
            ['Cebu Metropolitan Cathedral',  'Sunday Family Mass',           'Mass',       null],
            ['Simala Shrine',                'Novena to Our Lady',           'Novena',     '2026-05-01'],
            ['San Pedro Calungsod Shrine',   'Feast of San Pedro Calungsod', 'Feast Day',  '2027-04-02'],
            ['San Agustin Church',           'Holy Week Procession',         'Procession', '2026-04-17'],
        ];

        foreach ($events as [$churchName, $eventName, $type, $date]) {
            $church = Church::where('name', $churchName)->first();
            if (! $church) {
                continue;
            }

            Schedule::updateOrCreate(
                ['church_id' => $church->id, 'event_name' => $eventName],
                [
                    'event_type'    => $type,
                    'schedule_date' => $date,
                    'start_time'    => '08:00',
                    'is_recurring'  => $date === null,
                    'recurrence'    => $date === null ? 'Weekly (Sunday)' : null,
                    'created_at'    => now(),
                ]
            );
        }

        // Recurring Mass times - replaces the old churches.mass_schedule jsonb.
        $masses = [
            'Basilica del Santo Niño' => [
                ['Weekday Mass', 'Daily',            '05:00'],
                ['Weekday Mass', 'Daily',            '06:00'],
                ['Weekday Mass', 'Daily',            '12:00'],
                ['Weekday Mass', 'Daily',            '17:00'],
                ['Sunday Mass',  'Weekly (Sunday)',  '09:00'],
                ['Sunday Mass',  'Weekly (Sunday)',  '10:30'],
            ],
            'Cebu Metropolitan Cathedral' => [
                ['Weekday Mass', 'Daily',            '06:00'],
                ['Weekday Mass', 'Daily',            '18:00'],
                ['Sunday Mass',  'Weekly (Sunday)',  '08:00'],
            ],
            'Redemptorist Church' => [
                ['Novena to Our Mother of Perpetual Help', 'Weekly (Wednesday)', '06:00'],
                ['Novena to Our Mother of Perpetual Help', 'Weekly (Wednesday)', '17:30'],
            ],
        ];

        foreach ($masses as $churchName => $rows) {
            $church = Church::where('name', $churchName)->first();
            if (! $church) {
                continue;
            }

            foreach ($rows as [$name, $recurrence, $time]) {
                Schedule::updateOrCreate(
                    [
                        'church_id'  => $church->id,
                        'event_name' => $name,
                        'start_time' => $time,
                    ],
                    [
                        'event_type'    => 'Mass',
                        'schedule_date' => null,
                        'is_recurring'  => true,
                        'recurrence'    => $recurrence,
                        'created_at'    => now(),
                    ]
                );
            }
        }
    }
}
