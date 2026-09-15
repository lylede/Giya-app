<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The deck itself is JavaScript and is measured in a browser. What Blade is
 * responsible for is the handover: the panel hands the script two translated
 * strings, and the names it hands them under have to stay out of the script's
 * way.
 *
 * They did not. The close button is found with closest('[data-dismiss]'), and
 * the panel body was given the button's LABEL under that same attribute name.
 * Every click anywhere in the panel then matched the body, so opening a
 * notification was handled as dismissing one and the card's link never fired.
 * Nothing looked broken - notifications simply stopped opening.
 */
class NotificationStackTest extends TestCase
{
    use RefreshDatabase;

    private function devotee(): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function navbar(): string
    {
        /* /home, not / - / is a redirect, and a redirect page contains no
           data-dismiss either, so the negative assertion below passed on it
           while proving nothing. The positive assertion is what caught it,
           which is why both are here and in that order. */
        return $this->actingAs($this->devotee())->get('/home')->getContent();
    }

    public function test_the_panel_body_does_not_use_the_buttons_attribute_name(): void
    {
        $html = $this->navbar();

        // The label is present, under a name of its own.
        $this->assertStringContainsString('data-dismiss-label=', $html);

        // And the bare name appears nowhere in the served markup, because the
        // only thing entitled to it is a button the script writes at runtime.
        $this->assertDoesNotMatchRegularExpression(
            '/data-dismiss\s*=/',
            $html,
            'data-dismiss is on server-rendered markup; closest() will match it for every click in the panel'
        );
    }

    public function test_the_panel_hands_over_both_strings(): void
    {
        $html = $this->navbar();

        $this->assertStringContainsString('data-empty="'.e(__('giya.nav.notif_empty')).'"', $html);
        $this->assertStringContainsString('data-dismiss-label="'.e(__('giya.nav.notif_dismiss')).'"', $html);
    }

    public function test_the_script_reads_the_renamed_attribute(): void
    {
        $js = file_get_contents(public_path('assets/js/giya-notifications.js'));

        // dataset.dismissLabel is the camelCase of data-dismiss-label. If the
        // Blade name changes and this does not, the button loses its label.
        $this->assertStringContainsString('dataset.dismissLabel', $js);

        // And the lookup stays scoped to the button.
        $this->assertStringContainsString("closest('button[data-dismiss]')", $js);
    }
}
