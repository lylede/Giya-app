<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Feedback;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\ItineraryType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** An automatic check-in leaves "You have arrived" in the bell; Undo takes it back out. */
class ArrivalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private array $stops = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cat  = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);
        $type = ItineraryType::firstOrCreate(['name' => 'Custom'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $trip = Itinerary::create([
            'user_id' => $this->user->id, 'itinerary_type_id' => $type->id, 'name' => 'Liloan Pilgrimage',
            'status' => 'Upcoming', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['San Fernando Rey Parish', 'San Sebastian Martyr Church'] as $i => $name) {
            $church = Church::create([
                'name' => $name, 'category_id' => $cat->id, 'location' => 'Cebu',
                'latitude' => 10.4, 'longitude' => 124.0, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->stops[] = ItineraryStop::create([
                'itinerary_id' => $trip->id, 'church_id' => $church->id, 'stop_order' => $i + 1, 'is_visited' => false,
            ]);
        }
    }

    public function test_an_automatic_check_in_notifies_with_the_next_church(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('plan.stop.visited'), ['stop_id' => $this->stops[0]->id, 'auto' => true])
            ->assertOk();

        $note = Notification::where('user_id', $this->user->id)->sole();
        $this->assertSame(__('giya.plan.arrive_note_title', ['church' => 'San Fernando Rey Parish']), $note->title);
        $this->assertStringContainsString('San Sebastian Martyr Church', $note->message);
        $this->assertFalse($note->is_read);

        // It is what the bell reads.
        $this->actingAs($this->user)->getJson('/notifications')->assertJsonPath('unread', 1);
    }

    public function test_the_last_stop_says_the_trip_is_done(): void
    {
        foreach ($this->stops as $stop) {
            $this->actingAs($this->user)->postJson(route('plan.stop.visited'), ['stop_id' => $stop->id, 'auto' => true]);
        }

        $last = Notification::where('dedupe_key', 'arrive:'.$this->stops[1]->id)->sole();
        $this->assertStringContainsString('Liloan Pilgrimage', $last->message);
    }

    public function test_marking_by_hand_does_not_notify(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('plan.stop.visited'), ['stop_id' => $this->stops[0]->id])
            ->assertOk();

        $this->assertSame(0, Notification::count(), 'The devotee pressed the button; they know.');
    }

    public function test_undo_removes_the_arrival_note(): void
    {
        $this->actingAs($this->user)->postJson(route('plan.stop.visited'), ['stop_id' => $this->stops[0]->id, 'auto' => true]);
        $this->actingAs($this->user)->postJson(route('plan.stop.visited'), ['stop_id' => $this->stops[0]->id, 'undo' => true])->assertOk();

        $this->assertSame(0, Notification::count());
    }

    /* ── Rate your pilgrimage ─────────────────────────────────────────── */

    private function finishTrip(): void
    {
        foreach ($this->stops as $stop) {
            $this->actingAs($this->user)->postJson(route('plan.stop.visited'), ['stop_id' => $stop->id]);
        }
    }

    public function test_finishing_a_trip_leaves_a_rate_reminder_in_the_bell(): void
    {
        $this->finishTrip();

        $note = Notification::where('dedupe_key', 'rate:'.$this->stops[0]->itinerary_id)->sole();
        $this->assertSame(__('giya.plan.rate_note_title', ['trip' => 'Liloan Pilgrimage']), $note->title);
        $this->assertStringContainsString('rate=1', $note->url);

        // The link opens the trip with the rating sheet ready.
        $html = $this->actingAs($this->user)->get($note->url)->assertOk()->getContent();
        $this->assertStringContainsString('id="rateModal"', $html);
        $this->assertStringContainsString('if (true) setTimeout(openRate', $html);
    }

    public function test_ratings_are_saved_stars_count_at_once_and_comments_wait(): void
    {
        $this->finishTrip();
        $trip = $this->stops[0]->itinerary_id;

        $this->actingAs($this->user)->postJson(route('plan.ratings', $trip), ['ratings' => [
            $this->stops[0]->id => ['rating' => 5, 'comment' => ''],
            $this->stops[1]->id => ['rating' => 3, 'comment' => 'Crowded but beautiful.'],
        ]])->assertOk()->assertJsonPath('saved', 2);

        $this->assertSame('Approved', Feedback::where('church_id', $this->stops[0]->church_id)->value('status'), 'Stars only: shown at once.');
        $this->assertSame('Pending', Feedback::where('church_id', $this->stops[1]->church_id)->value('status'), 'A comment waits for an admin.');

        // The stars already count on the church.
        $this->assertSame(3.0, (float) $this->stops[1]->church->fresh()->rating);

        // And the reminder has gone from the bell.
        $this->assertSame(0, Notification::where('dedupe_key', 'rate:'.$trip)->count());

        // Rating again updates rather than duplicates.
        $this->actingAs($this->user)->postJson(route('plan.ratings', $trip), ['ratings' => [
            $this->stops[0]->id => ['rating' => 4],
        ]])->assertOk();
        $this->assertSame(2, Feedback::count());
        $this->assertSame(4, Feedback::where('church_id', $this->stops[0]->church_id)->value('rating'));
    }

    public function test_someone_else_cannot_rate_your_trip(): void
    {
        $this->finishTrip();
        $other = User::create([
            'name' => 'Other', 'email' => 'other@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($other)->postJson(route('plan.ratings', $this->stops[0]->itinerary_id), ['ratings' => [
            $this->stops[0]->id => ['rating' => 1],
        ]])->assertForbidden();
    }

    /* ── Saved Itineraries ────────────────────────────────────────────── */

    public function test_plan_links_to_saved_itineraries_instead_of_listing_trips(): void
    {
        $html = $this->actingAs($this->user)->get(route('plan.hub'))->assertOk()->getContent();

        $this->assertStringContainsString('class="hub-saved"', $html);
        $this->assertStringContainsString(__('giya.hub.saved_count', ['count' => 1]), $html);
        $this->assertStringNotContainsString(__('giya.hub.your_trips'), $html, 'The list lives on Saved Itineraries now.');
    }

    public function test_saved_itineraries_groups_trips_and_shows_your_stars(): void
    {
        $this->finishTrip();
        $trip = $this->stops[0]->itinerary_id;
        $this->actingAs($this->user)->postJson(route('plan.ratings', $trip), ['ratings' => [
            $this->stops[0]->id => ['rating' => 5], $this->stops[1]->id => ['rating' => 4],
        ]]);

        $html = $this->actingAs($this->user)->get(route('plan.index', ['trip' => $trip]))->assertOk()->getContent();

        $this->assertStringContainsString(__('giya.plan.my_title'), $html);
        $this->assertStringContainsString(e(__('giya.hub.you_rated', ['stars' => '4.5'])), $html);
        $this->assertStringContainsString('id="trip-'.$trip.'" class="card is-highlight"', $html, 'The trip just rated is outlined.');
    }

    public function test_after_rating_the_button_opens_saved_itineraries(): void
    {
        $html = $this->actingAs($this->user)->get(route('plan.show', $this->stops[0]->itinerary_id))->getContent();

        $this->assertStringContainsString(e(route('plan.index', ['trip' => $this->stops[0]->itinerary_id])), $html);
        $this->assertStringContainsString(__('giya.hub.view_saved'), $html);
    }
}
