<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\DevoteePreference;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Reminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Notify me" on a church page, and the reminder a week before each event.
 */
class ChurchNotifyAndRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2027, 1, 1)->setTime(9, 0));

        $cat = ChurchCategory::create(['name' => 'Basilica', 'created_at' => now(), 'updated_at' => now()]);
        $this->church = Church::create([
            'name' => 'Basilica del Santo Nino', 'category_id' => $cat->id, 'location' => 'Cebu City',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->admin = $this->user('admin@example.com', 'admin');
    }

    private function user(string $email, string $role = 'devotee'): User
    {
        return User::create([
            'name' => 'Test', 'email' => $email, 'password_hash' => bcrypt('secret'),
            'role' => $role, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Post a schedule through the admin form, as a person would. */
    private function postSchedule(array $fields): void
    {
        $this->actingAs($this->admin)->post(route('admin.schedules.store'), array_merge([
            'church_id'  => $this->church->id,
            'event_name' => 'Fiesta Senor',
            'event_type' => 'Feast Day',
            'status'     => 'Published',
            'start_time' => '09:00',
        ], $fields))->assertSessionHasNoErrors();
    }

    private function notificationsFor(User $user)
    {
        return Notification::where('user_id', $user->id)->orderBy('id')->get();
    }

    /* ── The button ─────────────────────────────────────────────────── */

    public function test_the_church_page_has_a_notify_me_button(): void
    {
        $this->actingAs($this->user('lyle@example.com'))
            ->get(route('churches.show', $this->church))
            ->assertOk()
            ->assertSee('Notify me');
    }

    public function test_pressing_notify_me_means_the_next_event_reaches_you(): void
    {
        $lyle = $this->user('lyle@example.com');

        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church))
            ->assertOk()->assertJson(['notify' => true]);

        $this->postSchedule(['schedule_date' => '2027-02-01']);

        $this->assertCount(1, $this->notificationsFor($lyle));
        $this->assertSame('Fiesta Senor', $this->notificationsFor($lyle)->first()->title);
    }

    public function test_without_the_button_or_a_save_nobody_is_told(): void
    {
        $lyle = $this->user('lyle@example.com');

        $this->postSchedule(['schedule_date' => '2027-02-01']);

        $this->assertCount(0, $this->notificationsFor($lyle));
    }

    public function test_a_saved_church_notifies_by_default_and_the_button_can_turn_it_off(): void
    {
        $lyle = $this->user('lyle@example.com');
        Favorite::create(['user_id' => $lyle->id, 'church_id' => $this->church->id, 'is_active' => true, 'created_at' => now()]);

        $this->actingAs($lyle)->get(route('churches.show', $this->church))->assertSee('Notifying you');

        // Pressing it turns it OFF for a saved church.
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church))->assertJson(['notify' => false]);

        $this->postSchedule(['schedule_date' => '2027-02-01']);
        $this->assertCount(0, $this->notificationsFor($lyle));
    }

    public function test_the_feast_day_switch_in_the_profile_is_respected(): void
    {
        $lyle = $this->user('lyle@example.com');
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church));
        DevoteePreference::create(['user_id' => $lyle->id, 'notify_feast_day' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->postSchedule(['schedule_date' => '2027-02-01']);
        $this->assertCount(0, $this->notificationsFor($lyle), 'Feast days are switched off.');

        $this->postSchedule(['schedule_date' => '2027-02-02', 'event_name' => 'Novena', 'event_type' => 'Novena']);
        $this->assertCount(1, $this->notificationsFor($lyle), 'Other events still come through.');
    }

    /* ── The reminder a week before ─────────────────────────────────── */

    public function test_a_reminder_arrives_a_week_before_and_only_once(): void
    {
        $lyle = $this->user('lyle@example.com');
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church));

        $this->postSchedule(['schedule_date' => '2027-01-20']);      // 19 days away
        $this->assertCount(1, $this->notificationsFor($lyle), 'The "new event" notice.');

        // 10 days before: not yet.
        $this->travelTo(now()->setDate(2027, 1, 10));
        $this->assertSame(0, Reminders::deliverFor($lyle));

        // 7 days before: the reminder.
        $this->travelTo(now()->setDate(2027, 1, 13));
        $this->assertSame(1, Reminders::deliverFor($lyle));

        // And never again.
        $this->travelTo(now()->setDate(2027, 1, 15));
        $this->assertSame(0, Reminders::deliverFor($lyle));

        $reminder = $this->notificationsFor($lyle)->last();
        $this->assertSame('Fiesta Senor', $reminder->title);
        $this->assertStringContainsString('in 5 days', Reminders::message(Schedule::first()));
        $this->assertStringContainsString('Basilica del Santo Nino', $reminder->message);
        $this->assertCount(2, $this->notificationsFor($lyle));
    }

    public function test_an_event_posted_inside_the_week_is_not_announced_twice(): void
    {
        $lyle = $this->user('lyle@example.com');
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church));

        $this->postSchedule(['schedule_date' => '2027-01-04']);      // 3 days away

        $this->assertSame(0, Reminders::deliverFor($lyle));
        $this->assertCount(1, $this->notificationsFor($lyle));
    }

    public function test_moving_the_date_brings_a_new_reminder(): void
    {
        $lyle = $this->user('lyle@example.com');
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church));
        $this->postSchedule(['schedule_date' => '2027-01-05']);
        $schedule = Schedule::first();

        $this->actingAs($this->admin)->post(route('admin.schedules.store'), [
            'schedule_id' => $schedule->id, 'church_id' => $this->church->id,
            'event_name' => 'Fiesta Senor', 'event_type' => 'Feast Day', 'status' => 'Published',
            'start_time' => '09:00', 'schedule_date' => '2027-01-06',
        ]);

        $this->assertSame(1, Notification::where('user_id', $lyle->id)->where('message', 'like', 'Updated:%')->count());
        $this->assertSame(0, Reminders::deliverFor($lyle), 'The update already covers the new date.');
    }

    public function test_a_weekly_mass_never_sends_reminders(): void
    {
        $lyle = $this->user('lyle@example.com');
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church));

        Schedule::create([
            'church_id' => $this->church->id, 'event_name' => 'Sunday Mass', 'event_type' => 'Mass',
            'is_recurring' => true, 'recurrence' => 'Weekly', 'schedule_date' => '2027-01-03',
            'start_time' => '06:00', 'status' => 'Published', 'created_at' => now(),
        ]);

        $this->assertSame(0, Reminders::deliverFor($lyle));
    }

    public function test_drafts_and_past_events_send_no_reminders(): void
    {
        $lyle = $this->user('lyle@example.com');
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church));

        foreach ([['2027-01-05', 'Draft'], ['2026-12-25', 'Published']] as [$date, $status]) {
            Schedule::create([
                'church_id' => $this->church->id, 'event_name' => 'X', 'event_type' => 'Feast Day',
                'schedule_date' => $date, 'status' => $status, 'created_at' => now(),
            ]);
        }

        $this->assertSame(0, Reminders::deliverFor($lyle));
    }

    /** End to end: the devotee just opens GIYA and the reminder is in the bell. */
    public function test_opening_any_page_delivers_the_reminder_to_the_bell(): void
    {
        $lyle = $this->user('lyle@example.com');
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church));
        Schedule::create([
            'church_id' => $this->church->id, 'event_name' => 'Fiesta Senor', 'event_type' => 'Feast Day',
            'schedule_date' => '2027-01-07', 'start_time' => '09:00', 'status' => 'Published', 'created_at' => now(),
        ]);

        // Pressing the button above already ran the check (nothing was due
        // then); it runs at most every few minutes per devotee.
        $this->travel(6)->minutes();

        $this->actingAs($lyle)->get(route('home'))->assertOk();

        $this->actingAs($lyle)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('items.0.title', 'Fiesta Senor')
            ->assertJsonPath('unread', 1);
    }
}
