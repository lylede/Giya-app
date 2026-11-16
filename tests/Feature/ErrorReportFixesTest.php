<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use App\Models\ItineraryType;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The three bugs in the team's error report of 17 Nov 2026. */
class ErrorReportFixesTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $email, string $role = 'user'): User
    {
        return User::create([
            'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password_hash' => bcrypt('Secret123!'),
            'role' => $role, 'status' => 'Active', 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function trips(User $user, int $count): Itinerary
    {
        $type = ItineraryType::firstOrCreate(['name' => 'Custom'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $last = null;
        for ($i = 1; $i <= $count; $i++) {
            $last = Itinerary::create([
                'user_id' => $user->id, 'itinerary_type_id' => $type->id, 'name' => "Trip {$i}",
                'status' => 'Upcoming', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $last;
    }

    private function premium(User $user): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Pilgrim Monthly', 'description' => 'One month.', 'price' => 99, 'currency' => 'PHP',
            'duration_days' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Transaction::create([
            'user_id' => $user->id, 'plan_type_id' => $plan->id, 'amount' => 99, 'currency' => 'PHP',
            'method' => 'Maya', 'provider' => 'maya', 'status' => 'Paid', 'reference_no' => 'GIYA-'.uniqid(),
            'created_at' => now(), 'processed_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_premium_sees_unlimited_not_the_free_meter_and_can_still_create(): void
    {
        $clint = $this->person('clint@example.com');
        $this->trips($clint, 3);
        $this->premium($clint);

        $this->actingAs($clint)->get(route('plan.index'))->assertOk()
            ->assertSee('Unlimited itineraries')
            ->assertSee('Premium until '.now()->addDays(30)->format('M j, Y'))
            ->assertDontSee('Free Itinerary Usage')
            ->assertDontSee('3 / 3')
            ->assertSee(route('map', ['plan' => 1]), false);
    }

    public function test_free_at_the_limit_still_sees_the_meter(): void
    {
        $free = $this->person('free@example.com');
        $this->trips($free, 3);

        $this->actingAs($free)->get(route('plan.index'))->assertOk()
            ->assertSee('Free Itinerary Usage')->assertSee('3 / 3')
            ->assertDontSee('Unlimited itineraries');
    }

    public function test_an_old_rating_link_to_a_deleted_trip_opens_visit_history(): void
    {
        $clint = $this->person('clint@example.com');
        $trip = $this->trips($clint, 1);
        $trip->delete();

        $this->actingAs($clint)->get(route('plan.show', ['itinerary' => $trip->id, 'rate' => 1]))
            ->assertRedirect(route('profile', ['tab' => 'visits']))
            ->assertSessionHas('info', __('giya.plan.trip_deleted_rate'));

        // Someone else's deleted trip is still not theirs to see.
        $other = $this->person('other@example.com');
        $this->actingAs($other)->get(route('plan.show', $trip->id))->assertNotFound();

        // A trip that is not deleted opens as before.
        $kept = $this->trips($clint, 1);
        $this->actingAs($clint)->get(route('plan.show', ['itinerary' => $kept->id, 'rate' => 1]))->assertOk();
    }

    public function test_destinations_and_transactions_have_rows_per_page(): void
    {
        $admin = $this->person('admin@example.com', 'admin');
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);
        for ($i = 1; $i <= 30; $i++) {
            Church::create(['name' => sprintf('Parish %02d', $i), 'category_id' => $cat->id, 'location' => 'Cebu',
                'latitude' => 10.3, 'longitude' => 123.9, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($admin)->get(route('admin.destinations'))->assertOk()
            ->assertSee('Rows per page')->assertSee('Showing 1 to 10 of 30 destinations')
            ;

        $this->actingAs($admin)->get(route('admin.destinations', ['per_page' => 25, 'search' => 'Parish']))->assertOk()
            ->assertSee('Showing 1 to 25 of 30 destinations')->assertSee('Parish 25')
            ->assertSee('name="search" value="Parish"', false);

        // Only the offered sizes: anything else falls back to 10.
        $this->actingAs($admin)->get(route('admin.destinations', ['per_page' => 100000]))
            ->assertSee('Showing 1 to 10 of 30 destinations');

        $this->actingAs($admin)->get(route('admin.transactions', ['per_page' => 50]))->assertOk()
            ->assertSee('Rows per page')->assertSee('<option value="50" selected', false);
    }
}
