<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    /** What Google sends back after "Allow". */
    private const CALLBACK = '/auth/google/callback?code=test-code&state=test-state';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
    }

    private function googleSays(array $attributes): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn(GoogleUser::fake($attributes + ['email_verified' => true]));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }

    private function account(array $extra = []): User
    {
        return User::create($extra + [
            'name' => 'Maria Santos', 'email' => 'maria@example.com', 'password_hash' => Hash::make('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_verified_google_user_gets_an_account_welcome_notes_and_is_signed_in(): void
    {
        $this->googleSays(['name' => 'Google Pilgrim', 'email' => 'Pilgrim@Example.com']);

        $this->get(self::CALLBACK)->assertRedirect(route('home'));

        $user = User::where('email', 'pilgrim@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['Google Pilgrim', 'user', 'Active'], [$user->name, $user->role, $user->status]);
        $this->assertNotEmpty($user->password_hash);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->terms_accepted_at, 'Continuing with Google accepts the Terms, as the Sign Up tab says');
        $this->assertSame(3, Notification::where('user_id', $user->id)->count());
    }

    public function test_an_existing_account_with_that_email_is_signed_in_not_duplicated(): void
    {
        $maria = $this->account(['email_verified_at' => null]);
        $this->googleSays(['name' => 'Someone Else', 'email' => 'MARIA@example.com']);

        $this->get(self::CALLBACK)->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($maria);
        $this->assertSame(1, User::count());
        $this->assertSame('Maria Santos', $maria->fresh()->name, 'Google never renames an account');
        $this->assertNotNull($maria->fresh()->email_verified_at, 'Google just confirmed the address');
        $this->assertSame(0, Notification::where('user_id', $maria->id)->count(), 'No second welcome');
    }

    public function test_an_admin_goes_to_the_admin_panel(): void
    {
        $this->account(['email' => 'admin@example.com', 'role' => 'admin']);
        $this->googleSays(['email' => 'admin@example.com']);

        $this->get(self::CALLBACK)->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_suspended_account_stays_out(): void
    {
        $this->account(['status' => 'Suspended']);
        $this->googleSays(['email' => 'maria@example.com']);

        $this->get(self::CALLBACK)->assertRedirect(route('login'))
            ->assertSessionHas('error', __('giya.auth.suspended'));
        $this->assertGuest();
    }

    public function test_an_unverified_google_email_cannot_create_an_account(): void
    {
        $this->googleSays(['email' => 'unverified@example.com', 'email_verified' => false]);

        $this->get(self::CALLBACK)->assertRedirect(route('login'))
            ->assertSessionHas('error', __('giya.auth.google_unverified'));

        $this->assertGuest();
        $this->assertDatabaseMissing('devotees', ['email' => 'unverified@example.com']);
    }

    public function test_cancelling_on_googles_screen_is_not_an_error_to_report(): void
    {
        Socialite::shouldReceive('driver')->never();

        $this->get('/auth/google/callback?error=access_denied')->assertRedirect(route('login'))
            ->assertSessionHas('error', __('giya.auth.google_cancelled'));
        $this->assertGuest();
    }

    public function test_a_failed_exchange_with_google_sends_them_back_politely(): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new \Laravel\Socialite\Two\InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get(self::CALLBACK)->assertRedirect(route('login'))
            ->assertSessionHas('error', __('giya.auth.google_failed'));
        $this->assertGuest();
    }

    public function test_sign_in_and_sign_up_both_offer_google_with_its_own_logo(): void
    {
        foreach (['login', 'register'] as $page) {
            $this->get(route($page))->assertOk()
                ->assertSee(route('auth.google'))
                ->assertSee('Continue with Google')
                ->assertSee('google-btn-g', false)
                ->assertDontSee('bi-google', false);
        }

        $this->get(route('register'))->assertSee('By continuing with Google, you agree to the');
    }

    public function test_the_button_speaks_cebuano_and_filipino(): void
    {
        foreach (['ceb' => 'Padayon gamit ang Google', 'fil' => 'Magpatuloy gamit ang Google'] as $locale => $text) {
            app()->setLocale($locale);
            $this->assertStringContainsString($text, view('auth.partials.google', ['signup' => true])->render());
        }
    }

    public function test_without_keys_there_is_no_button_and_the_link_explains(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('login'))->assertDontSee('Continue with Google');
        $this->get(route('auth.google'))->assertRedirect(route('login'))
            ->assertSessionHas('error', __('giya.auth.google_off'));
    }

    public function test_an_admin_can_still_use_google_while_maintenance_is_on(): void
    {
        SiteSetting::write(['maintenance' => 'on']);

        $this->get('/auth/google/callback?error=access_denied')->assertRedirect(route('login'));
    }
}
