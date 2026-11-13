<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private int $people = 0;

    private function person(string $role, string $status = 'Active'): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role.(++$this->people).'@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => $role, 'status' => $status, 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function announce(User $admin, array $extra = [])
    {
        return $this->actingAs($admin)->post(route('admin.announcements.store'), $extra + [
            'title' => 'Sinulog schedules are posted', 'message' => 'Open any church page to see its fiesta Masses.',
            'show_for' => 7, 'notify' => '1',
        ]);
    }

    public function test_an_admin_posts_one_and_every_active_account_is_told_once(): void
    {
        $admin = $this->person('admin');
        $maria = $this->person('user');
        $jose  = $this->person('user');
        $gone  = $this->person('user', 'Suspended');

        $this->announce($admin)->assertSessionHas('success', 'Announcement posted and sent to 3 people.');

        $announcement = Announcement::first();
        $this->assertSame(3, $announcement->sent_to);
        $this->assertSame($admin->id, $announcement->created_by);
        $this->assertTrue($announcement->isShowing());

        foreach ([$admin, $maria, $jose] as $who) {
            $row = Notification::where('user_id', $who->id)->sole();
            $this->assertSame('system', $row->type);
            $this->assertSame('Sinulog schedules are posted', $row->title);
        }

        $this->assertSame(0, Notification::where('user_id', $gone->id)->count());
    }

    public function test_banner_only_sends_nothing_to_the_bell(): void
    {
        $this->person('user');

        $this->announce($this->person('admin'), ['notify' => '0'])->assertSessionHas('success', 'Announcement posted on the banner.');

        $this->assertSame(0, Notification::count());
        $this->assertSame(0, Announcement::first()->sent_to);
    }

    public function test_the_banner_shows_to_pilgrims_and_guests(): void
    {
        $this->announce($this->person('admin'));
        auth()->logout();

        $this->get('/map')->assertOk()
            ->assertSee('Sinulog schedules are posted')
            ->assertSee('Open any church page to see its fiesta Masses.');

        $this->actingAs($this->person('user'))->get('/home')->assertOk()->assertSee('Sinulog schedules are posted');
    }

    public function test_only_the_newest_is_on_the_banner(): void
    {
        $admin = $this->person('admin');
        $this->announce($admin);
        $this->announce($admin, ['title' => 'Holy Week routes are ready', 'message' => 'Try the Visita Iglesia planner.']);
        auth()->logout();

        $this->get('/map')->assertSee('Holy Week routes are ready')->assertDontSee('Sinulog schedules are posted');
    }

    public function test_it_leaves_the_banner_when_stopped_or_past_its_date(): void
    {
        $admin = $this->person('admin');
        $this->announce($admin);
        $announcement = Announcement::first();

        $this->actingAs($admin)->patch(route('admin.announcements.stop', $announcement))->assertSessionHas('success');
        auth()->logout();
        $this->get('/map')->assertDontSee('Sinulog schedules are posted');

        $announcement = $announcement->fresh();
        $announcement->update(['is_active' => true, 'show_until' => now()->subMinute()]);
        $this->get('/map')->assertDontSee('Sinulog schedules are posted');

        $announcement->update(['show_until' => now()->addDay()]);
        $this->get('/map')->assertSee('Sinulog schedules are posted');
    }

    public function test_until_i_stop_it_has_no_end_date(): void
    {
        $this->announce($this->person('admin'), ['show_for' => 0]);

        $this->assertNull(Announcement::first()->show_until);
    }

    public function test_deleting_keeps_notifications_already_sent(): void
    {
        $admin = $this->person('admin');
        $this->announce($admin);

        $this->actingAs($admin)->delete(route('admin.announcements.destroy', Announcement::first()))->assertSessionHas('success');

        $this->assertSame(0, Announcement::count());
        $this->assertSame(1, Notification::count());
    }

    public function test_a_title_and_message_are_required_and_text_is_escaped(): void
    {
        $admin = $this->person('admin');

        $this->announce($admin, ['title' => '', 'message' => ''])->assertSessionHasErrors(['title', 'message']);
        $this->assertSame(0, Announcement::count());

        $this->announce($admin, ['title' => '<script>alert(1)</script>']);
        auth()->logout();
        $this->get('/map')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_admin_page_lists_them_and_pilgrims_cannot_post(): void
    {
        $admin = $this->person('admin');
        $this->announce($admin);

        $this->actingAs($admin)->get(route('admin.announcements'))->assertOk()
            ->assertSee('On the banner')->assertSee('sent to 1 person');

        $user = $this->person('user');
        $this->actingAs($user)->get(route('admin.announcements'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.announcements.store'), ['title' => 'x', 'message' => 'y', 'show_for' => 7])->assertForbidden();
    }

    public function test_the_site_still_works_before_the_table_exists(): void
    {
        Schema::drop('announcements');

        $this->get('/map')->assertOk();

        $admin = $this->person('admin');
        $this->actingAs($admin)->get(route('admin.announcements'))->assertOk()->assertSee('php artisan migrate');
        $this->announce($admin)->assertSessionHas('error');
    }
}
