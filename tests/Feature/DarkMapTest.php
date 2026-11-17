<?php

namespace Tests\Feature;

use Tests\TestCase;

/** In dark mode the map itself is dark, and it is light again in light mode. */
class DarkMapTest extends TestCase
{
    public function test_dark_mode_turns_the_same_tiles_dark(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        // Only under the dark theme, so light mode keeps the normal map.
        $this->assertStringContainsString("html[data-theme=\"dark\"] .leaflet-tile {\n  filter: invert(1) hue-rotate(180deg)", $css);
        $this->assertStringNotContainsString("\n.leaflet-tile { filter: invert", $css);
    }

    public function test_church_dots_stay_light_on_the_dark_map(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        $this->assertStringContainsString('html[data-theme="dark"] .giya-dot { background: #F8E3C6;', $css);
    }
}
