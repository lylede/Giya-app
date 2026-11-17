<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The serif is for each page's main title and the Giya wordmark only. */
class TitleFontTest extends TestCase
{
    public function test_only_page_titles_and_the_wordmark_use_the_serif(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        $this->assertStringContainsString("--font-title:    'Playfair Display', Georgia, serif;", $css);
        $this->assertStringContainsString('--font-display:  var(--font-body);', $css);
        $this->assertStringContainsString('h1 { font-family: var(--font-title); }', $css);
        $this->assertStringContainsString('h2, h3 { font-family: var(--font-display); }', $css);
        $this->assertStringContainsString('.admin-layout h1 { font-family: var(--font-display); }', $css);

        foreach (['.nav-logo-name', '.footer-logo-name', '.hero-title', '.about-title', '.legal-title'] as $selector) {
            $this->assertMatchesRegularExpression('/'.preg_quote($selector, '/').' \{[^}]*var\(--font-title\)/', $css, $selector);
        }
        // Church names, cards and popups are not titles.
        foreach (['.mx-card-name', '.giya-popup strong', '.hub-active-name', '.card-title'] as $selector) {
            $this->assertDoesNotMatchRegularExpression('/'.preg_quote($selector, '/').' \{[^}]*var\(--font-title\)/', $css, $selector);
        }
    }
}
