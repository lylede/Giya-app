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
        $this->assertSame(4, $b->schedules()->where('event_type', 'Mass')->count());
        $this->assertSame(['06:00:00', '08:00:00'], $b->schedules()->where('event_name', 'Sunday Mass')->orderBy('start_time')->pluck('start_time')->map(fn ($t) => strlen($t) === 5 ? $t.':00' : $t)->all());
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
}
