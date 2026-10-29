<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The back button (components/back-link) is on the church page only;
 * everywhere else the menu is the way around. The one other use is on the
 * map while picking churches for a Visita Iglesia route, where it carries
 * the chosen churches back to that planner.
 */
class BackButtonTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'devotee'): User
    {
        return User::create([
            'name' => 'Test', 'email' => $role.'@example.com', 'password_hash' => bcrypt('secret'),
            'role' => $role, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function church(): Church
    {
        $cat = ChurchCategory::create(['name' => 'Basilica', 'created_at' => now(), 'updated_at' => now()]);

        return Church::create([
            'name' => 'Basilica del Santo Nino', 'category_id' => $cat->id, 'location' => 'Cebu City',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** The back button on a page: [href, label]. */
    private function back(string $html): array
    {
        $this->assertSame(1, preg_match('#<a href="([^"]+)"\s+class="giya-back[^"]*"[^>]*>.*?giya-back-label">([^<]+)<#s', $html, $m),
            'The page has the shared back button.');

        return [$m[1], html_entity_decode(trim($m[2]))];
    }

    public function test_other_devotee_pages_have_no_back_button(): void
    {
        $user = $this->user();

        foreach (['home', 'map', 'plan.hub', 'chatbot', 'profile', 'upgrade', 'plan.visita', 'plan.index'] as $name) {
            $this->actingAs($user)->get(route($name))->assertOk()->assertDontSee('giya-back-label', false);
        }
    }

    public function test_sign_in_and_admin_pages_have_no_back_button(): void
    {
        $this->withCookie(OnboardingController::COOKIE, '1');
        foreach (['login', 'register', 'password.request'] as $name) {
            $this->get(route($name))->assertOk()->assertDontSee('giya-back-label', false);
        }

        $admin = $this->user('admin');
        foreach (['admin.destinations', 'admin.users', 'admin.schedules'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk()->assertDontSee('Back to Dashboard');
        }
    }

    public function test_a_church_goes_back_where_it_was_opened_from(): void
    {
        $church = $this->church();
        $user = $this->user();

        $html = $this->actingAs($user)->withHeader('referer', route('home'))->get(route('churches.show', $church))->getContent();
        $this->assertSame([route('home'), 'Back to Home'], $this->back($html));

        $html = $this->actingAs($user)->withHeader('referer', route('plan.visita'))->get(route('churches.show', $church))->getContent();
        $this->assertSame([route('plan.visita'), 'Back to Visita Iglesia'], $this->back($html));
    }

    public function test_a_church_never_goes_back_to_a_data_address_or_another_site(): void
    {
        $church = $this->church();
        $user = $this->user();

        foreach ([route('notifications.index'), 'https://evil.example/home'] as $ref) {
            $html = $this->actingAs($user)->withHeader('referer', $ref)->get(route('churches.show', $church))->getContent();
            $this->assertSame([route('map'), 'Back to Map'], $this->back($html), $ref);
        }
    }
}
