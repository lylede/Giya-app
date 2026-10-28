<?php

namespace Tests\Feature;

use App\Support\Text;
use Tests\TestCase;

/**
 * Mobile layout rules for the chat and church pages. These are checked here
 * so a later style change cannot quietly bring the old problems back.
 */
class MobileUiTest extends TestCase
{
    private function css(string $file): string
    {
        return file_get_contents(public_path('assets/css/'.$file));
    }

    public function test_chat_bubbles_do_not_double_their_line_breaks(): void
    {
        // Replies already carry <br> tags; pre-line would add a second gap.
        $this->assertDoesNotMatchRegularExpression(
            '/\.chat-bubble\s*\{[^}]*white-space:\s*pre-line/', $this->css('giya.css'));
    }

    public function test_phone_text_boxes_are_16px_so_iphone_does_not_zoom(): void
    {
        $this->assertStringContainsString('.chat-composer input { font-size: 16px; }', $this->css('giya.css'));
    }

    public function test_church_text_is_left_aligned_not_justified(): void
    {
        $css = $this->css('church-show.css');

        $this->assertStringNotContainsString('text-align:justify', $css);
        $this->assertMatchesRegularExpression('/\.church-section-title\s*\{[^}]*text-align:left/', $css);
    }

    public function test_church_actions_are_an_even_two_column_grid(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.church-hero-actions\s*\{[^}]*grid-template-columns:repeat\(2/', $this->css('church-show.css'));
    }

    public function test_chat_replies_render_bold_italic_and_breaks_safely(): void
    {
        $html = Text::chatHtml("**Simala** <script>x</script>\n_From the records._\nsnake_case stays");

        $this->assertStringContainsString('<strong>Simala</strong>', $html);
        $this->assertStringContainsString('<em>From the records.</em>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('snake_case stays', $html);
        $this->assertStringContainsString('<br>', $html);
    }
}
