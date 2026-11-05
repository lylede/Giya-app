<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TypicalMassTimesTest extends TestCase
{
    use RefreshDatabase;

    private function church(string $name, string $category = 'Parish'): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => $category], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => 'Carcar', 'municipality' => 'Carcar', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_churches_without_masses_get_usual_times_marked_unconfirmed(): void
    {
        $empty = $this->church('Empty Parish Zz');
        $has = $this->church('Has Masses Zz');
        $cross = $this->church("Magellan's Cross Zz", 'Heritage');
        DB::table('schedules')->insert(['church_id' => $has->id, 'event_name' => 'Sunday Mass', 'event_type' => 'Mass', 'start_time' => '07:00', 'is_recurring' => true, 'recurrence' => 'Weekly (Sunday)', 'created_at' => now()]);

        $this->artisan('giya:add-typical-mass-times')->assertSuccessful();
        $this->artisan('giya:add-typical-mass-times')->assertSuccessful();   // safe to run twice

        $this->assertSame(5, $empty->schedules()->where('is_estimate', true)->count());
        $this->assertSame(1, $has->schedules()->count());
        $this->assertSame(0, $cross->schedules()->count());

        $user = User::create(['name' => 'D', 'email' => 'd@example.com', 'password_hash' => bcrypt('x'), 'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get(route('churches.show', $empty))
            ->assertSee('not yet confirmed by this parish')
            ->assertSee('Usual Mass time')
            ->assertSee('Weekdays (Monday to Friday)');
    }

    public function test_real_times_replace_the_usual_ones_without_replace_flag(): void
    {
        $c = $this->church('Real Parish Zz');
        $this->artisan('giya:add-typical-mass-times');

        $f = tempnam(sys_get_temp_dir(), 'm').'.csv';
        file_put_contents($f, "Church,Town,Sunday,Saturday,Weekdays,Source,Checked by\nReal Parish Zz,Carcar,7:00 AM,,,Parish office,Lyle\n");
        $this->artisan('giya:import-mass-times', ['file' => $f])->assertSuccessful();

        $this->assertSame(0, $c->schedules()->where('is_estimate', true)->count());
        $this->assertSame(1, $c->schedules()->where('is_estimate', false)->count());
    }
}
