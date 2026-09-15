<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin panel's toolbar and its forms.
 *
 * Three things were wrong and all three were the same kind of wrong: a value
 * typed into the markup, where nothing else could see it or override it.
 *
 * The Add button had a row of its own above a full-width search, so the
 * schedules screen opened with four stacked rows of controls and the table
 * itself below the fold. Field labels were 15px bold in the full text colour,
 * the same weight as the values under them, so a form of eight fields read as
 * sixteen equally loud lines. And every modal was capped at 460px by the
 * dialog around it - including the destination form, which has more fields
 * than any other screen in the app and a map as well - while the inline style
 * inside it asked for 640 and was quietly overruled.
 *
 * How wide a modal ends up is a browser's question and is measured there. What
 * is asserted here is that the values are no longer typed into the templates.
 */
class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'admin',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function page(string $name): string
    {
        $cat = ChurchCategory::create(['name' => 'Church']);

        foreach (['Basilica del Santo Nino', 'Cebu Metropolitan Cathedral', 'Simala Shrine'] as $i => $n) {
            Church::create([
                'category_id' => $cat->id, 'name' => $n, 'municipality' => 'Cebu City',
                'latitude' => 10.3 + $i / 100, 'longitude' => 123.9, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $html = $this->actingAs($this->admin())->get("/admin/{$name}")->getContent();

        if (env('GIYA_DUMP_ADMIN')) {
            file_put_contents("/tmp/admin-{$name}.html", preg_replace(
                '#https?://[^"\']*/assets/#',
                'file://'.public_path('assets').'/',
                $html
            ));
        }

        return $html;
    }

    public function test_the_add_button_shares_a_row_with_the_search(): void
    {
        $html = $this->page('schedules');

        // One bar holding both, rather than a button alone above a search.
        $this->assertStringContainsString('sm-bar', $html);
        $this->assertStringContainsString('sm-bar-action', $html);

        /* The old markup put the button in its own right-aligned flex row.
           If that is back, so is the wasted row. */
        $this->assertStringNotContainsString('d-flex justify-content-end mb-3', $html);
    }

    public function test_the_forms_ask_for_a_width_instead_of_being_given_460(): void
    {
        $schedules = $this->page('schedules');

        $this->assertStringContainsString('modal-dialog is-wide', $schedules);

        // The width lived in a style attribute that the dialog overruled.
        $this->assertStringNotContainsString('max-width:640px', $schedules);
    }

    public function test_the_destination_form_is_the_widest_because_it_is_the_longest(): void
    {
        $html = $this->page('destinations');

        $this->assertStringContainsString('modal-dialog is-wider', $html);

        // None of the three dialogs carries its own inline sizing any more.
        $this->assertStringNotContainsString('max-width:460px', $html);
        $this->assertStringNotContainsString('max-width:520px', $html);
    }

    public function test_the_modal_widths_are_all_bounded_by_the_viewport(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        /* Every width is a min() against the screen, so "wide" on a desktop
           is still inside a phone. A bare max-width here would be a modal you
           cannot see the edges of. */
        $this->assertMatchesRegularExpression(
            '/\.modal-dialog\s*\{[^}]*max-width:\s*min\(/s',
            $css,
            'the dialog width is not clamped to the viewport'
        );
    }
}
