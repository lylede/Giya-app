<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\ItineraryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What GIYA shows while it is still getting ready.
 *
 * Two different waits, and they are not the same thing. Map tiles come from
 * a server and the panel over them leaves when Leaflet says they have
 * arrived. A list the script builds is already in the page as data, so its
 * skeleton covers the gap between the HTML painting and the script running -
 * short on a laptop, real on a phone fetching a separate file.
 *
 * Nowhere else gets one. A server-rendered page arrives complete, and a
 * skeleton there would be a delay we invented.
 */
class LoadingStatesTest extends TestCase
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

    /** @return \Illuminate\Support\Collection<int, Church> */
    private function seedChurches(int $count = 5)
    {
        $category = ChurchCategory::firstOrCreate(
            ['name' => 'Basilica'], ['created_at' => now(), 'updated_at' => now()]
        );

        return collect(range(1, $count))->map(fn ($i) => Church::create([
            'name' => "Church $i", 'category_id' => $category->id, 'location' => 'Cebu City',
            'latitude' => 10.29 + ($i / 1000), 'longitude' => 123.90 + ($i / 1000),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_the_map_waits_behind_the_turning_pin(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('map'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('giya-loading', $html);
        $this->assertStringContainsString(__('giya.map.loading'), $html);

        // The face and the edge are what make it an object rather than a
        // flipping sticker; losing the edge is a silent downgrade.
        $this->assertStringContainsString('giya-loading-face', $html);
        $this->assertStringContainsString('giya-loading-edge', $html);
    }

    public function test_the_map_list_holds_its_shape_until_the_script_fills_it(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('map'))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="churchList"[^>]*>\s*(<div class="gs-row")/',
            $html,
            'The church list should start as skeleton rows.'
        );
    }

    public function test_the_pilgrimage_waits_the_same_way(): void
    {
        $churches = $this->seedChurches(3);
        $user     = $this->devotee();
        $type     = ItineraryType::firstOrCreate(
            ['name' => 'Custom'],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        $itinerary = Itinerary::create([
            'user_id' => $user->id, 'itinerary_type_id' => $type->id,
            'name' => 'Holy Week', 'status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $order = 1;
        foreach ($churches as $church) {
            ItineraryStop::create([
                'itinerary_id' => $itinerary->id, 'church_id' => $church->id,
                'stop_order' => $order++, 'is_visited' => false,
            ]);
        }

        $html = $this->actingAs($user)->get(route('plan.show', $itinerary))
            ->assertOk()->getContent();

        $this->assertStringContainsString('giya-loading', $html);
        $this->assertMatchesRegularExpression(
            '/id="stopList"[^>]*>\s*(<div class="gs-row")/',
            $html,
            'The stop list should start as skeleton rows.'
        );
    }

    /**
     * The pieces only CSS can carry.
     *
     * The panel is taken away by a class the engine adds, and the shimmer is
     * an animation - neither is visible to an assertion about markup, and
     * both fail silently. A page with a loading panel that never leaves is
     * worse than one with no panel at all.
     */
    public function test_the_stylesheet_can_take_the_panel_away_again(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        $this->assertMatchesRegularExpression(
            '/\.is-map-ready\s+\.giya-loading\s*\{[^}]*(opacity:\s*0|visibility:\s*hidden)/',
            $css,
            'giya.css must hide the loading panel once the map is ready.'
        );

        $this->assertMatchesRegularExpression(
            '/@keyframes\s+giya-pin-turn\s*\{[^}]*rotateY/',
            $css,
            'The pin should turn in three dimensions, which is what rotateY does.'
        );

        $this->assertMatchesRegularExpression(
            '/@keyframes\s+giya-shimmer/',
            $css,
            'Skeletons need the shimmer they are named for.'
        );

        /* A loading animation is exactly the kind of thing that makes some
           people ill, and it runs unprompted. Named elements, not just the
           media query: GIYA has a dozen reduced-motion blocks already, so
           merely finding one proves nothing about these. */
        $this->assertMatchesRegularExpression(
            '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{[^}]*giya-loading-pin[^}]*\}/',
            $css,
            'The turning pin must hold still for anyone who asks for less motion.'
        );
    }

    /** The engine has to know when the tiles are actually on screen. */
    public function test_the_engine_waits_for_leaflet_rather_than_a_timer(): void
    {
        $js = file_get_contents(public_path('assets/js/giya-leaflet.js'));

        $this->assertStringContainsString("layer.on('load'", $js,
            "The panel should leave on Leaflet's own load event.");
        $this->assertStringContainsString('is-map-ready', $js);

        // And must not hang when the tile server only ever answers errors.
        $this->assertStringContainsString("layer.on('tileerror'", $js);
    }
}
