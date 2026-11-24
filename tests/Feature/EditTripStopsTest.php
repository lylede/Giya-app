<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\ItineraryType;
use App\Models\Notification;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Changing the stops of a saved trip, also halfway through it: add a church
 * (next or at the end), remove or swap a stop not yet visited, reorder the
 * rest. Premium only; visited stops never change; no free slot is used.
 */
class EditTripStopsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Itinerary $trip;

    /** @var array<int, ItineraryStop> */
    private array $stops = [];

    /** @var array<string, Church> */
    private array $churches = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->premium('lyle@example.com');
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);
        foreach (['Alcantara', 'Aloguinsan', 'Argao', 'Bantayan', 'Bogo', 'Carcar', 'Dalaguete'] as $town) {
            $this->churches[$town] = Church::create([
                'name' => $town.' Parish', 'category_id' => $cat->id, 'location' => $town,
                'latitude' => 10.0, 'longitude' => 123.6, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $type = ItineraryType::firstOrCreate(['name' => 'Custom'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->trip = Itinerary::create([
            'user_id' => $this->user->id, 'itinerary_type_id' => $type->id, 'name' => 'Alcantara Pilgrimage',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Two visited, three to come: the trip in the screenshot.
        foreach (['Alcantara', 'Aloguinsan', 'Argao', 'Bantayan', 'Bogo'] as $i => $town) {
            $this->stops[$town] = ItineraryStop::create([
                'itinerary_id' => $this->trip->id, 'church_id' => $this->churches[$town]->id,
                'stop_order' => $i + 1, 'is_visited' => $i < 2, 'visited_at' => $i < 2 ? now() : null,
            ]);
        }
    }

    private function premium(string $email): User
    {
        $user = User::create([
            'name' => 'Lyle', 'email' => $email, 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $plan = SubscriptionPlan::firstOrCreate(['name' => 'Pilgrim Monthly'], [
            'description' => 'One month.', 'price' => 99.00, 'currency' => 'PHP', 'duration_days' => 30,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Transaction::create([
            'user_id' => $user->id, 'plan_type_id' => $plan->id, 'amount' => 99.00, 'currency' => 'PHP',
            'method' => 'Maya', 'provider' => 'maya', 'status' => 'Paid', 'reference_no' => 'GIYA-'.uniqid(),
            'created_at' => now(), 'processed_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    /** The trip's churches by town, in order. */
    private function order(): array
    {
        return ItineraryStop::where('itinerary_id', $this->trip->id)->orderBy('stop_order')->with('church')->get()
            ->map(fn ($s) => $s->church->location.($s->is_visited ? '*' : ''))->all();
    }

    public function test_a_church_can_be_added_as_the_next_stop(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('plan.stops.add', $this->trip), ['church_id' => $this->churches['Carcar']->id, 'position' => 'next'])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('finished', false);

        $this->assertSame(['Alcantara*', 'Aloguinsan*', 'Carcar', 'Argao', 'Bantayan', 'Bogo'], $this->order());
    }

    public function test_a_church_can_be_added_at_the_end(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('plan.stops.add', $this->trip), ['church_id' => $this->churches['Carcar']->id, 'position' => 'end'])
            ->assertOk();

        $this->assertSame(['Alcantara*', 'Aloguinsan*', 'Argao', 'Bantayan', 'Bogo', 'Carcar'], $this->order());
    }

    public function test_a_church_already_on_the_trip_is_not_added_twice(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('plan.stops.add', $this->trip), ['church_id' => $this->churches['Alcantara']->id, 'position' => 'end'])
            ->assertStatus(422)->assertJsonPath('message', __('giya.plan.edit_already'));

        $this->assertCount(5, $this->order());
    }

    public function test_a_stop_not_yet_visited_can_be_removed_and_leaves_the_trip(): void
    {
        $this->actingAs($this->user)
            ->deleteJson(route('plan.stops.remove', [$this->trip, $this->stops['Argao']]))
            ->assertOk();

        $this->assertSame(['Alcantara*', 'Aloguinsan*', 'Bantayan', 'Bogo'], $this->order());
        $this->assertDatabaseMissing('itinerary_stops', ['id' => $this->stops['Argao']->id]);
    }

    public function test_a_visited_stop_cannot_be_removed_or_swapped(): void
    {
        $this->actingAs($this->user)
            ->deleteJson(route('plan.stops.remove', [$this->trip, $this->stops['Alcantara']]))
            ->assertStatus(422)->assertJsonPath('message', __('giya.plan.edit_visited'));
        $this->actingAs($this->user)
            ->postJson(route('plan.stops.swap', [$this->trip, $this->stops['Alcantara']]), ['church_id' => $this->churches['Carcar']->id])
            ->assertStatus(422);

        $this->assertSame(['Alcantara*', 'Aloguinsan*', 'Argao', 'Bantayan', 'Bogo'], $this->order());
    }

    public function test_the_current_stop_can_be_swapped_for_another_church(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('plan.stops.swap', [$this->trip, $this->stops['Argao']]), ['church_id' => $this->churches['Dalaguete']->id])
            ->assertOk();

        $this->assertSame(['Alcantara*', 'Aloguinsan*', 'Dalaguete', 'Bantayan', 'Bogo'], $this->order());
    }

    public function test_the_stops_still_to_come_can_be_reordered(): void
    {
        $ids = [$this->stops['Bogo']->id, $this->stops['Argao']->id, $this->stops['Bantayan']->id];

        $this->actingAs($this->user)
            ->postJson(route('plan.stops.reorder', $this->trip), ['stops' => $ids])
            ->assertOk();

        $this->assertSame(['Alcantara*', 'Aloguinsan*', 'Bogo', 'Argao', 'Bantayan'], $this->order());
    }

    public function test_a_reorder_that_adds_or_loses_a_stop_is_refused(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('plan.stops.reorder', $this->trip), ['stops' => [$this->stops['Bogo']->id, $this->stops['Argao']->id]])
            ->assertStatus(422)->assertJsonPath('message', __('giya.plan.edit_stale'));
        // A visited stop cannot be moved in among the ones to come.
        $this->actingAs($this->user)
            ->postJson(route('plan.stops.reorder', $this->trip), ['stops' => [$this->stops['Alcantara']->id, $this->stops['Argao']->id, $this->stops['Bantayan']->id, $this->stops['Bogo']->id]])
            ->assertStatus(422);

        $this->assertSame(['Alcantara*', 'Aloguinsan*', 'Argao', 'Bantayan', 'Bogo'], $this->order());
    }

    public function test_removing_the_last_stops_to_come_finishes_the_trip_and_asks_for_ratings(): void
    {
        foreach (['Argao', 'Bantayan'] as $town) {
            $this->actingAs($this->user)->deleteJson(route('plan.stops.remove', [$this->trip, $this->stops[$town]]))->assertJsonPath('finished', false);
        }
        $this->actingAs($this->user)
            ->deleteJson(route('plan.stops.remove', [$this->trip, $this->stops['Bogo']]))
            ->assertOk()->assertJsonPath('finished', true);

        $this->assertSame('Completed', $this->trip->fresh()->status);
        $this->assertSame(1, Notification::where('dedupe_key', 'rate:'.$this->trip->id)->count());
    }

    public function test_a_trip_keeps_at_least_one_stop(): void
    {
        $type = ItineraryType::firstOrCreate(['name' => 'Custom']);
        $solo = Itinerary::create(['user_id' => $this->user->id, 'itinerary_type_id' => $type->id, 'name' => 'Solo',
            'status' => 'Upcoming', 'created_at' => now(), 'updated_at' => now()]);
        $only = ItineraryStop::create(['itinerary_id' => $solo->id, 'church_id' => $this->churches['Carcar']->id, 'stop_order' => 1, 'is_visited' => false]);

        $this->actingAs($this->user)
            ->deleteJson(route('plan.stops.remove', [$solo, $only]))
            ->assertStatus(422)->assertJsonPath('message', __('giya.plan.edit_last'));
    }

    public function test_a_finished_trip_cannot_change(): void
    {
        $this->trip->update(['status' => 'Completed']);

        $this->actingAs($this->user)
            ->postJson(route('plan.stops.add', $this->trip), ['church_id' => $this->churches['Carcar']->id, 'position' => 'end'])
            ->assertStatus(422)->assertJsonPath('message', __('giya.plan.edit_finished'));
    }

    public function test_free_accounts_get_the_premium_offer_and_nothing_changes(): void
    {
        $free = User::create([
            'name' => 'Free', 'email' => 'free@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->trip->update(['user_id' => $free->id]);

        $this->actingAs($free)
            ->postJson(route('plan.stops.add', $this->trip), ['church_id' => $this->churches['Carcar']->id, 'position' => 'next'])
            ->assertForbidden()->assertJsonPath('reason', 'premium')->assertJsonPath('upgrade_url', route('upgrade'));
        $this->actingAs($free)
            ->deleteJson(route('plan.stops.remove', [$this->trip, $this->stops['Argao']]))
            ->assertForbidden();

        $this->assertSame(['Alcantara*', 'Aloguinsan*', 'Argao', 'Bantayan', 'Bogo'], $this->order());

        // The trip page shows the button with the Premium offer behind it.
        $this->actingAs($free)->get(route('plan.show', $this->trip))->assertOk()
            ->assertSee('data-edit-stops', false)
            ->assertSee(__('giya.plan.edit_premium_title'))
            ->assertSee(route('upgrade'), false);
    }

    public function test_someone_else_cannot_change_your_trip(): void
    {
        $other = $this->premium('other@example.com');

        $this->actingAs($other)
            ->postJson(route('plan.stops.add', $this->trip), ['church_id' => $this->churches['Carcar']->id, 'position' => 'end'])
            ->assertForbidden();
        $this->actingAs($other)
            ->deleteJson(route('plan.stops.remove', [$this->trip, $this->stops['Argao']]))
            ->assertForbidden();
    }

    public function test_a_stop_from_another_trip_cannot_be_used(): void
    {
        $type = ItineraryType::firstOrCreate(['name' => 'Custom']);
        $mine = Itinerary::create(['user_id' => $this->user->id, 'itinerary_type_id' => $type->id, 'name' => 'Other',
            'status' => 'Upcoming', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->user)
            ->deleteJson(route('plan.stops.remove', [$mine, $this->stops['Argao']]))
            ->assertNotFound();
    }

    public function test_editing_does_not_use_a_free_itinerary(): void
    {
        $before = Itinerary::withTrashed()->where('user_id', $this->user->id)->count();

        $this->actingAs($this->user)
            ->postJson(route('plan.stops.add', $this->trip), ['church_id' => $this->churches['Carcar']->id, 'position' => 'end'])
            ->assertOk();

        $this->assertSame($before, Itinerary::withTrashed()->where('user_id', $this->user->id)->count());
    }

    public function test_the_trip_page_offers_premium_users_the_editor(): void
    {
        $this->actingAs($this->user)->get(route('plan.show', $this->trip))->assertOk()
            ->assertSee('data-edit-stops', false)
            ->assertSee('id="esSearch"', false)
            ->assertSee('giya-edit-stops.js', false)
            ->assertDontSee(__('giya.plan.edit_premium_title'));

        // Not on a finished trip.
        $this->trip->update(['status' => 'Completed']);
        $this->actingAs($this->user)->get(route('plan.show', $this->trip))->assertOk()
            ->assertDontSee('data-edit-stops', false);
    }
}
