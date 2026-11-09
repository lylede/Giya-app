<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Church;
use App\Models\ChurchFollow;
use App\Models\User;
use App\Models\VisitHistory;
use App\Services\RoutePlanner;
use App\Support\CebuRegion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Insights admin page: a picture of how pilgrims use GIYA. All counting is done in PHP, not SQL
 * date functions, so the pages work on Neon (Postgres) and SQLite alike.
 */
class InsightsController extends Controller
{
    /** How pilgrims use GIYA: Giya AI questions, busiest days, follows, sign-ups. */
    public function insights(): View
    {
        $since = now()->subDays(30);

        $questions = ChatMessage::query()
            ->where('sender_type', 'user')
            ->where('created_at', '>=', $since)
            ->latest('id')->take(3000)
            ->get(['message', 'created_at', 'session_id']);

        // Questions per day, last 14 days.
        $days = collect(range(13, 0))->map(fn ($n) => now()->subDays($n)->startOfDay());
        $perDay = $days->map(fn (Carbon $d) => $questions->filter(fn ($q) => $q->created_at->isSameDay($d))->count());

        $visits = VisitHistory::where('visited_at', '>=', now()->subDays(90))->get(['visited_at']);
        $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $follows = ChurchFollow::query()
            ->selectRaw('church_id, count(*) as n')->groupBy('church_id')
            ->orderByDesc('n')->take(8)->get();
        $names = Church::whereIn('id', $follows->pluck('church_id'))->pluck('name', 'id');

        $weeks = collect(range(7, 0))->map(fn ($n) => now()->subWeeks($n)->startOfWeek());
        $signups = User::where('created_at', '>=', $weeks->first())->get(['created_at']);

        return view('admin.insights', [
            'ai' => [
                'questions' => $questions->count(),
                'people'    => ChatMessage::where('chat_messages.sender_type', 'user')->where('chat_messages.created_at', '>=', $since)
                                   ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.session_id')
                                   ->distinct()->count('chat_sessions.user_id'),
                'routes'    => ChatMessage::whereNotNull('plan_url')->where('created_at', '>=', $since)->count(),
            ],
            'perDay'   => ['labels' => $days->map->format('M j')->all(), 'data' => $perDay->all()],
            'topics'   => $this->chart($this->topics($questions)),
            'regions'  => $this->chart($questions
                ->map(fn ($q) => CebuRegion::asked($q->message))->filter()
                ->countBy()->sortDesc()
                ->mapWithKeys(fn ($n, $key) => [ucwords(strtolower(strtok(CebuRegion::label($key), '('))) => $n])),
            'weekdays' => [
                'labels' => $weekdays,
                'data'   => collect(range(0, 6))->map(fn ($d) => $visits->filter(fn ($v) => $v->visited_at?->dayOfWeek === $d)->count())->all(),
            ],
            'follows'  => $this->chart($follows->mapWithKeys(fn ($f) => [($names[$f->church_id] ?? 'Church #'.$f->church_id) => (int) $f->n])),
            'signups'  => [
                'labels' => $weeks->map(fn ($w) => $w->format('M j'))->all(),
                'data'   => $weeks->map(fn (Carbon $w) => $signups->filter(fn ($u) => $u->created_at->between($w, $w->copy()->endOfWeek()))->count())->all(),
            ],
        ]);
    }

    /**
     * What the questions were about. One question can count for one topic:
     * the first that matches, in this order.
     */
    protected function topics(Collection $questions): Collection
    {
        $rules = [
            'Routes and plans'  => fn ($t) => RoutePlanner::wants($t),
            'Mass times'        => fn ($t) => preg_match('/\b(mass|misa|masses|schedule|iskedyul|oras sa misa)\b/i', $t),
            'Festivals'         => fn ($t) => preg_match('/\b(fiesta|festival|feast|pista|sinulog|kapistahan)\b/i', $t),
            'Church history'    => fn ($t) => preg_match('/\b(history|kasaysayan|built|oldest|founded|story)\b/i', $t),
            'Directions'        => fn ($t) => preg_match('/\b(where|how to get|asa|saan|direction|near|duol|malapit)\b/i', $t),
            'Faith and prayers' => fn ($t) => preg_match('/\b(pray|prayer|rosary|novena|saint|santo|confession|kumpisal)\b/i', $t),
        ];

        return $questions->map(function ($q) use ($rules) {
            foreach ($rules as $topic => $match) {
                if ($match($q->message)) {
                    return $topic;
                }
            }

            return 'Other';
        })->countBy()->sortDesc();
    }

    /** ['A' => 3, 'B' => 1] -> what the bar chart takes. */
    protected function chart(Collection $counts): array
    {
        return ['labels' => $counts->keys()->all(), 'data' => $counts->values()->all()];
    }
}
