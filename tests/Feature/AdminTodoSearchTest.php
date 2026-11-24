<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin dashboard to-do list and the top bar's quick search. */
class AdminTodoSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = ChurchCategory::create(['name' => 'Church', 'created_at' => now(), 'updated_at' => now()]);
        $this->church = Church::create(['name' => 'Todo Parish', 'category_id' => $cat->id, 'location' => 'Argao',
            'municipality' => 'Argao', 'latitude' => 9.88, 'longitude' => 123.6, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now()]);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_dashboard_lists_what_needs_doing(): void
    {
        $user = User::create(['name' => 'Rev', 'email' => 'r@example.com', 'password_hash' => bcrypt('x'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        Feedback::create(['user_id' => $user->id, 'church_id' => $this->church->id, 'rating' => 4, 'comment' => 'Nice', 'status' => 'Pending', 'created_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('review waiting for approval')
            ->assertSee('published church with no Mass schedule')
            ->assertSee('issue=schedule', false)
            ->assertDontSee('payment to check');
    }

    public function test_the_dashboard_says_when_nothing_is_waiting(): void
    {
        $this->church->update(['is_active' => false]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('All caught up');
    }

    public function test_the_admin_bar_has_no_search_box(): void
    {
        // Each list searches as you type, so the bar keeps only the
        // light/dark switch and the administrator.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('id="admQsInput"', false)
            ->assertDontSee('giya-admin-search.js', false);
    }

    public function test_the_admin_bar_has_the_dark_mode_switch(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('id="navTheme"', false)
            ->assertSee(route('profile.preference'), false);
    }
}
