<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visita Iglesia suggests the main church of each of the nearest towns,
 * measured from the phone's location or a town the devotee picks. The
 * choosing runs in the browser (checked there with location on and off);
 * these keep what it is given and what it says.
 */
class VisitaNearTownsTest extends TestCase
{
    use RefreshDatabase;

    private function church(string $name, string $town, float $lat, float $lng, bool $major): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create([
            'name' => $name, 'category_id' => $cat->id, 'location' => "{$town}, Cebu", 'municipality' => $town,
            'latitude' => $lat, 'longitude' => $lng, 'is_major' => $major, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function pilgrim(): User
    {
        return User::create([
            'name' => 'Clint', 'email' => 'clint@example.com', 'password_hash' => bcrypt('Secret123!'),
            'role' => 'user', 'status' => 'Active', 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_page_gets_each_churchs_place_and_whether_it_is_the_towns_main_church(): void
    {
        $this->church('Carcar Parish', 'Carcar', 10.1066, 123.6402, true);
        $this->church('Carcar Chapel', 'Carcar', 10.11, 123.644, false);

        $html = $this->actingAs($this->pilgrim())->get(route('plan.visita'))->assertOk()->getContent();

        $this->assertStringContainsString('"name":"Carcar Parish","location":"Carcar, Cebu"', $html);
        $this->assertMatchesRegularExpression('/"name":"Carcar Parish"[^}]*"lat":10\.1066,"lng":123\.6402,"major":true,"town":"Carcar"/', $html);
        $this->assertMatchesRegularExpression('/"name":"Carcar Chapel"[^}]*"major":false/', $html);

        // It asks for the location first, and the old alphabetical seven is gone.
        $this->assertStringContainsString('navigator.geolocation.getCurrentPosition', $html);
        $this->assertStringNotContainsString('all.slice(0, Math.min(7, all.length))', $html);
    }

    public function test_the_starting_towns_are_one_per_town_from_main_churches_in_order(): void
    {
        $this->church('Talisay Parish', 'Talisay', 10.2447, 123.8494, true);
        $this->church('Argao Parish', 'Argao', 9.88, 123.60, true);
        $this->church('Argao Shrine', 'Argao', 9.881, 123.601, true);   // a second main church: still one Argao
        $this->church('Naga Chapel', 'Naga', 10.2087, 123.7577, false); // not a main church: not a starting town

        $html = $this->actingAs($this->pilgrim())->get(route('plan.visita'))->assertOk()->getContent();

        preg_match_all('/<option value="\d+">([^<]+)<\/option>/', $html, $m);
        $this->assertSame(['Argao', 'Talisay'], $m[1]);
        $this->assertStringContainsString('Choose your town', $html);
    }

    public function test_it_explains_itself_in_all_three_languages(): void
    {
        foreach (['en', 'ceb', 'fil'] as $locale) {
            app()->setLocale($locale);
            foreach (['visita_near_you', 'visita_near_town', 'visita_near_you_plus', 'visita_near_town_plus'] as $key) {
                $text = __("giya.plan.{$key}");
                $this->assertStringContainsString(':count', $text, "{$locale} {$key}");
            }
            $this->assertStringContainsString(':total', __('giya.plan.visita_near_town_plus'));
            $this->assertStringContainsString(':town', __('giya.plan.visita_near_town'));
        }
        app()->setLocale('en');
    }

    public function test_a_route_back_from_the_map_is_kept_not_replaced(): void
    {
        $a = $this->church('Talisay Parish', 'Talisay', 10.2447, 123.8494, true);

        $this->actingAs($this->pilgrim())->get(route('plan.visita', ['stops' => $a->id]))->assertOk()
            ->assertSee('if (returning.length) {', false)
            ->assertSee('"name":"Talisay Parish"', false);
    }

    public function test_the_route_is_reordered_by_dragging_not_arrows(): void
    {
        $this->church('Talisay Parish', 'Talisay', 10.2447, 123.8494, true);

        $html = $this->actingAs($this->pilgrim())->get(route('plan.visita'))->assertOk()->getContent();

        $this->assertStringNotContainsString('▲', $html);
        $this->assertStringNotContainsString('▼', $html);
        $this->assertStringContainsString('class="vi-grip"', $html);
        // Pointer events: mouse, finger and pen; the handle does not scroll the page.
        $this->assertStringContainsString("listEl.addEventListener('pointerdown'", $html);
        $this->assertStringContainsString('touch-action: none', $html);
        // Still usable from a keyboard.
        $this->assertStringContainsString("e.key !== 'ArrowUp' && e.key !== 'ArrowDown'", $html);
    }
}
