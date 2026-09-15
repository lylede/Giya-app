<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The plan hub's four cards.
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

    public function test_there_are_four_cards(): void
    {
        /* Anchored to the end of the attribute. A bare "plan-card" prefix
           also matches plan-card-icon, plan-card-title and every other part
           of a card, which counted 76 of them. */
        $this->assertSame(4, preg_match_all('/class="plan-card(?: featured)?"/', $this->hub()));
    }

    public function test_every_card_carries_both_halves_of_the_wave(): void
    {
        $html = $this->hub();

        // One shape is a circle growing, which is what came back rejected.
        $this->assertSame(4, substr_count($html, 'liquid-wave'));
        $this->assertSame(4, substr_count($html, '<i class="b1"></i>'));
        $this->assertSame(4, substr_count($html, '<i class="b2"></i>'));
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
        $this->assertSame(4, substr_count($html, 'plan-card-badge-row'));

        /* The old markup cleared the number with a hard margin. If that is
           back, the badge is back in the corner with it. */
        $this->assertStringNotContainsString('margin-right:42px', $html);
    }
}
