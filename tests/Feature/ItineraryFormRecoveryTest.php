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

        // The order the map worked out, which is not the order they were
        // seeded - a restore that quietly re-sorted would still look right
        // if this were ascending.
        $ordered = collect([$stops[2], $stops[0], $stops[1]]);
        $ids     = $ordered->pluck('id')->implode(',');

        $this->actingAs($user)
            ->from(route('map', ['plan' => 1]))
            ->post(route('plan.store'), [
                'name'     => '',
                'type'     => 'Custom',
                'stops'    => $ordered->pluck('name')->all(),
                'stop_ids' => $ids,
            ])
            ->assertSessionHasErrors('name');

        // Coming back, the whole route is there, in the order it was arranged.
        $this->actingAs($user)
            ->get(route('map', ['plan' => 1]))
            ->assertOk()
            ->assertSee($ids, false);
    }

    /** A past date is refused too, and must not cost the route either. */
    public function test_a_route_survives_a_rejected_date(): void
    {
        $user  = $this->devotee();
        $stops = $this->churches->take(2);
        $ids   = $stops->pluck('id')->implode(',');

        $this->actingAs($user)
            ->from(route('map', ['plan' => 1]))
            ->post(route('plan.store'), [
                'name'           => 'Weekend route',
                'type'           => 'Custom',
                'scheduled_date' => now()->subWeek()->toDateString(),
                'stops'          => $stops->pluck('name')->all(),
                'stop_ids'       => $ids,
            ])
            ->assertSessionHasErrors('scheduled_date');

        $this->actingAs($user)
            ->get(route('map', ['plan' => 1]))
            ->assertOk()
            ->assertSee($ids, false)
            ->assertSee('value="Weekend route"', false);   // what they typed is kept too
    }

    /** Arriving from a link still works - old input only wins when present. */
    public function test_stops_in_the_url_still_preset_the_route(): void
    {
        $user = $this->devotee();
        $ids  = $this->churches->pluck('id')->implode(',');

        $this->actingAs($user)
            ->get(route('map', ['plan' => 1, 'stops' => $ids]))
            ->assertOk()
            ->assertSee($this->churches->first()->name);
    }

    /**
     * The screen this file was written for is gone, and its URL leads to the
     * one that replaced it rather than to a 404 - carrying any stops with it,
     * because a link someone kept is usually a route they cared about.
     */
    public function test_the_old_planner_url_leads_to_the_map(): void
    {
        $ids = $this->churches->take(2)->pluck('id')->implode(',');

        $user = $this->devotee();

        $this->actingAs($user)
            ->get(route('plan.create', ['stops' => $ids]))
            ->assertRedirect(route('map', ['plan' => 1, 'stops' => $ids]));

        $this->actingAs($user)
            ->get(route('plan.create'))
            ->assertRedirect(route('map', ['plan' => 1]));
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
