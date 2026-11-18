<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Add Church on Visita Iglesia opens a sheet over the page, not a box far up the page. */
class VisitaAddChurchSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_church_opens_a_sheet_with_search_and_done(): void
    {
        $user = User::create(['name' => 'V', 'email' => 'v@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->actingAs($user)->get(route('plan.visita'))->assertOk()->getContent();

        $this->assertStringContainsString('class="modal vi-sheet" id="addPicker"', $html);
        $this->assertStringContainsString('id="pickerSearch"', $html);
        $this->assertStringContainsString(__('giya.plan.picker_done'), $html);
        $this->assertStringContainsString("GiyaUI.Modal.open('addPicker')", $html);
        $this->assertStringNotContainsString('card card-body mb-3 d-none" id="addPicker"', $html);
        // A real handle to swipe the sheet down with.
        $this->assertStringContainsString('class="vi-sheet-grab"', $html);
        $this->assertStringContainsString("addEventListener('pointerdown', startDrag)", $html);
        // Each church is an on/off switch, like the Map page's add circle.
        $this->assertStringContainsString('role="switch" aria-checked=', $html);
        $this->assertStringContainsString('// Added by mistake: tapping it again takes it back out.', $html);
    }
}
