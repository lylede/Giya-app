<?php

namespace Tests\Feature;

use App\Actions\Auth\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function person(?string $verified = null): User
    {
        return User::create([
            'name' => 'Lyle Test', 'email' => 'lyle@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => $verified,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_it_is_off_by_default_and_changes_nothing(): void
    {
        $this->assertFalse(EmailVerification::enabled());
        $user = $this->person();

        $this->actingAs($user)->get(route('home'))->assertDontSee('verify-banner');
        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'Hello there'])->assertOk();
    }

    public function test_sign_up_sends_a_confirmation_link_when_it_is_on(): void
    {
        config(['app.verify_email' => true]);
        Mail::fake();

        Livewire::test('auth.register')
            ->set('name', 'New Pilgrim')->set('email', 'new@example.com')
            ->set('password', 'secret123')->set('password_confirmation', 'secret123')->set('terms', true)
            ->call('register')->assertHasNoErrors();

        $this->assertNull(User::where('email', 'new@example.com')->value('email_verified_at'));
    }

    public function test_sign_up_leads_to_check_your_email_when_it_is_on(): void
    {
        config(['app.verify_email' => true]);
        Mail::fake();

        Livewire::test('auth.register')
            ->set('name', 'New Pilgrim')->set('email', 'new@example.com')
            ->set('password', 'secret123')->set('password_confirmation', 'secret123')->set('terms', true)
            ->call('register')
            ->assertRedirect(route('verification.pending'));

        $this->withSession(['verify_email' => 'new@example.com'])
            ->get(route('verification.pending'))->assertOk()->assertSee('new@example.com')->assertSee('Check your email');
    }

    public function test_an_unconfirmed_account_cannot_sign_in_until_it_confirms(): void
    {
        config(['app.verify_email' => true]);
        $user = $this->person();

        $this->post(route('login.store'), ['email' => 'lyle@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('email')
            ->assertSessionHas('verify_email', 'lyle@example.com');
        $this->assertGuest();

        $this->get(app(EmailVerification::class)->link($user));

        $this->post(route('login.store'), ['email' => 'lyle@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_wrong_password_never_says_the_account_is_unconfirmed(): void
    {
        config(['app.verify_email' => true]);
        $this->person();

        $this->post(route('login.store'), ['email' => 'lyle@example.com', 'password' => 'wrong-one'])
            ->assertSessionHasErrors(['email' => 'Incorrect email or password.'])
            ->assertSessionMissing('verify_email');
    }

    public function test_the_link_can_be_sent_again_from_check_your_email(): void
    {
        config(['app.verify_email' => true]);
        Mail::fake();
        $this->person();

        $this->withSession(['verify_email' => 'lyle@example.com'])
            ->post(route('verification.resend-pending'))
            ->assertRedirect(route('verification.pending'))
            ->assertSessionHas('success');
    }

    public function test_an_unconfirmed_user_can_browse_but_not_use_giya_ai(): void
    {
        config(['app.verify_email' => true]);
        $user = $this->person();

        $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('verify-banner', false);
        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'Hello there'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'unverified');
    }

    public function test_the_link_confirms_the_email(): void
    {
        config(['app.verify_email' => true]);
        $user = $this->person();

        $this->get(app(EmailVerification::class)->link($user))->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->actingAs($user->fresh())->get(route('home'))->assertDontSee('verify-banner');
    }

    public function test_a_tampered_or_old_address_link_does_not_work(): void
    {
        config(['app.verify_email' => true]);
        $user = $this->person();
        $link = app(EmailVerification::class)->link($user);

        $this->get(str_replace('signature=', 'signature=x', $link))->assertForbidden();   // signature broken

        $user->update(['email' => 'changed@example.com']);
        $this->get($link)->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_changing_the_email_asks_to_confirm_the_new_one(): void
    {
        config(['app.verify_email' => true]);
        Mail::fake();
        $user = $this->person(now());

        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Lyle Test', 'email' => 'new@example.com'])
            ->assertRedirect(route('verification.notice'));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_accounts_that_existed_before_count_as_confirmed(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_11_10_000003_add_email_verified_at_to_devotees.php'));
        $this->assertStringContainsString("update(['email_verified_at' => DB::raw('created_at')])", $migration);
    }

    public function test_an_admin_can_confirm_an_email_by_hand(): void
    {
        config(['app.verify_email' => true]);
        $stuck = $this->person();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => 'admin', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)->get(route('admin.users'))->assertSee('Unconfirmed')->assertSee(route('admin.users.confirm', $stuck), false);
        $this->actingAs($admin)->patch(route('admin.users.confirm', $stuck))->assertSessionHas('success');
        $this->assertNotNull($stuck->fresh()->email_verified_at);
    }

    /** A mail wait as long as PHP's own limit turned "could not send" into a 500 error page. */
    public function test_email_gives_up_well_before_php_does(): void
    {
        $this->assertLessThanOrEqual(20, config('mail.mailers.smtp.timeout'));
        $this->assertGreaterThan(0, config('mail.mailers.smtp.timeout'));
    }

    public function test_sign_up_still_finishes_when_the_email_cannot_be_sent(): void
    {
        config(['app.verify_email' => true]);
        \Illuminate\Support\Facades\Mail::shouldReceive('send')->andThrow(new \RuntimeException('Gmail is down'));

        Livewire::test('auth.register')
            ->set('name', 'New Pilgrim')->set('email', 'nomail@example.com')
            ->set('password', 'secret123')->set('password_confirmation', 'secret123')->set('terms', true)
            ->call('register')
            ->assertRedirect(route('verification.pending'));

        $this->assertDatabaseHas('devotees', ['email' => 'nomail@example.com']);
    }
}
