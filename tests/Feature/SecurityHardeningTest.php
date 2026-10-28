<?php

namespace Tests\Feature;

use App\Actions\Auth\PasswordResetAction;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Notification;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Regression tests for the security review of October 2026.
 *
 * Each test names the bug it pins down, so if one fails the message says
 * what came back rather than only that something changed.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function devotee(string $email = 'lyle@example.com', array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Lyle Dedumo', 'email' => $email,
            'password_hash' => Hash::make('old-password'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ], $extra));
    }

    private function resetToken(string $email, string $token, $createdAt): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $email, 'token' => Hash::make($token), 'created_at' => $createdAt,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('register:127.0.0.1');
    }

    /* ── Password reset ─────────────────────────────────────────────── */

    /** Carbon 3's signed diffInMinutes() made every reset link immortal. */
    public function test_a_reset_link_older_than_the_ttl_is_refused(): void
    {
        $user = $this->devotee();
        $this->resetToken($user->email, 'tok', now()->subMinutes(PasswordResetAction::TOKEN_TTL_MINUTES + 1));

        $this->post(route('password.update'), [
            'token' => 'tok', 'email' => $user->email,
            'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors('token');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password_hash),
            'An expired link still changed the password.');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_a_three_day_old_link_is_expired(): void
    {
        $this->assertTrue(PasswordResetAction::isExpired(now()->subDays(3)));
        $this->assertFalse(PasswordResetAction::isExpired(now()->subMinutes(5)));
    }

    public function test_a_fresh_reset_link_works_once(): void
    {
        $user = $this->devotee();
        $this->resetToken($user->email, 'tok', now()->subMinutes(5));

        $this->post(route('password.update'), [
            'token' => 'tok', 'email' => $user->email,
            'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password_hash));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    /** "No account with that email" let anyone test addresses against GIYA. */
    public function test_forgot_password_says_the_same_thing_for_unknown_emails(): void
    {
        Mail::fake();
        $this->devotee('known@example.com');

        $known = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'known@example.com']);
        $unknown = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nobody@example.com']);

        $known->assertSessionHasNoErrors();
        $unknown->assertSessionHasNoErrors();
        $this->assertSame(session('success'), PasswordResetAction::sentMessage());
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'known@example.com']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nobody@example.com']);
    }

    public function test_reset_emails_are_rate_limited_per_address(): void
    {
        Mail::fake();
        $this->devotee('known@example.com');

        for ($i = 0; $i < PasswordResetAction::MAX_REQUESTS; $i++) {
            app(PasswordResetAction::class)->sendLink('known@example.com', '10.0.0.1');
        }

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PasswordResetAction::class)->sendLink('known@example.com', '10.0.0.1');
    }

    /** The reset page used to print a second form above <!DOCTYPE html>. */
    public function test_the_reset_page_has_one_form_inside_the_document(): void
    {
        $html = $this->get(route('password.reset', ['token' => 'x']).'?email=a@b.com')->getContent();

        $this->assertStringStartsWith('<!DOCTYPE', ltrim($html));
        $this->assertSame(1, substr_count($html, 'name="password_confirmation"'));
    }

    /* ── Login ──────────────────────────────────────────────────────── */

    public function test_login_locks_out_after_repeated_wrong_passwords(): void
    {
        $user = $this->devotee();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        // Even the right password is refused during the lockout.
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'old-password']);

        $this->assertGuest();
        $this->assertTrue(RateLimiter::tooManyAttempts('login:'.$user->email.'|127.0.0.1', 5));
    }

    /** Suspension only applied at sign-in; a live session carried on. */
    public function test_a_suspended_devotee_is_signed_out_on_their_next_request(): void
    {
        $user = $this->devotee();
        $this->actingAs($user)->get(route('home'))->assertOk();

        $user->forceFill(['status' => 'Suspended'])->save();

        $this->get(route('home'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_changing_the_password_rotates_the_remember_me_token(): void
    {
        $user = $this->devotee(extra: ['remember_token' => 'old-remember-token']);

        $this->actingAs($user)->patch(route('profile.password'), [
            'current_password' => 'old-password',
            'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertRedirect(route('profile'));

        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
    }

    /* ── Stored XSS through church data ─────────────────────────────── */

    private const EVIL = 'Evil</script><img src=x onerror=alert(1)>';

    private function evilChurch(): Church
    {
        $cat = ChurchCategory::create(['name' => 'Basilica', 'created_at' => now(), 'updated_at' => now()]);

        return Church::create([
            'name' => self::EVIL, 'category_id' => $cat->id, 'location' => self::EVIL,
            'latitude' => 10.29, 'longitude' => 123.90, 'is_active' => true,
            'is_featured' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function pagesShowingChurches(): array
    {
        return ['map' => ['map'], 'visita planner' => ['plan.visita'], 'home' => ['home']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pagesShowingChurches')]
    public function test_a_church_name_cannot_break_out_of_a_script_block(string $route): void
    {
        $this->evilChurch();

        $html = $this->actingAs($this->devotee())->get(route($route))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><img', $html,
            "A church name closed a <script> block on {$route}.");
    }

    public function test_the_active_plan_encodes_stop_names_for_script(): void
    {
        $church = $this->evilChurch();
        $user = $this->devotee();
        $type = \App\Models\ItineraryType::create(['name' => 'Custom', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $plan = \App\Models\Itinerary::create([
            'user_id' => $user->id, 'itinerary_type_id' => $type->id, 'name' => 'Test',
            'status' => 'Upcoming', 'created_at' => now(), 'updated_at' => now(),
        ]);
        \App\Models\ItineraryStop::create([
            'itinerary_id' => $plan->id, 'church_id' => $church->id, 'stop_order' => 1, 'is_visited' => false,
        ]);

        $html = $this->actingAs($user)->get(route('plan.show', $plan))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><img', $html);
    }

    public function test_the_shared_escape_helper_exists_and_the_map_list_uses_it(): void
    {
        $this->assertStringContainsString('window.giyaEsc = esc', file_get_contents(public_path('assets/js/giya.js')));

        $map = file_get_contents(resource_path('views/map.blade.php'));
        $this->assertStringNotContainsString("'<i class=\"bi bi-geo-alt-fill\"></i>' + c.location", $map);
        $this->assertStringNotContainsString("'<span class=\"mx-tray-name\">' + s.name", $map);
    }

    /* ── CSV formula injection ──────────────────────────────────────── */

    public function test_csv_cells_that_look_like_formulas_are_neutralised(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", Csv::cell('=HYPERLINK("x")'));
        $this->assertSame("'+cmd|' /C calc'!A0", Csv::cell("+cmd|' /C calc'!A0"));
        $this->assertSame("'@SUM(A1)", Csv::cell('@SUM(A1)'));
        $this->assertSame('-12', Csv::cell('-12'), 'A real negative number must stay a number.');
        $this->assertSame(-3.5, Csv::cell(-3.5));
        $this->assertSame('Lyle', Csv::cell('Lyle'));
    }

    public function test_the_user_export_neutralises_a_formula_name(): void
    {
        $admin = $this->devotee('admin@example.com', ['role' => 'admin']);
        $this->devotee('evil@example.com', ['name' => '=HYPERLINK("http://evil.example","x")']);

        $csv = $this->actingAs($admin)->get(route('admin.users.export'))->streamedContent();

        $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
    }

    /* ── Secrets ────────────────────────────────────────────────────── */

    public function test_no_maya_key_is_hardcoded_in_config(): void
    {
        $config = file_get_contents(config_path('services.php'));

        $this->assertDoesNotMatchRegularExpression('/[ps]k-[A-Za-z0-9]{20,}/', $config,
            'A Maya key is a default in config/services.php - it belongs in .env.');
    }

    /* ── Notifications ──────────────────────────────────────────────── */

    public function test_a_devotee_can_dismiss_only_their_own_notification(): void
    {
        $mine = $this->devotee();
        $theirs = $this->devotee('other@example.com');

        $n = Notification::create(['user_id' => $theirs->id, 'title' => 'Hi', 'is_read' => false, 'created_at' => now()]);

        $this->actingAs($mine)->deleteJson(route('notifications.destroy', $n))->assertForbidden();
        $this->assertDatabaseHas('notifications', ['id' => $n->id]);

        $this->actingAs($theirs)->deleteJson(route('notifications.destroy', $n))->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $n->id]);
    }
}
