<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GiyaNudgesTest extends TestCase
{
    use RefreshDatabase;

    private function nudgesOn(string $url): array
    {
        $user = User::create([
            'name' => 'Maria', 'email' => 'maria@example.com', 'password_hash' => Hash::make('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
        $this->assertSame(1, preg_match('/data-nudges="([^"]*)"/', $html, $m), "No messages on $url");

        return json_decode(html_entity_decode($m[1]), true);
    }

    public function test_the_robot_speaks_on_every_page_with_a_line_for_that_page(): void
    {
        $this->assertSame(__('giya.chat.nudge_map'), $this->nudgesOn('/map')[0]);
    }

    public function test_the_plan_page_leads_with_the_planning_line(): void
    {
        $this->assertSame(__('giya.chat.nudge_plan'), $this->nudgesOn(route('plan.hub'))[0]);
    }

    public function test_the_profile_page_leads_with_its_own_line(): void
    {
        $this->assertSame(__('giya.chat.nudge_profile'), $this->nudgesOn(route('profile'))[0]);
    }

    public function test_home_uses_the_general_lines(): void
    {
        $lines = $this->nudgesOn(route('home'));

        $this->assertSame(__('giya.chat.nudges'), $lines);
    }
}
