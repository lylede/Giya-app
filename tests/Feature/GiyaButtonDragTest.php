<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Giya button moves like iPhone's AssistiveTouch: drag it, it snaps to
 * the nearest side, the spot is remembered on this device, and a tap still
 * opens the chat.
 */
class GiyaButtonDragTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_with_the_button_loads_the_drag_script(): void
    {
        $user = User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('home'))->assertOk()
            ->assertSee('id="giyaFab"', false)
            ->assertSee('giya-fab-drag.js', false);
    }

    public function test_it_snaps_to_a_side_remembers_the_spot_and_a_drag_is_not_a_tap(): void
    {
        $js = file_get_contents(public_path('assets/js/giya-fab-drag.js'));

        $this->assertStringContainsString("side = r.left + r.width / 2 < window.innerWidth / 2 ? 'left' : 'right'", $js);
        $this->assertStringContainsString("localStorage.setItem(KEY", $js);
        // The click that ends a drag is swallowed before the chat sees it.
        $this->assertStringContainsString("window.addEventListener('click'", $js);
        $this->assertStringContainsString('e.stopImmediatePropagation()', $js);
        // Never on the trip screen's bottom panel.
        $this->assertStringContainsString('.active-layout .active-sidebar', $js);
    }

    public function test_dragging_does_not_scroll_the_page_and_the_greeting_follows_the_side(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        $this->assertStringContainsString('.giya-fab { touch-action: none;', $css);
        $this->assertStringContainsString('.giya-fab.is-left .fab-say { right: auto; left: 4px;', $css);
        $this->assertStringContainsString('.giya-fab.is-high .fab-say { bottom: auto; top: calc(100% + 14px);', $css);
    }
}
