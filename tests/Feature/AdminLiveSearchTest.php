<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin lists filter as you type: Destinations, Users and Schedules swap
 * only their list a moment after the last key, with no Enter or Apply.
 */
class AdminLiveSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Giya Administrator', 'email' => 'admin@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_three_lists_search_as_you_type(): void
    {
        $admin = $this->admin();

        foreach (['admin.destinations' => 'dmList', 'admin.users' => 'umList', 'admin.schedules' => 'smList'] as $route => $list) {
            $html = $this->actingAs($admin)->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('data-soft-list-form="'.$list.'"', $html, $route);
            $this->assertStringContainsString('giya-soft-list.js', $html, $route);
            $this->assertStringContainsString('autocomplete="off"', $html, $route);
        }

        $js = file_get_contents(public_path('assets/js/giya-soft-list.js'));
        $this->assertStringContainsString("input.matches('input[type=search]')", $js);
        $this->assertStringContainsString('window.history.replaceState', $js);
        $this->assertStringContainsString("err.name === 'AbortError'", $js);
    }

    public function test_a_search_with_no_church_says_so(): void
    {
        $this->actingAs($this->admin())->get(route('admin.destinations', ['search' => 'zzzz']))->assertOk()
            ->assertSee('No church matches this search.')
            ->assertDontSee('No destinations yet.');
    }
}
