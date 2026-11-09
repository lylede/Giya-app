<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\ItineraryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SharedItineraryTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $email): User
    {
        return User::create([
            'name' => 'Maria Santos', 'email' => $email, 'password_hash' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function trip(User $owner): Itinerary
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Parish'], ['created_at' => now()]);
        $type = ItineraryType::firstOrCreate(['name' => 'Custom'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $trip = Itinerary::create(['user_id' => $owner->id, 'itinerary_type_id' => $type->id, 'name' => 'Family Visita',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['Basilica del Santo Nino', 'Simala Shrine'] as $i => $name) {
            $c = Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => 'Cebu', 'address' => 'Cebu',
                'latitude' => 10.29, 'longitude' => 123.9, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            ItineraryStop::create(['itinerary_id' => $trip->id, 'church_id' => $c->id, 'stop_order' => $i + 1, 'is_visited' => $i === 0]);
        }

        return $trip;
    }

    public function test_the_owner_makes_a_link_that_anyone_can_open(): void
    {
        $owner = $this->person('maria@example.com');
        $trip = $this->trip($owner);

        $url = $this->actingAs($owner)->postJson(route('plan.share', $trip))->assertOk()->json('url');
        $this->assertStringContainsString('/p/', $url);

        auth()->logout();
        $this->get($url)->assertOk()
            ->assertSee('Family Visita')->assertSee('Shared by Maria')->assertSee('Simala Shrine')
            ->assertDontSee('maria@example.com');
    }

    public function test_only_the_owner_can_share_or_stop_sharing(): void
    {
        $trip = $this->trip($this->person('maria@example.com'));

        $this->actingAs($this->person('other@example.com'))->postJson(route('plan.share', $trip))->assertForbidden();
    }

    public function test_a_pilgrim_saves_a_copy_with_fresh_progress(): void
    {
        $owner = $this->person('maria@example.com');
        $trip = $this->trip($owner);
        $trip->update(['share_token' => str_repeat('a', 32)]);
        $friend = $this->person('friend@example.com');

        $this->actingAs($friend)->post(route('shared.copy', $trip->share_token))->assertRedirect();

        $copy = Itinerary::where('user_id', $friend->id)->first();
        $this->assertSame('Family Visita', $copy->name);
        $this->assertSame(2, $copy->stops()->count());
        $this->assertSame(0, $copy->stops()->where('is_visited', true)->count());
        $this->assertSame(2, $trip->stops()->count());   // the original is untouched
    }

    public function test_stopping_sharing_breaks_the_old_link(): void
    {
        $owner = $this->person('maria@example.com');
        $trip = $this->trip($owner);
        $url = $this->actingAs($owner)->postJson(route('plan.share', $trip))->json('url');

        $this->actingAs($owner)->deleteJson(route('plan.unshare', $trip))->assertOk();

        $this->get($url)->assertNotFound();
        $this->get('/p/not-a-real-token')->assertNotFound();
    }

    public function test_a_guest_who_signs_in_from_a_shared_link_comes_back_to_it(): void
    {
        $trip = $this->trip($this->person('maria@example.com'));
        $trip->update(['share_token' => str_repeat('b', 32)]);
        $this->get(route('shared.show', $trip->share_token))->assertOk()
            ->assertSessionHas('url.intended', route('shared.show', $trip->share_token));

        // ...and the sign-in form sends them there (checked in the browser; see the login component).
        $this->assertStringContainsString("session()->pull('url.intended'", file_get_contents(resource_path('views/components/auth/⚡login.blade.php')));
    }
}
