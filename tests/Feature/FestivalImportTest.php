<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Schedule;
use App\Models\User;
use App\Services\GiyaAssistant;
use App\Support\Recurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Cebu's festivals: fixed-date and Easter rules, town festivals, the import. */
class FestivalImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 10, 29, 9));
    }

    private function basilica(): Church
    {
        $cat = ChurchCategory::create(['name' => 'Basilica', 'created_at' => now(), 'updated_at' => now()]);

        return Church::create(['name' => 'Basilica del Santo Niño', 'category_id' => $cat->id,
            'location' => 'Osmeña Blvd, Cebu City', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function admin(): User
    {
        return User::where('email', 'a@example.com')->first() ?? User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password_hash' => bcrypt('x'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_fixed_date_and_easter_rules(): void
    {
        $jan15 = Recurrence::fromInput('yearly', [], null, null, 1, 'date', 15);
        $this->assertSame('Yearly - January 15', Recurrence::label($jan15));
        $this->assertSame('2027-01-15', Recurrence::next($jan15)->toDateString());

        $easter = Recurrence::fromInput('yearly', [], null, null, null, 'easter');
        $this->assertSame('Yearly - Easter Sunday', Recurrence::label($easter));
        $this->assertSame('2027-03-28', Recurrence::next($easter)->toDateString());
        $this->assertSame('2028-04-16', Recurrence::easter(2028)->toDateString());

        $this->assertNull(Recurrence::fromInput('yearly', [], null, null, 2, 'date', 30), 'No February 30.');
    }

    public function test_the_import_adds_all_53_once_and_attaches_sinulog_to_the_basilica(): void
    {
        $basilica = $this->basilica();

        $this->artisan('giya:import-festivals', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Schedule::count(), 'A dry run saves nothing.');

        $this->artisan('giya:import-festivals')->assertSuccessful();
        $this->assertSame(53, Schedule::count());

        $sinulog = Schedule::where('event_name', 'Sinulog Festival')->first();
        $this->assertSame($basilica->id, $sinulog->church_id);
        $this->assertSame('Yearly - 3rd Sunday of January', $sinulog->recurrence);
        $this->assertSame('2027-01-17', $sinulog->schedule_date->toDateString());

        $panuhug = Schedule::where('event_name', 'Panuhug Festival')->first();
        $this->assertNull($panuhug->church_id);
        $this->assertSame('Santa Fe', $panuhug->location);
        $this->assertSame('Yearly - January 15', $panuhug->recurrence);
        $this->assertTrue($panuhug->is_whole_day);
        $this->assertStringContainsString('tourism.cebu.gov.ph', $panuhug->notes);

        $this->assertSame('Yearly - Easter Sunday', Schedule::where('event_name', 'Sugat Kabanhawan Festival')->value('recurrence'));

        $this->artisan('giya:import-festivals')->assertSuccessful();
        $this->assertSame(53, Schedule::count(), 'Running it again adds nothing.');
    }

    public function test_an_admin_can_save_a_town_festival_without_a_destination(): void
    {
        $this->actingAs($this->admin())->post(route('admin.schedules.store'), [
            'event_name' => 'Tuba Festival', 'event_type' => 'Feast Day', 'status' => 'Published',
            'location' => 'Borbon', 'repeat_type' => 'yearly', 'repeat_yearly_on' => 'date',
            'repeat_month' => 1, 'repeat_day' => 20,
        ])->assertSessionHasNoErrors();

        $s = Schedule::first();
        $this->assertNull($s->church_id);
        $this->assertSame('Yearly - January 20', $s->recurrence);

        $this->actingAs($this->admin())->get(route('admin.schedules'))->assertOk()->assertSee('Town festival');
    }

    public function test_a_schedule_needs_a_destination_or_a_town(): void
    {
        $this->actingAs($this->admin())->post(route('admin.schedules.store'), [
            'event_name' => 'Fiesta', 'event_type' => 'Feast Day', 'status' => 'Published', 'schedule_date' => '2027-01-01',
        ])->assertSessionHasErrors('church_id');
    }

    public function test_giya_ai_gets_the_festivals_only_for_festival_questions(): void
    {
        $this->artisan('giya:import-festivals');
        $ai = new class extends GiyaAssistant {
            public function prompt(string $q): string { return $this->systemPrompt($q); }
        };

        $this->assertStringContainsString('Panuhug Festival, Santa Fe: Yearly - January 15', $ai->prompt('What festivals are in January?'));
        $this->assertStringNotContainsString('Panuhug Festival', $ai->prompt('What should I wear to Mass?'));
    }
}
