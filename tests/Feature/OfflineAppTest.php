<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_can_be_installed(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame('GIYA', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }

        $this->get('/login')->assertSee('rel="manifest"', false)->assertSee('giya-offline.js', false);
    }

    public function test_the_offline_page_holds_no_personal_data(): void
    {
        $this->get('/offline')->assertOk()->assertSee('You are offline')->assertDontSee('csrf-token');
    }

    public function test_the_worker_never_keeps_admin_chat_or_form_pages(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("if (request.method !== 'GET') return;", $sw);
        $this->assertStringContainsString("url.pathname.startsWith('/admin')", $sw);
        $this->assertStringContainsString("url.pathname.startsWith('/chatbot')", $sw);
        $this->assertStringContainsString("'giya:clear-pages'", $sw);
        $this->assertStringContainsString('MAX_TILES = 600', $sw);
    }
}
