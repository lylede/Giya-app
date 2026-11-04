<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The Delete button in the destination modal must actually appear when editing. */
class DestinationModalDeleteTest extends TestCase
{
    public function test_the_delete_button_is_shown_by_its_hidden_attribute(): void
    {
        $view = file_get_contents(resource_path('views/admin/destinations.blade.php'));

        $this->assertStringContainsString('id="btnDelete" hidden', $view);
        $this->assertStringContainsString('del.hidden = !churchId;', $view);
        // style.display cannot beat "[hidden] { display: none !important }".
        $this->assertStringNotContainsString("del.style.display", $view);
    }
}
