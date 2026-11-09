<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PrivacyAndAccountTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'user'): User
    {
        return User::create([
            'name' => 'Lyle Test', 'email' => $role.'@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => $role, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_privacy_policy_and_terms_are_open_to_everyone(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Data Privacy Act')->assertSee('Groq')->assertSee('Maya');
        $this->get('/terms')->assertOk()->assertSee('Giya AI is an artificial intelligence');
    }

    public function test_the_contact_email_shows_when_it_is_set(): void
    {
        config(['app.contact_email' => 'team@giya.test']);
        $this->get('/privacy')->assertSee('mailto:team@giya.test', false);
    }

    public function test_sign_up_needs_the_consent_box(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'New Pilgrim')->set('email', 'new@example.com')
            ->set('password', 'secret123')->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertHasErrors(['terms' => 'accepted']);

        $this->assertDatabaseMissing('devotees', ['email' => 'new@example.com']);
    }

    public function test_sign_up_records_when_the_terms_were_accepted(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'New Pilgrim')->set('email', 'new@example.com')
            ->set('password', 'secret123')->set('password_confirmation', 'secret123')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors();

        $this->assertNotNull(User::where('email', 'new@example.com')->value('terms_accepted_at'));
    }

    public function test_a_devotee_can_delete_their_account_and_its_data(): void
    {
        $user = $this->user();
        ChatSession::create(['user_id' => $user->id, 'title' => 'South Cebu', 'status' => 'Active']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'secret123'])
            ->assertRedirect(route('root'));

        $this->assertGuest();
        $this->assertDatabaseMissing('devotees', ['id' => $user->id]);
        $this->assertDatabaseMissing('chat_sessions', ['user_id' => $user->id]);
    }

    public function test_deleting_needs_the_right_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password', null, 'deleteAccount');

        $this->assertDatabaseHas('devotees', ['id' => $user->id]);
    }

    public function test_an_admin_cannot_delete_their_own_account_here(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'secret123'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('devotees', ['id' => $admin->id]);
    }

    public function test_the_profile_offers_delete_and_the_footer_links_the_policies(): void
    {
        $this->actingAs($this->user())->get(route('profile', ['tab' => 'preferences']))
            ->assertOk()
            ->assertSee('deleteAccountModal', false)
            ->assertSee(route('privacy'), false)
            ->assertSee(route('terms'), false);
    }
}
