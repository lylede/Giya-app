<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guests may browse the map. A church's own page needs an account.
 *
 * The line is drawn where the value is: the map shows where churches are,
 * which is worth being open to anyone who finds GIYA. The church page holds
 * the mass schedules, the reviews and the visit history, and that is what an
 * account is for.
 */
class GuestMapAccessTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ChurchCategory::create([
            'name' => 'Basilica', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Plain ASCII on purpose: the marker list is JSON-encoded into the
        // page, so an "n with a tilde" arrives escaped and an assertSee for it
        // would fail for reasons that have nothing to do with access.
        $this->church = Church::create([
            'name' => 'Redemptorist Church', 'category_id' => $category->id,
            'location' => 'Cebu City', 'latitude' => 10.2945, 'longitude' => 123.9020,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function devotee(): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ── What a guest may do ───────────────────────────────────────────── */

    public function test_a_guest_can_open_the_map_and_see_the_churches(): void
    {
        $this->get(route('map'))
            ->assertOk()
            ->assertSee('Redemptorist Church', false);
    }

    /* ── What a guest may not do ───────────────────────────────────────── */

    public function test_a_guest_is_sent_to_login_for_a_church_page(): void
    {
        $this->get(route('churches.show', $this->church))
            ->assertRedirect(route('login'));
    }

    /**
     * The point of sending them to login rather than showing a bare 403: they
     * come back to the church they picked. A devotee's sign-in used to go to
     * /home unconditionally, which lost it.
     */
    public function test_signing_in_returns_a_guest_to_the_church_they_wanted(): void
    {
        $user   = $this->devotee();
        $church = route('churches.show', $this->church);

        $this->get($church)->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email'    => $user->email,
            'password' => 'secret',
        ])->assertRedirect($church);
    }

    /** With nowhere in mind, signing in still lands on the home page. */
    public function test_signing_in_without_an_intended_page_still_goes_home(): void
    {
        $user = $this->devotee();

        $this->post(route('login.store'), [
            'email'    => $user->email,
            'password' => 'secret',
        ])->assertRedirect(route('home'));
    }

    /* ── What a member sees ────────────────────────────────────────────── */

    public function test_a_signed_in_devotee_opens_the_church_page(): void
    {
        $this->actingAs($this->devotee())
            ->get(route('churches.show', $this->church))
            ->assertOk()
            ->assertSee('Redemptorist Church', false);
    }

    /**
     * The map tells its own JavaScript who is looking, so a guest gets the
     * explanation and the lock rather than a silent bounce.
     */
    public function test_the_map_marks_church_links_for_a_guest_only(): void
    {
        $this->get(route('map'))
            ->assertOk()
            ->assertSee('const GUEST = true', false);

        $this->actingAs($this->devotee())
            ->get(route('map'))
            ->assertOk()
            ->assertSee('const GUEST = false', false);
    }

    /** Planning a route was already members-only and stays that way. */
    public function test_a_guest_cannot_reach_the_planner(): void
    {
        $this->get(route('plan.create'))->assertRedirect(route('login'));
        $this->get(route('plan.hub'))->assertRedirect(route('login'));
    }

    /**
     * A guest pressing Plan Route is sent to the planner URL, not to /login,
     * so the churches they picked survive signing in. This is the server half
     * of that: the planner URL with stops is the intended page, and after
     * signing in they arrive there with the selection intact.
     */
    public function test_a_selection_survives_signing_in(): void
    {
        $user    = $this->devotee();
        $planner = route('plan.create', ['stops' => $this->church->id]);

        $this->get($planner)->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email, 'password' => 'secret',
        ])->assertRedirect($planner);

        $this->actingAs($user)
            ->get($planner)
            ->assertOk()
            ->assertSee('Redemptorist Church', false);
    }

    /**
     * The map installs a gate on the engine only for guests. Without it the
     * popup's Directions and Add to route buttons would act immediately.
     */
    public function test_the_map_gates_engine_actions_for_a_guest_only(): void
    {
        $this->get(route('map'))
            ->assertOk()
            ->assertSee('GiyaLeaflet.requireAccess', false);

        $this->actingAs($this->devotee())
            ->get(route('map'))
            ->assertOk()
            ->assertSee('const GUEST = false', false);
    }
}
