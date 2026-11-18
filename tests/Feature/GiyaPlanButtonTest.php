<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Services\GiyaAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Giya AI: an answer that names GIYA churches carries a button that puts them on the planner. */
class GiyaPlanButtonTest extends TestCase
{
    use RefreshDatabase;

    private function church(string $name, string $town): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => $town, 'municipality' => $town,
            'latitude' => 10.5, 'longitude' => 124.0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function answer(string $reply, string $question = 'Which churches should I visit in north Cebu?'): array
    {
        GiyaAssistant::forget();
        config(['services.groq.key' => 'gsk-test', 'services.groq.model' => 'openai/gpt-oss-120b']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '[mood:happy] '.$reply]]]])]);

        return app(GiyaAssistant::class)->ask($question);
    }

    public function test_named_churches_become_planner_stops_in_order(): void
    {
        $sogod  = $this->church('St. James the Apostle Parish', 'Sogod');
        $carmen = $this->church('St. Michael Parish', 'Carmen');

        $r = $this->answer("Try **St. Michael Parish**, Carmen, then **St. James the Apostle Parish** in Sogod.");

        $this->assertSame('/plan/create?stops='.urlencode($carmen->id.','.$sogod->id).'&picked=1', $r['plan_url']);
    }

    public function test_a_shared_name_is_told_apart_by_its_town(): void
    {
        $this->church('Sto. Nino Parish', 'Mandaue City');
        $talisay = $this->church('Sto. Nino Parish', 'Talisay City');

        $r = $this->answer('Visit **Sto. Nino Parish (Talisay City)** in the morning.');
        $this->assertStringContainsString('stops='.$talisay->id.'&', $r['plan_url']);
    }

    public function test_a_shared_name_without_a_town_is_not_guessed(): void
    {
        $this->church('Sto. Nino Parish', 'Mandaue City');
        $this->church('Sto. Nino Parish', 'Talisay City');

        $this->assertArrayNotHasKey('plan_url', $this->answer('Visit **Sto. Nino Parish** in the morning.'));
    }

    public function test_no_button_when_no_giya_church_is_named(): void
    {
        $this->church('St. Michael Parish', 'Carmen');

        $this->assertArrayNotHasKey('plan_url', $this->answer('A novena is nine days of prayer. **Pray** with an open heart.', 'What is a novena?'));
    }

    public function test_the_button_says_what_it_does(): void
    {
        $one   = new ChatMessage(['plan_url' => '/plan/create?stops=4&picked=1']);
        $many  = new ChatMessage(['plan_url' => '/plan/create?stops=4%2C7&picked=1']);
        $route = new ChatMessage(['plan_url' => '/plan/create?stops=4%2C7']);

        $this->assertSame('Plan a trip to this church', $one->planLabel());
        $this->assertSame('Plan a trip with these churches', $many->planLabel());
        $this->assertSame('Open this route in the planner', $route->planLabel());
    }

    public function test_the_link_opens_the_planner_with_those_stops(): void
    {
        $this->get('/plan/create?stops=4%2C7&picked=1')->assertRedirect();
        $user = \App\Models\User::create(['name' => 'Juan', 'email' => 'j@example.com', 'password_hash' => bcrypt('x'),
            'role' => 'devotee', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get('/plan/create?stops=4%2C7&picked=1')
            ->assertRedirect(route('map', ['plan' => 1, 'stops' => '4,7']));
    }
}
