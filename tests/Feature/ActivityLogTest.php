<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private int $people = 0;

    private function person(string $role, string $name = null): User
    {
        return User::create([
            'name' => $name ?? ucfirst($role), 'email' => $role.(++$this->people).'@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => $role, 'status' => 'Active', 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function church(): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now()]);

        return Church::create([
            'name' => 'Simala Shrine', 'category_id' => $cat->id, 'location' => 'Sibonga', 'municipality' => 'Sibonga',
            'latitude' => 10.03, 'longitude' => 123.57, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_change_is_logged_with_who_what_and_the_result(): void
    {
        $admin = $this->person('admin', 'Lyle');
        $maria = $this->person('user', 'Maria');

        $this->actingAs($admin)->patch(route('admin.users.update', $maria), [
            'name' => 'Maria', 'email' => $maria->email, 'role' => 'user', 'status' => 'Suspended',
        ])->assertSessionHas('success');

        $entry = ActivityLog::sole();
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame('Lyle', $entry->admin_name);
        $this->assertSame('Edited a user', $entry->action);
        $this->assertSame('Maria ('.$maria->email.')', $entry->subject);
        $this->assertSame('Maria updated.', $entry->details);
    }

    public function test_deleting_keeps_the_name_of_what_was_deleted(): void
    {
        $admin = $this->person('admin');
        $maria = $this->person('user', 'Maria');
        $church = $this->church();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $maria));
        $this->actingAs($admin)->patch(route('admin.destinations.toggle', $church));

        $this->assertSame(['Deleted a user', 'Showed or hid a destination'], ActivityLog::orderBy('id')->pluck('action')->all());
        $this->assertSame('Simala Shrine', ActivityLog::orderByDesc('id')->first()->subject);
        $this->assertStringContainsString('Maria', ActivityLog::orderBy('id')->first()->subject);
    }

    public function test_a_refused_action_is_not_logged(): void
    {
        $admin = $this->person('admin');
        $maria = $this->person('user');

        // Validation error.
        $this->actingAs($admin)->patch(route('admin.users.update', $maria), ['name' => ''])->assertSessionHasErrors();
        // Refused by the controller.
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_looking_is_not_logged_but_downloading_is(): void
    {
        $admin = $this->person('admin');

        $this->actingAs($admin)->get(route('admin.users'))->assertOk();
        $this->actingAs($admin)->get(route('admin.activity'))->assertOk();
        $this->assertSame(0, ActivityLog::count());

        $this->actingAs($admin)->get(route('admin.users.export'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports.download', 'users'))->assertOk();

        $this->assertSame(['Downloaded the users list', 'Downloaded a report'], ActivityLog::orderBy('id')->pluck('action')->all());
        $this->assertSame('users', ActivityLog::orderByDesc('id')->first()->subject);
    }

    public function test_maintenance_announcements_and_feedback_are_logged(): void
    {
        $admin = $this->person('admin');
        $feedback = Feedback::create([
            'user_id' => $admin->id, 'church_id' => $this->church()->id, 'rating' => 5, 'comment' => 'Lovely.',
            'status' => 'Pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.system.maintenance'), ['state' => 'on']);
        $this->actingAs($admin)->patch(route('admin.system.maintenance'), ['state' => 'off']);
        $this->actingAs($admin)->post(route('admin.announcements.store'), ['title' => 'Holy Week', 'message' => 'Routes are ready.', 'show_for' => 7, 'notify' => '0']);
        $this->actingAs($admin)->patch(route('admin.feedback.update', $feedback), ['status' => 'Approved']);

        $this->assertSame([
            'Turned on or updated maintenance mode', 'Turned maintenance mode off', 'Posted an announcement', 'Reviewed feedback',
        ], ActivityLog::orderBy('id')->pluck('action')->all());

        $this->assertSame('Holy Week', ActivityLog::where('action', 'Posted an announcement')->first()->subject);
        $this->assertSame('Review #'.$feedback->id, ActivityLog::where('action', 'Reviewed feedback')->first()->subject);
    }

    public function test_the_page_lists_entries_newest_first_and_filters(): void
    {
        $lyle = $this->person('admin', 'Lyle');
        $clint = $this->person('admin', 'Clint');

        ActivityLog::record($lyle, 'Added a destination', 'Simala Shrine', 'Destination saved.');
        ActivityLog::record($clint, 'Deleted a schedule', 'Sunday Mass', 'Schedule removed.');

        $this->actingAs($lyle)->get(route('admin.activity'))->assertOk()
            ->assertSeeInOrder(['Deleted a schedule', 'Added a destination']);

        $this->actingAs($lyle)->get(route('admin.activity', ['admin' => 'Clint']))
            ->assertSee('Deleted a schedule')->assertDontSee('Added a destination');

        $this->actingAs($lyle)->get(route('admin.activity', ['q' => 'simala']))
            ->assertSee('Added a destination')->assertDontSee('Deleted a schedule');

        $this->actingAs($lyle)->get(route('admin.activity', ['from' => now()->addDay()->toDateString()]))
            ->assertSee('Nothing logged yet');
    }

    public function test_the_entry_survives_the_admin_account_being_deleted(): void
    {
        $lyle = $this->person('admin', 'Lyle');
        $clint = $this->person('admin', 'Clint');
        ActivityLog::record($clint, 'Deleted a schedule', 'Sunday Mass');

        $clint->delete();

        $entry = ActivityLog::sole();
        $this->assertNull($entry->user_id);
        $this->assertSame('Clint', $entry->admin_name);
        $this->actingAs($lyle)->get(route('admin.activity'))->assertSee('Clint');
    }

    public function test_pilgrims_cannot_read_it_and_their_actions_are_not_logged(): void
    {
        $user = $this->person('user');

        $this->actingAs($user)->get(route('admin.activity'))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.system.maintenance'), ['state' => 'on'])->assertForbidden();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_admin_actions_still_work_before_the_table_exists(): void
    {
        Schema::drop('activity_logs');
        $admin = $this->person('admin');
        $maria = $this->person('user', 'Maria');

        $this->actingAs($admin)->patch(route('admin.users.update', $maria), [
            'name' => 'Maria', 'email' => $maria->email, 'role' => 'user', 'status' => 'Inactive',
        ])->assertSessionHas('success');

        $this->assertSame('Inactive', $maria->fresh()->status);
        $this->actingAs($admin)->get(route('admin.activity'))->assertOk()->assertSee('php artisan migrate');
    }
}
