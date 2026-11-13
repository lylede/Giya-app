<?php

namespace Tests\Feature;

use App\Actions\Auth\LoginAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Where sign-in sends you.
 *
 * A browser that had last tried to open /admin remembered it, and the next
 * person to sign in there - a pilgrim - was sent to /admin and met a bare
 * "403 Administrator access only" with nothing to tap.
 */
class SignInDestinationTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role.'@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => $role, 'status' => 'Active', 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function signIn(User $user)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'secret123']);
    }

    public function test_a_pilgrim_is_never_sent_to_an_admin_page_after_signing_in(): void
    {
        $maria = $this->person('user');

        $this->get('/admin/system')->assertRedirect(route('login'));
        $this->assertSame(url('/admin/system'), session('url.intended'));

        $this->signIn($maria)->assertRedirect(route('home'));
        $this->assertNull(session('url.intended'));
    }

    public function test_the_sign_in_form_on_the_page_uses_the_same_rule(): void
    {
        // The form on the page is a Livewire form; it must ask the same
        // question rather than reading the remembered page itself.
        $form = file_get_contents(resource_path('views/components/auth/⚡login.blade.php'));

        $this->assertStringContainsString('LoginAction::destination($user)', $form);
        $this->assertStringNotContainsString('url.intended', $form);

        session()->put('url.intended', url('/admin/users'));
        $this->assertSame(route('home'), LoginAction::destination($this->person('user')));

        session()->put('url.intended', url('/admin/users'));
        $this->assertSame(url('/admin/users'), LoginAction::destination($this->person('admin')));
    }

    public function test_an_admin_still_goes_back_to_the_admin_page_they_asked_for(): void
    {
        $admin = $this->person('admin');

        $this->get('/admin/system')->assertRedirect(route('login'));

        $this->signIn($admin)->assertRedirect(url('/admin/system'));
    }

    public function test_a_pilgrim_still_goes_back_to_the_page_that_asked_them_to_sign_in(): void
    {
        $maria = $this->person('user');

        $this->get('/profile')->assertRedirect(route('login'));

        $this->signIn($maria)->assertRedirect(url('/profile'));
    }

    public function test_with_nothing_remembered_each_goes_to_their_start_page(): void
    {
        $this->signIn($this->person('user'))->assertRedirect(route('home'));
        $this->post('/logout');
        $this->signIn($this->person('admin'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_link_to_another_site_is_not_followed(): void
    {
        $this->withSession(['url.intended' => 'https://evil.example.com/profile']);

        $this->signIn($this->person('user'))->assertRedirect(route('home'));
    }

    public function test_the_403_page_tells_a_pilgrim_what_happened_and_offers_a_way_out(): void
    {
        $maria = $this->person('user');

        $this->actingAs($maria)->get('/admin')
            ->assertForbidden()
            ->assertSee('This page is for administrators')
            ->assertSee('You are signed in as User')
            ->assertSee('Go to GIYA home')
            ->assertSee(route('home'))
            ->assertSee('Sign out and switch account');
    }
}
