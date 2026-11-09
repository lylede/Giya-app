<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailCheckTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        return User::create(['name' => 'Admin', 'email' => $role.'@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => $role, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_it_shows_the_settings_without_secret_values(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.gmail.com', 'mail.mailers.smtp.password' => 'super-secret-app-pass']);

        $this->actingAs($this->person('admin'))->get(route('admin.mail'))->assertOk()
            ->assertSee('smtp.gmail.com')->assertSee('blocks SMTP')
            ->assertDontSee('super-secret-app-pass');
    }

    public function test_a_failed_test_email_shows_the_error_and_hides_the_key(): void
    {
        config(['mail.default' => 'brevo', 'services.brevo.key' => 'xkeysib-abc123']);
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'Key not found: xkeysib-abc123'], 401)]);
        $admin = $this->person('admin');

        $this->actingAs($admin)->post(route('admin.mail.send'))->assertSessionHas('mail_error');
        $error = session('mail_error');

        $this->assertStringContainsString('401', $error['message']);
        $this->assertStringNotContainsString('abc123', $error['message']);
    }

    public function test_a_working_mailer_reports_success(): void
    {
        config(['mail.default' => 'array']);

        $this->actingAs($this->person('admin'))->post(route('admin.mail.send'))->assertSessionHas('success');
    }

    public function test_devotees_cannot_use_it(): void
    {
        $this->actingAs($this->person('user'))->get(route('admin.mail'))->assertForbidden();
    }
}
