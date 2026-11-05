<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassTimetableTest extends TestCase
{
    use RefreshDatabase;

    public function test_days_with_the_same_times_are_merged_sunday_first(): void
    {
        $cat = ChurchCategory::create(['name' => 'Basilica', 'created_at' => now(), 'updated_at' => now()]);
        $c = Church::create(['name' => 'T Zz', 'category_id' => $cat->id, 'location' => 'Cebu City', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $add = fn ($t, $days) => Schedule::create(['church_id' => $c->id, 'event_name' => 'Mass', 'event_type' => 'Mass', 'start_time' => $t, 'is_recurring' => true, 'recurrence_rule' => ['type' => 'weekly', 'days' => $days], 'created_at' => now()]);
        $add('06:00', [1, 2, 3, 4, 6]);
        $add('17:30', [0, 1, 2, 3, 4, 5, 6]);
        $add('05:00', [5]);
        $add('08:30', [0]);

        $this->assertSame([
            'Sunday'             => ['8:30 AM', '5:30 PM'],
            'Monday to Thursday' => ['6:00 AM', '5:30 PM'],
            'Friday'             => ['5:00 AM', '5:30 PM'],
            'Saturday'           => ['6:00 AM', '5:30 PM'],
        ], $c->fresh()->massTimetable());
    }
}
