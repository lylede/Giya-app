<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Add to route used to throw the map out over Cebu: the list redrew and
 * re-framed every pin, and the route was framed whenever it ran off a phone's
 * screen. Checked in a phone-sized browser; these keep the rules in place.
 */
class MapStaysPutTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_church_does_not_reframe_the_map(): void
    {
        $js = file_get_contents(public_path('assets/js/giya-leaflet.js'));

        // The list redrawing with the same churches does not move the map.
        $this->assertStringContainsString('if (!shown.length || keepView || sameSet) return;', $js);
        // The route is framed only when asked, never because it is off-screen.
        $this->assertStringContainsString('if (fit) fitRoute();', $js);
        $this->assertStringNotContainsString('map.getBounds().contains(bounds)', $js);
        $this->assertStringContainsString('fitRoute: fitRoute', $js);
    }

    public function test_the_map_has_a_show_whole_route_button(): void
    {
        $user = User::create([
            'name' => 'Clint', 'email' => 'clint@example.com', 'password_hash' => bcrypt('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get('/map?plan=1')->assertOk()
            ->assertSee('id="btnFitRoute" hidden', false)
            ->assertSee('Show the whole route')
            ->assertSee("document.getElementById('btnFitRoute').hidden = stops.length < 2;", false);
    }
}
