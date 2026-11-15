<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_google_user_can_create_an_account_and_sign_in(): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn(GoogleUser::fake([
            'name' => 'Google Pilgrim',
            'email' => 'pilgrim@example.com',
            'email_verified' => true,
        ]));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'pilgrim@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Google Pilgrim', $user->name);
        $this->assertSame('user', $user->role);
        $this->assertSame('Active', $user->status);
        $this->assertNotEmpty($user->password_hash);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_an_unverified_google_email_cannot_create_an_account(): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn(GoogleUser::fake([
            'email' => 'unverified@example.com',
            'email_verified' => false,
        ]));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Google auth failed.');

        $this->assertGuest();
        $this->assertDatabaseMissing('devotees', ['email' => 'unverified@example.com']);
    }

    public function test_the_login_page_offers_google_sign_in(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('auth.google'))
            ->assertSee('Continue with Google');
    }
}
