<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\GiyaAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ChatbotController extends Controller
{
    public function __construct(protected GiyaAssistant $assistant)
    {
    }

    public function index(): View
    {
        $session = $this->currentSession();

        return view('chatbot', [
            'session'   => $session,
            'messages'  => $session->messages,
            'online'    => $this->assistant->configured(),
            'starters'  => [
                'What churches can I visit in Cebu?',
                'Plan a Visita Iglesia for me',
                'How do I plan a pilgrimage day?',
                'What should I know before visiting a church?',
            ],
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        // A free Groq tier is a shared resource; keep one devotee from draining it.
        $key = 'giya-chat:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json([
                'ok'    => false,
                'reply' => 'That is a lot of questions in a short time. Try again in a minute.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $session = $this->currentSession();

        $question = ChatMessage::create([
            'session_id'  => $session->id,
            'sender_type' => 'user',
            'message'     => $data['message'],
            'created_at'  => now(),
        ]);

        // Prior turns give the model context for follow-up questions.
        $history = $session->messages()
            ->where('id', '<', $question->id)
            ->orderByDesc('id')
            ->take(8)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $m) => [
                'role'    => $m->sender_type === 'user' ? 'user' : 'assistant',
                'content' => $m->message,
            ])
            ->values()
            ->all();

        $result = $this->assistant->ask($data['message'], $history);

        $answer = ChatMessage::create([
            'session_id'  => $session->id,
            'sender_type' => 'assistant',
            'message'     => $result['reply'],
            'created_at'  => now(),
        ]);

        // Name the conversation after its first question.
        if (! $session->title) {
            $session->update(['title' => str($data['message'])->limit(60)]);
        }

        return response()->json([
            'ok'      => $result['ok'],
            'reason'  => $result['reason'] ?? null,
            'reply'   => $answer->message,
            'when'    => $answer->created_at->format('g:i A'),
        ]);
    }

    public function reset(): RedirectResponse
    {
        $this->currentSession()->end();

        return redirect()->route('chatbot')->with('success', 'Started a new conversation.');
    }

    /** The devotee's open conversation, or a fresh one. */
    protected function currentSession(): ChatSession
    {
        return ChatSession::firstOrCreate(
            ['user_id' => Auth::id(), 'status' => 'Active'],
            ['started_at' => now(), 'created_at' => now()]
        );
    }
}
