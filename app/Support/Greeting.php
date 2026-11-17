<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The time-of-day greeting GIYA opens with, in Cebuano as everywhere in
 * GIYA's voice: good morning, noon, afternoon or evening by Manila time.
 * The opening line used to say "Maayong buntag" at any hour.
 */
class Greeting
{
    public static function word(?Carbon $at = null): string
    {
        $hour = ($at ?? now())->copy()->timezone(config('app.timezone', 'Asia/Manila'))->hour;

        return match (true) {
            $hour >= 4 && $hour < 12  => 'Maayong buntag',   // morning
            $hour === 12              => 'Maayong udto',     // noon
            $hour >= 13 && $hour < 18 => 'Maayong hapon',    // afternoon
            default                   => 'Maayong gabii',    // evening and night
        };
    }
}
