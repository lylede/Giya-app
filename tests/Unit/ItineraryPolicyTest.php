<?php

namespace Tests\Unit;

use App\Models\Itinerary;
use App\Models\User;
use App\Policies\ItineraryPolicy;
use PHPUnit\Framework\TestCase;

class ItineraryPolicyTest extends TestCase
{
    public function test_owner_can_view_update_and_delete_their_itinerary(): void
    {
        $owner = new User();
        $owner->forceFill(['id' => 10]);

        $itinerary = new Itinerary();
        $itinerary->forceFill(['user_id' => 10]);

        $policy = new ItineraryPolicy();

        $this->assertTrue($policy->view($owner, $itinerary));
        $this->assertTrue($policy->update($owner, $itinerary));
        $this->assertTrue($policy->delete($owner, $itinerary));
    }

    public function test_another_user_cannot_view_update_or_delete_the_itinerary(): void
    {
        $anotherUser = new User();
        $anotherUser->forceFill(['id' => 20]);

        $itinerary = new Itinerary();
        $itinerary->forceFill(['user_id' => 10]);

        $policy = new ItineraryPolicy();

        $this->assertFalse($policy->view($anotherUser, $itinerary));
        $this->assertFalse($policy->update($anotherUser, $itinerary));
        $this->assertFalse($policy->delete($anotherUser, $itinerary));
    }

    public function test_owner_comparison_handles_integer_and_numeric_string_ids(): void
    {
        $owner = new User();
        $owner->forceFill(['id' => 10]);

        $itinerary = new Itinerary();
        $itinerary->forceFill(['user_id' => '10']);

        $this->assertTrue((new ItineraryPolicy())->view($owner, $itinerary));
    }
}
