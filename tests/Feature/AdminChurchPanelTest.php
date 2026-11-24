<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The church panel on Admin > Destinations: the photo itself is the upload
 * (as on the profile), so there is no separate Photos tab, and the actions
 * sit in one row.
 */
class AdminChurchPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_photo_is_the_upload_and_the_buttons_fit_one_row(): void
    {
        $admin = User::create([
            'name' => 'Giya Administrator', 'email' => 'admin@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->actingAs($admin)->get(route('admin.destinations'))->assertOk()->getContent();

        $this->assertStringContainsString('id="dmPhotoForm"', $html);
        $this->assertStringContainsString('<label class="dm-side-photo" id="dmSidePhoto" for="dmPhotoFile"', $html);
        $this->assertStringContainsString('name="photo" id="dmPhotoFile"', $html);
        $this->assertStringNotContainsString('data-tab="photos"', $html);
        // "No photo" opens the picker instead of a tab.
        $this->assertStringContainsString("document.getElementById('dmPhotoFile').click()", $html);
        // View page is an icon beside Edit and Draft.
        $this->assertStringContainsString('dm-icon-btn', $html);
    }
}
