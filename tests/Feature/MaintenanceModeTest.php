<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role.'@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => $role, 'status' => 'Active', 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function close(array $extra = []): void
    {
        SiteSetting::write(['maintenance' => 'on'] + $extra);
    }

    public function test_the_site_is_open_by_default(): void
    {
        $this->get('/map')->assertOk();
        $this->actingAs($this->person('user'))->get('/home')->assertOk();
    }

    public function test_pilgrims_and_guests_see_the_closed_page_while_it_is_on(): void
    {
        $this->close(['maintenance_message' => 'Adding new churches tonight.']);

        $this->get('/')
            ->assertStatus(503)
            ->assertHeader('Retry-After')
            ->assertSee('GIYA is being updated')
            ->assertSee('Adding new churches tonight.');

        $this->get('/map')->assertStatus(503);
        $this->get('/register')->assertStatus(503);

        $this->actingAs($this->person('user'))->get('/home')->assertStatus(503);
    }

    public function test_the_default_message_is_used_when_none_is_written(): void
    {
        $this->close();

        $this->get('/')->assertStatus(503)->assertSee('We are making GIYA better.');
    }

    public function test_admins_carry_on_and_are_reminded(): void
    {
        $this->close();
        $admin = $this->person('admin');

        $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee('Maintenance mode is on. Pilgrims see the closed page.');
        $this->actingAs($admin)->get('/home')->assertOk()->assertSee('Only admins can see this page.');
    }

    public function test_sign_in_stays_open_so_an_admin_can_get_in(): void
    {
        $this->close();

        $this->get('/login')->assertOk();
        $this->get('/forgot-password')->assertOk();
    }

    public function test_a_json_request_gets_a_clear_message(): void
    {
        $this->close();

        $this->actingAs($this->person('user'))
            ->postJson('/chatbot/send', ['message' => 'hello'])
            ->assertStatus(503)
            ->assertJson(['ok' => false, 'maintenance' => true]);
    }

    public function test_maya_can_still_confirm_a_payment(): void
    {
        $this->close();

        // Whatever the webhook answers for an empty body, it is not the closed page.
        $this->assertNotSame(503, $this->postJson('/maya/webhook', [])->getStatusCode());
    }

    public function test_an_admin_turns_it_on_and_off_from_the_panel(): void
    {
        $admin = $this->person('admin');

        $this->actingAs($admin)->get(route('admin.system'))->assertOk()->assertSee('Off: site is open');

        $this->actingAs($admin)->patch(route('admin.system.maintenance'), [
            'state' => 'on', 'message' => 'Back after lunch.', 'back_by' => now()->addHours(2)->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success');

        $this->assertSame('on', SiteSetting::query()->find('maintenance')->value);
        $this->assertSame('Admin', SiteSetting::query()->find('maintenance_by')->value);

        $this->actingAs($admin)->get(route('admin.system'))->assertOk()->assertSee('On: site is closed')->assertSee('Back after lunch.');

        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('Back after lunch.')->assertSee('We expect to be back by');

        $this->actingAs($admin)->patch(route('admin.system.maintenance'), ['state' => 'off'])->assertSessionHas('success');

        auth()->logout();
        $this->get('/map')->assertOk();
    }

    public function test_a_back_by_time_in_the_past_is_not_shown(): void
    {
        $this->close(['maintenance_back_by' => now()->subHour()->toIso8601String()]);

        $this->get('/')->assertStatus(503)->assertDontSee('We expect to be back by');
    }

    public function test_only_admins_can_reach_the_system_page(): void
    {
        $user = $this->person('user');

        $this->actingAs($user)->get(route('admin.system'))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.system.maintenance'), ['state' => 'on'])->assertForbidden();

        $this->assertNull(SiteSetting::query()->find('maintenance'));
    }

    public function test_the_preview_shows_the_page_without_closing_the_site(): void
    {
        $this->actingAs($this->person('admin'))->get(route('admin.system.preview'))
            ->assertOk()->assertSee('Preview only.')->assertSee('GIYA is being updated');

        auth()->logout();
        $this->get('/map')->assertOk();
    }

    public function test_sign_up_is_refused_from_a_form_left_open(): void
    {
        $this->close();

        \Livewire\Livewire::test('auth.register')
            ->set('name', 'Maria')->set('email', 'maria@example.com')
            ->set('password', 'secret123')->set('password_confirmation', 'secret123')->set('terms', true)
            ->call('register')
            ->assertHasErrors('email');

        $this->assertNull(User::where('email', 'maria@example.com')->first());
    }

    public function test_the_site_still_works_before_the_table_exists(): void
    {
        Schema::drop('site_settings');

        $this->get('/map')->assertOk();

        $admin = $this->person('admin');
        $this->actingAs($admin)->get(route('admin.system'))->assertOk()->assertSee('php artisan migrate');
        $this->actingAs($admin)->patch(route('admin.system.maintenance'), ['state' => 'on'])->assertSessionHas('error');
    }
}
