<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Add GIYA to Home Screen": a card in Profile > Preferences, hidden until
 * giya-install.js knows this phone can add GIYA, with the steps for iPhone
 * and for Android browsers that cannot be asked directly. Not in the menu.
 */
class InstallButtonTest extends TestCase
{
    use RefreshDatabase;

    private function devotee(): \App\Models\User
    {
        return \App\Models\User::create([
            'name' => 'Pilgrim', 'email' => 'pilgrim@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_preferences_has_the_card_and_the_page_has_both_sets_of_steps(): void
    {
        $this->actingAs($this->devotee())->get(route('profile'))
            ->assertOk()
            ->assertSee('data-giya-install-card hidden', false)
            ->assertSee('data-giya-install>', false)
            ->assertSee('id="installModal"', false)
            ->assertSee('data-install-steps="ios"', false)
            ->assertSee('data-install-steps="android"', false)
            ->assertSee(__('giya.install.ios_2'))
            ->assertSee('giya-install.js', false);
    }

    public function test_it_is_not_in_the_menu(): void
    {
        $this->actingAs($this->devotee())->get(route('profile'))
            ->assertDontSee('mobile-nav-install', false);
        $this->get(route('about'))->assertDontSee('data-giya-install', false);
    }

    public function test_the_script_uses_the_browsers_own_install_dialog_when_it_can(): void
    {
        $js = file_get_contents(public_path('assets/js/giya-install.js'));

        $this->assertStringContainsString('beforeinstallprompt', $js);
        $this->assertStringContainsString('.prompt()', $js);
        $this->assertStringContainsString("display-mode: standalone", $js);
        $this->assertStringContainsString('appinstalled', $js);
    }
}
