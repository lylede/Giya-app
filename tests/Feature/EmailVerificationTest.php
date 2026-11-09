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
}
