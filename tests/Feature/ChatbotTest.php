<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_shows_faq_prompts_for_a_new_conversation(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('chatbot'));

        $response->assertOk()
            ->assertSee('id="chatStarters"', false)
            ->assertSee('What churches can I visit in Cebu?')
            ->assertSee('How do I plan a pilgrimage day?')
            ->assertDontSee('When is mass at the Cathedral?');
    }

    public function test_chatbot_keeps_faq_prompts_after_a_conversation_has_started(): void
    {
        $user = $this->createUser();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'status' => 'Active',
            'started_at' => now(),
            'created_at' => now(),
        ]);
        ChatMessage::create([
            'session_id' => $session->id,
            'sender_type' => 'user',
            'message' => 'Tell me about the Cathedral.',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('chatbot'));

        $response->assertOk()
            ->assertSee('Tell me about the Cathedral.')
            ->assertSee('id="chatStarters"', false)
            ->assertSee('What should I know before visiting a church?');
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Chat User',
            'email' => 'chat-user@example.com',
            'password_hash' => 'hashed-password',
        ]);
    }
}