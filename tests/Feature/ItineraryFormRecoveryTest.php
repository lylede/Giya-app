<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A rejected form must not cost the devotee the route they just built.
 *
 * The planner keeps its stops in JavaScript, so any round trip to the server
 * wipes them unless the page puts them back. Leaving the itinerary name empty
 * did exactly that: the form posted anyway, the server refused it, and the
 * page came back with an empty route and every church to pick again.
 */
class ItineraryFormRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /** @var \Illuminate\Support\Collection<int, Church> */
    private $churches;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ChurchCategory::create([
            'name' => 'Church', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['Cebu Metropolitan Cathedral', 'Redemptorist Church', 'Mabolo Church'] as $i => $name) {
            Church::create([
                'name' => $name, 'category_id' => $category->id, 'location' => 'Cebu City',
                'latitude' => 10.29 + $i * 0.01, 'longitude' => 123.90,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->churches = Church::orderBy('id')->get();
    }

    private function devotee(): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_route_survives_a_rejected_form(): void
    {
        $user  = $this->devotee();
        $stops = $this->churches->take(3);

        // What the planner posts when the name was left empty.
        $this->actingAs($user)
            ->from(route('plan.create'))
            ->post(route('plan.store'), [
                'name'     => '',
                'type'     => 'Custom',
                'stops'    => $stops->pluck('name')->all(),
                'stop_ids' => $stops->pluck('id')->implode(','),
            ])
            ->assertSessionHasErrors('name');

        // Coming back, every church is still on the route, in order.
        $page = $this->actingAs($user)->get(route('plan.create'))->assertOk();

        foreach ($stops as $church) {
            $page->assertSee($church->name);
        }

        $content = $page->getContent();
        $preset  = json_decode(
            substr($content, strpos($content, 'const PRESET = ') + 15,
                   strpos($content, ';', strpos($content, 'const PRESET = ')) - strpos($content, 'const PRESET = ') - 15),
            true
        );

        $this->assertCount(3, $preset, 'The route came back empty after the form was rejected.');
        $this->assertSame(
            $stops->pluck('id')->all(),
            array_column($preset, 'id'),
            'The stops came back in a different order than they were arranged in.'
        );
    }

    /** A past date is refused too, and must not cost the route either. */
    public function test_a_route_survives_a_rejected_date(): void
    {
        $user  = $this->devotee();
        $stops = $this->churches->take(2);

        $this->actingAs($user)
            ->from(route('plan.create'))
            ->post(route('plan.store'), [
                'name'           => 'Weekend route',
                'type'           => 'Custom',
                'scheduled_date' => now()->subWeek()->toDateString(),
                'stops'          => $stops->pluck('name')->all(),
                'stop_ids'       => $stops->pluck('id')->implode(','),
            ])
            ->assertSessionHasErrors('scheduled_date');

        $this->actingAs($user)
            ->get(route('plan.create'))
            ->assertOk()
            ->assertSee($stops->first()->name)
            ->assertSee('Weekend route', false);   // the name they typed is kept too
    }

    /** Arriving from the map still works - old input only wins when present. */
    public function test_stops_from_the_map_still_preset_the_route(): void
    {
        $user = $this->devotee();
        $ids  = $this->churches->pluck('id')->implode(',');

        $this->actingAs($user)
            ->get(route('plan.create', ['stops' => $ids]))
            ->assertOk()
            ->assertSee($this->churches->first()->name);
    }

    /** A valid submission is unaffected by any of the above. */
    public function test_a_complete_form_still_saves(): void
    {
        $user  = $this->devotee();
        $stops = $this->churches->take(2);

        $this->actingAs($user)
            ->post(route('plan.store'), [
                'name'     => 'Cebu City Pilgrimage',
                'type'     => 'Custom',
                'stops'    => $stops->pluck('name')->all(),
                'stop_ids' => $stops->pluck('id')->implode(','),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('itineraries', [
            'user_id' => $user->id, 'name' => 'Cebu City Pilgrimage',
        ]);
    }
}
