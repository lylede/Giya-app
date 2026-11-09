<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role.'@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => $role, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function church(string $name, array $extra = []): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Parish'], ['created_at' => now()]);

        return Church::create($extra + [
            'name' => $name, 'category_id' => $cat->id, 'location' => 'Carcar', 'municipality' => 'City of Carcar',
            'address' => 'Carcar', 'latitude' => 10.1, 'longitude' => 123.6, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_content_gaps_lists_churches_without_confirmed_mass_times(): void
    {
        $missing = $this->church('No Mass Yet Parish');
        $done = $this->church('Confirmed Parish', ['history' => 'Old.', 'contact_phone' => '032 123']);
        Schedule::create(['church_id' => $done->id, 'event_name' => 'Sunday Mass', 'event_type' => 'Mass',
            'start_time' => '06:00', 'is_recurring' => true, 'recurrence' => 'Every Sunday',
            'recurrence_rule' => ['type' => 'weekly', 'days' => [0]], 'is_estimate' => false, 'created_at' => now()]);

        $admin = $this->person('admin');
        $this->actingAs($admin)->get(route('admin.gaps'))
            ->assertOk()
            ->assertSee('No Mass Yet Parish')
            ->assertDontSee('Confirmed Parish')
            ->assertSee(route('admin.schedules', ['church_id' => $missing->id]), false);

        $this->actingAs($admin)->get(route('admin.gaps', ['gap' => 'history']))
            ->assertSee('No Mass Yet Parish')->assertDontSee('Confirmed Parish');
    }

    public function test_insights_count_questions_by_topic_and_region_without_showing_them(): void
    {
        $user = $this->person('user');
        $s = ChatSession::create(['user_id' => $user->id, 'status' => 'Active', 'started_at' => now()]);
        foreach (['churches in south cebu', 'mass times at simala', 'plan a 5 hour route in north cebu'] as $q) {
            ChatMessage::create(['session_id' => $s->id, 'sender_type' => 'user', 'message' => $q, 'created_at' => now()]);
        }
        ChatMessage::create(['session_id' => $s->id, 'sender_type' => 'assistant', 'message' => 'ok', 'plan_url' => '/plan/create?stops=1', 'created_at' => now()]);

        $this->actingAs($this->person('admin'))->get(route('admin.insights'))
            ->assertOk()
            ->assertSee('Routes and plans')
            ->assertSee('Mass times')
            ->assertSee('South Cebu')
            ->assertDontSee('mass times at simala');
    }

    public function test_devotees_cannot_open_these_pages(): void
    {
        $user = $this->person('user');
        $this->actingAs($user)->get(route('admin.gaps'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.insights'))->assertForbidden();
    }
}
