<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use App\Models\ItineraryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The map is the custom itinerary planner.
 *
 * Choosing a destination is a question about where things are, so it happens
 * on the map rather than on a separate screen with a list beside it. ?plan=1
 * puts the trip's details above the map and turns the selection tray into a
 * save; without it the map is what it always was.
 *
 * The old /plan/create screen is gone. Its URL redirects here, carrying any
 * stops, so a bookmark someone kept lands on the planner rather than on a
 * 404 - but there is no second custom planner to drift out of step.
 */
class MapPlannerTest extends TestCase
{
    use RefreshDatabase;

    private function devotee(): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Church> */
    private function seedChurches(int $count = 5)
    {
        $category = ChurchCategory::firstOrCreate(
            ['name' => 'Basilica'],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return collect(range(1, $count))->map(fn ($i) => Church::create([
            'name' => "Church $i", 'category_id' => $category->id, 'location' => 'Cebu City',
            'latitude' => 10.29 + ($i / 1000), 'longitude' => 123.90 + ($i / 1000),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    /* ── getting there ──────────────────────────────────────────────── */

    public function test_the_plan_hub_sends_a_custom_itinerary_to_the_map(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('plan.hub'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(e(route('map', ['plan' => 1])), $html);
        $this->assertStringNotContainsString('href="'.route('plan.create').'"', $html);
    }

    /* ── what planning mode looks like ──────────────────────────────── */

    public function test_planning_mode_puts_the_trip_details_above_the_map(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('map', ['plan' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="planForm"', $html);
        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('name="scheduled_date"', $html);
        $this->assertStringContainsString('name="notes"', $html);

        // The search box is still there - that was the point of the move.
        $this->assertStringContainsString('id="mapSearch"', $html);

        // And the tray saves rather than handing off to another screen.
        $this->assertStringContainsString(__('giya.plan.start'), $html);
    }

    /**
     * The ordinary map carries the details bar too, closed.
     *
     * Plan Route used to leave for /plan/create, which meant a devotee who
     * had just picked seven churches on the map arrived at a second screen
     * to pick them again. The bar is on the page from the start so the
     * button can open it where they are standing; until they press it the
     * map looks and reads exactly as it did.
     */
    public function test_the_plain_map_carries_the_details_bar_closed(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('map'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="planForm"', $html);
        $this->assertMatchesRegularExpression(
            '/<form[^>]*id="planForm"[^>]*\shidden/',
            $html,
            'The details bar should start hidden on the ordinary map.'
        );

        // The heading and the button are the map's until it is opened.
        $this->assertStringContainsString(__('giya.map.title'), $html);
        $this->assertStringContainsString(__('giya.map.plan_route'), $html);
    }

    public function test_planning_mode_starts_with_the_bar_open(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('map', ['plan' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]*id="planForm"[^>]*\shidden/',
            $html,
            'Arriving with ?plan=1 should show the details bar.'
        );
        $this->assertStringContainsString(__('giya.plan.start'), $html);
    }

    /**
     * A save rejected from the ordinary map comes back with the bar open.
     *
     * The errors live inside it, so leaving it closed would hide the reason
     * the save did not happen.
     */
    public function test_a_rejected_save_reopens_the_bar_on_the_plain_map(): void
    {
        $churches = $this->seedChurches();
        $user     = $this->devotee();

        $this->actingAs($user)
            ->from(route('map'))
            ->post(route('plan.store'), [
                'name'     => '',
                'type'     => 'Custom',
                'stops'    => $churches->take(2)->pluck('name')->all(),
                'stop_ids' => $churches->take(2)->pluck('id')->implode(','),
            ])
            ->assertRedirect(route('map'));

        $html = $this->actingAs($user)->get(route('map'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]*id="planForm"[^>]*\shidden/',
            $html,
            'A rejected save should reopen the details bar so its error is visible.'
        );
    }

    /* ── the map plans whichever trip arrived ───────────────────────── */

    /**
     * A Visita Iglesia route keeps its name on the map.
     *
     * It used to lose it: the map only ever planned a Custom trip, so a
     * devotee who came over to see their seven churches and adjusted one
     * saved a Custom itinerary, with no way back to the planner they came
     * from.
     */
    public function test_the_map_plans_a_visita_trip_as_a_visita_trip(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('map', ['plan' => 'visita']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="Visita Iglesia"', $html);
        $this->assertStringContainsString(__('giya.plan.visita_title'), $html);
        $this->assertStringNotContainsString(__('giya.plan.custom_title'), $html);

        // And a way back to the planner it came from, not just to the hub.
        $this->assertStringContainsString(e(route('plan.visita')), $html);
        $this->assertStringContainsString(__('giya.plan.back_visita'), $html);
    }

    public function test_the_map_still_plans_a_custom_trip_by_default(): void
    {
        $this->seedChurches();

        $html = $this->actingAs($this->devotee())->get(route('map', ['plan' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="Custom"', $html);
        $this->assertStringContainsString(__('giya.plan.custom_title'), $html);
    }

    /** Arriving with a route is arriving mid-plan, whoever sent it. */
    public function test_arriving_with_stops_opens_the_bar(): void
    {
        $churches = $this->seedChurches();

        $html = $this->actingAs($this->devotee())
            ->get(route('map', ['stops' => $churches->take(3)->pluck('id')->implode(',')]))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]*id="planForm"[^>]*\shidden/',
            $html,
            'A route handed to the map should arrive with its details open.'
        );
    }

    /**
     * A trip saved from the map with plan=visita is a Visita Iglesia
     * itinerary in the database, not a Custom one wearing the name.
     */
    public function test_a_visita_trip_saved_from_the_map_keeps_its_type(): void
    {
        $churches = $this->seedChurches();
        $user     = $this->devotee();

        $this->actingAs($user)->post(route('plan.store'), [
            'name'     => 'Holy Week',
            'type'     => 'Visita Iglesia',
            'stops'    => $churches->take(3)->pluck('name')->all(),
            'stop_ids' => $churches->take(3)->pluck('id')->implode(','),
        ])->assertRedirect();

        $this->assertSame(
            'Visita Iglesia',
            Itinerary::where('user_id', $user->id)->firstOrFail()->type
        );
    }

    /**
     * The way back carries the route.
     *
     * Drop a church on the map and the planner you return to should show the
     * six you have, not the seven you left with.
     */
    public function test_the_visita_planner_reads_a_route_handed_back(): void
    {
        $churches = $this->seedChurches();
        $wanted   = $churches->only([3, 1])->values();

        $html = $this->actingAs($this->devotee())
            ->get(route('plan.visita', ['stops' => $wanted->pluck('id')->implode(',')]))
            ->assertOk()
            ->getContent();

        // Two churches, in the order the map sent them - not the seven the
        // planner would otherwise start with.
        $this->assertMatchesRegularExpression(
            '/const returning = \[\{.*"id":'.$wanted[0]->id.'.*"id":'.$wanted[1]->id.'.*\}\]/s',
            $html,
            'The Visita planner should be handed the route the map sent back.'
        );

        /* And that it starts from it. Rendering the route into the page is
           only half of it - the list has to be seeded from it, and nothing
           PHPUnit can see distinguishes a preset that is used from one that
           is quietly ignored in favour of the usual seven. So the branch
           itself is the assertion. The browser proves the rest. */
        $this->assertMatchesRegularExpression(
            '/list\s*=\s*returning\.length/',
            $html,
            'The planner renders the returning route but does not start from it.'
        );
    }

    /**
     * The hidden attribute has to actually hide.
     *
     * A browser's own rule for [hidden] is display:none at the very bottom of
     * the cascade, so any class that sets display beats it - and .plan-bar,
     * .eyebrow and .back-link all set display. Marking the itinerary bar
     * hidden did nothing at all: it sat open on the ordinary map while both
     * the attribute and el.hidden insisted it was closed. The stylesheet
     * settles it once, and this is the guard, because no assertion about the
     * markup can see it.
     */
    public function test_the_stylesheet_makes_hidden_mean_hidden(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        $this->assertMatchesRegularExpression(
            '/\[hidden\]\s*\{[^}]*display:\s*none\s*!important/',
            $css,
            'giya.css must neutralise [hidden] with !important, or a class that sets display will override it.'
        );
    }

    /**
     * No screen in GIYA points at the old planner.
     *
     * This checked the map alone, and the map was clean - while the home
     * page's own Plan Your Pilgrimage card, the church page, My Itineraries
     * and the profile all still went there. A test that names the one page
     * you were working on proves that page, and quietly says nothing about
     * the app, which is how a screen nothing was supposed to reach stayed
     * one click from the front page.
     *
     * So: every devotee-facing screen, and the list is the point.
     */
    public function test_no_screen_links_to_the_old_planner(): void
    {
        $churches = $this->seedChurches();
        $user     = $this->devotee();

        $pages = [
            route('map'),
            route('map', ['plan' => 1]),
            route('home'),
            route('plan.hub'),
            route('plan.visita'),
            route('plan.index'),
            route('profile'),
            route('churches.show', $churches->first()),
        ];

        // Both spellings: @json escapes forward slashes, so the plain URL
        // alone would miss a link handed to a script.
        $needles = [route('plan.create'), str_replace('/', '\\/', route('plan.create'))];

        foreach ($pages as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            foreach ($needles as $needle) {
                $this->assertStringNotContainsString(
                    $needle, $html, "$url still links to the old planner"
                );
            }
        }
    }

    /**
     * A guest gets the ordinary map. A details form would only bounce them to
     * a login and lose everything they had picked.
     */
    public function test_a_guest_asking_for_planning_mode_just_gets_the_map(): void
    {
        $this->seedChurches();

        $html = $this->get(route('map', ['plan' => 1]))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="planForm"', $html);

        // And not the planner's heading either - offering to plan a trip that
        // cannot be saved is its own small dishonesty.
        $this->assertStringContainsString(__('giya.map.title'), $html);
        $this->assertStringNotContainsString(__('giya.plan.custom_title'), $html);
    }

    /* ── saving ─────────────────────────────────────────────────────── */

    public function test_it_saves_the_route_the_map_posts(): void
    {
        $churches = $this->seedChurches();
        $user     = $this->devotee();

        /* The order the map worked out, which is not the order they were
           seeded and not ascending by id either - otherwise a save that
           quietly re-sorted the stops would still look correct here. */
        $chosen = collect([$churches[2], $churches[0], $churches[4]]);

        $this->actingAs($user)->post(route('plan.store'), [
            'name'           => 'Holy Week 2026',
            'type'           => 'Custom',
            'scheduled_date' => now()->addWeek()->toDateString(),
            'notes'          => 'Start early.',
            'stops'          => $chosen->pluck('name')->all(),
            'stop_ids'       => $chosen->pluck('id')->implode(','),
        ])->assertRedirect();

        $itinerary = Itinerary::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Holy Week 2026', $itinerary->name);
        $this->assertSame('Start early.', $itinerary->notes);
        $this->assertSame(
            $chosen->pluck('id')->all(),
            $itinerary->stops()->orderBy('stop_order')->pluck('church_id')->all(),
            'The stops should keep the order the map sent them in.'
        );
    }

    /**
     * Two churches can share a name. Ids are what the map has, so ids are what
     * decides - a name lookup would put the wrong one on the route.
     */
    public function test_ids_win_over_names_when_two_churches_share_one(): void
    {
        $category = ChurchCategory::firstOrCreate(
            ['name' => 'Chapel'], ['created_at' => now(), 'updated_at' => now()]
        );

        $first = Church::create([
            'name' => 'San Roque Chapel', 'category_id' => $category->id, 'location' => 'Cebu City',
            'latitude' => 10.29, 'longitude' => 123.90, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $second = Church::create([
            'name' => 'San Roque Chapel', 'category_id' => $category->id, 'location' => 'Talisay',
            'latitude' => 10.24, 'longitude' => 123.84, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = $this->devotee();

        /* The Cebu City one, deliberately the one a name lookup would NOT
           land on: keying churches by name keeps the last row, so if ids
           stopped deciding this would silently save Talisay instead. */
        $this->actingAs($user)->post(route('plan.store'), [
            'name'     => 'The Cebu City one',
            'type'     => 'Custom',
            'stops'    => [$first->name],
            'stop_ids' => (string) $first->id,
        ])->assertRedirect();

        $stops = Itinerary::where('user_id', $user->id)->firstOrFail()
            ->stops()->pluck('church_id')->all();

        $this->assertSame([$first->id], $stops);
        $this->assertNotContains($second->id, $stops);
    }

    /** Without ids - the two form planners - names still work. */
    public function test_the_form_planners_can_still_post_names_alone(): void
    {
        $churches = $this->seedChurches(3);
        $user     = $this->devotee();

        $this->actingAs($user)->post(route('plan.store'), [
            'name'  => 'By name',
            'type'  => 'Visita Iglesia',
            'stops' => $churches->pluck('name')->all(),
        ])->assertRedirect();

        $this->assertCount(3, Itinerary::where('user_id', $user->id)->firstOrFail()->stops);
    }

    /* ── when it goes wrong ─────────────────────────────────────────── */

    public function test_a_rejected_save_comes_back_with_the_route_intact(): void
    {
        $churches = $this->seedChurches();
        $user     = $this->devotee();
        $ids      = $churches->take(3)->pluck('id')->implode(',');

        $this->actingAs($user)
            ->from(route('map', ['plan' => 1]))
            ->post(route('plan.store'), [
                'name'     => '',                        // required
                'type'     => 'Custom',
                'stops'    => $churches->take(3)->pluck('name')->all(),
                'stop_ids' => $ids,
            ])
            ->assertRedirect(route('map', ['plan' => 1]))
            ->assertSessionHasErrors('name');

        // The map reads old('stop_ids') and ticks them again, so the devotee
        // does not have to rebuild a route the server rejected for a name.
        $this->actingAs($user)
            ->get(route('map', ['plan' => 1]))
            ->assertOk()
            ->assertSee($ids, false);
    }

    public function test_the_free_limit_disables_the_button_and_refuses_the_post(): void
    {
        $churches = $this->seedChurches();
        $user     = $this->devotee();
        $type     = ItineraryType::firstOrCreate(
            ['name' => 'Custom'],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        foreach (range(1, 3) as $i) {
            Itinerary::create([
                'user_id' => $user->id, 'itinerary_type_id' => $type->id,
                'name' => "Trip $i", 'status' => 'Upcoming',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $html = $this->actingAs($user)->get(route('map', ['plan' => 1]))
            ->assertOk()->getContent();

        // On the button itself. The bare word appears elsewhere in the page,
        // so assertSee('disabled') passed even with the guard taken out.
        $this->assertMatchesRegularExpression(
            '/<button[^>]*id="btnDirections"[^>]*\sdisabled/',
            $html,
            'The tray button should be disabled at the free limit.'
        );

        $this->actingAs($user)->post(route('plan.store'), [
            'name'  => 'One too many',
            'type'  => 'Custom',
            'stops' => [$churches->first()->name],
        ]);

        $this->assertSame(3, Itinerary::where('user_id', $user->id)->count());
    }

    /** The old screen is gone; its URL leads to the one that replaced it. */
    public function test_the_old_planner_url_redirects_to_the_map(): void
    {
        $this->seedChurches();

        $this->actingAs($this->devotee())
            ->get(route('plan.create'))
            ->assertRedirect(route('map', ['plan' => 1]));
    }
}
