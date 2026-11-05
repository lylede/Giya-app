<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** A long yearly date must not squeeze the event name into one letter per line. */
class ChurchEventRowTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_show_the_date_under_the_name_soonest_first(): void
    {
        $cat = ChurchCategory::create(['name' => 'Basilica', 'created_at' => now(), 'updated_at' => now()]);
        $church = Church::create(['name' => 'Test Basilica', 'category_id' => $cat->id, 'location' => 'Cebu City', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('schedules')->insert([
            ['church_id' => $church->id, 'event_name' => 'Grand Parade', 'event_type' => 'Feast Day', 'is_recurring' => false, 'recurrence' => null, 'schedule_date' => now()->addDays(20)->toDateString(), 'created_at' => now()],
            ['church_id' => $church->id, 'event_name' => 'Big Festival', 'event_type' => 'Feast Day', 'is_recurring' => true, 'recurrence' => 'Yearly - 3rd Sunday of January', 'schedule_date' => now()->addDays(5)->toDateString(), 'created_at' => now()],
        ]);
        $user = User::create(['name' => 'D', 'email' => 'd@example.com', 'password_hash' => bcrypt('x'), 'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->actingAs($user)->get(route('churches.show', $church))->assertOk()->getContent();

        $this->assertStringNotContainsString('church-event-meta', $html);
        $this->assertStringContainsString('church-event-when', $html);
        $this->assertLessThan(strpos($html, 'Grand Parade'), strpos($html, 'Big Festival'), 'Soonest event first.');
    }
}
