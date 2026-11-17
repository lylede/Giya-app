<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The footer carries no developer credits; the team is on the About page. */
class FooterCreditsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Test', 'email' => 't@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_home_footer_has_no_developer_list(): void
    {
        $html = $this->actingAs($this->user())->get(route('home'))->assertOk()->getContent();
        $footer = substr($html, (int) strpos($html, '<footer'));

        $this->assertStringNotContainsString('>Developers<', $footer);
        $this->assertStringNotContainsString('footer-credits', $footer);
    }

    public function test_visitors_do_not_see_it_on_the_start_page_either(): void
    {
        $html = $this->withCookie(OnboardingController::COOKIE, '1')->get(route('root'))->assertOk()->getContent();

        $this->assertStringNotContainsString('footer-credits', $html);
    }
}
