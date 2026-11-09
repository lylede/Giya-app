<?php

namespace Tests\Unit;

use App\Services\RoutePlanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RoutePlannerTest extends TestCase
{
    public static function asks(): array
    {
        return [
            ['can you plan me a 5 hours itinerary route', true],
            ['Plan a Visita Iglesia for Holy Thursday', true],
            ['plan a half day trip in north cebu', true],
            ['3 hour church tour please', true],
            ['what is the route to Simala?', false],
            ['what time is Mass at the Basilica?', false],
        ];
    }

    #[DataProvider('asks')]
    public function test_it_knows_when_a_route_is_asked_for(string $text, bool $wants): void
    {
        $this->assertSame($wants, RoutePlanner::wants($text));
    }

    public function test_hours_and_start_time_are_read_from_the_question(): void
    {
        $this->assertSame(5.0, RoutePlanner::hours('plan me a 5 hours itinerary'));
        $this->assertSame(3.5, RoutePlanner::hours('a 3.5 hr route'));
        $this->assertSame(4.0, RoutePlanner::hours('half day trip'));
        $this->assertNull(RoutePlanner::hours('plan a route'));

        $this->assertSame(9 * 60, RoutePlanner::startTime('start at 9am'));
        $this->assertSame(13 * 60 + 30, RoutePlanner::startTime('leave 1:30 pm'));
        $this->assertSame(8 * 60, RoutePlanner::startTime('plan a route'));
    }

    public function test_town_names_read_the_way_pilgrims_say_them(): void
    {
        $this->assertSame('Carcar', RoutePlanner::townName('City of Carcar'));
        $this->assertSame('Sibonga', RoutePlanner::townName('Lindogon, Sibonga, Cebu'));
        $this->assertSame('Cebu City', RoutePlanner::townName('P. Burgos St, Cebu City'));
    }

    /** The AI's answer put these two 20 minutes apart. */
    public function test_dumanjug_to_compostela_is_hours_not_twenty_minutes(): void
    {
        $leg = RoutePlanner::leg([10.040855, 123.506557], [10.453832, 124.011032], false);

        $this->assertGreaterThan(150, $leg['minutes']);
        $this->assertFalse($leg['walk']);
    }

    /** ... and called these two Carcar churches "a short walk". */
    public function test_two_carcar_churches_5_km_apart_are_a_drive(): void
    {
        $leg = RoutePlanner::leg([10.106019, 123.640161], [10.064705, 123.628137], false);

        $this->assertFalse($leg['walk']);
        $this->assertGreaterThanOrEqual(10, $leg['minutes']);
    }

    public function test_churches_next_door_are_a_short_walk(): void
    {
        // Basilica del Santo Nino to the Cathedral, Cebu City.
        $leg = RoutePlanner::leg([10.2939, 123.9019], [10.2958, 123.9026], true);

        $this->assertTrue($leg['walk']);
        $this->assertLessThanOrEqual(10, $leg['minutes']);
    }
}
