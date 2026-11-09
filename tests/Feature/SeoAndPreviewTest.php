<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_have_a_description_and_a_share_preview(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta property="og:title" content="GIYA - ', false)
            ->assertSee(asset('images/brand/giya-share.jpg'), false)
            ->assertSee('summary_large_image', false);

        $this->get('/privacy')->assertSee('<meta property="og:image"', false);
    }

    public function test_the_share_image_and_app_icons_exist(): void
    {
        foreach (['giya-share.jpg', 'icon-192.png', 'icon-512.png', 'apple-touch-icon.png'] as $file) {
            $this->assertFileExists(public_path('images/brand/'.$file));
        }
        [$w, $h] = getimagesize(public_path('images/brand/giya-share.jpg'));
        $this->assertSame([1200, 630], [$w, $h]);
    }

    public function test_the_sitemap_lists_the_public_pages_only(): void
    {
        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<loc>'.route('map').'</loc>', false)
            ->assertSee('<loc>'.route('privacy').'</loc>', false)
            ->assertDontSee('/admin');
    }

    public function test_robots_keeps_private_areas_out_and_points_to_the_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: '.route('sitemap'));
    }
}
