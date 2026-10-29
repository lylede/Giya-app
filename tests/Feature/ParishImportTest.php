<?php

namespace Tests\Feature;

use App\Console\Commands\ImportParishes;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Archdiocese of Cebu parishes as destinations: no duplicates, honest pins. */
class ParishImportTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'parishes').'.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function church(string $name, string $location, ?float $lat = null, ?float $lng = null): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => $location,
            'latitude' => $lat, 'longitude' => $lng, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function data(array $rows): void
    {
        $base = ['address' => null, 'feast' => null, 'district' => '1', 'vicariate' => 'the Most Holy Rosary', 'note' => null];
        file_put_contents($this->file, '<?php return '.var_export(array_map(fn ($r) => $r + $base, $rows), true).';');
    }

    private function import(): void
    {
        $this->artisan('giya:import-parishes', ['--file' => $this->file])->assertSuccessful();
    }

    public function test_name_keys_ignore_titles_and_languages(): void
    {
        $this->assertSame(ImportParishes::nameKey('Basilica del Santo Niño'), ImportParishes::nameKey('Basilica Minore del Santo Niño de Cebu'));
        $this->assertSame(ImportParishes::nameKey('Our Lady of the Rule Parish'), ImportParishes::nameKey('Nuestra Señora de Regla Parish National Shrine'));
        $this->assertSame(['our', 'lady', 'guadalupe'], ImportParishes::nameKey('Archdiocesan Shrine of Our Lady of Guadalupe'));
    }

    public function test_existing_churches_are_not_added_again(): void
    {
        $this->church('Basilica del Santo Niño', 'Osmeña Blvd, Cebu City', 10.2946, 123.9020);
        $this->church('Our Lady of Guadalupe Parish', 'Guadalupe, Cebu City');
        $this->church('Our Lady of the Rule Parish', 'Poblacion, Lapu-Lapu City', 10.3103, 123.9494);

        $this->data([
            // same pin (different name)
            ['name' => 'Basilica Minore del Santo Niño de Cebu', 'town' => 'Cebu City', 'lat' => 10.29465, 'lng' => 123.90205, 'pin' => 'exact'],
            // same name in the same town, no pin to compare
            ['name' => 'Archdiocesan Shrine of Our Lady of Guadalupe', 'town' => 'Cebu City', 'lat' => null, 'lng' => null, 'pin' => null],
            // Spanish name, pins 100 m apart
            ['name' => 'Nuestra Señora de Regla Parish National Shrine', 'town' => 'Lapu-Lapu City', 'lat' => 10.3110, 'lng' => 123.9500, 'pin' => 'exact'],
        ]);
        $this->import();

        $this->assertSame(3, Church::count(), 'Nothing new was added.');
    }

    public function test_a_parish_that_only_shares_a_short_name_is_not_a_duplicate(): void
    {
        // Sto. Niño Parish in Pasil is not the Basilica, though both are "Santo Niño" in Cebu City.
        $this->church('Basilica del Santo Niño', 'Osmeña Blvd, Cebu City', 10.2946, 123.9020);
        $this->data([['name' => 'Sto. Niño Parish', 'address' => 'Pasil, Cebu City', 'town' => 'Cebu City',
            'lat' => 10.2875, 'lng' => 123.8920, 'pin' => 'exact']]);
        $this->import();

        $this->assertSame(2, Church::count());
    }

    public function test_exact_pins_are_published_and_town_pins_wait_as_draft(): void
    {
        $this->data([
            ['name' => 'Archdiocesan Shrine of St. Catherine of Alexandria', 'address' => 'Poblacion I Carcar', 'town' => 'City of Carcar',
             'feast' => 'November 25', 'lat' => 10.1059, 'lng' => 123.6405, 'pin' => 'exact'],
            // A town pin beside the main church: not a duplicate of it, and Draft.
            ['name' => 'San Isidro Labrador Parish', 'address' => 'Calidngan, Carcar City, Cebu 6019', 'town' => 'City of Carcar',
             'lat' => 10.1063, 'lng' => 123.6409, 'pin' => 'town'],
        ]);
        $this->import();

        $carcar = Church::where('name', 'like', '%Catherine%')->first();
        $this->assertTrue($carcar->is_active);
        $this->assertSame('Shrine', $carcar->category);
        $this->assertSame(10.1059, (float) $carcar->latitude);
        $this->assertStringContainsString('Feast day: November 25', $carcar->description);
        $this->assertStringContainsString('thearchdioceseofcebu.com', $carcar->description);

        $calidngan = Church::where('name', 'San Isidro Labrador Parish')->first();
        $this->assertNotNull($calidngan, 'A town pin is never compared by distance.');
        $this->assertFalse($calidngan->is_active, 'A town-centre pin waits as Draft.');
        $this->assertSame('Church', $calidngan->category);
    }

    public function test_the_main_church_of_a_town_is_marked_major_once(): void
    {
        $this->data([
            ['name' => 'Sta. Rosa de Lima Parish', 'address' => 'Alcoy, Cebu 6023', 'town' => 'Alcoy', 'lat' => 9.7076, 'lng' => 123.5063, 'pin' => 'exact', 'main' => true],
            ['name' => 'San Isidro Labrado Parish', 'address' => 'Nug-as, Alcoy, Cebu', 'town' => 'Alcoy', 'lat' => 9.6800, 'lng' => 123.4700, 'pin' => 'exact'],
            ['name' => 'San Narciso Parish', 'address' => 'Consolacion, Cebu 6001', 'town' => 'Consolacion', 'lat' => 10.3766, 'lng' => 123.9573, 'pin' => 'exact', 'main' => true],
        ]);
        $existing = $this->church('Old Consolacion Church', 'Consolacion', 10.40, 123.97);
        $existing->update(['is_major' => true, 'municipality' => 'Consolacion']);
        $this->import();

        $this->assertTrue((bool) Church::where('name', 'Sta. Rosa de Lima Parish')->value('is_major'));
        $this->assertFalse((bool) Church::where('name', 'San Isidro Labrado Parish')->value('is_major'));
        $this->assertFalse((bool) Church::where('name', 'San Narciso Parish')->value('is_major'), 'Consolacion already had its main church.');
    }

    public function test_feast_days_become_yearly_schedules(): void
    {
        $this->assertSame(['type' => 'yearly', 'month' => 11, 'day' => 25], ImportParishes::feastRule('November 25'));
        $this->assertSame(['type' => 'yearly', 'month' => 4, 'day' => 30], ImportParishes::feastRule('April 30 (San Vitalis of Milan)'));
        $this->assertSame(['type' => 'yearly', 'nth' => 3, 'weekday' => 0, 'month' => 1], ImportParishes::feastRule('3rd Sunday of January'));
        $this->assertSame(['type' => 'yearly', 'nth' => -1, 'weekday' => 6, 'month' => 4], ImportParishes::feastRule('Last Saturday of April'));
        $this->assertSame(['type' => 'yearly', 'nth' => 1, 'weekday' => 6, 'month' => 2], ImportParishes::feastRule('First Saturday of February'));
        $this->assertNull(ImportParishes::feastRule('Solemnity of the Most Holy Body and Blood of Christ (moveable solemnity)'));
        $this->assertNull(ImportParishes::feastRule('3rd Week of October'));

        $basilica = $this->church('Basilica del Santo Niño', 'Osmeña Blvd, Cebu City', 10.2946, 123.9020);
        $this->data([
            ['name' => 'Basilica Minore del Santo Niño de Cebu', 'town' => 'Cebu City', 'feast' => '3rd Sunday of January',
             'lat' => 10.29465, 'lng' => 123.90205, 'pin' => 'exact'],
            ['name' => 'Sta. Rosa de Lima Parish', 'address' => 'Alcoy, Cebu 6023', 'town' => 'Alcoy', 'feast' => 'August 30',
             'lat' => 9.7076, 'lng' => 123.5063, 'pin' => 'exact'],
        ]);
        $this->import();
        $this->import();

        $this->assertSame(2, Schedule::where('event_type', 'Feast Day')->count(), 'One each, even run twice.');
        $this->assertSame(1, Schedule::where('church_id', $basilica->id)->count(), 'An existing church gets its feast too.');

        $alcoy = Schedule::where('event_name', 'like', '%Sta. Rosa%')->first();
        $this->assertTrue((bool) $alcoy->is_recurring);
        $this->assertSame('Yearly - August 30', $alcoy->recurrence);
        $this->assertSame('08-30', $alcoy->schedule_date->format('m-d'));
    }

    public function test_the_shipped_list_leaves_chapels_out(): void
    {
        $rows = require database_path('data/archdiocese_parishes.php');

        $this->assertCount(170, $rows);
        foreach ($rows as $r) {
            $this->assertDoesNotMatchRegularExpression('/chapel|chaplaincy/i', $r['name']);
            $this->assertContains($r['pin'], ['exact', 'town']);
            $this->assertNotNull($r['lat']);
        }
    }

    public function test_running_twice_adds_nothing_the_second_time(): void
    {
        $this->data([['name' => 'San Narciso Parish', 'address' => 'Consolacion, Cebu 6001', 'town' => 'Consolacion',
            'lat' => 10.3766, 'lng' => 123.9573, 'pin' => 'exact']]);
        $this->import();
        $this->import();

        $this->assertSame(1, Church::count());
    }
}
