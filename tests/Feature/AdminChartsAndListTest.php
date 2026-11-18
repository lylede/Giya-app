<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use App\Models\VisitHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Charts with page-size text, the palette dropdown, and the Destinations list that pages in place. */
class AdminChartsAndListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_bar_chart_keeps_full_names_and_says_what_is_counted(): void
    {
        $html = Blade::render('<x-bar-chart :labels="$l" :data="$d" unit="follows" />', [
            'l' => ['Archdiocesan Shrine of Our Lady of Guadalupe de Cebu', 'Simala'], 'd' => [4, 1],
        ]);

        $this->assertStringContainsString('Archdiocesan Shrine of Our Lady of Guadalupe de Cebu</span>', $html);
        $this->assertStringContainsString(': 4 follows', $html);
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('width: 25%', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_line_chart_text_is_not_inside_the_stretched_svg(): void
    {
        $html = Blade::render('<x-line-chart :labels="$l" :data="$d" />', ['l' => ['Jun', 'Jul', 'Aug'], 'd' => [0, 3, 8]]);

        $this->assertStringContainsString('preserveAspectRatio="none"', $html);
        $this->assertStringContainsString('<span style="left: 50%">Jul</span>', $html);
        $this->assertStringNotContainsString('<text', $html);
    }

    public function test_line_chart_names_the_newest_point_and_thins_the_rest(): void
    {
        $labels = array_map(fn ($i) => 'D'.$i, range(1, 30));
        $html = Blade::render('<x-line-chart :labels="$l" :data="$d" />', ['l' => $labels, 'd' => array_fill(0, 30, 1)]);

        $this->assertStringContainsString('>D30</span>', $html);
        $this->assertStringNotContainsString('>D29</span>', $html);
        $this->assertLessThanOrEqual(8, substr_count($html, '<span style="left:'));
    }

    public function test_dashboard_and_insights_render_with_visits(): void
    {
        $cat = ChurchCategory::create(['name' => 'Church', 'created_at' => now(), 'updated_at' => now()]);
        $church = Church::create(['name' => 'Chart Parish', 'category_id' => $cat->id, 'location' => 'Cebu', 'latitude' => 10.3,
            'longitude' => 123.9, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        VisitHistory::create(['user_id' => $this->admin->id, 'church_id' => $church->id, 'visited_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('class="lchart"', false)->assertSee('class="hbar"', false)->assertSee('Chart Parish');
        $this->actingAs($this->admin)->get(route('admin.insights'))->assertOk()
            ->assertDontSee('&amp;quot;', false);
    }

    public function test_admin_pages_load_the_palette_dropdown(): void
    {
        $this->actingAs($this->admin)->get(route('admin.schedules', ['view' => 'church']))->assertOk()
            ->assertSee('giya-combo.js', false)->assertSee('list="ttChurches"', false);
    }

    public function test_destinations_list_pages_in_place_and_the_map_groups_pins(): void
    {
        $this->actingAs($this->admin)->get(route('admin.destinations'))->assertOk()
            ->assertSee('id="dmList"', false)
            ->assertSee('data-soft-list-form="dmList"', false)
            ->assertSee('giya-soft-list.js', false)
            ->assertSee('leaflet.markercluster.js', false)
            ->assertSee('cluster: true', false)
            ->assertSee('loaderMinMs: 0', false);
    }
}
