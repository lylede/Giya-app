<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\ChurchImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FeaturedProvinceChurchesTest extends TestCase
{
    use RefreshDatabase;

    private function church(string $name, string $town, bool $featured = false): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create([
            'name' => $name, 'category_id' => $cat->id, 'location' => $town, 'municipality' => $town,
            'latitude' => 10.0, 'longitude' => 123.7, 'is_active' => true, 'is_featured' => $featured,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_migration_features_the_province_churches_by_name_and_town(): void
    {
        $argao   = $this->church('Archdiocesan Shrine of St. Michael Archangel', 'Argao');
        $samboan = $this->church('St. Michael Archangel Parish', 'Samboan');
        $oslob   = $this->church('Our Lady of the Immaculate Conception Parish', 'Oslob');
        $toledo  = $this->church('Our Lady of the Immaculate Conception Parish', 'Toledo City');
        $boljoon = $this->church('The Archdiocesan Shrine of Patrocinio de Maria Santisima', 'Boljoon');

        $migration = require database_path('migrations/2026_11_17_000001_feature_popular_province_churches.php');
        $migration->up();

        $this->assertTrue($argao->fresh()->is_featured);
        $this->assertTrue($oslob->fresh()->is_featured);
        $this->assertTrue($boljoon->fresh()->is_featured);
        $this->assertFalse($samboan->fresh()->is_featured, 'Same saint, other town: not featured.');
        $this->assertFalse($toledo->fresh()->is_featured, 'Same name, other town: not featured.');

        $migration->down();
        $this->assertFalse($argao->fresh()->is_featured);
    }

    public function test_home_shows_up_to_twelve_featured_churches_that_have_photos(): void
    {
        foreach (range(1, 13) as $n) {
            $c = $this->church("Featured Church {$n}", 'Argao', true);
            ChurchImage::create(['church_id' => $c->id, 'image_url' => "https://upload.wikimedia.org/wikipedia/commons/a/a1/Featured_{$n}.jpg", 'is_primary' => true, 'created_at' => now()]);
        }
        $this->church('Featured Without Photo', 'Oslob', true);

        $html = $this->withCookie(\App\Http\Controllers\OnboardingController::COOKIE, '1')->get('/')->assertOk()->getContent();

        $this->assertSame(12, substr_count($html, 'class="proc-bar"'));
        $this->assertStringNotContainsString('Featured Without Photo', $html);
    }
}
