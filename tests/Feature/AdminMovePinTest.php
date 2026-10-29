<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Moving a destination's pin on the admin map must only change its
 * coordinates. It used to open an empty "Add Destination" form instead.
 * (Checked in a real browser too; this guards the wiring.)
 */
class AdminMovePinTest extends TestCase
{
    public function test_the_picker_says_whether_the_pin_was_dragged_or_clicked(): void
    {
        $js = file_get_contents(public_path('assets/js/giya-leaflet.js'));

        $this->assertStringContainsString("report(e.target.getLatLng(), 'drag')", $js);
        $this->assertStringContainsString("cfg.onPin(lat, lng, how || 'click')", $js);
    }

    public function test_moving_the_pin_keeps_the_form_and_only_a_fresh_click_starts_a_new_one(): void
    {
        $view = file_get_contents(resource_path('views/admin/destinations.blade.php'));

        $this->assertStringContainsString("const moving = picking || how === 'drag';", $view);
        $this->assertMatchesRegularExpression("/if \\(moving\\) \\{\\s*GiyaUI\\.Modal\\.open\\('destModal'\\);/", $view);
    }
}
