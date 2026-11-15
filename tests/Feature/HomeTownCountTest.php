<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HomeTownCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_counts_towns_and_cities_not_addresses(): void
    {
        // The seed has 10 churches at 7 different addresses in 4 places:
        // Cebu City, Sibonga, Lapu-Lapu City and Mandaue City.
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $user = User::create([
            'name' => 'Maria', 'email' => 'maria@example.com', 'password_hash' => Hash::make('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('home'))->assertOk()
            ->assertSee('Municipalities &amp; Cities', false)
            ->assertViewHas('stats', fn ($stats) => $stats['cities'] === 4);
    }
}
