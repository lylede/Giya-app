<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sign in and sign up show the breathing GIYA logo behind the card. */
class AuthBackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_and_sign_up_have_the_breathing_logo(): void
    {
        foreach (['login', 'register'] as $route) {
            $html = $this->withCookie(OnboardingController::COOKIE, '1')->get(route($route))->assertOk()->getContent();

            $this->assertStringContainsString('class="auth-bg" aria-hidden="true"', $html);
            $this->assertStringContainsString('auth-bg-logo', $html);
            $this->assertSame(3, substr_count($html, 'class="auth-bg-ring'));
        }
    }

    public function test_it_holds_still_for_anyone_who_asks_for_less_motion(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        $this->assertStringContainsString('@keyframes auth-breathe', $css);
        $this->assertStringContainsString(".auth-bg-logo, .auth-bg-ring { animation: none; }", $css);
    }
}
