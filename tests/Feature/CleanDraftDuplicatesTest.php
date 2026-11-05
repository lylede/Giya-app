<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanDraftDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private function church(string $name, string $town, bool $active, ?float $lat = null, ?float $lng = null): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Parish'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create([
            'name' => $name, 'category_id' => $cat->id, 'location' => $town, 'municipality' => $town,
            'latitude' => $lat, 'longitude' => $lng, 'is_active' => $active,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function schedule(Church $c, string $name): void
    {
        DB::table('schedules')->insert(['church_id' => $c->id, 'event_name' => $name, 'event_type' => 'Feast Day', 'created_at' => now()]);
    }

    public function test_without_delete_nothing_changes(): void
    {
        $this->church('San Roque Parish', 'Talisay City', true, 10.24, 123.84);
        $draft = $this->church('Archdiocesan Shrine of San Roque', 'Talisay City', false, 10.241, 123.841);

        $this->artisan('giya:clean-drafts')->expectsOutputToContain('1 duplicate draft(s) found')->assertSuccessful();

        $this->assertNotNull(Church::find($draft->id));
    }

    public function test_a_duplicate_draft_is_removed_and_its_data_moves_to_the_published_church(): void
    {
        $keep = $this->church('San Roque Parish', 'Talisay City', true, 10.24, 123.84);
        $draft = $this->church('Archdiocesan Shrine of San Roque Parish', 'Talisay City', false, 10.241, 123.841);
        $user = User::create(['name' => 'D', 'email' => 'd@example.com', 'password_hash' => bcrypt('x'), 'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);

        $this->schedule($keep, 'Fiesta of San Roque');
        $this->schedule($draft, 'Fiesta of San Roque');      // same as the kept one: not copied
        $this->schedule($draft, 'Novena to San Roque');      // new: moves
        DB::table('feedback')->insert(['user_id' => $user->id, 'church_id' => $draft->id, 'rating' => 5, 'status' => 'Approved', 'created_at' => now()]);

        $this->artisan('giya:clean-drafts --delete')->assertSuccessful();

        $this->assertNull(Church::find($draft->id));
        $this->assertSame(2, DB::table('schedules')->where('church_id', $keep->id)->count());
        $this->assertSame(1, DB::table('feedback')->where('church_id', $keep->id)->count());
    }

    public function test_different_churches_and_real_drafts_are_kept(): void
    {
        $this->church('Sto. Nino Parish', 'Cebu City', true, 10.29, 123.90);
        $pasil = $this->church('Sto. Nino Parish', 'Cebu City', false, 10.30, 123.88 + 0.03);   // 3 km away: another church
        $town  = $this->church('Our Lady of Fatima Parish', 'Argao', false, 9.88, 123.60);       // a real draft
        $other = $this->church('San Roque Parish', 'Carcar', false);                             // same name, other town

        $this->church('San Roque Parish', 'Talisay City', true);
        $this->artisan('giya:clean-drafts --delete')->assertSuccessful();

        $this->assertNotNull(Church::find($pasil->id));
        $this->assertNotNull(Church::find($town->id));
        $this->assertNotNull(Church::find($other->id));
    }
}
