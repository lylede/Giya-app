<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Feedback;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin Destinations map: side panel, needs-attention pins, dragging pins. */
class AdminMapWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = ChurchCategory::create(['name' => 'Church', 'created_at' => now(), 'updated_at' => now()]);
        $this->church = Church::create(['name' => 'Map Parish', 'category_id' => $cat->id, 'location' => 'Cebu City',
            'municipality' => 'Cebu City', 'latitude' => 10.3, 'longitude' => 123.9, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now()]);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_page_has_attention_chips_and_the_side_panel(): void
    {
        $this->actingAs($this->admin)->get(route('admin.destinations'))->assertOk()
            ->assertSee('data-issue="any"', false)
            ->assertSee('id="dmSide"', false)
            ->assertSee('onChurchMove', false);
    }

    public function test_issues_flag_a_church_with_no_photo_and_no_mass(): void
    {
        $issues = \App\Http\Controllers\Admin\ChurchController::issues($this->church->fresh());

        $this->assertContains('photo', $issues);
        $this->assertContains('schedule', $issues);
        $this->assertNotContains('hidden', $issues);
    }

    public function test_the_panel_returns_schedule_and_reviews_pending_first(): void
    {
        Schedule::create(['church_id' => $this->church->id, 'event_name' => 'Mass', 'event_type' => 'Mass', 'start_time' => '06:00',
            'is_recurring' => true, 'recurrence_rule' => ['type' => 'daily'], 'recurrence' => 'Daily',
            'status' => 'Published', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::create(['name' => 'Rev', 'email' => 'r@example.com', 'password_hash' => bcrypt('x'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        Feedback::create(['user_id' => $user->id, 'church_id' => $this->church->id, 'rating' => 5, 'comment' => 'Old', 'status' => 'Approved', 'created_at' => now()->subDay()]);
        Feedback::create(['user_id' => $user->id, 'church_id' => $this->church->id, 'rating' => 2, 'comment' => 'New', 'status' => 'Pending', 'created_at' => now()]);

        $json = $this->actingAs($this->admin)->getJson(route('admin.destinations.panel', $this->church))->assertOk()->json();

        $this->assertSame(1, $json['schedule']['published']);
        $this->assertSame('Pending', $json['feedback']['items'][0]['status']);
        $this->assertSame(1, $json['feedback']['pending']);
        $this->assertNotContains('schedule', $json['issues']);
    }

    public function test_a_dragged_pin_saves_only_its_coordinates(): void
    {
        $this->actingAs($this->admin)->patchJson(route('admin.destinations.move', $this->church), [
            'latitude' => 10.31234567, 'longitude' => 123.89123456,
        ])->assertOk()->assertJson(['ok' => true]);

        $c = $this->church->fresh();
        $this->assertEqualsWithDelta(10.31234567, (float) $c->latitude, 1e-6);
        $this->assertSame('Map Parish', $c->name);
    }

    public function test_a_review_can_be_approved_from_the_panel(): void
    {
        $user = User::create(['name' => 'Rev', 'email' => 'r2@example.com', 'password_hash' => bcrypt('x'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        $f = Feedback::create(['user_id' => $user->id, 'church_id' => $this->church->id, 'rating' => 4, 'status' => 'Pending', 'created_at' => now()]);

        $this->actingAs($this->admin)->patchJson(route('admin.feedback.update', $f), ['status' => 'Approved'])
            ->assertOk()->assertJson(['status' => 'Approved']);
    }

    public function test_devotees_cannot_move_pins(): void
    {
        $devotee = User::create(['name' => 'D', 'email' => 'd@example.com', 'password_hash' => bcrypt('x'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($devotee)->patchJson(route('admin.destinations.move', $this->church), ['latitude' => 1, 'longitude' => 1])
            ->assertForbidden();
    }
}
