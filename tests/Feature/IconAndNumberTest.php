<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Icons that exist, and numbers in a face that can be read.
 *
 * ICONS. A mistyped icon name does not fail: the glyph is a CSS mask, so an
 * unknown name renders as an empty square and the page looks merely a bit
 * wrong. The admin sidebar is nine of them in a column, and one blank in that
 * column is the kind of thing that survives to a defence.
 *
 * NUMBERS. They were set in Playfair, which is a display serif. Its figures
 * are proportional - a 1 is narrower than a 0 - so a column of counts never
 * lines up and a number that ticks upward shifts everything beside it. They
 * are in Lato now, which is already loaded and is what the rest of the app
 * reads in, with tabular lining figures.
 */
class IconAndNumberTest extends TestCase
{
    /** @return array<string> every bi- name any template or script asks for */
    private function iconsUsed(): array
    {
        $found = [];

        foreach (['resources/views', 'public/assets/js'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));

            foreach ($it as $file) {
                if (! $file->isFile() || ! preg_match('/\.(blade\.php|js)$/', $file->getFilename())) {
                    continue;
                }

                preg_match_all('/bi bi-([a-z0-9-]+)/', file_get_contents($file->getPathname()), $m);
                $found = array_merge($found, $m[1]);
            }
        }

        // Names built at runtime from data cannot be checked from here.
        return array_values(array_unique(array_filter($found, fn ($n) => ! str_contains($n, '{{'))));
    }

    private function iconsAvailable(): array
    {
        $css = '';

        foreach (glob(public_path('assets/css/*icons*.css')) as $file) {
            $css .= file_get_contents($file);
        }

        preg_match_all('/\.bi-([a-z0-9-]+)::before/', $css, $m);

        return array_values(array_unique($m[1]));
    }

    public function test_every_icon_the_app_asks_for_exists(): void
    {
        $missing = array_diff($this->iconsUsed(), $this->iconsAvailable());

        $this->assertSame([], array_values($missing),
            'these render as empty squares: '.implode(', ', $missing));
    }

    public function test_the_admin_sidebar_uses_giyas_own_icons(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        foreach ([
            'Dashboard'    => 'giya-magellan',
            'Users'        => 'giya-pilgrim',
            'Destinations' => 'giya-spires',
            'Schedules'    => 'giya-candle',
            'Feedback'     => 'giya-star',
            'Transactions' => 'giya-payment',
            'Reports'      => 'giya-tally',
        ] as $label => $icon) {
            /* The label is wrapped in a span now, so the rail can hide the
               word without hiding the icon. Matching on the raw text between
               the two would fail on markup that is perfectly correct. */
            $this->assertMatchesRegularExpression(
                '/bi-'.preg_quote($icon, '/').'"><\/i>\s*<span class="admin-nav-label">'
                    .preg_quote($label, '/').'<\/span>/',
                $sidebar,
                "{$label} is not using {$icon}"
            );
        }

        /* Signing out and leaving for the public site are both "this takes
           you away from here", and the arrow says that better than any
           devotional glyph would. They are deliberately not swapped. */
        $this->assertStringContainsString('bi-box-arrow-up-right', $sidebar);
        $this->assertStringContainsString('bi-box-arrow-right', $sidebar);
    }

    public function test_the_two_new_glyphs_were_drawn_not_borrowed(): void
    {
        $css = file_get_contents(public_path('assets/css/giya-icons.css'));

        foreach (['giya-payment', 'giya-tally'] as $name) {
            $this->assertStringContainsString(".bi-{$name}::before", $css);
            // A mask, like every other icon in the set, not a font glyph.
            $this->assertMatchesRegularExpression(
                '/\.bi-'.preg_quote($name, '/').'::before\{[^}]*mask-image:url\("data:image\/svg/',
                $css
            );
        }
    }

    public function test_numbers_are_not_set_in_the_display_serif(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        $this->assertStringContainsString('--font-numeric:', $css);

        /* The ones that carried the serif. A statistic in Playfair is thin
           at a glance and never lines up with the one below it.

           .um-stat-value was here too. It is gone rather than fixed: user
           management now uses the same <x-stat-tile> as every other module,
           so the rule it named has no markup left to style. */
        foreach (['.hero-stat-value', '.profile-stat-val', '.stat-tile-value'] as $sel) {
            preg_match('/'.preg_quote($sel, '/').'\s*\{([^}]*)\}/s', $css, $m);

            $this->assertNotEmpty($m, "{$sel} is not in the stylesheet");
            $this->assertStringNotContainsString('--font-display', $m[1],
                "{$sel} is still set in the display serif");
        }

        /* And they line up, which is the point of changing it.

           Anchored to the .giya-num block rather than searching the whole
           file: a bare "is tabular-nums in here somewhere" passed with the
           declaration deleted, because two unrelated rules elsewhere already
           had it. */
        preg_match('/\.giya-num,(.*?)\}/s', $css, $block);

        $this->assertNotEmpty($block, 'the numeric block is gone');
        $this->assertStringContainsString('tabular-nums', $block[1],
            'the numeric block no longer asks for tabular figures');
        $this->assertStringContainsString('var(--font-numeric)', $block[1]);
    }
}
