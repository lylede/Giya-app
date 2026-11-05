<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportMassTimesTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $rows): string
    {
        $f = tempnam(sys_get_temp_dir(), 'm').'.csv';
        $h = fopen($f, 'w');
        fputcsv($h, ['Church', 'Town', 'Sunday', 'Saturday', 'Weekdays', 'Source', 'Checked by']);
        foreach ($rows as $r) fputcsv($h, $r);
        fclose($h);

        return $f;
    }

    private function church(string $name, string $town): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Parish'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => $town, 'municipality' => $town, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_times_with_a_source_are_imported_and_show_as_next_mass(): void
    {
        $a = $this->church('San Roque Parish', 'Talisay City');
        $b = $this->church('San Roque Parish', 'Carcar');

        $this->artisan('giya:import-mass-times', ['file' => $this->csv([
            ['San Roque Parish', 'Carcar', '6:00 AM, 8:00 AM', '5:30 PM', '6 am', 'Parish Facebook page, Nov 2026', 'Lyle'],
            ['San Roque Parish', 'Talisay City', '7:00 AM', '', '', '', ''],   // no source: skipped
        ])])->assertSuccessful();

        $this->assertSame(0, $a->schedules()->count());
        // 6:00 AM is said Sunday and on weekdays, so it is one schedule.
        $this->assertSame(3, $b->schedules()->where('event_type', 'Mass')->count());
        $six = $b->schedules()->where('start_time', 'like', '06:00%')->first();
        $this->assertSame([0, 1, 2, 3, 4, 5], $six->recurrence_rule['days']);
        $this->assertSame([0], $b->schedules()->where('start_time', 'like', '08:00%')->first()->recurrence_rule['days']);
        $this->assertNotNull($b->fresh()->nextMass());
    }

    public function test_existing_mass_times_are_kept_unless_replace(): void
    {
        $c = $this->church('Sto. Nino Parish', 'Poro');
        $file = $this->csv([['Sto. Nino Parish', 'Poro', '6:00 AM', '', '', 'Parish office', 'Clint']]);

        $this->artisan('giya:import-mass-times', ['file' => $file]);
        $this->artisan('giya:import-mass-times', ['file' => $file]);
        $this->assertSame(1, $c->schedules()->count());

        $this->artisan('giya:import-mass-times', ['file' => $this->csv([['Sto. Nino Parish', 'Poro', '6:00 AM, 9:00 AM', '', '', 'Parish office', 'Clint']]), '--replace' => true]);
        $this->assertSame(2, $c->schedules()->count());
    }

    public function test_a_day_column_overrides_weekdays_for_that_day(): void
    {
        $c = $this->church('Basilica Zz', 'Cebu City');
        $this->artisan('giya:import-mass-times', ['file' => $this->csvWith(
            ['Church', 'Town', 'Sunday', 'Saturday', 'Weekdays', 'Friday', 'Source', 'Checked by'],
            [['Basilica Zz', 'Cebu City', '', '', '6:00 AM, 5:30 PM', '5:00 AM, 3:00 PM', 'Parish Facebook cover', 'Lyle']]
        )])->assertSuccessful();

        $this->assertSame([1, 2, 3, 4], $c->schedules()->where('start_time', 'like', '06:00%')->first()->recurrence_rule['days']);
        $this->assertSame([5], $c->schedules()->where('start_time', 'like', '15:00%')->first()->recurrence_rule['days']);
        $this->assertSame('Weekly (Monday, Tuesday, Wednesday, Thursday)', $c->schedules()->where('start_time', 'like', '06:00%')->first()->recurrence);
    }

    private function csvWith(array $head, array $rows): string
    {
        $f = tempnam(sys_get_temp_dir(), 'm').'.csv';
        $h = fopen($f, 'w');
        fputcsv($h, $head);
        foreach ($rows as $r) fputcsv($h, $r);
        fclose($h);

        return $f;
    }
}
