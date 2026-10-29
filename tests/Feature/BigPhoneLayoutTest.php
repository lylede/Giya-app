<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phone layout rules (checked in a browser at 360-440px wide). These guard
 * the wiring so a later change cannot quietly bring the old problems back.
 */
class BigPhoneLayoutTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(public_path('assets/css/giya.css'));
    }

    public function test_admin_menu_is_a_drawer_on_phones(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('id="adminMenu"', $layout);
        $this->assertStringContainsString('id="adminScrim"', $layout);
        $this->assertStringContainsString('.admin-layout.is-drawer-open .admin-sidebar { transform: none; }', $this->css());
        $this->assertStringContainsString("getElementById('adminMenu')", file_get_contents(public_path('assets/js/giya-admin.js')));
    }

    public function test_the_floating_guide_never_covers_the_itinerary_button(): void
    {
        // The phone position applies everywhere except an itinerary, where
        // the guide sits above the bottom sheet instead.
        $this->assertStringContainsString('body:not(:has(.active-layout)) .giya-fab', $this->css());
        $this->assertStringNotContainsString("  .giya-fab { right: 12px !important; bottom:", $this->css());
    }

    public function test_visita_stops_put_their_buttons_on_their_own_line(): void
    {
        $view = file_get_contents(resource_path('views/plan/visita.blade.php'));

        foreach (['vi-info', 'vi-actions', 'vi-move', 'vi-mark', 'vi-drop'] as $class) {
            $this->assertStringContainsString('class="'.$class.'"', $view);
        }
        $this->assertStringContainsString('.vi-row .vi-actions { flex: 1 1 100%;', $this->css());
    }

    public function test_long_event_types_fit_their_tile(): void
    {
        $this->assertStringContainsString('.event-kind { font-size: .5rem; letter-spacing: -.01em; overflow-wrap: anywhere; }', $this->css());
    }

    public function test_chat_keyboard_check_does_not_trust_inner_height(): void
    {
        $view = file_get_contents(resource_path('views/chatbot.blade.php'));

        // iPhone Safari's innerHeight is far taller than the visible screen
        // with no keyboard, which left the chat stuck in "typing" layout.
        $this->assertStringNotContainsString('window.innerHeight - vv.height', $view);
        $this->assertStringContainsString('baseline - vv.height > 150', $view);
    }

    public function test_chat_box_is_only_focused_automatically_on_a_computer(): void
    {
        $view = file_get_contents(resource_path('views/chatbot.blade.php'));
        $panel = file_get_contents(public_path('assets/js/giya-ai-panel.js'));

        foreach ([$view, $panel] as $code) {
            $this->assertStringContainsString("matchMedia('(hover: hover) and (pointer: fine)')", $code);
            $this->assertDoesNotMatchRegularExpression('/^\s*input\.focus\(\);/m', $code);
        }
    }

    public function test_the_chat_guide_sits_above_the_chat_with_a_message_box(): void
    {
        $view = file_get_contents(resource_path('views/chatbot.blade.php'));
        $css = file_get_contents(public_path('assets/css/giya.css'));

        // The robot is above the chat card, its message above its head.
        $this->assertLessThan(strpos($view, 'class="card chat-shell"'), strpos($view, 'id="guide"'));
        $this->assertLessThan(strpos($view, 'class="guide-face"'), strpos($view, 'id="guideBubble"'));
        // A message box, not an oval.
        $this->assertStringContainsString(".chat-page .guide-bubble {\n  border-radius: 6px;", $css);
        // Typing hides the robot but keeps the FAQ chips.
        $this->assertStringContainsString('.chat-page.is-typing #guide { display: none; }', $view);
        $this->assertStringNotContainsString('.chat-page.is-typing .chat-starters { display: none; }', $view);
        // Replies keep their small robot, feet included.
        $this->assertStringNotContainsString('.chat-row .chat-bot-icon { display: none; }', $css);
        $this->assertStringContainsString('.chat-bot-icon::before,', $css);
    }

    public function test_the_chat_opens_on_the_guide_and_shows_the_conversation_on_tap(): void
    {
        $view = file_get_contents(resource_path('views/chatbot.blade.php'));
        $css = file_get_contents(public_path('assets/css/giya.css'));

        // Arrival: guide, chips and text box only.
        $this->assertStringContainsString('class="page-wrap chat-page is-intro"', $view);
        $this->assertStringContainsString(".chat-page.is-intro .chat-bar,\n.chat-page.is-intro .chat-log { display: none !important; }", $css);
        // Tapping the box or a chip opens the conversation.
        $this->assertStringContainsString("input.addEventListener('pointerdown', openChat);", $view);
        $this->assertStringContainsString('if (chip) { openChat(); ask(chip.dataset.ask); }', $view);
        $this->assertStringContainsString('.chat-page.is-chatting #guide { display: none !important; }', $css);
        // ...with the small robot beside "Giya AI Assistant".
        $this->assertStringContainsString('id="guideMini"', $view);
    }

    public function test_home_stats_stay_three_in_one_row_on_phones(): void
    {
        $css = $this->css();

        $this->assertStringNotContainsString('.hero-stats { grid-template-columns: repeat(2, 1fr); }', $css);
        $this->assertMatchesRegularExpression('/\.hero-stats \{\s*display: grid;\s*grid-template-columns: repeat\(3, 1fr\);/', $css);
    }
}
