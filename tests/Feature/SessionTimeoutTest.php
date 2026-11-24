<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminIdleTimeout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Signed-in time. Pilgrims stay signed in until they sign out, so a long trip
 * is never interrupted. Admins are signed out after 30 minutes without
 * activity, never let back in by the Remember me cookie. A tap refused after
 * a long pause recovers instead of showing "Page Expired".
 */
class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'devotee', string $email = 'pilgrim@example.com'): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => $email, 'password_hash' => bcrypt('Secret123'),
            'role' => $role, 'status' => 'Active', 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_remember_me_is_ticked_by_default_on_the_sign_in_form(): void
    {
        Livewire::test('auth.login')->assertSet('remember', true);
    }

    public function test_a_pilgrim_who_signs_in_stays_signed_in(): void
    {
        $user = $this->user();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Secret123', 'remember' => '1']);

        $this->assertAuthenticated();
        $this->assertNotNull($user->fresh()->remember_token, 'A pilgrim should get the long-lived sign-in.');
    }

    public function test_an_admin_is_never_remembered_even_with_the_box_ticked(): void
    {
        $admin = $this->user('admin', 'admin@example.com');

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'Secret123', 'remember' => '1']);

        $this->assertAuthenticated();
        $this->assertNull($admin->fresh()->remember_token);
    }

    public function test_an_admin_idle_for_more_than_30_minutes_is_signed_out_and_sent_to_sign_in(): void
    {
        $admin = $this->user('admin', 'admin@example.com');

        $this->actingAs($admin)
            ->withSession([AdminIdleTimeout::SESSION_KEY => time() - 31 * 60])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');

        $this->assertGuest();
        $this->assertSame(route('admin.dashboard'), session('url.intended'), 'After signing in they return to the same admin page.');
    }

    public function test_an_active_admin_stays_signed_in_and_the_clock_restarts(): void
    {
        $admin = $this->user('admin', 'admin@example.com');

        $this->actingAs($admin)
            ->withSession([AdminIdleTimeout::SESSION_KEY => time() - 10 * 60])
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertGreaterThanOrEqual(time() - 5, session(AdminIdleTimeout::SESSION_KEY));
    }

    public function test_the_background_notification_poll_does_not_count_as_activity(): void
    {
        $admin = $this->user('admin', 'admin@example.com');
        $seen = time() - 20 * 60;

        $this->actingAs($admin)
            ->withSession([AdminIdleTimeout::SESSION_KEY => $seen])
            ->getJson(route('notifications.index'), ['X-Giya-Background' => '1'])
            ->assertOk();

        $this->assertSame($seen, session(AdminIdleTimeout::SESSION_KEY));
    }

    public function test_pilgrims_are_never_signed_out_for_being_idle(): void
    {
        $this->actingAs($this->user())
            ->withSession([AdminIdleTimeout::SESSION_KEY => time() - 5 * 3600])
            ->get(route('profile'))
            ->assertOk();

        $this->assertAuthenticated();
    }

    public function test_keep_alive_is_for_admins_and_restarts_the_clock(): void
    {
        $admin = $this->user('admin', 'admin@example.com');

        $this->actingAs($admin)
            ->withSession([AdminIdleTimeout::SESSION_KEY => time() - 25 * 60])
            ->postJson(route('admin.keep-alive'))
            ->assertOk();
        $this->assertGreaterThanOrEqual(time() - 5, session(AdminIdleTimeout::SESSION_KEY));

        $this->actingAs($this->user())->postJson(route('admin.keep-alive'))->assertForbidden();
    }

    public function test_when_the_countdown_ends_the_admin_is_signed_out_and_returns_to_the_same_page(): void
    {
        $admin = $this->user('admin', 'admin@example.com');

        $this->actingAs($admin)
            ->post(route('admin.idle-sign-out'), ['back' => route('admin.schedules')])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');

        $this->assertGuest();
        $this->assertSame(route('admin.schedules'), session('url.intended'));
    }

    public function test_a_page_gets_a_fresh_token_or_is_sent_to_sign_in_and_back(): void
    {
        $this->actingAs($this->user())->getJson(route('session.refresh'))
            ->assertOk()->assertJson(['signed_in' => true])->assertJsonStructure(['token']);

        Auth::logout();
        $trip = url('/plan/5');
        $this->getJson(route('session.refresh', ['back' => $trip]))
            ->assertOk()->assertJson(['signed_in' => false, 'login_url' => route('login')]);
        $this->assertSame($trip, session('url.intended'));

        // Never sends anyone off-site.
        $this->getJson(route('session.refresh', ['back' => 'https://evil.example/plan']));
        $this->assertSame($trip, session('url.intended'));
    }

    public function test_an_expired_token_never_shows_page_expired(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => throw new TokenMismatchException());

        // A tap from a script: an answer it can recover from.
        $this->actingAs($this->user())->postJson('/_test/expired')
            ->assertStatus(419)->assertJson(['reason' => 'session_expired']);

        // A form: back to the same page, fresh, with a note.
        $this->actingAs($this->user('devotee', 'other@example.com'))
            ->from(url('/plan/5'))->post('/_test/expired')
            ->assertRedirect(url('/plan/5'))->assertSessionHas('warning');

        // Signed out: to sign in, then back to that page.
        Auth::logout();
        $this->from(url('/plan/5'))->post('/_test/expired')
            ->assertRedirect(route('login'));
        $this->assertSame(url('/plan/5'), session('url.intended'));
    }

    public function test_every_page_loads_the_recovery_script_and_admin_pages_know_the_limit(): void
    {
        $this->actingAs($this->user())->get(route('profile'))
            ->assertSee('giya-session.js', false)
            ->assertDontSee('giya-admin-idle', false);

        $this->actingAs($this->user('admin', 'admin@example.com'))->get(route('admin.dashboard'))
            ->assertSee('giya-session.js', false)
            ->assertSee('<meta name="giya-admin-idle" content="1800">', false);
    }

    public function test_opening_the_sign_out_address_as_a_page_never_shows_an_error(): void
    {
        // Signed out (the usual case: a reload or a restored tab after the countdown).
        $this->get('/admin/idle-sign-out')
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', \App\Http\Controllers\SessionController::SIGNED_OUT_MESSAGE);

        // Still signed in: straight back to work.
        $this->actingAs($this->user('admin', 'admin@example.com'))
            ->get('/admin/idle-sign-out')
            ->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->user())
            ->get('/admin/idle-sign-out')
            ->assertRedirect(route('home'));
    }

    public function test_any_form_only_address_opened_as_a_page_goes_somewhere_useful(): void
    {
        $this->actingAs($this->user())
            ->get('/admin/keep-alive')
            ->assertRedirect(route('home'))
            ->assertSessionHas('warning');

        $this->getJson('/admin/keep-alive')->assertStatus(405);
    }

    public function test_the_countdown_waits_for_the_network_before_signing_out(): void
    {
        $js = file_get_contents(public_path('assets/js/giya-session.js'));
        $this->assertStringContainsString('navigator.onLine === false', $js);
    }
}
