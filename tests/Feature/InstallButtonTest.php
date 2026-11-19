<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Add GIYA to Home Screen": the phone menu has the button, hidden until
 * giya-install.js knows this phone can add GIYA, and the page carries the
 * steps for iPhone and for Android browsers that cannot be asked directly.
 */
class InstallButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_menu_has_the_button_and_the_page_has_both_sets_of_steps(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('data-giya-install hidden', false)
            ->assertSee('id="installModal"', false)
            ->assertSee('data-install-steps="ios"', false)
            ->assertSee('data-install-steps="android"', false)
            ->assertSee(__('giya.install.ios_2'))
            ->assertSee('giya-install.js', false);
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
