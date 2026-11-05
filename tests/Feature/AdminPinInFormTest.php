<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** One map for pins: Move pin zooms it onto the church, and a save keeps the map there. */
class AdminPinInFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_one_map_move_pin_and_a_paste_box(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.destinations'))->assertOk()->getContent();

        // One map only: the form has no map of its own; Move pin uses the big one.
        $this->assertStringNotContainsString('id="dmMiniMap"', $html);
        $this->assertStringContainsString('id="dmRepin"', $html);
        $this->assertStringContainsString('id="f-pinpaste"', $html);

        // The page script must still parse once Blade has filled it in.
        preg_match_all('#<script>(.*?)</script>#s', $html, $m);
        $js = tempnam(sys_get_temp_dir(), 'js').'.js';
        file_put_contents($js, implode("\n;\n", $m[1]));
        exec('node --check '.escapeshellarg($js).' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }

    public function test_saving_a_destination_tells_the_map_to_open_on_it(): void
    {
        $cat = ChurchCategory::create(['name' => 'Parish', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin())->from(route('admin.destinations'))
            ->post(route('admin.destinations.store'), [
                'name' => 'Pin Test Chapel', 'category' => 'Parish', 'location' => 'Cebu City',
                'latitude' => 10.2945, 'longitude' => 123.9022, 'status' => 'Published',
            ])
            ->assertSessionHas('focus_church', Church::where('name', 'Pin Test Chapel')->value('id'));
    }
}
