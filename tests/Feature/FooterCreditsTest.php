<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The developer credits in the Home page footer. */
class FooterCreditsTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM = [
        'Lyle Adrien Dedumo'   => 'Software Engineer',
        'Clint Eroll Capondag' => 'UI/UX Designer',
        'Josh Efraim Padernal' => 'Project Manager',
        'Ian Francis Loyola'   => 'Database Designer',
    ];

    private function user(): User
    {
        return User::create([
            'name' => 'Test', 'email' => 't@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_home_footer_credits_the_team_with_names_under_the_heading(): void
    {
        $html = $this->actingAs($this->user())->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('>Developers<', $html);
        $after = substr($html, strpos($html, '>Developers<'));
        foreach (self::TEAM as $name => $role) {
            $this->assertStringContainsString($name, $after);
            $this->assertStringContainsString($role, $after);
        }
    }

    public function test_visitors_see_it_on_the_start_page_too(): void
    {
        $this->withCookie(OnboardingController::COOKIE, '1')
            ->get(route('root'))->assertOk()->assertSee('Lyle Adrien Dedumo');
    }

    public function test_other_pages_keep_the_plain_footer(): void
    {
        $this->actingAs($this->user())->get(route('plan.hub'))->assertOk()->assertDontSee('Lyle Adrien Dedumo');
    }
}
