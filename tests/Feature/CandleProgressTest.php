<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use App\Models\ItineraryType;
use App\Models\ItineraryStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Progress as a row of candles rather than a bar.
 *
 * How it looks and how it lights is measured in a browser. What the server
 * owes it is narrower and worth pinning: one candle per stop, the ones
 * already visited rendered lit on arrival, and the next one marked - so a
 * devotee coming back to a half-finished route sees where they are instead
 * of a row that lights itself from scratch on every page load.
 */
class CandleProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function route(int $stops, int $visited): Itinerary
    {
        $this->user = User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $category = ChurchCategory::create(['name' => 'Church']);

        $type = ItineraryType::firstOrCreate(
            ['name' => 'Visita Iglesia'],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        $itinerary = Itinerary::create([
            'user_id' => $this->user->id, 'itinerary_type_id' => $type->id,
            'name' => 'Holy Week', 'status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($i = 1; $i <= $stops; $i++) {
            $church = Church::create([
                'category_id' => $category->id, 'name' => "Church {$i}",
                'municipality' => 'Cebu City', 'latitude' => 10.3, 'longitude' => 123.9,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            ItineraryStop::create([
                'itinerary_id' => $itinerary->id, 'church_id' => $church->id,
                'stop_order' => $i, 'is_visited' => $i <= $visited,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $itinerary;
    }

    private function active(int $stops, int $visited): string
    {
        $itinerary = $this->route($stops, $visited);

        $html = $this->actingAs($this->user)
            ->get(route('plan.show', $itinerary))
            ->getContent();

        /* Only the seven-stop, three-visited case, because the probe
           asserts on those numbers. Every method in this class renders this
           page, so an unguarded dump left whichever ran last on disk and the
           probe failed against a route it was never describing. */
        if (env('GIYA_DUMP_ACTIVE') && $stops === 7 && $visited === 3) {
            file_put_contents('/tmp/active.html', preg_replace(
                '#https?://[^"\']*/assets/#',
                'file://'.public_path('assets').'/',
                $html
            ));
        }

        return $html;
    }

    public function test_one_candle_per_stop(): void
    {
        $html = $this->active(5, 0);

        $this->assertSame(5, preg_match_all('/class="candle[ "]/', $html));
        $this->assertSame(5, substr_count($html, 'candle-flame'));
        $this->assertSame(5, substr_count($html, 'candle-wick'));
    }

    public function test_visited_stops_arrive_already_lit(): void
    {
        $html = $this->active(7, 3);

        /* Three lit, and the fourth marked as the one to come. Without this
           the row would light itself from empty on every page load, which
           turns "you have done three" into a replay of doing three. */
        $this->assertSame(3, substr_count($html, 'candle is-lit'));
        $this->assertSame(1, substr_count($html, 'is-next'));

        // The row carries the numbers so the script can tell what changed.
        $this->assertStringContainsString('data-total="7"', $html);
        $this->assertStringContainsString('data-done="3"', $html);
    }

    public function test_a_finished_route_has_no_next_candle(): void
    {
        $html = $this->active(4, 4);

        $this->assertSame(4, substr_count($html, 'candle is-lit'));

        // is-next would be the fifth candle, and there is no fifth candle.
        $this->assertStringNotContainsString('is-next', $html);
    }

    public function test_the_bar_it_replaces_is_gone_from_both_screens(): void
    {
        $this->assertStringNotContainsString('progressBar', $this->active(3, 1));

        $visita = $this->actingAs($this->user)->get(route('plan.visita'))->getContent();

        if (env('GIYA_DUMP_VISITA')) {
            file_put_contents('/tmp/visita.html', preg_replace(
                '#https?://[^"\']*/assets/#',
                'file://'.public_path('assets').'/',
                $visita
            ));
        }

        $this->assertStringNotContainsString('progressBar', $visita);
        $this->assertStringContainsString('candle-row', $visita);
    }

    public function test_the_row_is_hidden_from_screen_readers(): void
    {
        /* Twenty list items each announcing "lit" is worse than the sentence
           beside it, which already says three of seven. */
        $this->assertMatchesRegularExpression('/candle-row[^>]*aria-hidden="true"/s', $this->active(3, 1));
    }
}
