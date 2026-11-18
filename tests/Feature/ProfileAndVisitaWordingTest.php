<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UX review #7 and #8: no "Role" for pilgrims, and a warm first step on Visita Iglesia. */
class ProfileAndVisitaWordingTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $role): User
    {
        return User::create(['name' => 'Juan', 'email' => $role.'@example.com', 'password_hash' => bcrypt('x'),
            'role' => $role, 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_pilgrims_do_not_see_a_role_on_their_profile(): void
    {
        $this->actingAs($this->make('devotee'))->get(route('profile'))->assertOk()
            ->assertDontSee(__('giya.profile.role'))
            ->assertSee(__('giya.profile.member_since'));
    }

    public function test_admins_still_see_their_role(): void
    {
        $this->actingAs($this->make('admin'))->get(route('profile'))->assertOk()
            ->assertSee(__('giya.profile.role'))->assertSee('Admin');
    }

    public function test_visita_iglesia_starts_with_a_question_and_no_empty_progress(): void
    {
        $this->actingAs($this->make('devotee'))->get(route('plan.visita'))->assertOk()
            ->assertSee('id="viProgress" hidden', false)
            ->assertSee('Where are you starting from?')
            ->assertDontSee('Your location is off');
    }
}
