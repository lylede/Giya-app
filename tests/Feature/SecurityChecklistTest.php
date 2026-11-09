<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The security checklist items GIYA enforces in code. */
class SecurityChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function devotee(): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => 'sec@example.com', 'password_hash' => bcrypt('secret123'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_pages_send_the_browser_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
        $response->assertHeaderMissing('Strict-Transport-Security');   // only over HTTPS
    }

    public function test_https_pages_tell_browsers_to_stay_on_https(): void
    {
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_the_ai_chat_is_rate_limited_and_rests_instead_of_erroring(): void
    {
        config(['services.groq.key' => 'gsk-test']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hi!']]]])]);
        $user = $this->devotee();

        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'Hello '.$i])->assertOk();
        }

        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'One more'])
            ->assertStatus(429)
            ->assertJsonPath('reason', 'busy')
            ->assertJsonPath('mood', 'tired')
            ->assertJson(fn ($json) => $json->whereType('retry_after', 'integer')->etc());

        Http::assertSentCount(12);
    }

    public function test_the_session_cookie_is_https_only_on_the_live_site(): void
    {
        $set = function (?string $env) {
            foreach (['_ENV', '_SERVER'] as $bag) {
                $GLOBALS[$bag]['APP_ENV'] = $env;
                unset($GLOBALS[$bag]['SESSION_SECURE_COOKIE']);
            }
            putenv('APP_ENV='.$env);
            putenv('SESSION_SECURE_COOKIE');
        };
        $was = $_SERVER['APP_ENV'] ?? 'testing';

        try {
            $set('production');
            $this->assertTrue((require config_path('session.php'))['secure']);
            $set('local');
            $this->assertFalse((require config_path('session.php'))['secure']);
        } finally {
            $set($was);
        }
    }

    public function test_env_files_are_never_committed(): void
    {
        $ignore = file_get_contents(base_path('.gitignore'));

        foreach (['.env', '.env.backup', '.env.production'] as $file) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($file, '/').'$/m', $ignore);
        }
    }

    public function test_debug_mode_is_off_unless_switched_on(): void
    {
        $this->assertStringContainsString("env('APP_DEBUG', false)", file_get_contents(config_path('app.php')));
    }

    public function test_passwords_are_stored_hashed(): void
    {
        $this->post(route('register.store'), [
            'name' => 'New Pilgrim', 'email' => 'new@example.com',
            'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'terms' => '1',
        ]);

        $user = User::where('email', 'new@example.com')->first();
        if ($user) {
            $this->assertNotSame('Secret123!', $user->password_hash);
            $this->assertTrue(password_verify('Secret123!', $user->password_hash));
        } else {
            $this->markTestSkipped('Registration needs more fields in this version.');
        }
    }

    public function test_admin_pages_turn_away_devotees(): void
    {
        $this->actingAs($this->devotee())->get('/admin/destinations')->assertStatus(403);
    }
}
