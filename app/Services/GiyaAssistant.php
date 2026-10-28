<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\Text;

/**
 * Giya AI - a pilgrimage assistant grounded in this database.
 *
 * Every request carries GIYA's own records: each destination's category,
 * address, hours, description, history and schedules. The model answers from
 * those first. That is the difference between an assistant and a
 * plausible-sounding liar: asked for a mass time GIYA does not have, it says
 * so rather than inventing one.
 *
 * What it knows about each church comes in two sizes:
 *
 *   - a SHORT entry for every active church (where, what, hours, the start of
 *     its history), so it can compare and recommend across all of them;
 *   - a FULL entry (whole history, every schedule) for the churches the
 *     devotee is asking about - named in the question or in the last few
 *     turns, so "when was it built?" after "tell me about Simala" still works.
 *
 * Sending every church in full on every question would be simpler, but Groq's
 * free tier limits tokens per minute, and with a few dozen imported churches
 * one question would use up the minute.
 */
class GiyaAssistant
{
    protected string $endpoint = 'https://api.groq.com/openai/v1/chat/completions';

    /** Cache key for the per-church entries. Cleared whenever a church or schedule changes. */
    public const CACHE_KEY = 'giya:assistant:knowledge:v2';

    /** How much of a history the short entry carries. */
    protected const HISTORY_PREVIEW = 220;

    /** At most this many churches get a full entry in one request. */
    protected const MAX_FULL = 4;

    /** Words too common in church names to say which church someone means. */
    protected const COMMON_WORDS = [
        'church', 'parish', 'chapel', 'shrine', 'basilica', 'cathedral', 'minore', 'national',
        'archdiocesan', 'metropolitan', 'monastery', 'our', 'lady', 'the', 'of', 'and', 'san',
        'santa', 'santo', 'sto', 'del', 'de', 'la', 'las', 'los', 'city', 'cebu', 'saint',
        'holy', 'sacred', 'heart', 'mother', 'blvd', 'street', 'poblacion', 'road',
    ];

    public function configured(): bool
    {
        return filled(config('services.groq.key'));
    }

