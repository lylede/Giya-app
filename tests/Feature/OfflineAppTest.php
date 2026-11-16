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

    public function test_one_failed_request_is_not_treated_as_no_signal(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Pages are fetched with retries, and nothing else calls fetch() for a page.
        $this->assertStringContainsString('await fetchPatiently(request, url)', $sw);
        $this->assertStringContainsString('for (const ms of [500, 1500])', $sw);
        // No waiting when the phone itself reports no connection, and
        // one-time links (Google's return, signed email links) go out once.
        $this->assertStringContainsString('self.navigator.onLine === false || oneTime', $sw);
        $this->assertStringContainsString("url.pathname.startsWith('/auth/')", $sw);
        $this->assertStringContainsString("url.searchParams.has('signature')", $sw);
    }

    public function test_the_offline_page_comes_back_by_itself_and_says_which_problem_it_is(): void
    {
        $this->get('/offline')->assertOk()
            ->assertSee('GIYA is not answering')
            ->assertSee("fetch('/up?t='", false)
            ->assertSee('tries >= 4', false);

        // The health check it asks must answer, also while maintenance is on.
        $this->get('/up')->assertOk();
        \App\Models\SiteSetting::write(['maintenance' => 'on']);
        $this->get('/up')->assertOk();

        $this->assertStringContainsString("sessionStorage.removeItem('giya-offline-retries')", file_get_contents(public_path('assets/js/giya-offline.js')));
    }
}
