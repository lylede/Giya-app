<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Both planners hand their route to the map, and the map reads it back.
 *
 * The planners list names; the map answers "how far apart are these actually",
 * which is the question a seven-church route raises and the reason to look
 * before saving. The handoff is one URL - /map?stops=3,7,1 - so the two
 * screens cannot drift apart: only the map implements the reading.
 *
 * PHPUnit can prove the button is there and that the map still contains the
 * code that reads the parameter. It cannot prove the ticking, which is
 * JavaScript; that was verified in a browser and the numbers are in the
 * commit message.
 */
class PlannerMapHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function devotee(): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedChurches(int $count = 8): void
    {
        $category = ChurchCategory::firstOrCreate(
            ['name' => 'Basilica'],
            ['created_at' => now(), 'updated_at' => now()]
        );

        for ($i = 1; $i <= $count; $i++) {
            Church::create([
                'name' => "Church $i", 'category_id' => $category->id,
                'location' => 'Cebu City',
                'latitude' => 10.29 + ($i / 1000), 'longitude' => 123.90 + ($i / 1000),
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_the_visita_planner_offers_a_way_to_see_the_route_on_the_map(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('plan.visita'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('GiyaVisita.viewOnMap()', $html);
        $this->assertStringContainsString(__('giya.plan.view_on_map'), $html);
    }

    public function test_the_custom_planner_offers_the_same(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('plan.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="planViewMap"', $html);
        $this->assertStringContainsString(__('giya.plan.view_on_map'), $html);
    }

    /**
     * Both build the same URL against the same route, so a change to one of
     * them cannot quietly point somewhere else.
     */
    public function test_both_planners_aim_at_the_map(): void
    {
        $this->seedChurches();
        $user = $this->devotee();

        foreach (['plan.visita', 'plan.create'] as $page) {
            $html = $this->actingAs($user)->get(route($page))->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '/const base\s*=\s*"[^"]*\\\\?\/map(\?[^"]*)?"/',
                $html,
                "$page does not point its map button at the map route."
            );

            // '?stops=' on a bare /map, '&stops=' when the URL already carries
            // the trip's type. Either way the ids are appended to `base`.
            $this->assertMatchesRegularExpression(
                "/'[?&]stops=' \+/",
                $html,
                "$page does not pass its stops."
            );
        }
    }

    /**
     * The Visita planner says which kind of trip it is handing over.
     *
     * Without it the map planned a Custom itinerary, so adjusting a seven
     * church route on the map saved something that was no longer a Visita
     * Iglesia - and there was no way back to the planner it came from.
     */
    public function test_the_visita_planner_hands_over_its_type(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('plan.visita'))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/const base\s*=\s*"[^"]*plan=visita"/',
            $html,
            'The Visita planner should send the map its type, not just its stops.'
        );
    }

    /**
     * The other half: the map still knows how to read what they send. If this
     * block ever goes, both buttons become a link to a blank map and nothing
     * else would complain.
     */
    public function test_the_map_still_reads_the_stops_it_is_given(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('map'))->assertOk()->getContent();

        $this->assertStringContainsString(".get('stops')", $html);
        $this->assertStringContainsString('map.addStop(id)', $html);
    }

    /** A guest following the link still gets the map rather than a login form. */
    public function test_a_guest_can_open_the_map_with_stops(): void
    {
        $this->seedChurches();

        $ids = Church::orderBy('id')->take(7)->pluck('id')->implode(',');

        $this->get(route('map').'?stops='.$ids)
            ->assertOk()
            ->assertSee('Church 1');
    }
}
