<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MassLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function church(): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Church'], ['created_at' => now()]);

        return Church::create([
            'name' => 'Basilica del Santo Nino', 'category_id' => $cat->id, 'location' => 'Cebu City', 'municipality' => 'Cebu City',
            'latitude' => 10.29, 'longitude' => 123.90, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function mass(Church $church, string $time, ?string $language, array $days = [0]): Schedule
    {
        return Schedule::create([
            'church_id' => $church->id, 'event_name' => 'Mass', 'event_type' => 'Mass', 'language' => $language,
            'start_time' => $time, 'status' => 'Published', 'is_recurring' => true,
            'recurrence' => 'Weekly', 'recurrence_rule' => ['type' => 'weekly', 'days' => $days],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => 'admin', 'status' => 'Active', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_timetable_carries_each_mass_language(): void
    {
        $church = $this->church();
        $this->mass($church, '06:00', 'Cebuano');
        $this->mass($church, '09:30', 'English');
        $this->mass($church, '17:00', null);

        $this->assertSame(['Sunday' => [
            ['time' => '6:00 AM', 'language' => 'Cebuano'],
            ['time' => '9:30 AM', 'language' => 'English'],
            ['time' => '5:00 PM', 'language' => null],
        ]], $church->fresh()->massTimetableWithLanguage());

        // The plain timetable is unchanged.
        $this->assertSame(['Sunday' => ['6:00 AM', '9:30 AM', '5:00 PM']], $church->fresh()->massTimetable());
    }

    public function test_days_merge_only_when_their_languages_match(): void
    {
        $church = $this->church();
        $this->mass($church, '06:00', 'Cebuano', [1, 2]);
        $this->mass($church, '06:00', 'English', [3]);

        $this->assertSame(['Monday to Tuesday', 'Wednesday'], array_keys($church->fresh()->massTimetableWithLanguage()));
    }

    public function test_the_church_page_colours_each_time_and_shows_a_key(): void
    {
        $church = $this->church();
        $this->mass($church, '06:00', 'Cebuano');
        $this->mass($church, '09:30', 'English');
        $this->mass($church, '17:00', null);

        $this->actingAs($this->admin())->get(route('churches.show', $church))->assertOk()
            ->assertSee('church-schedule-time is-cebuano', false)
            ->assertSee('church-schedule-time is-english', false)
            ->assertSee('title="English"', false)
            ->assertSee('church-lang-key', false)
            ->assertSee('Language not confirmed');
    }

    public function test_no_key_when_no_language_is_set(): void
    {
        $church = $this->church();
        $this->mass($church, '06:00', null);

        $this->actingAs($this->admin())->get(route('churches.show', $church))->assertOk()
            ->assertDontSee('church-lang-key', false);
    }

    public function test_an_admin_sets_the_language_and_only_known_languages_are_accepted(): void
    {
        $church = $this->church();
        $admin = $this->admin();
        $base = [
            'church_id' => $church->id, 'event_name' => 'Sunday Mass', 'event_type' => 'Mass',
            'start_time' => '09:30', 'status' => 'Published', 'repeat_type' => 'weekly', 'repeat_days' => [0],
        ];

        $this->actingAs($admin)->post(route('admin.schedules.store'), $base + ['language' => 'English'])->assertSessionHasNoErrors();
        $this->assertSame('English', Schedule::sole()->language);

        $this->actingAs($admin)->post(route('admin.schedules.store'), $base + ['language' => 'Klingon'])->assertSessionHasErrors('language');
        $this->assertSame(1, Schedule::count());
    }
}
