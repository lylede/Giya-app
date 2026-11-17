<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use App\Models\ChurchCategory;
use App\Models\Church;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tile beside each upcoming activity shows when it is: month and day
 * for a dated event, "Every Sun" or "Daily" for one that repeats. The type
 * ("Feast Day") is a tag beside the church instead of filling the tile.
 */
class UpcomingDateTileTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);
        $this->church = Church::create([
            'name' => 'Tile Parish', 'category_id' => $cat->id, 'location' => 'Cebu City', 'municipality' => 'Cebu City',
            'latitude' => 10.3, 'longitude' => 123.9, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function event(array $fields): void
    {
        Schedule::create(array_merge([
            'church_id' => $this->church->id, 'event_name' => 'Tile Event', 'event_type' => 'Feast Day',
            'status' => 'Published', 'created_at' => now(),
        ], $fields));
    }

    private function home(): string
    {
        return $this->withCookie(OnboardingController::COOKIE, '1')->get('/')->assertOk()->getContent();
    }

    public function test_a_dated_event_shows_its_month_and_day(): void
    {
        $this->event(['schedule_date' => now()->addDays(3)->toDateString(), 'start_time' => '17:30']);

        $html = $this->home();
        $when = now()->addDays(3);

        $this->assertMatchesRegularExpression('/class="event-month">'.$when->format('M').'</', $html);
        $this->assertMatchesRegularExpression('/class="event-day">'.$when->format('j').'</', $html);
        $this->assertStringContainsString('class="event-tag">Feast Day<', $html);
        $this->assertStringContainsString('5:30 PM', $html);
    }

    public function test_a_weekly_event_shows_every_and_its_day(): void
    {
        $this->event(['is_recurring' => true, 'recurrence' => 'Weekly (Sunday)', 'event_type' => 'Mass']);

        $html = $this->home();

        $this->assertStringContainsString('class="event-month">Every<', $html);
        $this->assertStringContainsString('class="event-day">Sun<', $html);
        $this->assertStringContainsString('is-word', $html);
    }

    public function test_a_daily_event_says_daily(): void
    {
        $this->event(['is_recurring' => true, 'recurrence' => 'Daily', 'event_type' => 'Mass']);

        $this->assertStringContainsString('class="event-day">Daily<', $this->home());
    }
}
