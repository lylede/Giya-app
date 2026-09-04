<?php

namespace Tests\Feature;

use App\Http\Controllers\ItineraryController;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The free allowance is three saved itineraries, and the profile has to show
 * where a devotee stands against it.
 *
 * The bug these cover: the counting was always right, but nothing on the
 * profile said so. The Pilgrimages figure in the header counts only COMPLETED
 * trips, so a devotee who had just planned three still read 0 there and quite
 * reasonably concluded that nothing had been recorded.
 */
class FreeItineraryUsageTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ChurchCategory::create([
            'name' => 'Church', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->church = Church::create([
            'name' => 'Sto. Niño Basilica', 'category_id' => $category->id,
            'location' => 'Cebu City', 'latitude' => 10.2937, 'longitude' => 123.9021,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function devotee(): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => 'lyle'.uniqid().'@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function premiumDevotee(): User
    {
        $user = $this->devotee();

        $plan = SubscriptionPlan::firstOrCreate(['name' => 'Pilgrim Monthly'], [
            'description' => 'One month.', 'price' => 99.00, 'currency' => 'PHP',
            'duration_days' => 30, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Transaction::create([
            'user_id' => $user->id, 'plan_type_id' => $plan->id,
            'amount' => 99.00, 'currency' => 'PHP', 'method' => 'Maya',
            'provider' => 'maya', 'status' => 'Paid',
            'reference_no' => 'GIYA-'.uniqid(),
            'created_at' => now(), 'processed_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function plan(User $user, string $type, string $name)
    {
        return $this->actingAs($user)->post(route('plan.store'), [
            'name'  => $name,
            'type'  => $type,
            'stops' => [$this->church->name],
        ]);
    }

    /** Both kinds of route take a slot - neither is free of the limit. */
    public function test_a_custom_route_and_a_visita_iglesia_both_use_a_slot(): void
    {
        $user = $this->devotee();

        $this->plan($user, 'Custom', 'Weekend route');
        $this->assertSame(1, Itinerary::where('user_id', $user->id)->count());

        $this->plan($user, 'Visita Iglesia', 'Holy Week');
        $this->assertSame(2, Itinerary::where('user_id', $user->id)->count());
    }

    public function test_the_fourth_itinerary_is_refused(): void
    {
        $user = $this->devotee();

        $this->plan($user, 'Custom', 'One');
        $this->plan($user, 'Visita Iglesia', 'Two');
        $this->plan($user, 'Custom', 'Three');

        $this->plan($user, 'Custom', 'Four')->assertSessionHas('error');

        $this->assertSame(
            ItineraryController::FREE_LIMIT,
            Itinerary::where('user_id', $user->id)->count()
        );
    }

    /**
     * The heart of the complaint. After planning two, the profile must say so
     * somewhere a devotee will actually see it.
     */
    public function test_the_profile_reports_how_many_free_slots_are_used(): void
    {
        $user = $this->devotee();

        $this->actingAs($user)->get(route('profile'))->assertOk()->assertSee('0 of 3 used');

        $this->plan($user, 'Custom', 'Weekend route');
        $this->plan($user, 'Visita Iglesia', 'Holy Week');

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('2 of 3 used')
            ->assertSee('1 slot left');
    }

    public function test_the_profile_says_when_the_allowance_is_gone(): void
    {
        $user = $this->devotee();

        foreach (['One', 'Two', 'Three'] as $name) {
            $this->plan($user, 'Custom', $name);
        }

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('3 of 3 used')
            ->assertSee('used all 3');
    }

    /**
     * A devotee who has decided they want Premium should not have to fill up
     * their free slots first to find the button.
     */
    public function test_go_premium_is_offered_before_the_limit_is_reached(): void
    {
        $user = $this->devotee();

        $this->actingAs($user)->get(route('profile'))->assertOk()->assertSee('Go Premium');

        $this->plan($user, 'Custom', 'Weekend route');

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Go Premium')
            ->assertSee(route('upgrade'), false);
    }

    /* ── The allowance is for the life of the account ──────────────────── */

    /**
     * The decision this exists to enforce: three per ACCOUNT, not three at a
     * time. Without it a free devotee plans three, deletes them, plans three
     * more, and never has any reason to pay.
     */
    public function test_deleting_an_itinerary_does_not_give_the_slot_back(): void
    {
        $user = $this->devotee();

        foreach (['One', 'Two', 'Three'] as $name) {
            $this->plan($user, 'Custom', $name);
        }

        $doomed = Itinerary::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->delete(route('plan.destroy', $doomed));

        // Gone from their list...
        $this->assertSame(2, Itinerary::where('user_id', $user->id)->count());

        // ...but still spending a slot.
        $this->assertSame(3, Itinerary::countingAgainstFreeLimit($user->id));

        $this->plan($user, 'Custom', 'Sneaky fourth')->assertSessionHas('error');

        $this->assertSame(3, Itinerary::countingAgainstFreeLimit($user->id));
    }

    /** Deleted, not erased - so the count can be trusted and audited. */
    public function test_a_deleted_itinerary_is_kept_with_its_stops(): void
    {
        $user = $this->devotee();

        $this->plan($user, 'Custom', 'Weekend route');
        $itinerary = Itinerary::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->delete(route('plan.destroy', $itinerary));

        $trashed = Itinerary::withTrashed()->find($itinerary->id);

        $this->assertNotNull($trashed, 'The row was hard deleted, so it can never be counted.');
        $this->assertNotNull($trashed->deleted_at);
        $this->assertSame('Weekend route', $trashed->name);
        $this->assertGreaterThan(0, $trashed->stops()->count(), 'Its stops went with it.');
    }

    /** Completing a pilgrimage was never a way to reclaim a slot either. */
    public function test_completing_an_itinerary_does_not_give_the_slot_back(): void
    {
        $user = $this->devotee();

        foreach (['One', 'Two', 'Three'] as $name) {
            $this->plan($user, 'Custom', $name);
        }

        Itinerary::where('user_id', $user->id)->firstOrFail()->update(['status' => 'Completed']);

        $this->plan($user, 'Custom', 'Fourth')->assertSessionHas('error');
    }

    /** The devotee is told, rather than left to work it out. */
    public function test_the_profile_says_deleting_will_not_return_a_slot(): void
    {
        $user = $this->devotee();
        $this->plan($user, 'Custom', 'Weekend route');

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('not returned if you delete an itinerary');
    }

    public function test_deleting_tells_a_free_devotee_the_slot_is_still_spent(): void
    {
        $user = $this->devotee();
        $this->plan($user, 'Custom', 'Weekend route');

        $this->actingAs($user)
            ->delete(route('plan.destroy', Itinerary::where('user_id', $user->id)->firstOrFail()))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'still counts towards your 3'));
    }

    /** Premium is unlimited, so none of the above applies to them. */
    public function test_a_premium_devotee_is_not_told_about_slots_on_delete(): void
    {
        $user = $this->premiumDevotee();
        $this->plan($user, 'Custom', 'Weekend route');

        $this->actingAs($user)
            ->delete(route('plan.destroy', Itinerary::where('user_id', $user->id)->firstOrFail()))
            ->assertSessionHas('success', 'Itinerary deleted.');
    }

    /** A Premium account is shown its expiry, not a usage bar it has escaped. */
    public function test_a_premium_account_sees_unlimited_and_an_expiry_instead(): void
    {
        $user = $this->devotee();

        $plan = SubscriptionPlan::create([
            'name' => 'Pilgrim Monthly', 'description' => 'One month.',
            'price' => 99.00, 'currency' => 'PHP', 'duration_days' => 30,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Transaction::create([
            'user_id' => $user->id, 'plan_type_id' => $plan->id,
            'amount' => 99.00, 'currency' => 'PHP', 'method' => 'Maya',
            'provider' => 'maya', 'status' => 'Paid', 'reference_no' => 'GIYA-TEST-1',
            'created_at' => now(), 'processed_at' => now(), 'updated_at' => now(),
        ]);

        $this->plan($user, 'Custom', 'Weekend route');

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Unlimited itineraries')
            ->assertSee('Premium until '.now()->addDays(30)->format('M j, Y'))
            ->assertDontSee('of 3 used')
            ->assertDontSee('Go Premium');
    }
}
