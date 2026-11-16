<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserHelperMethodsTest extends TestCase
{
    public function test_initials_returns_the_uppercase_first_character(): void
    {
        $user = new User(['name' => 'clint Eroll Capondag']);

        $this->assertSame('C', $user->initials());
    }

    public function test_first_name_returns_the_first_name_without_surrounding_spaces(): void
    {
        $user = new User(['name' => '  Clint Eroll Capondag  ']);

        $this->assertSame('Clint', $user->firstName());
    }

    public function test_haversine_returns_zero_for_identical_coordinates(): void
    {
        $distance = User::haversine(10.2937, 123.9021, 10.2937, 123.9021);

        $this->assertSame(0.0, $distance);
    }

    public function test_haversine_calculates_distance_between_two_coordinates_in_kilometres(): void
    {
        // One degree of latitude along the equator is approximately 111.2 km.
        $distance = User::haversine(0.0, 0.0, 1.0, 0.0);

        $this->assertEqualsWithDelta(111.2, $distance, 0.2);
    }

    public function test_haversine_distance_is_symmetric(): void
    {
        $forward = User::haversine(10.2937, 123.9021, 10.3157, 123.8854);
        $reverse = User::haversine(10.3157, 123.8854, 10.2937, 123.9021);

        $this->assertEqualsWithDelta($forward, $reverse, 0.000001);
    }
}
