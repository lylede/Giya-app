<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Feedback;
use App\Models\Schedule;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin panel as one design, rather than seven screens that happen to
 * share a sidebar.
 *
 * Each module had written its own version of the same three things: a
 * headline figure, a table cell, a form field. The dashboard's tiles were
 * 18px tall with a 1.75rem number; feedback's were a card-body with a
 * 1.375rem number in the display serif; user management's were a third shape
 * again, laid out sideways; reports had a fifth. A figure meant four
 * different sizes depending on which page you were looking at.
 *
 * They are one component now. What is asserted here is that no module has
 * gone back to hand-rolling its own - which is what actually happens, since
 * the quickest way to add a statistic to a page is to copy the div next to
 * it.
 *
 * INLINE STYLES. Counted rather than forbidden outright, because a style
 * attribute is the mechanism by which all of the above happened: a value
 * typed into the markup where no stylesheet can reach it, no other screen
 * can share it, and no media query can change it on a phone. The count is
 * zero across every admin template, and the assertion names the offender.
 */
class AdminDesignSystemTest extends TestCase
{
    use RefreshDatabase;

    /** Every module, by the URL it lives at. */
    private const MODULES = [
        'dashboard', 'users', 'destinations', 'schedules',
        'feedback', 'transactions', 'reports',
    ];

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'admin',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Enough of everything that no module renders its empty state. */
    private function records(): User
    {
        $admin = $this->admin();

        $cat = ChurchCategory::create(['name' => 'Church']);

        $church = Church::create([
            'category_id' => $cat->id, 'name' => 'Basilica del Santo Nino',
            'municipality' => 'Cebu City', 'latitude' => 10.294, 'longitude' => 123.902,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Church::create([
            'category_id' => $cat->id, 'name' => 'Unplaced Chapel',
            'municipality' => 'Cebu City', 'is_active' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schedule::create([
            'church_id' => $church->id, 'event_name' => 'Weekday Mass',
            'event_type' => 'Mass', 'recurrence' => 'Monday',
            'start_time' => '06:00', 'status' => 'Published',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Feedback::create([
            'user_id' => $admin->id, 'church_id' => $church->id,
            'rating' => 5, 'comment' => 'Beautiful.', 'status' => 'Approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Pilgrim Plus', 'price' => 199, 'currency' => 'PHP',
            'duration_days' => 30, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Transaction::create([
            'user_id' => $admin->id, 'plan_type_id' => $plan->id,
            'amount' => 199, 'currency' => 'PHP', 'method' => 'Maya',
            'provider' => 'Maya', 'status' => 'Paid', 'reference_no' => 'PB-DESIGN1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $admin;
    }

    /**
     * The dashboard's visit chart groups by to_char(visited_at, 'YYYY-MM'),
     * which is Postgres - what the app actually runs on - and which SQLite
     * has no function for. Without this the dashboard is the one module no
     * test can render, which is exactly the module most worth rendering.
     *
     * So the function is taught to the test connection. Only the one format
     * the query uses, because a shim that guesses at the rest would quietly
     * disagree with Postgres instead of failing.
     */
    private function teachSqliteToChar(): void
    {
        $pdo = \DB::connection()->getPdo();

        if (! method_exists($pdo, 'sqliteCreateFunction')) {
            return;                                  // not SQLite; nothing to do
        }

        $pdo->sqliteCreateFunction('to_char', function ($value, $format) {
            if ($format !== 'YYYY-MM') {
                throw new \RuntimeException("to_char format {$format} is not shimmed for tests");
            }

            return $value === null ? null : substr((string) $value, 0, 7);
        }, 2);
    }

    private function render(string $module, User $admin): string
    {
        // The dashboard lives at the panel's root, not at /admin/dashboard.
        $url = $module === 'dashboard' ? '/admin' : "/admin/{$module}";

        $this->withoutExceptionHandling();

        $res = $this->actingAs($admin)->get($url);

        $this->assertSame(200, $res->getStatusCode(), "{$url} did not render");

        $html = $res->getContent();

        /* A way to get every module in front of a real browser. The asset
           URLs are rewritten to the files on disk so the page can be opened
           straight from /tmp with its stylesheet attached. */
        if (env('GIYA_DUMP_ADMIN')) {
            file_put_contents("/tmp/admin-{$module}.html", preg_replace(
                '#https?://[^"\']*/assets/#',
                'file://'.public_path('assets').'/',
                $html
            ));
        }

        return $html;
    }

    public function test_every_module_renders_with_records_present(): void
    {
        $admin = $this->records();
        $this->teachSqliteToChar();

        foreach (self::MODULES as $module) {
            $html = $this->render($module, $admin);

            /* A Blade error inside a @section can still return 200 with the
               layout around a gap, so the sidebar alone proves nothing. */
            $this->assertStringContainsString('admin-content', $html);
            $this->assertStringNotContainsString('Undefined variable', $html);
        }
    }

    public function test_no_admin_template_carries_an_inline_style(): void
    {
        foreach (glob(resource_path('views/admin/*.blade.php')) as $file) {
            $name = basename($file);
            $body = file_get_contents($file);

            $this->assertSame(0, substr_count($body, 'style="'),
                "{$name} has a style attribute: a value typed where no stylesheet can reach it");
        }

        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertSame(0, substr_count($layout, 'style="'),
            'the admin layout has a style attribute');
    }

    public function test_the_modules_with_figures_use_the_one_tile(): void
    {
        $admin = $this->records();
        $this->teachSqliteToChar();

        /* Reports is the fifth: its five-across summary was the last place
           still setting a statistic in the display serif. */
        foreach (['dashboard', 'users', 'destinations', 'feedback', 'transactions', 'reports'] as $module) {
            $html = $this->render($module, $admin);

            $this->assertStringContainsString('stat-row', $html,
                "{$module} does not put its figures in the shared row");
            $this->assertStringContainsString('stat-tile-value giya-num', $html,
                "{$module} is not using the shared tile, so its numbers are its own");
        }
    }

    /** @return array<string> the bi- name inside each stat tile, in order */
    private function tileIcons(string $html): array
    {
        preg_match_all(
            '/class="stat-tile-icon[^"]*">\s*<i class="bi bi-([a-z0-9-]+)"/',
            $html, $m
        );

        return $m[1];
    }

    public function test_each_module_leads_with_giyas_own_icon(): void
    {
        $admin = $this->records();
        $this->teachSqliteToChar();

        /* The glyph on the FIRST tile - the one naming what the module is
           about. A generic people-fill on the users screen is not wrong, it
           just belongs to any admin panel; these are drawn for this app.

           Anchored inside the tile rather than searched for anywhere on the
           page: giya-pilgrim also appears in the users modal and its empty
           state, so a bare assertStringContainsString passed with the tile
           switched back to people-fill. */
        foreach ([
            'users'        => 'giya-pilgrim',
            'destinations' => 'giya-spires',
            'feedback'     => 'giya-star',
            'transactions' => 'giya-payment',
            'reports'      => 'giya-pilgrim',
        ] as $module => $icon) {
            $icons = $this->tileIcons($this->render($module, $admin));

            $this->assertNotEmpty($icons, "{$module} renders no stat tiles at all");
            $this->assertSame($icon, $icons[0],
                "{$module} leads with {$icons[0]} rather than {$icon}");
        }
    }

    public function test_a_tile_that_names_a_thing_names_it_in_giyas_vocabulary(): void
    {
        $admin = $this->records();
        $this->teachSqliteToChar();

        /* The exception, and it is a deliberate one.

           A tile that names a SUBJECT - a devotee, a church, a payment, a
           journey - uses the drawn set. A tile that names a STATE or a paid
           TIER does not: pending, approved, flagged, draft, suspended and
           premium are not ideas this app has its own word for, and a
           devotional glyph standing in for "suspended" is decoration where a
           meaning should be. An hourglass says waiting to anybody.

           The gem is also what the Plan column in the same table already
           shows beside the word Premium, so the tile and the row agree. */
        $conventional = [
            'hourglass-split', 'check-circle-fill', 'flag-fill',
            'eye-slash', 'x-circle', 'gem',
        ];

        foreach (['dashboard', 'users', 'destinations', 'feedback', 'transactions', 'reports'] as $module) {
            foreach ($this->tileIcons($this->render($module, $admin)) as $icon) {
                if (in_array($icon, $conventional, true)) {
                    continue;
                }

                $this->assertStringStartsWith('giya-', $icon,
                    "{$module} has a tile named with {$icon}, which is neither GIYA's own "
                    ."nor one of the conventional state glyphs");
            }
        }
    }

    public function test_no_tile_leans_on_a_glyph_that_dissolves_at_its_own_size(): void
    {
        $admin = $this->records();
        $this->teachSqliteToChar();

        /* Rendered at 20px and judged by eye, which is the only way this can
           be judged. Three chosen for their meaning did not survive it:

             giya-seven  - seven dots in a ring, which at 20px is a loading
                           spinner rather than the seven churches
             giya-veil   - a covered head, which shrinks to a mailbox
             giya-footprint - a foot, which shrinks to a light bulb

           They are still in the set and still right where they are large.
           They are not allowed on a 20px tile, and this is the note that
           stops them being chosen again for their meaning alone. */
        $tooFineAtTwenty = ['giya-seven', 'giya-veil', 'giya-footprint'];

        foreach (self::MODULES as $module) {
            $used = array_intersect($this->tileIcons($this->render($module, $admin)), $tooFineAtTwenty);

            $this->assertSame([], array_values($used),
                "{$module} puts ".implode(', ', $used).' on a 20px tile, where it does not hold up');
        }
    }

    public function test_no_table_can_push_the_page_sideways(): void
    {
        $admin = $this->records();

        /* A table of nine columns is wider than a phone. It has to scroll
           inside its own card; without the wrapper the whole page scrolls
           sideways and the sidebar goes with it. */
        foreach (['users', 'destinations', 'transactions'] as $module) {
            $this->assertStringContainsString('adm-scroller', $this->render($module, $admin),
                "{$module}'s table is not in a scroller, so it will push the page sideways on a phone");
        }

        // Schedules keeps its table inside the filter card, with its own.
        $this->assertStringContainsString('adm-table-scroll', $this->render('schedules', $admin));
    }

    public function test_the_rail_collapses_at_every_width_it_exists_at(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        /* The narrow-screen rule used to set .admin-sidebar's width outright,
           which beats var(--admin-rail) - so between 768px and 1024px the
           toggle rotated its chevron and moved nothing. */
        $this->assertStringNotContainsString('.admin-sidebar { width: 220px; }', $css,
            'a fixed sidebar width overrides the rail, so collapsing stops working at that size');

        $this->assertMatchesRegularExpression(
            '/\.admin-layout\s*\{\s*--admin-rail:\s*220px;\s*\}/',
            $css,
            'the narrow breakpoint should change the rail property, not the width'
        );
    }

    public function test_a_section_heading_in_the_rail_is_a_rule_not_a_clipped_word(): void
    {
        $css = file_get_contents(public_path('assets/css/giya.css'));

        /* "MANAGEMENT" does not fit in 78px. Shrinking it produced
           "MANAGEME" with the end cut off, which reads as a rendering bug
           rather than a heading. */
        preg_match('/\.is-railed \.admin-nav-section \{([^}]*)\}/s', $css, $m);

        $this->assertNotEmpty($m, 'the railed section heading has no rule of its own');
        $this->assertStringContainsString('font-size: 0', $m[1],
            'the heading still renders its text in the rail, where it cannot fit');
    }
}
