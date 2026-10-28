<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ChurchController;
use App\Http\Controllers\ItineraryController;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\ItineraryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Round-2 regressions: photo file names, the free-tier allowance inside its
 * transaction, and ownership through the new policies.
 */
class ChurchPhotoAndOwnershipTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> files this test wrote into public/ */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    private function user(string $email, string $role = 'devotee'): User
    {
        return User::create([
            'name' => 'Test User', 'email' => $email, 'password_hash' => bcrypt('secret'),
            'role' => $role, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function church(string $name): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Parish'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create([
            'name' => $name, 'category_id' => $cat->id, 'location' => 'Cebu City',
            'latitude' => 10.3, 'longitude' => 123.9, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function itineraryFor(User $user): Itinerary
    {
        $type = ItineraryType::firstOrCreate(['name' => 'Custom'], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return Itinerary::create([
            'user_id' => $user->id, 'itinerary_type_id' => $type->id, 'name' => 'Trip',
            'status' => 'Upcoming', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ── Photos ─────────────────────────────────────────────────────── */

    /** Uploading for one "San Roque Chapel" used to delete the other's photo. */
    public function test_two_churches_with_the_same_name_keep_separate_photos(): void
    {
        $admin = $this->user('admin@example.com', 'admin');
        $a = $this->church('Test Twin Chapel Zz');
        $b = $this->church('Test Twin Chapel Zz');

        foreach ([$a, $b] as $church) {
            $this->actingAs($admin)->post(route('admin.destinations.photo', $church), [
                'photo' => UploadedFile::fake()->image('photo.png', 20, 20),
            ])->assertSessionHasNoErrors();
        }

        $pathA = public_path('images/churches/'.ChurchController::photoBasename($a).'.png');
        $pathB = public_path('images/churches/'.ChurchController::photoBasename($b).'.png');
        $this->written = [$pathA, $pathB];

        $this->assertNotSame($pathA, $pathB);
        $this->assertFileExists($pathA, "The first church's photo was deleted by the second upload.");
        $this->assertFileExists($pathB);
        $this->assertStringContainsString(basename($pathA), $a->fresh()->imagePath());
    }

    /** The extension follows the file's content, not the name it was sent with. */
    public function test_the_saved_extension_comes_from_the_content(): void
    {
        $admin = $this->user('admin@example.com', 'admin');
        $church = $this->church('Test Extension Chapel Zz');

        // A real PNG whose name claims .jpeg.
        $png = UploadedFile::fake()->image('photo.png', 20, 20);
        $file = new UploadedFile($png->getPathname(), 'photo.jpeg', 'image/jpeg', null, true);

        $this->actingAs($admin)->post(route('admin.destinations.photo', $church), ['photo' => $file]);

        $path = public_path('images/churches/test-extension-chapel-zz.png');
        $this->written = [$path, public_path('images/churches/test-extension-chapel-zz.jpg')];

        $this->assertFileExists($path);
    }

    /* ── Free allowance ─────────────────────────────────────────────── */

    public function test_a_free_account_cannot_save_a_fourth_itinerary(): void
    {
        $user = $this->user('lyle@example.com');
        $church = $this->church('Allowance Chapel');

        for ($i = 0; $i < ItineraryController::FREE_LIMIT; $i++) {
            $this->itineraryFor($user);
        }

        $this->actingAs($user)->post(route('plan.store'), [
            'name' => 'One too many', 'type' => 'Custom',
            'stops' => [$church->name], 'stop_ids' => (string) $church->id,
        ])->assertSessionHas('error');

        $this->assertSame(ItineraryController::FREE_LIMIT, Itinerary::where('user_id', $user->id)->count());
    }

    /* ── Ownership via policies ─────────────────────────────────────── */

    public function test_a_devotee_cannot_open_delete_or_tick_someone_elses_itinerary(): void
    {
        $owner = $this->user('owner@example.com');
        $other = $this->user('other@example.com');
        $plan = $this->itineraryFor($owner);
        $stop = ItineraryStop::create([
            'itinerary_id' => $plan->id, 'church_id' => $this->church('Owned Chapel')->id,
            'stop_order' => 1, 'is_visited' => false,
        ]);

        $this->actingAs($other)->get(route('plan.show', $plan))->assertForbidden();
        $this->actingAs($other)->delete(route('plan.destroy', $plan))->assertForbidden();
        $this->actingAs($other)->postJson(route('plan.stop.visited'), ['stop_id' => $stop->id])->assertForbidden();

        $this->assertNotSoftDeleted($plan);
        $this->assertFalse((bool) $stop->fresh()->is_visited);

        $this->actingAs($owner)->get(route('plan.show', $plan))->assertOk();
    }
}
