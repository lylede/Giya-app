<?php

namespace Tests\Unit;

use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NextMassTest extends TestCase
{
    private function mass(array $attrs): Schedule
    {
        return new Schedule(array_merge(['event_type' => 'Mass', 'is_recurring' => true], $attrs));
    }

    public function test_daily_mass_later_today_or_tomorrow(): void
    {
        $now = Carbon::parse('2026-11-05 10:00');   // a Thursday
        $this->assertSame('2026-11-05 12:00', $this->mass(['recurrence' => 'Daily', 'start_time' => '12:00'])->nextAt($now)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-06 06:00', $this->mass(['recurrence' => 'Daily', 'start_time' => '06:00'])->nextAt($now)->format('Y-m-d H:i'));
    }

    public function test_weekly_by_label_and_by_rule(): void
    {
        $now = Carbon::parse('2026-11-05 10:00');
        $this->assertSame('2026-11-08 09:00', $this->mass(['recurrence' => 'Weekly (Sunday)', 'start_time' => '09:00'])->nextAt($now)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-07 17:00', $this->mass(['recurrence_rule' => ['type' => 'weekly', 'days' => [6]], 'start_time' => '17:00'])->nextAt($now)->format('Y-m-d H:i'));
    }

    public function test_a_past_one_off_or_no_time_has_no_next(): void
    {
        $now = Carbon::parse('2026-11-05 10:00');
        $this->assertNull($this->mass(['is_recurring' => false, 'schedule_date' => '2026-11-01', 'start_time' => '09:00'])->nextAt($now));
        $this->assertNull($this->mass(['recurrence' => 'Daily', 'start_time' => null])->nextAt($now));
    }
}
