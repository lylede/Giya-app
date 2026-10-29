<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The three welcome slides on a visitor's first visit. */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first_visit_opens_the_welcome_slides(): void
    {
        $this->get('/')->assertRedirect(route('onboarding'));

        $this->get(route('onboarding'))->assertOk()
            ->assertSee('Discover Sacred Places')
            ->assertSee('Plan Pilgrimage Routes')
            ->assertSee('AI-Guided Journeys')
            ->assertSee('Skip')
            ->assertSee('I already have an account');
    }

    public function test_get_started_goes_to_sign_up_and_is_remembered(): void
    {
        $this->get(route('onboarding.finish', ['next' => 'register']))
            ->assertRedirect(route('register'))
            ->assertCookie(OnboardingController::COOKIE, '1');

        $this->withCookie(OnboardingController::COOKIE, '1')->get('/')->assertOk();
    }

    public function test_skip_goes_to_the_start_page_and_login_link_to_login(): void
    {
        $this->get(route('onboarding.finish', ['next' => 'root']))->assertRedirect(route('root'));
        $this->get(route('onboarding.finish', ['next' => 'login']))->assertRedirect(route('login'));
    }

    public function test_it_never_redirects_off_site(): void
    {
        $this->get(route('onboarding.finish', ['next' => 'https://evil.example']))
            ->assertRedirect(route('root'));
    }

    public function test_signed_in_devotees_never_see_it(): void
    {
        $user = User::create([
            'name' => 'Test', 'email' => 't@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get('/')->assertRedirect(route('home'));
        $this->actingAs($user)->get(route('onboarding'))->assertRedirect();
    }
}
