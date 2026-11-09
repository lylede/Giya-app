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
        return $this->page($this->currentSession());
    }

    /** A past chat, read-only, with "Continue this chat". */
    public function show(int $chat): View
    {
        return $this->page($this->owned($chat));
    }

    /** Make a past chat the open one again; the open one is kept in History. */
    public function resume(int $chat): RedirectResponse
    {
        $past = $this->owned($chat);

        ChatSession::where('user_id', Auth::id())->where('status', 'Active')->whereKeyNot($past->id)->get()
            ->each(fn (ChatSession $s) => $s->messages()->exists() ? $s->end() : $s->delete());

        $past->update(['status' => 'Active', 'ended_at' => null]);

        return redirect()->route('chatbot');
    }

    public function destroy(int $chat): RedirectResponse
    {
        $this->owned($chat)->delete();   // its messages go with it (cascade)

        return redirect()->route('chatbot')->with('success', __('giya.chat.deleted_chat'));
    }

    /** Clear chat history: every chat of this devotee. */
    public function clear(): RedirectResponse
    {
        ChatSession::where('user_id', Auth::id())->delete();

        return redirect()->route('chatbot')->with('success', __('giya.chat.cleared'));
    }

    /** One of the signed-in devotee's chats; anyone else's is "not found". */
    protected function owned(int $id): ChatSession
    {
        return ChatSession::where('user_id', Auth::id())->findOrFail($id);
    }

    protected function page(ChatSession $session): View
    {
        // History: chats that have messages, newest first.
        $past = ChatSession::where('user_id', Auth::id())
            ->whereHas('messages')
            ->withCount('messages')
            ->orderByDesc('started_at')->orderByDesc('id')
            ->take(100)
            ->get();

        return view('chatbot', [
            'session'   => $session,
            'messages'  => $session->messages,
            'viewing'   => $session->status !== 'Active',
            'past'      => $past,
            'online'    => $this->assistant->configured(),
            // Translated here rather than in the view: the same four strings
            // are both the button label and the question that gets asked, so
            // a devotee reading Cebuano should also be asking in Cebuano.
            'starters'  => [
                __('giya.chat.suggest_1'),
                __('giya.chat.suggest_2'),
                __('giya.chat.suggest_3'),
                __('giya.chat.suggest_4'),
            ],
        ]);
    }

    /**
     * The conversation so far, for the pop-up chat.
     *
     * The pop-up is drawn fresh on every page, so without this it started
     * empty after moving to another page or refreshing, although the
     * conversation was saved all along. Read-only: no session is created
     * just by looking.
     */
    public function history(): JsonResponse
    {
        $session = ChatSession::where('user_id', Auth::id())->where('status', 'Active')->first();

        $messages = $session
            ? $session->messages()->orderByDesc('id')->take(30)->get()->reverse()->values()
                ->map(fn (ChatMessage $m) => [
                    'from'     => $m->sender_type === 'user' ? 'user' : 'bot',
                    'text'     => $m->message,
                    'mood'     => $m->mood,
                    'plan_url' => $m->planLink(),
                ])
            : collect();

        return response()->json(['ok' => true, 'messages' => $messages]);
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

        /* Questions in a row: this conversation's questions in the last half
           hour. Every few, Giya says (playfully) that it needs a breather. */
        $streak = $session->messages()
            ->where('sender_type', 'user')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->count();

        $result = $this->assistant->ask($data['message'], $history, $streak);

        /* "Giya is catching its breath" is not an answer. Neither it nor the
           question is kept, so "Ask again" does not leave the question twice
           in the conversation (or in what the model sees next time). */
        if (($result['reason'] ?? null) === 'busy') {
            $question->delete();

            return response()->json([
                'ok'          => false,
                'reason'      => 'busy',
                'mood'        => $result['mood'] ?? 'tired',
                'retry_after' => $result['retry_after'],
                'reply'       => $result['reply'],
            ]);
        }

        $answer = ChatMessage::create([
            'session_id'  => $session->id,
            'sender_type' => 'assistant',
            'message'     => $result['reply'],
            'mood'        => $result['mood'] ?? null,
            'plan_url'    => $result['plan_url'] ?? null,
            'created_at'  => now(),
        ]);

        // Name the conversation after its first question.
        if (! $session->title) {
            $session->update(['title' => str($data['message'])->limit(60)]);
        }

        return response()->json([
            'ok'      => $result['ok'],
            'reason'  => $result['reason'] ?? null,
            'mood'    => $result['mood'] ?? 'happy',
            // A route reply: opens its stops in the planner.
            'plan_url' => $result['plan_url'] ?? null,
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
