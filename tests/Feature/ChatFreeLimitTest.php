<?php

namespace Tests\Feature;

use App\Http\Controllers\ChatbotController;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GiyaAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Giya AI and the free limits.
 *
 * A free devotee who has used the 3 free itineraries still gets answers, but
 * not a new trip: a route request gets the limit and an Upgrade button, and
 * an answer's trip button becomes Upgrade. Free devotees may also ask 20
 * questions a day. Premium has neither limit.
 */
class ChatFreeLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GiyaAssistant::forget();
        config(['services.groq.key' => 'gsk-test', 'services.groq.model' => 'openai/gpt-oss-120b', 'services.groq.fallback_model' => null]);

        // South Cebu down the east coast, so "Plan a Visita Iglesia in south Cebu" is a route.
        foreach ([
            ['San Fernando Rey Parish', 'San Fernando', 10.1617, 123.7083],
            ['St. Catherine Parish', 'Carcar City', 10.1062, 123.6402],
            ['Simala Shrine', 'Sibonga', 10.0250, 123.6247],
            ['St. Michael Parish', 'Argao', 9.8794, 123.6070],
            ['San Guillermo Parish', 'Dalaguete', 9.7614, 123.5345],
            ['Sto. Tomas Parish', 'Alcoy', 9.7085, 123.5063],
            ['Patrocinio de Maria Parish', 'Boljoon', 9.6295, 123.4797],
            ['Our Lady of the Immaculate Conception', 'Oslob', 9.5196, 123.4321],
        ] as [$name, $town, $lat, $lng]) {
            $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);
            Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => $town, 'municipality' => $town,
                'latitude' => $lat, 'longitude' => $lng, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function devotee(): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => 'lyle'.uniqid().'@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function premium(): User
    {
        $user = $this->devotee();
        $plan = SubscriptionPlan::firstOrCreate(['name' => 'Pilgrim Monthly'], [
            'description' => 'One month.', 'price' => 99.00, 'currency' => 'PHP', 'duration_days' => 30,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Transaction::create([
            'user_id' => $user->id, 'plan_type_id' => $plan->id, 'amount' => 99.00, 'currency' => 'PHP',
            'method' => 'Maya', 'provider' => 'maya', 'status' => 'Paid', 'reference_no' => 'GIYA-'.uniqid(),
            'created_at' => now(), 'processed_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function useUpFreeItineraries(User $user): void
    {
        foreach (['One', 'Two', 'Three'] as $name) {
            $this->actingAs($user)->post(route('plan.store'), ['name' => $name, 'type' => 'Custom', 'stops' => ['Simala Shrine']]);
        }
    }

    private function modelSays(string $reply): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '[mood:happy] '.$reply]]]])]);
    }

    private function questions(User $user, int $n, $when): void
    {
        $session = ChatSession::create(['user_id' => $user->id, 'status' => 'Ended', 'started_at' => $when, 'created_at' => $when]);
        for ($i = 0; $i < $n; $i++) {
            ChatMessage::create(['session_id' => $session->id, 'sender_type' => 'user', 'message' => 'Question '.$i, 'created_at' => $when]);
        }
    }

    public function test_at_the_limit_a_trip_request_gets_the_limit_and_upgrade_without_asking_the_model(): void
    {
        Http::fake();
        $user = $this->devotee();
        $this->useUpFreeItineraries($user);

        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'Plan a Visita Iglesia in south Cebu'])
            ->assertOk()
            ->assertJsonPath('reason', 'itinerary_limit')
            ->assertJsonPath('plan_url', '/upgrade')
            ->assertJsonPath('reply', fn ($r) => str_contains($r, '3 free itineraries') && str_contains($r, 'Premium'));

        Http::assertNothingSent();
    }

    public function test_at_the_limit_questions_are_still_answered_and_the_trip_button_becomes_upgrade(): void
    {
        $this->modelSays('**St. Michael Parish** in Argao has Mass at 6 AM on Sundays.');
        $user = $this->devotee();
        $this->useUpFreeItineraries($user);

        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'When is Sunday Mass in Argao?'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('plan_url', '/upgrade');

        // The model is told not to draw up a route for this devotee.
        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'FREE LIMIT'));
        $this->assertTrue(ChatMessage::where('sender_type', 'assistant')->first()->isUpgradeLink());
    }

    public function test_under_the_limit_the_route_opens_in_the_planner_as_before(): void
    {
        $this->modelSays('A lovely route down the coast.');
        $user = $this->devotee();

        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'Plan a Visita Iglesia in south Cebu'])
            ->assertOk()
            ->assertJsonPath('plan_url', fn ($u) => str_starts_with($u, '/plan/create?stops='));

        Http::assertSent(fn (Request $r) => ! str_contains($r->body(), 'FREE LIMIT'));
    }

    public function test_premium_is_never_held_to_the_itinerary_limit(): void
    {
        $this->modelSays('A lovely route down the coast.');
        $user = $this->premium();
        $this->useUpFreeItineraries($user);

        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'Plan a Visita Iglesia in south Cebu'])
            ->assertOk()
            ->assertJsonPath('plan_url', fn ($u) => str_starts_with($u, '/plan/create?stops='))
            ->assertJsonPath('left', null);
    }

    public function test_a_free_devotee_gets_twenty_questions_a_day(): void
    {
        Http::fake();
        $user = $this->devotee();
        $this->questions($user, ChatbotController::DAILY_FREE, now());

        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'Where is Simala Shrine?'])
            ->assertOk()
            ->assertJsonPath('reason', 'daily_limit')
            ->assertJsonPath('plan_url', '/upgrade');

        Http::assertNothingSent();
        $this->assertSame(ChatbotController::DAILY_FREE, ChatMessage::where('sender_type', 'user')->count(), 'The refused question must not be kept.');
    }

    public function test_yesterdays_questions_do_not_count_and_the_answer_says_how_many_are_left(): void
    {
        $this->modelSays('Simala Shrine is in Sibonga.');
        $user = $this->devotee();
        $this->questions($user, ChatbotController::DAILY_FREE, now()->timezone('Asia/Manila')->subDay()->setTime(20, 0)->timezone(config('app.timezone')));
        $this->questions($user, 4, now());

        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'Where is Simala Shrine?'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('left', ChatbotController::DAILY_FREE - 5);
    }

    public function test_premium_has_no_daily_limit(): void
    {
        $this->modelSays('Simala Shrine is in Sibonga.');
        $user = $this->premium();
        $this->questions($user, ChatbotController::DAILY_FREE + 5, now());

        $this->actingAs($user)->postJson(route('chatbot.send'), ['message' => 'Where is Simala Shrine?'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_the_upgrade_button_says_so_in_the_chat_history(): void
    {
        $m = new ChatMessage(['plan_url' => '/upgrade']);

        $this->assertTrue($m->isUpgradeLink());
        $this->assertSame('Upgrade to Premium', $m->planLabel());
        $this->assertFalse((new ChatMessage(['plan_url' => '/plan/create?stops=4']))->isUpgradeLink());
    }
}
