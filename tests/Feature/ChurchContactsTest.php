<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchContactsTest extends TestCase
{
    use RefreshDatabase;

    private function church(string $name, string $town, array $extra = []): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Parish'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create(array_merge(['name' => $name, 'category_id' => $cat->id, 'location' => $town, 'municipality' => $town, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()], $extra));
    }

    public function test_contacts_fill_empty_fields_by_name_or_alias_and_show_on_the_page(): void
    {
        $f = tempnam(sys_get_temp_dir(), 'c').'.php';
        file_put_contents($f, '<?php return '.var_export([
            ['name' => 'Basilica Minore Zz', 'town' => 'Cebu City', 'aka' => ['Basilica Zz'], 'phone' => '(032) 255 6697, 0956 992 4224', 'email' => 'bmsn@example.com', 'facebook' => 'https://www.facebook.com/BMSNCebu'],
            ['name' => 'Kept Parish Zz', 'town' => 'Carmen', 'phone' => '0911 111 1111', 'email' => 'new@example.com'],
        ], true).';');
        $a = $this->church('Basilica Zz', 'Osmena Blvd, Cebu City');
        $b = $this->church('Kept Parish Zz', 'Carmen', ['contact_phone' => '0922 222 2222']);

        $this->artisan('giya:add-church-contacts', ['--file' => $f])->assertSuccessful();

        $this->assertSame('bmsn@example.com', $a->fresh()->contact_email);
        $this->assertSame('0922 222 2222', $b->fresh()->contact_phone, 'An admin-typed phone is kept.');
        $this->assertSame('new@example.com', $b->fresh()->contact_email);

        $user = User::create(['name' => 'D', 'email' => 'd@example.com', 'password_hash' => bcrypt('x'), 'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get(route('churches.show', $a))
            ->assertSee('Contact the parish')
            ->assertSee('href="tel:+63322556697"', false)
            ->assertSee('href="tel:+639569924224"', false)
            ->assertSee('mailto:bmsn@example.com', false)
            ->assertSee('https://www.facebook.com/BMSNCebu', false);
    }

    public function test_the_shipped_directory_parses_and_every_entry_has_a_contact(): void
    {
        $list = require database_path('data/parish_contacts.php');
        $this->assertGreaterThan(150, count($list));
        foreach ($list as $e) {
            $this->assertTrue(! empty($e['phone']) || ! empty($e['email']) || ! empty($e['facebook']), $e['name']);
            if (! empty($e['facebook'])) {
                $this->assertStringStartsWith('https://www.facebook.com/', $e['facebook']);
            }
        }
    }

    public function test_an_admin_can_save_contacts(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a@example.com', 'password_hash' => bcrypt('x'), 'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        ChurchCategory::firstOrCreate(['name' => 'Parish'], ['created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)->post(route('admin.destinations.store'), [
            'name' => 'Admin Typed Zz', 'category' => 'Parish', 'location' => 'Carcar', 'status' => 'Published',
            'contact_phone' => '0917 000 0000', 'contact_email' => 'office@example.com', 'facebook_url' => 'https://www.facebook.com/x',
        ])->assertSessionHasNoErrors();

        $c = Church::where('name', 'Admin Typed Zz')->first();
        $this->assertSame('0917 000 0000', $c->contact_phone);
        $this->assertSame('https://www.facebook.com/x', $c->facebook_url);
    }
}
