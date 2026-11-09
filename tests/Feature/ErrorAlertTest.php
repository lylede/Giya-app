<?php

namespace Tests\Feature;

use App\Support\ErrorAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.error_alert.in_tests' => true, 'cache.default' => 'array']);
        Route::middleware('web')->post('/boom', fn (Request $r) => throw new RuntimeException('Neon is down'));
    }

    public function test_a_crash_is_sent_to_the_team_webhook_without_private_data(): void
    {
        config(['services.error_alert.webhook' => 'https://discord.com/api/webhooks/1/abc']);
        Http::fake();

        $this->post('/boom?email=secret@example.com', ['password' => 'hunter2'])->assertStatus(500);

        Http::assertSent(function ($request) {
            $text = $request['content'];

            return str_contains($text, 'RuntimeException: Neon is down')
                && str_contains($text, 'POST /boom')
                && ! str_contains($text, 'hunter2')
                && ! str_contains($text, 'secret@example.com');
        });
    }

    public function test_the_same_error_alerts_once_every_ten_minutes(): void
    {
        config(['services.error_alert.webhook' => 'https://hooks.slack.com/services/x']);
        Http::fake();

        $e = new RuntimeException('Same bug');
        ErrorAlert::report($e);
        ErrorAlert::report($e);

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => isset($r['text']));   // Slack format
    }

    public function test_nothing_is_sent_when_no_alert_is_set_up(): void
    {
        config(['services.error_alert.webhook' => null, 'services.error_alert.email' => null]);
        Http::fake();

        ErrorAlert::report(new RuntimeException('x'));

        Http::assertNothingSent();
    }
}
