<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Notification;
use App\Models\User;
use App\Models\VisitHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MapCheckInTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now()]);
        $this->church = Church::create([
            'name' => 'Simala Shrine', 'category_id' => $cat->id, 'location' => 'Sibonga', 'municipality' => 'Sibonga',
            'latitude' => 10.0353, 'longitude' => 123.5795, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::create([
            'name' => 'Maria', 'email' => 'maria@example.com', 'password_hash' => Hash::make('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function arrive(float $lat, float $lng)
    {
        return $this->actingAs($this->user)->postJson(route('visits.arrive'), [
            'church_id' => $this->church->id, 'lat' => $lat, 'lng' => $lng,
        ]);
    }

    public function test_standing_at_the_church_records_a_visit_and_a_bell_note(): void
    {
        // About 30 m from the church.
        $this->arrive(10.0355, 123.5797)->assertOk()->assertJson(['ok' => true, 'church' => 'Simala Shrine']);

        $visit = VisitHistory::sole();
        $this->assertSame($this->user->id, $visit->user_id);
        $this->assertNull($visit->itinerary_id);

        $note = Notification::where('user_id', $this->user->id)->sole();
        $this->assertSame(__('giya.plan.arrive_note_title', ['church' => 'Simala Shrine']), $note->title);
        $this->assertSame('visit:'.$visit->id, $note->dedupe_key);
    }

    public function test_a_position_far_from_the_church_is_refused(): void
    {
        // About 1 km away.
        $this->arrive(10.0443, 123.5795)->assertStatus(422)->assertJson(['reason' => 'too_far']);

        $this->assertSame(0, VisitHistory::count());
    }

    public function test_one_check_in_per_church_per_day(): void
    {
        $this->arrive(10.0355, 123.5797)->assertOk();
        $this->arrive(10.0354, 123.5796)->assertOk()->assertJson(['already' => true]);

        $this->assertSame(1, VisitHistory::count());

        $this->travel(1)->days();
        $this->arrive(10.0355, 123.5797)->assertOk()->assertJsonMissing(['already' => true]);
        $this->assertSame(2, VisitHistory::count());
    }

    public function test_undo_removes_the_visit_and_its_note(): void
    {
        $url = $this->arrive(10.0355, 123.5797)->json('undo_url');

        $this->actingAs($this->user)->postJson($url)->assertOk()->assertJson(['undone' => true]);

        $this->assertSame(0, VisitHistory::count());
        $this->assertSame(0, Notification::count());
    }

    public function test_nobody_can_undo_someone_elses_visit(): void
    {
        $url = $this->arrive(10.0355, 123.5797)->json('undo_url');
        $other = User::create([
            'name' => 'Jose', 'email' => 'jose@example.com', 'password_hash' => Hash::make('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($other)->postJson($url)->assertForbidden();
        $this->assertSame(1, VisitHistory::count());
    }

    public function test_guests_cannot_check_in(): void
    {
        $this->postJson(route('visits.arrive'), ['church_id' => $this->church->id, 'lat' => 10.0355, 'lng' => 123.5797])
            ->assertUnauthorized();
    }

    public function test_the_map_loads_the_watcher_for_a_signed_in_devotee_only(): void
    {
        $this->actingAs($this->user)->get('/map')->assertOk()
            ->assertSee('giya-arrive.js', false)->assertSee('id="visitToast"', false);

        auth()->logout();
        $this->get('/map')->assertOk()->assertDontSee('giya-arrive.js', false);
    }
}
