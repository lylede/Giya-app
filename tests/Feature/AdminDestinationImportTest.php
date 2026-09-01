<?php

namespace Tests\Feature;

use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDestinationImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_destination_page_shows_bulk_import_controls(): void
    {
        ChurchCategory::create(['name' => 'Church']);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password_hash' => 'hashed-password',
            'role' => 'admin',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.destinations'));

        $response->assertOk()
            ->assertSee('id="dmImportHere"', false)
            ->assertSee('Import destinations');
    }
}