    /** Make the next question read fresh records. Called when a church or schedule changes. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{ok: bool, reply: string, reason?: string}
     */
    public function ask(string $question, array $history = []): array
    {
        if (! $this->configured()) {
            return [
                'ok'     => false,
                'reason' => 'no_key',
                'reply'  => Text::withoutLongDashes($this->offlineAnswer($question)),
            ];
        }

        $history = $this->trimHistory($history);

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($question, $history)]],
            $history,
            [['role' => 'user', 'content' => $question]]
        );

        /* Groq's free plan allows about 8,000 tokens a minute PER MODEL, and
           one question here is 2,000-4,000 with the church records. Two or
           three quick questions used to hit the limit and the devotee was
           told the AI was unreachable. Now a busy main model hands the
           question to a second model, which has its own allowance. */
        $models = array_values(array_unique(array_filter([
            (string) config('services.groq.model'),
            (string) config('services.groq.fallback_model'),
        ])));

        $result = ['ok' => false, 'reason' => 'upstream'];

        foreach ($models as $i => $model) {
            $result = $this->call($model, $messages);

            // Only "busy" and "ran out of room" are worth a second model.
            // A bad key or a network failure would fail there just the same.
            if ($result['ok'] || ! in_array($result['reason'], ['rate_limited', 'empty'], true)) {
                break;
            }

            if (isset($models[$i + 1])) {
                Log::info("Groq model \"{$model}\" was {$result['reason']}; retrying on \"{$models[$i + 1]}\".");
            }
        }

        if ($result['ok']) {
            // House style: no long dashes, even if the model slips.
            return ['ok' => true, 'reply' => Text::withoutLongDashes($result['reply'])];
        }

        return [
            'ok'     => false,
            'reason' => $result['reason'],
            'reply'  => Text::withoutLongDashes($this->offlineAnswer($question, $result['reason'])),
        ];
    }

    /**
     * One request to one model.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{ok: bool, reply?: string, reason?: string}
     */
    protected function call(string $model, array $messages): array
    {
        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.3,            // low: this is a factual assistant
            // Enough for a two-paragraph history answer. Kept modest because
            // Groq counts it against the per-minute allowance.
            'max_tokens'  => 900,
        ];

        // gpt-oss thinks before answering; "low" keeps that short so the
        // budget above goes to the answer and the reply comes back quickly.
        if (str_starts_with($model, 'openai/gpt-oss')) {
            $payload['reasoning_effort'] = 'low';
        }

        try {
            $response = Http::withToken(config('services.groq.key'))
                ->timeout(30)
                ->post($this->endpoint, $payload);

            if (! $response->successful()) {
                $code = $response->json('error.code');

                /* A retired model was the cause of "the AI is offline" in
                   October 2026: Groq shut llama-3.3-70b-versatile down and
                   every question failed with a 404 nobody saw. Said plainly in
                   the log so the next one takes a minute to find. */
                if (in_array($code, ['model_not_found', 'model_decommissioned'], true)) {
                    Log::warning("Groq model \"{$model}\" is not available (retired or misspelled). "
                        .'Set GROQ_MODEL in .env to a current model - see https://console.groq.com/docs/models');
                } else {
                    Log::warning("Groq ({$model}) returned ".$response->status().': '.mb_substr($response->body(), 0, 300));
                }

                return [
                    'ok'     => false,
                    'reason' => match (true) {
                        $response->status() === 401 => 'bad_key',
                        $response->status() === 429 => 'rate_limited',
                        in_array($code, ['model_not_found', 'model_decommissioned'], true) => 'bad_model',
                        default => 'upstream',
                    },
                ];
            }

            $reply = trim((string) $response->json('choices.0.message.content'));

            return $reply === ''
                ? ['ok' => false, 'reason' => 'empty']
                : ['ok' => true, 'reply' => $reply];
        } catch (\Throwable $e) {
            Log::warning("Groq ({$model}) call failed: ".$e->getMessage());

            return ['ok' => false, 'reason' => 'offline'];
        }
    }

    /**
     * The last few turns, each cut short. Earlier answers can be long (a
     * church history), and resending them in full on every question was the
     * biggest share of each request.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    protected function trimHistory(array $history): array
    {
        return array_map(fn (array $m) => [
            'role'    => $m['role'],
            'content' => Str::limit((string) $m['content'], 600),
        ], array_values(array_slice($history, -6)));
    }

    /* ------------------------------------------------------------------ */

    /**
     * Every active church, each with a short and a full write-up.
     *
     * @return Collection<int, array{id: int, name: string, category: string, keywords: string[], short: string, full: string, history: string, line: string}>
     */
    protected function entries(): Collection
    {
        $rows = Cache::remember(self::CACHE_KEY, now()->addHours(6), function () {
            return Church::with('churchCategory', 'schedules')
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn (Church $c) => [
                    'id'       => $c->id,
                    'name'     => $c->name,
                    'category' => Str::lower((string) $c->category),
                    'keywords' => $this->keywordsFor($c),
                    'short'    => $this->shortEntry($c),
                    'full'     => $this->fullEntry($c),
                    'history'  => (string) $c->history,
                    'line'     => "**{$c->name}** - {$c->category} in ".($c->address ?: $c->location ?: 'Metro Cebu').'.'
                        .($c->opening_time && $c->closing_time ? " Open {$c->hours_label}." : ''),
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /** One line per church: enough to compare and recommend. */
    protected function shortEntry(Church $c): string
    {
        $bits = [
            $c->name,
            'category: '.$c->category,
            'location: '.($c->address ?: $c->location ?: 'Metro Cebu'),
        ];

        if ($c->opening_time && $c->closing_time) {
            $bits[] = 'open '.$c->hours_label;
        }

        $masses = $c->schedules
            ->where('event_type', 'Mass')
            ->take(4)
            ->map(fn (Schedule $s) => $this->when($s))
            ->filter()
            ->implode('; ');

        if ($masses) {
            $bits[] = 'masses: '.$masses;
        }

        if ($c->description) {
            $bits[] = 'about: '.Str::limit($c->description, 160);
        }

        $bits[] = $c->history
            ? 'history (start): '.Str::limit(str_replace("\n", ' ', $c->history), self::HISTORY_PREVIEW)
            : 'history: not recorded in GIYA';

        return '- '.implode(' | ', $bits);
    }

    /** Everything GIYA has on one church. */
    protected function fullEntry(Church $c): string
    {
        $lines = [
            "### {$c->name}",
            'Category: '.$c->category,
            'Town / city: '.($c->municipality ?: $c->location ?: 'Metro Cebu'),
            'Address: '.($c->address ?: $c->location ?: 'not recorded'),
            'Hours: '.($c->opening_time && $c->closing_time ? $c->hours_label : 'not recorded'),
        ];

        if ($c->rating > 0) {
            $lines[] = 'Average devotee rating: '.number_format($c->rating, 1).' of 5';
        }

        $lines[] = 'About: '.($c->description ?: 'not recorded');
        $lines[] = 'History: '.($c->history ? "\n".$c->history : 'not recorded in GIYA');

        $schedules = $c->schedules
            ->sortBy(fn (Schedule $s) => [$s->event_type, (string) $s->start_time])
            ->map(fn (Schedule $s) => '  - '.trim(
                $s->event_type.': '.$s->event_name.' - '.$this->when($s)
                .($s->notes ? ' ('.Str::limit($s->notes, 120).')' : '')
            ))
            ->implode("\n");

        $lines[] = 'Schedules:'.($schedules ? "\n".$schedules : ' none recorded');

        return implode("\n", $lines);
    }

    /** "Sundays 06:00" / "Jan 18 09:00" - whichever the schedule has. */
    protected function when(Schedule $s): string
    {
        return trim(
            ($s->recurrence ?: optional($s->schedule_date)->format('M j, Y'))
            .' '.substr((string) $s->start_time, 0, 5)
        );
    }

    /**
     * Words in a church's name or town that pick it out from the others:
     * "simala", "calungsod", "nino", "guadalupe", "lapu".
     *
     * @return string[]
     */
    protected function keywordsFor(Church $c): array
    {
        $text = Str::lower(Str::ascii($c->name.' '.$c->municipality.' '.$c->location));

        return collect(preg_split('/[^a-z0-9]+/', $text))
            ->filter(fn ($w) => strlen($w) >= 4 && ! in_array($w, self::COMMON_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The churches this conversation is about, best match first.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    protected function relevant(string $question, array $history): Collection
    {
        // The question counts double: a follow-up should not be dragged back
        // to an older church just because it was named more often.
        $recent = collect($history)->where('role', 'user')->take(-3)->pluck('content')->implode(' ');
        $now    = $this->normalize($question);
        $before = $this->normalize($recent);

        $entries = $this->entries();

        // "the basilica", "the cathedral": a category word names a church
        // when only one church has that category.
        $uniqueCategories = $entries->countBy('category')->filter(fn ($n) => $n === 1)->keys()->all();

        return $entries
            ->map(function (array $e) use ($now, $before, $uniqueCategories) {
                $score = 0;
                $name  = $this->normalize($e['name']);

                if (in_array($e['category'], $uniqueCategories, true)
                    && preg_match('/\b'.preg_quote($e['category'], '/').'\b/', $now)) {
                    $score += 2;
                }

                if ($name !== '' && str_contains($now, $name)) {
                    $score += 10;
                }

                foreach ($e['keywords'] as $word) {
                    $score += preg_match('/\b'.preg_quote($word, '/').'\b/', $now) ? 3 : 0;
                    $score += preg_match('/\b'.preg_quote($word, '/').'\b/', $before) ? 1 : 0;
                }

                return $e + ['score' => $score];
            })
            ->filter(fn ($e) => $e['score'] > 0)
            ->sortByDesc('score')
            ->take(self::MAX_FULL)
            ->values();
    }

    protected function normalize(string $text): string
    {
        return ' '.trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text)))).' ';
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    protected function systemPrompt(string $question, array $history = []): string
    {
        $entries = $this->entries();
        $focus   = $this->relevant($question, $history);

        $list = $entries->isEmpty()
            ? '- (no destinations recorded yet)'
            : $entries->pluck('short')->implode("\n");

        $details = $focus->isEmpty()
            ? '(The devotee has not named a specific destination. Use the list above.)'
            : $focus->pluck('full')->implode("\n\n");

        return <<<PROMPT
        You are Giya AI, a warm and knowledgeable pilgrimage guide for GIYA, a
        travel companion for religious tourism in Cebu, Philippines. Talk like a
        friendly local guide who knows Cebu's churches, their history and the
        Catholic faith well.

        WHAT YOU CAN TALK ABOUT - answer all of these fully and helpfully:
        - Any church, shrine, chapel or pilgrimage site in Cebu (and the wider
          Philippines when asked): history, architecture, patron saints, why it
          matters, what to see, what pilgrims usually do there.
        - Recommendations and opinions, e.g. "the most beautiful church", "the
          oldest", "best for a quiet visit", "good for families". Give two to
          four strong picks with a short reason each (architecture, history,
          setting, devotion). Lead with GIYA destinations when they fit, and
          you may also name well-known places GIYA does not list yet, such as
          the old coral-stone churches of southern Cebu.
        - Pilgrimage traditions: Visita Iglesia, Sinulog and the Santo Nino,
          Simbang Gabi, fiestas, novenas, processions, Holy Week customs.
        - Practical faith questions: what to wear, church etiquette, how to
          light candles, how to join a Mass, what a novena is.
        - Planning: routes, how many churches fit in a day, combining places.

        SOURCES, in order of trust:
        1. GIYA's records below. Prefer them over anything else you know.
        2. Your own general knowledge, for everything the records do not cover.
           Use it freely for history, architecture, traditions and
           recommendations. If you are not sure of a date, name or fact, say so
           rather than guessing.

        STRICT RULES:
        - Mass times, opening hours, addresses and event dates: ONLY from the
          records. Never invent them, even for famous churches. If they are
          not in the records, say GIYA does not have that schedule yet and
          suggest checking with the parish. Schedules can change, so remind
          the devotee to confirm.
        - When you mention a church that is not in GIYA's list, say once,
          briefly, that it is not on GIYA's map yet.
        - If a question has nothing to do with churches, faith, pilgrimage or
          visiting Cebu, answer in one friendly sentence and steer back to how
          you can help with their pilgrimage.
        - Length: two to four sentences for simple questions; up to two short
          paragraphs or a short list for history, recommendations or plans.
        - For a Visita Iglesia, propose seven churches from GIYA's list,
          ordered so the route is sensible.
        - Reply in the language the devotee writes in: English, Cebuano or
          Filipino.
        - Plain text only. You may use **bold** for church names; no headings,
          tables or other markdown - the chat window shows them as symbols.
        - Never use em dashes or en dashes. Use a comma, a colon or a plain
          hyphen instead.
        - You cannot book, pay, or change anything. Point the devotee to the Map
          or Plan Hub for those.

        ALL DESTINATIONS (short):
        {$list}

        FULL RECORDS for the destinations this conversation is about:
        {$details}
        PROMPT;
    }

    /**
     * Answer from the database when Groq is unavailable.
     *
     * Not a stub: it matches the question against real destinations, so a
     * missing key or a dropped connection degrades to something useful rather
     * than an error message.
     */
    protected function offlineAnswer(string $question, string $reason = 'offline'): string
    {
        /* Busy is not broken. Telling a devotee the AI is unreachable when it
           will answer again in a few seconds makes them give up on it. */
        $busy = $reason === 'rate_limited';
        $note = $busy
            ? '_Giya AI is answering a lot of questions right now, so this comes straight from the destination records. Ask again in a few seconds for a full answer._'
            : '_The AI assistant is offline, so this comes straight from the destination records._';

        $q = $this->normalize($question);
        $matches = $this->relevant($question, [])->take(3);

        if ($matches->isNotEmpty()) {
            $wantsHistory = (bool) preg_match('/\b(history|kasaysayan|built|founded|old|oldest|when|origin|story)\b/', $q);

            return $matches->map(function (array $e) use ($wantsHistory, $matches) {
                $text = $e['line'];

                if ($e['history'] !== '') {
                    $text .= "\n\n".($wantsHistory || $matches->count() === 1
                        ? $e['history']
                        : Str::limit(str_replace("\n", ' ', $e['history']), 220));
                }

                return $text;
            })->implode("\n\n")
                ."\n\n".$note;
        }

        foreach (['visita', 'seven', 'route', 'plan'] as $word) {
            if (str_contains($q, " {$word} ")) {
                $seven = Church::active()->featured()->take(7)->pluck('name');
                if ($seven->count() < 7) {
                    $seven = Church::active()->take(7)->pluck('name');
                }

                return "A Visita Iglesia route from GIYA's destinations:\n\n"
                    .$seven->map(fn ($n, $i) => ($i + 1).'. '.$n)->implode("\n")
                    ."\n\nOpen the Plan Hub to build this into an itinerary."
                    ."\n\n_The AI assistant is offline; this list comes from the destination records._";
            }
        }

        $count = Church::active()->count();

        if ($busy) {
            return 'Giya AI is answering a lot of questions right now. Please ask again in a few seconds - '
                .'or ask about a church by name and I can answer from the destination records.';
        }

        return 'I cannot reach the AI service right now, so I can only answer from the '
            ."destination records. GIYA currently has {$count} destinations across Metro Cebu - "
            .'try asking about one by name, or browse them on the Map.';
    }
}
