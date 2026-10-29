<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One back button design (components/back-link) on every page below Home,
 * each pointing to the page it belongs to.
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

    public function test_every_devotee_page_below_home_goes_back_to_its_parent(): void
    {
        $user = $this->user();

        $pages = [
            'map'            => [route('home'), 'Back to Home'],
            'plan.hub'       => [route('home'), 'Back to Home'],
            'chatbot'        => [route('home'), 'Back to Home'],
            'profile'        => [route('home'), 'Back to Home'],
            'upgrade'        => [route('home'), 'Back to Home'],
            'plan.visita'    => [route('plan.hub'), 'Back to Plan Hub'],
            'plan.index'     => [route('plan.hub'), 'Back to Plan Hub'],
        ];

        foreach ($pages as $name => [$href, $label]) {
            $html = $this->actingAs($user)->get(route($name))->assertOk()->getContent();
            $this->assertSame([$href, $label], $this->back($html), "Back on {$name}");
        }
    }

    public function test_home_has_no_back_button(): void
    {
        $this->actingAs($this->user())->get(route('home'))->assertOk()->assertDontSee('giya-back-label', false);
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

    public function test_sign_in_pages_go_back_to_the_start_and_password_pages_to_login(): void
    {
        $this->withCookie(OnboardingController::COOKIE, '1');

        $this->assertSame([route('root'), 'Back to start page'], $this->back($this->get(route('login'))->getContent()));
        $this->assertSame([route('root'), 'Back to start page'], $this->back($this->get(route('register'))->getContent()));
        $this->assertSame([route('login'), 'Back to Login'], $this->back($this->get(route('password.request'))->getContent()));
    }

    public function test_admin_modules_go_back_to_the_dashboard(): void
    {
        $admin = $this->user('admin');

        // The Dashboard itself has none (@unless in layouts/admin). It is not
        // opened here: its charts use PostgreSQL's to_char, which the
        // in-memory SQLite test database does not have.
        $this->assertStringContainsString("@unless (request()->routeIs('admin.dashboard'))",
            file_get_contents(resource_path('views/layouts/admin.blade.php')));

        foreach (['admin.destinations', 'admin.users', 'admin.schedules', 'admin.reports', 'admin.feedback', 'admin.transactions'] as $name) {
            $html = $this->actingAs($admin)->get(route($name))->assertOk()->getContent();
            $this->assertSame([route('admin.dashboard'), 'Back to Dashboard'], $this->back($html), $name);
        }
    }

    public function test_the_old_one_off_back_styles_are_gone_from_the_pages(): void
    {
        foreach (glob(resource_path('views/{*,*/*}.blade.php'), GLOB_BRACE) as $file) {
            $view = file_get_contents($file);
            $this->assertStringNotContainsString('class="back-link"', $view, basename($file));
            $this->assertStringNotContainsString('class="church-back-link"', $view, basename($file));
        }
    }
}
