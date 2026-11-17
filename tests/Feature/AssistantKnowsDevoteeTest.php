<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Favorite;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\ItineraryType;
use App\Models\User;
use App\Models\VisitHistory;
use App\Services\GiyaAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Giya AI knows the signed-in devotee's visits, favorites and trips. */
class AssistantKnowsDevoteeTest extends TestCase
{
    use RefreshDatabase;

    private function church(string $name, string $town): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create([
            'name' => $name, 'category_id' => $cat->id, 'location' => "{$town}, Cebu", 'municipality' => $town,
            'latitude' => 10.3, 'longitude' => 123.9, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function prompt(string $question = 'What church did I visit last?'): string
    {
        $assistant = app(GiyaAssistant::class);
        $m = new \ReflectionMethod($assistant, 'systemPrompt');

        return $m->invoke($assistant, $question, []);
    }

    public function test_a_signed_in_devotee_s_visits_favorites_and_trips_are_in_the_prompt(): void
    {
        $clint = User::create([
            'name' => 'Clint Capondag', 'email' => 'clint@example.com', 'password_hash' => bcrypt('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $nino   = $this->church('Basilica del Santo Nino', 'Cebu City');
        $simala = $this->church('Simala Shrine', 'Sibonga');
        $argao  = $this->church('St. Michael Archangel Parish', 'Argao');

        VisitHistory::create(['user_id' => $clint->id, 'church_id' => $nino->id, 'visited_at' => now()->subDays(3), 'completion_status' => 'Completed', 'created_at' => now(), 'updated_at' => now()]);
        VisitHistory::create(['user_id' => $clint->id, 'church_id' => $simala->id, 'visited_at' => now()->subDay(), 'completion_status' => 'Completed', 'created_at' => now(), 'updated_at' => now()]);
        Favorite::create(['user_id' => $clint->id, 'church_id' => $argao->id, 'is_active' => true, 'created_at' => now()]);
        Favorite::create(['user_id' => $clint->id, 'church_id' => $nino->id, 'is_active' => true, 'created_at' => now()->subHour()]);

        $type = ItineraryType::firstOrCreate(['name' => 'Visita Iglesia'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $trip = Itinerary::create(['user_id' => $clint->id, 'itinerary_type_id' => $type->id, 'name' => 'Holy Week walk', 'status' => 'Upcoming', 'created_at' => now(), 'updated_at' => now()]);
        ItineraryStop::create(['itinerary_id' => $trip->id, 'church_id' => $nino->id, 'stop_order' => 1, 'is_visited' => true]);
        ItineraryStop::create(['itinerary_id' => $trip->id, 'church_id' => $argao->id, 'stop_order' => 2, 'is_visited' => false]);

        $this->actingAs($clint);
        $prompt = $this->prompt();

        $this->assertStringContainsString('ABOUT THIS DEVOTEE', $prompt);
        $this->assertStringContainsString('Name: Clint.', $prompt);
        $this->assertStringContainsString('(latest first; 2 visits to 2 churches in all)', $prompt);
        // Latest first: Simala yesterday before the Basilica three days ago.
        $this->assertMatchesRegularExpression('/Simala Shrine \(Sibonga\)\n- [A-Z][a-z]{2} \d+, \d{4}: Basilica del Santo Nino \(Cebu City\)/', $prompt);
        $this->assertStringContainsString('- St. Michael Archangel Parish (Argao), not visited yet', $prompt);
        $this->assertStringContainsString('- Basilica del Santo Nino (Cebu City), visited', $prompt);
        $this->assertStringContainsString('"Holy Week walk" (Visita Iglesia, Upcoming, no date): 1 of 2 churches visited. Stops in order: Basilica del Santo Nino (visited); St. Michael Archangel Parish', $prompt);
        $this->assertStringContainsString('suggest favorites they have not visited yet', $prompt);
    }

    public function test_someone_else_s_history_never_appears(): void
    {
        $other = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password_hash' => bcrypt('Secret123!'), 'role' => 'user', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        $me    = User::create(['name' => 'Ben', 'email' => 'ben@example.com', 'password_hash' => bcrypt('Secret123!'), 'role' => 'user', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        $c = $this->church('Simala Shrine', 'Sibonga');
        VisitHistory::create(['user_id' => $other->id, 'church_id' => $c->id, 'visited_at' => now(), 'completion_status' => 'Completed', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($me);
        $prompt = $this->prompt();

        $this->assertStringContainsString('Visited churches: none yet.', $prompt);
        $this->assertStringContainsString('Favorites: none yet.', $prompt);
        $this->assertStringContainsString('Itineraries: none yet.', $prompt);
    }

    public function test_a_guest_is_told_to_sign_in_for_this(): void
    {
        $this->assertStringContainsString('THE DEVOTEE is not signed in', $this->prompt());
        $this->assertStringNotContainsString('ABOUT THIS DEVOTEE', $this->prompt());
    }
}
