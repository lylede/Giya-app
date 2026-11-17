<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StrongPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function signUp(string $password)
    {
        return Livewire::test('auth.register')
            ->set('name', 'Maria Santos')->set('email', 'maria@example.com')
            ->set('password', $password)->set('password_confirmation', $password)->set('terms', true)
            ->call('register');
    }

    public function test_passwords_without_a_capital_or_under_8_are_refused(): void
    {
        foreach (['secret12', 'mariasantos', 'Maria12', 'Se1!'] as $weak) {
            $this->signUp($weak)->assertHasErrors('password');
        }

        $this->assertSame(0, User::count());
    }

    public function test_8_characters_with_one_capital_is_enough(): void
    {
        // No number and no symbol needed: simple for older devotees.
        $this->signUp('Mariasantos')->assertHasNoErrors();

        $this->assertSame(1, User::count());
    }

    public function test_numbers_and_symbols_are_still_allowed(): void
    {
        $this->signUp('Pilgrim2026!')->assertHasNoErrors();
    }

    public function test_the_message_says_what_is_missing(): void
    {
        $this->signUp('secret123')->assertHasErrors(['password']);

        $errors = $this->signUp('secret123')->errors()->get('password');
        $this->assertContains('The password needs at least one capital letter (like A or B).', $errors);
        $this->assertNotContains('The password needs at least one symbol (like ! or #).', $errors);
        $this->assertNotContains('The password needs at least one number.', $errors);
    }

    public function test_an_old_weak_password_still_signs_in(): void
    {
        User::create([
            'name' => 'Jose', 'email' => 'jose@example.com', 'password_hash' => Hash::make('oldpass12'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Signing in checks only that the password matches, never the new rule.
        $this->post(route('login'), ['email' => 'jose@example.com', 'password' => 'oldpass12'])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_changing_the_password_in_profile_needs_a_strong_one(): void
    {
        $user = User::create([
            'name' => 'Jose', 'email' => 'jose@example.com', 'password_hash' => Hash::make('oldpass12'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->patch(route('profile.password'), [
            'current_password' => 'oldpass12', 'password' => 'newpass12', 'password_confirmation' => 'newpass12',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('oldpass12', $user->fresh()->password_hash));
    }

    public function test_the_rule_is_shown_under_the_field(): void
    {
        $this->get(route('register'))->assertOk()->assertSee(__('giya.auth.pw_rule'));
    }
}
