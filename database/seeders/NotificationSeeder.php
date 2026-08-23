<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Gives every devotee something in their bell on a fresh install, drawn from
 * real rows so the links go somewhere.
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $featured = Church::active()->featured()->first() ?? Church::active()->first();
        $mass     = Schedule::with('church')->where('event_type', 'Mass')->first();

        foreach (User::where('role', 'user')->get() as $user) {
            if (Notification::where('user_id', $user->id)->exists()) {
                continue;
            }

            Notification::notify($user->id, 'Welcome to GIYA', [
                'type'    => 'system',
                'message' => 'Explore churches across Metro Cebu and plan your first pilgrimage.',
                'url'     => route('map'),
            ]);

            if ($featured) {
                Notification::notify($user->id, 'Featured destination', [
                    'type'    => 'general',
                    'message' => $featured->name.' is worth a visit this week.',
                    'url'     => route('churches.show', $featured),
                ]);
            }

            if ($mass && $mass->church) {
                Notification::notify($user->id, 'Mass schedule', [
                    'type'    => 'schedule',
                    'message' => $mass->event_name.' at '.$mass->church->name.'.',
                    'url'     => route('churches.show', $mass->church),
                ]);
            }
        }
    }
}
