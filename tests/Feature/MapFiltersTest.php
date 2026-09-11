<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ChurchController;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The map's filters, and what each one promises.
 *
 * All means all. It did not: with a position it returned only churches within
 * ten kilometres, which made it a second copy of Near, and without one it
 * returned only three of the five categories - so a Cathedral or a Heritage
 * church was in neither. Two different ways of being not-all, in the filter
 * named All.
 *
 * Major is the principal church of each town. Which church that is, is a
 * judgement about the place rather than a rule, so it is a flag an admin
 * ticks; the municipality it belongs to is a column, not a guess made afresh
 * each time an address is read.
 */
class MapFiltersTest extends TestCase
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

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'admin',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function church(string $name, string $category, string $location, array $extra = []): Church
    {
        $cat = ChurchCategory::firstOrCreate(
            ['name' => $category], ['created_at' => now(), 'updated_at' => now()]
        );

        /* array_merge, not +. The + operator keeps the left-hand value for a
           key both sides have, so an is_active override was silently ignored
           and the "hidden churches stay off the map" assertion was testing a
           church that was never hidden. */
        return Church::create(array_merge([
            'name' => $name, 'category_id' => $cat->id, 'location' => $location,
            'latitude' => 10.29, 'longitude' => 123.90, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], $extra));
    }

    /* ── All ────────────────────────────────────────────────────────── */

    /**
     * Every active church reaches the page, whatever its category.
     *
     * The filtering itself is JavaScript and PHPUnit cannot run it, but it can
     * only ever hide what it was given - so a church missing from the markers
     * is a church All can never show, whatever the script does.
     */
    public function test_every_active_church_is_on_the_page(): void
    {
        $this->church('Basilica del Santo Nino', 'Basilica', 'Cebu City');
        $this->church('Cebu Metropolitan Cathedral', 'Cathedral', 'Cebu City');
        $this->church("Magellan's Cross", 'Heritage', 'Cebu City');
        $this->church('Simala Shrine', 'Shrine', 'Lindogon, Sibonga, Cebu');
        $this->church('Closed Chapel', 'Chapel', 'Cebu City', ['is_active' => false]);

        $html = $this->actingAs($this->devotee())->get(route('map'))->assertOk()->getContent();

        foreach (['Basilica del Santo Nino', 'Cebu Metropolitan Cathedral', 'Simala Shrine'] as $name) {
            $this->assertStringContainsString($name, $html, "$name is not on the map at all.");
        }

        // Heritage has no chip of its own, which is not the same as being gone.
        $this->assertStringContainsString("Magellan", $html,
            'A church whose category has no chip must still reach All.');

        // Hidden is the one thing that does keep a church off the map.
        $this->assertStringNotContainsString('Closed Chapel', $html);
    }

    /** The script's All branch hides nothing. */
    public function test_the_all_filter_filters_nothing(): void
    {
        $this->church('Basilica del Santo Nino', 'Basilica', 'Cebu City');

        $html = $this->actingAs($this->devotee())->get(route('map'))->assertOk()->getContent();

        /* The assertion is the branch, because no assertion about the markup
           can see what the filter does with them. It reads: under All, every
           church is kept - not "every church within the radius", which is
           what it used to say and what made All a copy of Near. */
        $this->assertMatchesRegularExpression(
            "/if \(category === 'All'\) \{\s*return true;/",
            $html,
            'All must keep every church.'
        );
    }

    /**
     * A category chip filters by category, and by nothing else.
     *
     * It used to be paired with the radius, which was a third quiet way of
     * hiding churches: pressing Basilica told a devotee in Cebu City they had
     * none, when what they had was none within ten kilometres.
     */
    public function test_a_category_chip_is_not_also_a_radius(): void
    {
        $this->church('Basilica del Santo Nino', 'Basilica', 'Cebu City');

        $html = $this->actingAs($this->devotee())->get(route('map'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/return normalizedChurchCategory === normalizedCategory;\s*\}\);/',
            $html,
            'A category chip must not be gated by the radius as well.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/return inRadius && normalizedChurchCategory === normalizedCategory;/',
            $html
        );
    }

    /* ── Major ──────────────────────────────────────────────────────── */

    public function test_the_major_chip_appears_only_when_a_church_carries_the_flag(): void
    {
        $this->church('Ordinary Parish', 'Church', 'Cebu City');

        $user = $this->devotee();

        $without = $this->actingAs($user)->get(route('map'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-cat="Major"', $without,
            'An empty filter teaches the devotee the app is broken.');

        $this->church('Cebu Metropolitan Cathedral', 'Cathedral', 'Cebu City', [
            'municipality' => 'Cebu City', 'is_major' => true,
        ]);

        $with = $this->actingAs($user)->get(route('map'))->assertOk()->getContent();
        $this->assertStringContainsString('data-cat="Major"', $with);
        $this->assertStringContainsString(__('giya.map.cat_major'), $with);
    }

    /** The flag and the town both reach the script, or the chip cannot work. */
    public function test_the_markers_carry_the_town_and_the_flag(): void
    {
        $this->church('Simala Shrine', 'Shrine', 'Lindogon, Sibonga, Cebu', [
            'municipality' => 'Sibonga', 'is_major' => true,
        ]);

        $html = $this->actingAs($this->devotee())->get(route('map'))->assertOk()->getContent();

        $this->assertStringContainsString('"town":"Sibonga"', $html);
        $this->assertStringContainsString('"major":true', $html);
    }

    /* ── reading the town out of an address ─────────────────────────── */

    /**
     * The longer name has to be tried first.
     *
     * "Lapu-Lapu City" contains no "Cebu City", but plenty of Cebu addresses
     * name both the town and the province, and a list walked in the wrong
     * order files half the map under whichever name it happens to reach
     * first.
     */
    public function test_the_town_is_read_out_of_an_address(): void
    {
        $this->assertSame('Sibonga', ChurchController::townIn('Lindogon, Sibonga, Cebu'));
        $this->assertSame('Mandaue City', ChurchController::townIn('A.C. Cortes Ave, Mandaue City'));
        $this->assertSame('Lapu-Lapu City', ChurchController::townIn('Poblacion, Lapu-Lapu City'));
        $this->assertSame('Cebu City', ChurchController::townIn('P. Burgos St, Cebu City'));

        // Nothing recognisable is left blank rather than guessed at: a blank
        // is a question someone can answer.
        $this->assertNull(ChurchController::townIn('Somewhere else entirely'));
        $this->assertNull(ChurchController::townIn(null));
    }

    /**
     * A town whose name contains another town's must still win.
     *
     * 'Bantayan' is inside 'Daanbantayan'. Taken in the order the list was
     * written, every Daanbantayan address was filed under Bantayan - a town
     * three hours away, on a different island, and nothing said so.
     */
    public function test_the_longer_town_name_wins(): void
    {
        $this->assertSame('Daanbantayan', ChurchController::townIn('Poblacion, Daanbantayan, Cebu'));
        $this->assertSame('Bantayan', ChurchController::townIn('Bantayan, Cebu'));
    }

    /**
     * And no town in the list can be shadowed by another, however the list is
     * edited later. The rule is what is tested, not the one pair that broke.
     */
    public function test_no_town_in_the_list_can_shadow_another(): void
    {
        foreach (ChurchController::TOWNS as $town) {
            $this->assertSame(
                $town,
                ChurchController::townIn("Somewhere, $town, Cebu"),
                "An address in $town is read as a different town."
            );
        }
    }

    /* ── the admin screen, which is where the flag is set ───────────── */

    public function test_an_admin_can_mark_a_church_major(): void
    {
        ChurchCategory::firstOrCreate(['name' => 'Cathedral'],
            ['created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin())->post(route('admin.destinations.store'), [
            'name'         => 'Cebu Metropolitan Cathedral',
            'category'     => 'Cathedral',
            'location'     => 'Cebu City',
            'municipality' => 'Cebu City',
            'is_major'     => '1',
            'latitude'     => 10.2985,
            'longitude'    => 123.9028,
        ])->assertRedirect();

        $church = Church::where('name', 'Cebu Metropolitan Cathedral')->firstOrFail();

        $this->assertTrue((bool) $church->is_major);
        $this->assertSame('Cebu City', $church->municipality);
    }

    /** Left blank, the town is read from the address rather than lost. */
    public function test_a_blank_municipality_is_read_from_the_address(): void
    {
        ChurchCategory::firstOrCreate(['name' => 'Shrine'],
            ['created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin())->post(route('admin.destinations.store'), [
            'name'      => 'Simala Shrine',
            'category'  => 'Shrine',
            'location'  => 'Lindogon, Sibonga, Cebu',
            'latitude'  => 10.02,
            'longitude' => 123.53,
        ])->assertRedirect();

        $this->assertSame('Sibonga',
            Church::where('name', 'Simala Shrine')->value('municipality'));
    }

    /** Unticking has to actually untick - a checkbox sends nothing when off. */
    public function test_unticking_major_clears_the_flag(): void
    {
        $church = $this->church('Cebu Metropolitan Cathedral', 'Cathedral', 'Cebu City', [
            'municipality' => 'Cebu City', 'is_major' => true,
        ]);

        $this->actingAs($this->admin())->post(route('admin.destinations.store'), [
            'church_id'    => $church->id,
            'name'         => $church->name,
            'category'     => 'Cathedral',
            'location'     => 'Cebu City',
            'municipality' => 'Cebu City',
            // no is_major: an unticked checkbox is an absent field, not a false
        ])->assertRedirect();

        $this->assertFalse((bool) $church->fresh()->is_major);
    }

    /** Major and featured are different questions and stay apart. */
    public function test_major_is_not_featured(): void
    {
        $church = $this->church('Cebu Metropolitan Cathedral', 'Cathedral', 'Cebu City', [
            'municipality' => 'Cebu City', 'is_major' => true, 'is_featured' => false,
        ]);

        $this->assertTrue(Church::major()->whereKey($church->id)->exists());
        $this->assertFalse(Church::featured()->whereKey($church->id)->exists());
    }
}
