<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin schedules, one church at a time: the timetable, copying, and bulk changes. */
class ScheduleTimetableTest extends TestCase
{
    use RefreshDatabase;

    private Church $basilica;

    private Church $cathedral;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = ChurchCategory::create(['name' => 'Church', 'created_at' => now(), 'updated_at' => now()]);
        $make = fn ($name) => Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => 'Cebu City',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->basilica  = $make('Basilica del Santo Nino');
        $this->cathedral = $make('Cebu Metropolitan Cathedral');
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function mass(Church $church, string $time, array $rule, string $status = 'Published'): Schedule
    {
        return Schedule::create([
            'church_id' => $church->id, 'event_name' => 'Mass', 'event_type' => 'Mass', 'start_time' => $time,
            'is_recurring' => true, 'recurrence_rule' => $rule, 'recurrence' => \App\Support\Recurrence::label($rule),
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_by_church_is_the_default_and_shows_one_churchs_week(): void
    {
        $this->mass($this->basilica, '06:00', ['type' => 'weekly', 'days' => [0]]);
        $this->mass($this->cathedral, '07:00', ['type' => 'weekly', 'days' => [1]]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.schedules', ['church_id' => $this->basilica->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('class="sm-tab is-on"', $html);
        $this->assertStringContainsString('6:00 AM', $html);
        $this->assertStringNotContainsString('7:00 AM', $html);
        $this->assertSame(1, substr_count($html, 'tt-day is-on'));
    }

    public function test_ticking_a_day_adds_it_and_seven_days_make_it_daily(): void
    {
        $s = $this->mass($this->basilica, '06:00', ['type' => 'weekly', 'days' => [0, 1, 2, 3, 4, 5]]);

        $this->actingAs($this->admin)->post(route('admin.schedules.days', $s), ['day' => 6])->assertRedirect();

        $this->assertSame('daily', $s->fresh()->recurrence_rule['type']);
        $this->assertSame('Daily', $s->fresh()->recurrence);
    }

    public function test_unticking_removes_a_day_but_never_the_last_one(): void
    {
        $s = $this->mass($this->basilica, '06:00', ['type' => 'weekly', 'days' => [0, 3]]);

        $this->actingAs($this->admin)->post(route('admin.schedules.days', $s), ['day' => 3]);
        $this->assertSame([0], $s->fresh()->weekdays());

        $this->actingAs($this->admin)->post(route('admin.schedules.days', $s), ['day' => 0])
            ->assertSessionHas('warning');
        $this->assertSame([0], $s->fresh()->weekdays());
    }

    public function test_copying_a_schedule_brings_it_over_as_draft(): void
    {
        $this->mass($this->basilica, '06:00', ['type' => 'weekly', 'days' => [0]]);
        $this->mass($this->basilica, '17:00', ['type' => 'daily']);

        $this->actingAs($this->admin)->post(route('admin.schedules.copy'), [
            'from_church_id' => $this->basilica->id, 'to_church_id' => $this->cathedral->id,
        ])->assertRedirect(route('admin.schedules', ['view' => 'church', 'church_id' => $this->cathedral->id]));

        $copies = Schedule::where('church_id', $this->cathedral->id)->get();
        $this->assertCount(2, $copies);
        $this->assertSame(['Draft'], $copies->pluck('status')->unique()->values()->all());
        $this->assertSame(2, Schedule::where('church_id', $this->basilica->id)->count());
    }

    public function test_ticked_rows_can_be_published_drafted_or_deleted_together(): void
    {
        $a = $this->mass($this->basilica, '06:00', ['type' => 'daily'], 'Draft');
        $b = $this->mass($this->cathedral, '07:00', ['type' => 'daily'], 'Draft');
        $c = $this->mass($this->cathedral, '08:00', ['type' => 'daily'], 'Draft');

        $this->actingAs($this->admin)->post(route('admin.schedules.bulk'), ['ids' => [$a->id, $b->id], 'action' => 'publish']);
        $this->assertSame('Published', $a->fresh()->status);
        $this->assertSame('Draft', $c->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.schedules.bulk'), ['ids' => [$a->id, $c->id], 'action' => 'delete']);
        $this->assertNull(Schedule::find($a->id));
        $this->assertNotNull(Schedule::find($b->id));
    }

    public function test_the_full_list_still_works_with_its_filters(): void
    {
        $this->mass($this->cathedral, '07:00', ['type' => 'weekly', 'days' => [1]]);

        $this->actingAs($this->admin)->get(route('admin.schedules', ['view' => 'all']))
            ->assertOk()->assertSee('id="bulkAll"', false)->assertSee('Cebu Metropolitan Cathedral');
    }

    public function test_destinations_and_the_church_page_link_to_the_timetable(): void
    {
        $link = route('admin.schedules', ['view' => 'church', 'church_id' => $this->basilica->id]);

        $this->actingAs($this->admin)->get(route('admin.destinations'))->assertOk()->assertSee(e($link), false);
        $this->actingAs($this->admin)->get(route('churches.show', $this->basilica))->assertOk()->assertSee(e($link), false);
    }

    public function test_devotees_cannot_change_schedules(): void
    {
        $s = $this->mass($this->basilica, '06:00', ['type' => 'weekly', 'days' => [0]]);
        $devotee = User::create(['name' => 'D', 'email' => 'd@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($devotee)->post(route('admin.schedules.days', $s), ['day' => 1])->assertForbidden();
        $this->assertSame([0], $s->fresh()->weekdays());
    }
}
