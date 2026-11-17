<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavThemeSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function pilgrim(): User
    {
        return User::create([
            'name' => 'Clint', 'email' => 'clint@example.com', 'password_hash' => bcrypt('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_signed_in_the_button_saves_to_the_account(): void
    {
        $user = $this->pilgrim();

        $this->actingAs($user)->get(route('home'))->assertOk()
            ->assertSee('id="navTheme"', false)
            ->assertSee('data-save-url="'.route('profile.preference').'"', false)
            ->assertSee('Switch to dark mode')
            ->assertDontSee("localStorage.getItem('giya-theme')", false);

        // What the button sends, and the next page comes back dark.
        $this->actingAs($user)->patchJson(route('profile.preference'), ['field' => 'theme_style', 'value' => 'Dark'])
            ->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($user)->get(route('home'))->assertSee('data-theme="dark"', false);
    }

    public function test_a_guest_gets_the_button_and_it_is_remembered_on_the_device(): void
    {
        $this->get(route('about'))->assertOk()
            ->assertSee('id="navTheme"', false)
            ->assertSee('data-save-url=""', false)
            ->assertSee("localStorage.getItem('giya-theme')", false);
    }

    public function test_it_speaks_cebuano_and_filipino(): void
    {
        app()->setLocale('ceb');
        $this->assertSame('Ibalhin sa dark mode', __('giya.nav.theme_dark'));
        app()->setLocale('fil');
        $this->assertSame('Lumipat sa light mode', __('giya.nav.theme_light'));
    }
}
