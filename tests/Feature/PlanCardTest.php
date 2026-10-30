<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The plan hub's two cards (Custom and Visita Iglesia). Saved trips are one
 * list below them, and a trip being walked is a banner above them.
 *
 * What the card looks like is a browser's question and is measured there -
 * whether the wave covers the card, what colour is actually painted under the
 * title, what the contrast is once it has. What Blade owns is narrower: four
 * cards, each with both halves of the wave, none of them carrying a colour of
 * its own, and the badge out of the corner the wave and the number occupy.
 *
 * Each of those was a real defect. The cards had four different browns until
 * the accent moved into the stylesheet; the wave was one shape and read as a
 * circle growing; and the badge sat opposite the icon, under the wave, where
 * "Traditional Route" rendered as an unreadable "Trac".
 *
 * Setting GIYA_DUMP_HUB=1 also writes the rendered page to /tmp/hub.html for
 * the browser probe, with asset URLs rewritten to the working tree - APP_URL
 * makes them absolute, and a file:// page cannot follow those, which once had
 * the probe reporting the effect broken when the page simply had no CSS.
 */
class PlanCardTest extends TestCase
{
    use RefreshDatabase;

    private function hub(): string
    {
        $user = User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->actingAs($user)->get('/plan')->getContent();

        if (env('GIYA_DUMP_HUB')) {
            file_put_contents('/tmp/hub.html', preg_replace(
                '#https?://[^"\']*/assets/#',
                'file://'.public_path('assets').'/',
                $html
            ));
        }

        return $html;
    }

    public function test_there_are_two_cards(): void
    {
        /* Anchored to the end of the attribute. A bare "plan-card" prefix
           also matches plan-card-icon, plan-card-title and every other part
           of a card, which counted 76 of them. */
        $this->assertSame(2, preg_match_all('/class="plan-card(?: featured)?"/', $this->hub()));
    }

    public function test_every_card_carries_both_halves_of_the_wave(): void
    {
        $html = $this->hub();

        // One shape is a circle growing, which is what came back rejected.
        $this->assertSame(2, substr_count($html, 'liquid-wave'));
        $this->assertSame(2, substr_count($html, '<i class="b1"></i>'));
        $this->assertSame(2, substr_count($html, '<i class="b2"></i>'));
    }

    public function test_no_card_carries_a_colour_of_its_own(): void
    {
        $html = $this->hub();

        /* The cards used to be handed an 'accent' and an 'ink' per card. The
           accent is one value in the stylesheet now; a style attribute
           setting --card-accent anywhere on this page means a card has gone
           back to choosing for itself. */
        $this->assertStringNotContainsString('--card-accent:', $html);
        $this->assertStringNotContainsString('--card-ink:', $html);
    }

    public function test_the_badge_is_not_in_the_waves_corner(): void
    {
        $html = $this->hub();

        // Its own row under the icon, not the right-hand end of the icon row.
        $this->assertSame(2, substr_count($html, 'plan-card-badge-row'));

        /* The old markup cleared the number with a hard margin. If that is
           back, the badge is back in the corner with it. */
        $this->assertStringNotContainsString('margin-right:42px', $html);
    }

    /** "In progress" means a church was visited - opening a trip does not start it - and only then is there a banner. */
    public function test_the_banner_shows_only_for_a_trip_being_walked(): void
    {
        $user = \App\Models\User::create([
            'name' => 'Lyle', 'email' => 'banner@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cat  = \App\Models\ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);
        $type = \App\Models\ItineraryType::firstOrCreate(['name' => 'Custom'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $trip = \App\Models\Itinerary::create(['user_id' => $user->id, 'itinerary_type_id' => $type->id, 'name' => 'Liloan Pilgrimage', 'status' => 'Upcoming', 'created_at' => now(), 'updated_at' => now()]);
        $stops = collect(['San Fernando Rey Parish', 'San Sebastian Martyr Church'])->map(function ($name, $i) use ($cat, $trip) {
            $c = \App\Models\Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => 'Cebu', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            return \App\Models\ItineraryStop::create(['itinerary_id' => $trip->id, 'church_id' => $c->id, 'stop_order' => $i + 1, 'is_visited' => false]);
        });

        // Opening it only shows it.
        $this->actingAs($user)->get(route('plan.show', $trip))->assertOk();
        $this->assertSame('Upcoming', $trip->fresh()->status);
        $this->assertStringNotContainsString('class="hub-active"', $this->actingAs($user)->get(route('plan.hub'))->getContent());

        // The first church visited starts it, and the banner says what is next.
        $this->actingAs($user)->postJson(route('plan.stop.visited'), ['stop_id' => $stops[0]->id])->assertOk();
        $this->assertSame('Active', $trip->fresh()->status);
        $html = $this->actingAs($user)->get(route('plan.hub'))->getContent();
        $this->assertStringContainsString('class="hub-active"', $html);
        $this->assertStringContainsString('San Sebastian Martyr Church', $html);

        // Undoing the only visit puts it back to Upcoming.
        $this->actingAs($user)->postJson(route('plan.stop.visited'), ['stop_id' => $stops[0]->id, 'undo' => true])->assertOk();
        $this->assertSame('Upcoming', $trip->fresh()->status);
    }
}
