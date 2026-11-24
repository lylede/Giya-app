<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guided tour after login: a dimmed screen with one button lit at a
 * time. Each page's tour shows once per account; Skip ends them all; the
 * profile page and the menu can show it again. Admins never see it.
 */
class GuidedTourTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'devotee'): User
    {
        return User::create([
            'name' => 'Lyle Dumo', 'email' => $role.'@example.com', 'password_hash' => bcrypt('secret'),
            'role' => $role, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_new_account_sees_the_home_tour_with_its_name(): void
    {
        $this->actingAs($this->person())->get(route('home'))->assertOk()
            ->assertSee('id="giyaTour"', false)
            ->assertSee('giya-tour.js', false)
            ->assertSee('Welcome to GIYA, Lyle!');
    }

    public function test_finishing_a_page_hides_only_that_page(): void
    {
        $user = $this->person();

        $this->actingAs($user)->post(route('tour.done'), ['tour' => 'home'])->assertNoContent();

        $this->assertSame(['home'], $user->fresh()->tours_done);
        $this->actingAs($user)->get(route('home'))->assertDontSee('id="giyaTour"', false);
        $this->actingAs($user)->get(route('profile'))->assertSee('id="giyaTour"', false);
    }

    public function test_skip_ends_every_page_and_unknown_names_change_nothing(): void
    {
        $user = $this->person();

        $this->actingAs($user)->post(route('tour.done'), ['tour' => 'nonsense'])->assertNoContent();
        $this->assertEmpty($user->fresh()->tours_done ?? []);

        $this->actingAs($user)->post(route('tour.done'), ['tour' => 'all'])->assertNoContent();
        $this->assertEqualsCanonicalizing(['home', 'map', 'trip', 'profile'], $user->fresh()->tours_done);
        $this->actingAs($user)->get(route('profile'))->assertDontSee('id="giyaTour"', false);
    }

    public function test_show_the_tour_again_clears_it_and_goes_home(): void
    {
        $user = $this->person();
        $user->tours_done = ['home', 'map', 'trip', 'profile'];
        $user->save();

        $this->actingAs($user)->get(route('profile'))
            ->assertSee(route('tour.replay'), false)
            ->assertSee(__('giya.tour.replay'));

        $this->actingAs($user)->post(route('tour.replay'))->assertRedirect(route('home'));
        $this->assertSame([], $user->fresh()->tours_done);
        $this->actingAs($user)->get(route('home'))->assertSee('id="giyaTour"', false);
    }

    public function test_admins_and_guests_are_not_toured(): void
    {
        $this->actingAs($this->person('admin'))->get(route('home'))->assertDontSee('id="giyaTour"', false);
    }

    public function test_a_guest_cannot_mark_a_tour(): void
    {
        $this->post(route('tour.done'), ['tour' => 'all'])->assertRedirect(route('login'));
    }

    public function test_the_spotlight_dims_to_55_percent_and_a_lit_button_can_be_tapped(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));
        $js = file_get_contents(public_path('assets/js/giya-tour.js'));

        $this->assertStringContainsString('0 0 0 9999px rgba(20, 14, 10, .55)', $css);
        $this->assertStringContainsString('.gt-root.is-open .gt-ring { pointer-events: none; }', $css);
        $this->assertStringContainsString("document.addEventListener('click', onTap, true)", $js);
        $this->assertStringContainsString("send('all')", $js);
    }
}
