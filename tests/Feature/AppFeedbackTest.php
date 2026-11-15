<?php

namespace Tests\Feature;

use App\Models\AppFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    private function person(string $role = 'user'): User
    {
        return User::create([
            'name' => ucfirst($role).(++$this->n), 'email' => $role.$this->n.'@example.com', 'password_hash' => Hash::make('Secret123!'),
            'role' => $role, 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_pilgrim_sends_feedback_about_giya(): void
    {
        $user = $this->person();

        $this->actingAs($user)->get(route('app-feedback'))->assertOk()->assertSee(__('giya.app_feedback.title'));

        $this->actingAs($user)->post(route('app-feedback.store'), ['rating' => 4, 'topic' => 'ai', 'comment' => 'Giya AI found my Mass fast.'])
            ->assertRedirect(route('app-feedback'))->assertSessionHas('success');

        $row = AppFeedback::sole();
        $this->assertSame([$user->id, 4, 'ai', 'Giya AI found my Mass fast.'], [$row->user_id, $row->rating, $row->topic, $row->comment]);
    }

    public function test_a_rating_is_required_and_topics_are_checked(): void
    {
        $user = $this->person();

        $this->actingAs($user)->post(route('app-feedback.store'), ['topic' => 'map'])->assertSessionHasErrors('rating');
        $this->actingAs($user)->post(route('app-feedback.store'), ['rating' => 9, 'topic' => 'map'])->assertSessionHasErrors('rating');
        $this->actingAs($user)->post(route('app-feedback.store'), ['rating' => 3, 'topic' => 'weather'])->assertSessionHasErrors('topic');

        $this->assertSame(0, AppFeedback::count());
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get(route('app-feedback'))->assertRedirect(route('login'));
        $this->post(route('app-feedback.store'), ['rating' => 5, 'topic' => 'map'])->assertRedirect(route('login'));
    }

    public function test_admins_see_the_average_by_topic_and_can_download(): void
    {
        $a = $this->person(); $b = $this->person();
        AppFeedback::create(['user_id' => $a->id, 'rating' => 5, 'topic' => 'map', 'comment' => 'Love the map', 'created_at' => now()]);
        AppFeedback::create(['user_id' => $b->id, 'rating' => 2, 'topic' => 'ai', 'comment' => 'AI was slow', 'created_at' => now()]);
        $admin = $this->person('admin');

        $this->actingAs($admin)->get(route('admin.app-feedback'))->assertOk()
            ->assertSee('3.50 / 5')->assertSee('Love the map')->assertSee('AI was slow');

        $this->actingAs($admin)->get(route('admin.app-feedback', ['topic' => 'ai']))->assertOk()
            ->assertSee('AI was slow')->assertDontSee('Love the map');

        $csv = $this->actingAs($admin)->get(route('admin.app-feedback.download'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Love the map', $csv);
    }

    public function test_pilgrims_cannot_open_the_admin_page(): void
    {
        $this->actingAs($this->person())->get(route('admin.app-feedback'))->assertForbidden();
    }

    public function test_the_pages_link_to_it(): void
    {
        $this->actingAs($this->person())->get(route('about'))->assertSee('href="'.route('app-feedback').'"', false);
    }

    public function test_it_still_works_before_the_table_exists(): void
    {
        Schema::drop('app_feedback');

        $this->actingAs($this->person())->post(route('app-feedback.store'), ['rating' => 5, 'topic' => 'map'])->assertSessionHas('error');
        $this->actingAs($this->person('admin'))->get(route('admin.app-feedback'))->assertOk()->assertSee('php artisan migrate');
    }
}
