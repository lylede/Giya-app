<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\ItineraryType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** An automatic check-in leaves "You have arrived" in the bell; Undo takes it back out. */
class ArrivalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private array $stops = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cat  = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);
        $type = ItineraryType::firstOrCreate(['name' => 'Custom'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $trip = Itinerary::create([
            'user_id' => $this->user->id, 'itinerary_type_id' => $type->id, 'name' => 'Liloan Pilgrimage',
            'status' => 'Upcoming', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['San Fernando Rey Parish', 'San Sebastian Martyr Church'] as $i => $name) {
            $church = Church::create([
                'name' => $name, 'category_id' => $cat->id, 'location' => 'Cebu',
                'latitude' => 10.4, 'longitude' => 124.0, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->stops[] = ItineraryStop::create([
                'itinerary_id' => $trip->id, 'church_id' => $church->id, 'stop_order' => $i + 1, 'is_visited' => false,
            ]);
        }
    }

    public function test_an_automatic_check_in_notifies_with_the_next_church(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('plan.stop.visited'), ['stop_id' => $this->stops[0]->id, 'auto' => true])
            ->assertOk();

        $note = Notification::where('user_id', $this->user->id)->sole();
        $this->assertSame(__('giya.plan.arrive_note_title', ['church' => 'San Fernando Rey Parish']), $note->title);
        $this->assertStringContainsString('San Sebastian Martyr Church', $note->message);
        $this->assertFalse($note->is_read);

        // It is what the bell reads.
        $this->actingAs($this->user)->getJson('/notifications')->assertJsonPath('unread', 1);
    }

    public function test_the_last_stop_says_the_trip_is_done(): void
    {
        foreach ($this->stops as $stop) {
            $this->actingAs($this->user)->postJson(route('plan.stop.visited'), ['stop_id' => $stop->id, 'auto' => true]);
        }

        $last = Notification::where('dedupe_key', 'arrive:'.$this->stops[1]->id)->sole();
        $this->assertStringContainsString('Liloan Pilgrimage', $last->message);
    }

    public function test_marking_by_hand_does_not_notify(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('plan.stop.visited'), ['stop_id' => $this->stops[0]->id])
            ->assertOk();

        $this->assertSame(0, Notification::count(), 'The devotee pressed the button; they know.');
    }

    public function test_undo_removes_the_arrival_note(): void
    {
        $this->actingAs($this->user)->postJson(route('plan.stop.visited'), ['stop_id' => $this->stops[0]->id, 'auto' => true]);
        $this->actingAs($this->user)->postJson(route('plan.stop.visited'), ['stop_id' => $this->stops[0]->id, 'undo' => true])->assertOk();

        $this->assertSame(0, Notification::count());
    }
}
