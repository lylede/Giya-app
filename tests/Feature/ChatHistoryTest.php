<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = $this->person('me@example.com');
    }

    private function person(string $email): User
    {
        return User::create([
            'name' => 'Pilgrim', 'email' => $email, 'password_hash' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function chat(User $user, string $title, string $status = 'Ended', array $extra = []): ChatSession
    {
        $s = ChatSession::create(['user_id' => $user->id, 'title' => $title, 'status' => $status, 'started_at' => now()]);
        ChatMessage::create(['session_id' => $s->id, 'sender_type' => 'user', 'message' => $title, 'created_at' => now()]);
        ChatMessage::create(['session_id' => $s->id, 'sender_type' => 'assistant', 'message' => 'Answer to '.$title, 'created_at' => now()] + $extra);

        return $s;
    }

    public function test_the_chat_page_lists_past_chats(): void
    {
        $this->chat($this->me, 'Churches in south Cebu');
        $this->chat($this->person('other@example.com'), 'Someone else');

        $this->actingAs($this->me)->get(route('chatbot'))
            ->assertOk()
            ->assertSee('chatHistoryModal', false)
            ->assertSee('Churches in south Cebu')
            ->assertDontSee('Someone else');
    }

    public function test_a_past_chat_opens_read_only_with_continue(): void
    {
        $past = $this->chat($this->me, 'Simala visit');

        $this->actingAs($this->me)->get(route('chatbot.show', $past->id))
            ->assertOk()
            ->assertSee('Answer to Simala visit')
            ->assertSee(route('chatbot.resume', $past->id), false)
            ->assertSee('id="chatForm" autocomplete="off"  hidden', false);
    }

    public function test_someone_elses_chat_is_not_found(): void
    {
        $theirs = $this->chat($this->person('other@example.com'), 'Private');

        $this->actingAs($this->me)->get(route('chatbot.show', $theirs->id))->assertNotFound();
        $this->actingAs($this->me)->delete(route('chatbot.destroy', $theirs->id))->assertNotFound();
        $this->assertDatabaseHas('chat_sessions', ['id' => $theirs->id]);
    }

    public function test_continuing_a_chat_makes_it_the_open_one_and_keeps_the_other(): void
    {
        $old = $this->chat($this->me, 'Old chat');
        $current = $this->chat($this->me, 'Current chat', 'Active');

        $this->actingAs($this->me)->post(route('chatbot.resume', $old->id))->assertRedirect(route('chatbot'));

        $this->assertSame('Active', $old->fresh()->status);
        $this->assertSame('Ended', $current->fresh()->status);
    }

    public function test_an_empty_open_chat_is_dropped_when_continuing_another(): void
    {
        $old = $this->chat($this->me, 'Old chat');
        $empty = ChatSession::create(['user_id' => $this->me->id, 'status' => 'Active', 'started_at' => now()]);

        $this->actingAs($this->me)->post(route('chatbot.resume', $old->id));

        $this->assertDatabaseMissing('chat_sessions', ['id' => $empty->id]);
    }

    public function test_one_chat_or_all_chats_can_be_deleted(): void
    {
        $a = $this->chat($this->me, 'A');
        $b = $this->chat($this->me, 'B');
        $theirs = $this->chat($this->person('other@example.com'), 'Theirs');

        $this->actingAs($this->me)->delete(route('chatbot.destroy', $a->id))->assertRedirect(route('chatbot'));
        $this->assertDatabaseMissing('chat_sessions', ['id' => $a->id]);
        $this->assertDatabaseMissing('chat_messages', ['session_id' => $a->id]);

        $this->actingAs($this->me)->delete(route('chatbot.clear'));
        $this->assertDatabaseMissing('chat_sessions', ['id' => $b->id]);
        $this->assertDatabaseHas('chat_sessions', ['id' => $theirs->id]);
    }

    public function test_a_saved_route_keeps_its_planner_button_and_mood(): void
    {
        $this->chat($this->me, 'Plan a route', 'Active', ['mood' => 'excited', 'plan_url' => '/plan/create?stops=1,2']);

        $this->actingAs($this->me)->get(route('chatbot'))
            ->assertSee('data-mood="excited"', false)
            ->assertSee('href="/plan/create?stops=1,2"', false);

        $this->actingAs($this->me)->getJson(route('chatbot.history'))
            ->assertJsonPath('messages.1.mood', 'excited')
            ->assertJsonPath('messages.1.plan_url', '/plan/create?stops=1,2');
    }

    public function test_a_planner_link_to_another_site_is_never_shown(): void
    {
        $this->chat($this->me, 'Odd', 'Active', ['plan_url' => '//evil.example/x']);

        $this->actingAs($this->me)->get(route('chatbot'))->assertDontSee('evil.example');
    }
}
