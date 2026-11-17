<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Greeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GreetingAndMapPanelTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $manilaTime): Carbon
    {
        return Carbon::parse('2026-11-17 '.$manilaTime, 'Asia/Manila');
    }

    public function test_the_greeting_follows_the_time_in_manila(): void
    {
        $this->assertSame('Maayong buntag', Greeting::word($this->at('04:00')));
        $this->assertSame('Maayong buntag', Greeting::word($this->at('11:59')));
        $this->assertSame('Maayong udto', Greeting::word($this->at('12:30')));
        $this->assertSame('Maayong hapon', Greeting::word($this->at('15:00')));
        $this->assertSame('Maayong gabii', Greeting::word($this->at('18:31')));
        $this->assertSame('Maayong gabii', Greeting::word($this->at('02:00')));
        // A server clock in UTC still gives Manila's greeting: 10:31 UTC is 6:31 PM in Manila.
        $this->assertSame('Maayong gabii', Greeting::word(Carbon::parse('2026-11-17 10:31', 'UTC')));
    }

    public function test_the_chatbot_says_good_evening_in_the_evening(): void
    {
        $user = User::create([
            'name' => 'Ana', 'email' => 'ana@example.com', 'password_hash' => bcrypt('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->travelTo($this->at('18:31'));
        $this->actingAs($user)->get(route('chatbot'))->assertOk()
            ->assertSee('Maayong gabii! I am Giya AI.')
            ->assertDontSee('Maayong buntag!');

        $this->travelTo($this->at('08:00'));
        $this->actingAs($user)->get(route('chatbot'))->assertSee('Maayong buntag! I am Giya AI.');

        foreach (['ceb', 'fil'] as $locale) {
            app()->setLocale($locale);
            $this->assertStringStartsWith('Maayong hapon!', __('giya.chat.greeting_long', ['greet' => 'Maayong hapon']));
        }
    }

    public function test_giya_ai_is_told_the_time_and_the_right_greeting(): void
    {
        $this->travelTo($this->at('19:05'));
        $assistant = app(\App\Services\GiyaAssistant::class);
        $prompt = (new \ReflectionMethod($assistant, 'systemPrompt'))->invoke($assistant, 'hi', []);

        $this->assertMatchesRegularExpression('/It is now 7:05 PM in Cebu/', $prompt);
        $this->assertMatchesRegularExpression('/say\s+"Maayong gabii", never a greeting for another time of day/', $prompt);
    }

    public function test_the_map_is_hidden_under_an_open_panel_on_phones(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));
        $js  = file_get_contents(public_path('assets/js/giya-notifications.js'));

        $this->assertStringContainsString('body[data-page="map"].has-nav-overlay .map-grid,', $css);
        $this->assertStringContainsString('body[data-page="map"]:has(.nav-toggle-input:checked) .map-grid { visibility: hidden; }', $css);
        $this->assertStringContainsString("document.body.classList.toggle('has-nav-overlay', !!open);", $js);
        $this->assertStringContainsString('body.has-nav-overlay .giya-fab,', $css);
    }
}
