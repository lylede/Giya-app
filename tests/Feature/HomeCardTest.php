<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four "Start Your Journey" cards on the home page.
 *
 * They carry the same wave as the plan hub's cards, and deliberately the same
 * one: two of the four lead to the screens the hub leads to, so a different
 * hover on the same destination would be saying they were different places.
 *
 * Sharing it means sharing the implementation, not copying it. The wave lives
 * under .liquid-wave and both card families use it; a second copy of those
 * rules under another name is the thing this guards against.
 */
class HomeCardTest extends TestCase
{
    use RefreshDatabase;

    private function home(): string
    {
        $html = $this->get('/')->getContent();

        if (env('GIYA_DUMP_HOME')) {
            file_put_contents('/tmp/home.html', preg_replace(
                '#https?://[^"\']*/assets/#',
                'file://'.public_path('assets').'/',
                $html
            ));
        }

        return $html;
    }

    public function test_all_four_journey_cards_carry_the_wave(): void
    {
        $html = $this->home();

        $this->assertSame(4, preg_match_all('/class="card card-hover home-card"/', $html));
        $this->assertSame(4, substr_count($html, 'liquid-wave'));
        $this->assertSame(4, substr_count($html, '<i class="b1"></i>'));
        $this->assertSame(4, substr_count($html, '<i class="b2"></i>'));
    }

    public function test_the_cards_use_classes_rather_than_inline_colour(): void
    {
        $html = $this->home();

        /* They were built from inline styles, which is how the icon tile ended
           up a different colour from the hub's. Nothing about these cards
           should be deciding a colour in the markup. */
        $this->assertSame(4, substr_count($html, 'home-card-icon'));
        $this->assertSame(4, substr_count($html, 'home-card-title'));
        $this->assertStringNotContainsString('background:var(--gold-bg);display:flex', $html);
        $this->assertStringNotContainsString('--card-accent:', $html);
    }

    public function test_the_wave_is_defined_once_for_both_card_families(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        // The shared name exists...
        $this->assertStringContainsString('.liquid-wave {', $css);

        // ...and the name it replaced does not, anywhere, which is what would
        // happen if the plan cards were left on a private copy of the rules.
        $this->assertStringNotContainsString('plan-card-liquid', $css);

        /* How big the wave is on each card is not asserted here. The obvious
           assertion - that --wave-scale appears in the stylesheet - passed
           with every real declaration of it deleted, because the comment
           above them says the name too. Size is a geometry question and the
           browser probe measures it against the card it sits on. */
    }
}
