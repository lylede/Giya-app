<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Recurrence;
use App\Support\Reminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** "Every 4th Sunday of January" and friends, from the admin form to the reminder. */
class ScheduleRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 10, 29, 9));

        $cat = ChurchCategory::create(['name' => 'Basilica', 'created_at' => now(), 'updated_at' => now()]);
        $this->church = Church::create(['name' => 'Basilica del Santo Nino', 'category_id' => $cat->id,
            'location' => 'Cebu City', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->admin = $this->user('admin@example.com', 'admin');
    }

    private function user(string $email, string $role = 'devotee'): User
    {
        return User::create(['name' => 'Test', 'email' => $email, 'password_hash' => bcrypt('secret'),
            'role' => $role, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function save(array $fields)
    {
        return $this->actingAs($this->admin)->post(route('admin.schedules.store'), array_merge([
            'church_id' => $this->church->id, 'event_name' => 'Fiesta', 'event_type' => 'Feast Day',
            'status' => 'Published', 'start_time' => '08:00',
        ], $fields));
    }

    /* ── The dates ─────────────────────────────────────────────────── */

    public function test_nth_weekday_dates_are_right(): void
    {
        $this->assertSame('2027-01-24', Recurrence::nthWeekday(2027, 1, 4, 0)->toDateString()); // 4th Sun, Jan 2027
        $this->assertSame('2027-01-17', Recurrence::nthWeekday(2027, 1, 3, 0)->toDateString()); // Sinulog 2027
        $this->assertSame('2028-01-16', Recurrence::nthWeekday(2028, 1, 3, 0)->toDateString()); // Sinulog 2028
        $this->assertSame('2026-11-06', Recurrence::nthWeekday(2026, 11, 1, 5)->toDateString()); // 1st Friday
        $this->assertSame('2026-11-29', Recurrence::nthWeekday(2026, 11, -1, 0)->toDateString()); // last Sunday
    }

    public function test_next_rolls_into_next_year_once_this_years_date_has_passed(): void
    {
        $rule = Recurrence::fromInput('yearly', [], 4, 0, 1);

        $this->assertSame('2027-01-24', Recurrence::next($rule, Carbon::parse('2027-01-24'))->toDateString());
        $this->assertSame('2028-01-23', Recurrence::next($rule, Carbon::parse('2027-01-25'))->toDateString());
    }

    /* ── The admin form ────────────────────────────────────────────── */

    public function test_a_yearly_festival_saves_with_its_rule_label_and_next_date(): void
    {
        $this->save(['repeat_type' => 'yearly', 'repeat_nth' => 4, 'repeat_weekday' => 0, 'repeat_month' => 1])
            ->assertSessionHasNoErrors();

        $s = Schedule::first();
        $this->assertTrue($s->is_recurring);
        $this->assertSame(['type' => 'yearly', 'nth' => 4, 'weekday' => 0, 'month' => 1], $s->recurrence_rule);
        $this->assertSame('Yearly - 4th Sunday of January', $s->recurrence);
        $this->assertSame('2027-01-24', $s->schedule_date->toDateString());
        $this->assertSame('Yearly - 4th Sunday of January (Jan 24, 2027)', $s->day_label);
    }

    public function test_several_weekdays_and_monthly_rules_save(): void
    {
        $this->save(['event_name' => 'Mass', 'event_type' => 'Mass', 'repeat_type' => 'weekly', 'repeat_days' => [5, 1, 3]])
            ->assertSessionHasNoErrors();
        $this->save(['event_name' => 'First Friday', 'event_type' => 'Mass', 'repeat_type' => 'monthly', 'repeat_nth' => 1, 'repeat_weekday' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame('Weekly (Monday, Wednesday, Friday)', Schedule::where('event_name', 'Mass')->value('recurrence'));
        $first = Schedule::where('event_name', 'First Friday')->first();
        $this->assertSame('Monthly - 1st Friday', $first->recurrence);
        $this->assertSame('2026-11-06', $first->schedule_date->toDateString());
    }

    public function test_the_form_explains_what_is_missing(): void
    {
        $this->save(['repeat_type' => 'weekly'])->assertSessionHasErrors('repeat_days');
        $this->save(['repeat_type' => 'yearly', 'repeat_nth' => 4, 'repeat_weekday' => 0])->assertSessionHasErrors('repeat_month');
        $this->save(['repeat_type' => 'none'])->assertSessionHasErrors('schedule_date');
    }

    public function test_the_old_repeats_on_value_still_works(): void
    {
        $this->save(['event_type' => 'Mass', 'recurrence' => 'Sunday'])->assertSessionHasNoErrors();

        $this->assertSame('Weekly (Sunday)', Schedule::first()->recurrence);
    }

    /* ── Reminders, every year ─────────────────────────────────────── */

    public function test_a_yearly_festival_reminds_followers_a_week_before_every_year(): void
    {
        $lyle = $this->user('lyle@example.com');
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church));
        $this->save(['repeat_type' => 'yearly', 'repeat_nth' => 4, 'repeat_weekday' => 0, 'repeat_month' => 1]);
        $reminders = fn () => Notification::where('user_id', $lyle->id)->where('dedupe_key', 'like', 'reminder:%')->count();

        $this->travelTo(Carbon::create(2027, 1, 17, 9));          // a week before Jan 24, 2027
        $this->assertSame(1, Reminders::deliverFor($lyle));
        $this->assertSame(0, Reminders::deliverFor($lyle), 'Only once.');

        $this->travelTo(Carbon::create(2028, 1, 16, 9));          // a week before Jan 23, 2028
        Cache::flush();
        $this->assertSame(1, Reminders::deliverFor($lyle), 'Again the next year.');
        $this->assertSame('2028-01-23', Schedule::first()->fresh()->schedule_date->toDateString());
        $this->assertSame(2, $reminders());
    }

    public function test_weekly_and_monthly_masses_send_no_reminders(): void
    {
        $lyle = $this->user('lyle@example.com');
        $this->actingAs($lyle)->postJson(route('churches.notify', $this->church));
        $this->save(['event_type' => 'Mass', 'repeat_type' => 'monthly', 'repeat_nth' => 1, 'repeat_weekday' => 5]);

        $this->assertSame(0, Reminders::deliverFor($lyle));
    }

    /* ── Home ──────────────────────────────────────────────────────── */

    public function test_home_lists_upcoming_activities_soonest_first_and_skips_the_past(): void
    {
        Schedule::create(['church_id' => $this->church->id, 'event_name' => 'Old Novena', 'event_type' => 'Novena',
            'schedule_date' => '2026-01-01', 'status' => 'Published', 'created_at' => now()]);
        $this->save(['event_name' => 'Fiesta Yearly', 'repeat_type' => 'yearly', 'repeat_nth' => 4, 'repeat_weekday' => 0, 'repeat_month' => 1]);
        $this->save(['event_name' => 'Soon Procession', 'event_type' => 'Procession', 'schedule_date' => '2026-11-05']);

        $html = $this->actingAs($this->user('lyle@example.com'))->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Old Novena', $html);
        $this->assertLessThan(strpos($html, 'Fiesta Yearly'), strpos($html, 'Soon Procession'));
    }
}
