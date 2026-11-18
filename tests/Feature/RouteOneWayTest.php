<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Services\RoutePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Giya AI's routes go one way: every stop is further along than the last. */
class RouteOneWayTest extends TestCase
{
    use RefreshDatabase;

    private function church(string $name, string $town, float $lat, float $lng, bool $major = false): void
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);
        Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => $town, 'municipality' => $town,
            'latitude' => $lat, 'longitude' => $lng, 'is_active' => true, 'is_major' => $major,
            'created_at' => now(), 'updated_at' => now()]);
    }

    /** South Cebu down the east coast, real town positions, a famous church part way down to tempt a jump. */
    private function southCoast(): void
    {
        $this->church('San Fernando Rey Parish', 'San Fernando', 10.1617, 123.7083);
        $this->church('St. Catherine Parish', 'Carcar City', 10.1062, 123.6402, true);
        $this->church('Simala Shrine', 'Sibonga', 10.0250, 123.6247, true);
        $this->church('St. Michael Parish', 'Argao', 9.8794, 123.6070, true);
        $this->church('San Guillermo Parish', 'Dalaguete', 9.7614, 123.5345);
        $this->church('Sto. Tomas Parish', 'Alcoy', 9.7085, 123.5063);
        $this->church('Patrocinio de Maria Parish', 'Boljoon', 9.6295, 123.4797, true);
        $this->church('Our Lady of the Immaculate Conception', 'Oslob', 9.5196, 123.4321, true);
        // Inland and back north of Carcar: the old planner could turn back for it.
        $this->church('St. Isidore Chapel', 'San Fernando', 10.1700, 123.7300, true);
    }

    public function test_a_visita_iglesia_route_never_doubles_back(): void
    {
        $this->southCoast();

        $plan = app(RoutePlanner::class)->plan('Plan a Visita Iglesia in south Cebu');
        $this->assertNotNull($plan);
        $this->assertCount(7, $plan['stops']);

        $lat = Church::whereIn('id', $plan['ids'])->pluck('latitude', 'id');
        $lats = array_map(fn ($id) => (float) $lat[$id], $plan['ids']);
        for ($i = 1; $i < count($lats); $i++) {
            // Each stop is south of (or level with) the one before: one way down the coast.
            $this->assertLessThanOrEqual($lats[$i - 1] + 0.005, $lats[$i], 'Stop '.($i + 1).' goes back north');
        }
    }

    public function test_starting_in_the_middle_it_does_not_turn_back_past_the_start(): void
    {
        $this->southCoast();

        $plan = app(RoutePlanner::class)->plan('Plan a Visita Iglesia starting from Argao');
        $this->assertNotNull($plan);
        $this->assertGreaterThanOrEqual(4, count($plan['stops']));

        $lat = Church::whereIn('id', $plan['ids'])->pluck('latitude', 'id');
        $lats = array_map(fn ($id) => (float) $lat[$id], $plan['ids']);
        $south = $lats[1] < $lats[0];
        for ($i = 1; $i < count($lats); $i++) {
            $south
                ? $this->assertLessThanOrEqual($lats[$i - 1] + 0.005, $lats[$i], 'Turned back north at stop '.($i + 1))
                : $this->assertGreaterThanOrEqual($lats[$i - 1] - 0.005, $lats[$i], 'Turned back south at stop '.($i + 1));
        }
    }

    public function test_the_line_follows_the_churches(): void
    {
        $pool = collect([
            ['id' => 1, 'lat' => 10.20, 'lng' => 123.80],
            ['id' => 2, 'lat' => 10.30, 'lng' => 123.90],
            ['id' => 3, 'lat' => 10.10, 'lng' => 123.70],
        ]);
        // Starting in the middle: the other two lie on opposite sides of it, in a line.
        $along = RoutePlanner::heading($pool[0], $pool);

        $this->assertEqualsWithDelta(0.0, $along($pool[0]), 0.001);
        $this->assertLessThan(0, $along($pool[1]) * $along($pool[2]));
    }
}
