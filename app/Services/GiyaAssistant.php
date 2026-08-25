<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Giya AI - a pilgrimage assistant grounded in this database.
 *
 * The model is given the actual destinations, their categories, hours and mass
 * schedules on every request, and told to answer only from them. That is the
 * difference between an assistant and a plausible-sounding liar: asked about a
 * church GIYA does not have, it says so rather than inventing one.
 */
class GiyaAssistant
{
    protected string $endpoint = 'https://api.groq.com/openai/v1/chat/completions';

    public function configured(): bool
    {
        return filled(config('services.groq.key'));
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
                'reply'  => $this->offlineAnswer($question),
            ];
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            array_slice($history, -8),               // keep the request small
            [['role' => 'user', 'content' => $question]]
        );

        try {
            $response = Http::withToken(config('services.groq.key'))
                ->timeout(25)
                ->post($this->endpoint, [
                    'model'       => config('services.groq.model'),
                    'messages'    => $messages,
                    'temperature' => 0.3,            // low: this is a factual assistant
                    'max_tokens'  => 700,
                ]);

            if (! $response->successful()) {
                Log::warning('Groq returned '.$response->status().': '.$response->body());

                return [
                    'ok'     => false,
                    'reason' => $response->status() === 401 ? 'bad_key' : 'upstream',
                    'reply'  => $this->offlineAnswer($question),
                ];
            }

            $reply = trim((string) $response->json('choices.0.message.content'));

            return $reply === ''
                ? ['ok' => false, 'reason' => 'empty', 'reply' => $this->offlineAnswer($question)]
                : ['ok' => true, 'reply' => $reply];
        } catch (\Throwable $e) {
            Log::warning('Groq call failed: '.$e->getMessage());

            return [
                'ok'     => false,
                'reason' => 'offline',
                'reply'  => $this->offlineAnswer($question),
            ];
        }
    }

    /* ------------------------------------------------------------------ */

    /** The destination list, refreshed whenever a church changes. */
    protected function knowledge(): string
    {
        return Cache::remember('giya:assistant:knowledge', now()->addHours(6), function () {
            $lines = Church::with('churchCategory', 'schedules')
                ->active()
                ->orderBy('name')
                ->get()
                ->map(function (Church $c) {
                    $bits = [
                        $c->name,
                        'category: '.$c->category,
                        'location: '.($c->location ?: 'Metro Cebu'),
                    ];

                    if ($c->opening_time && $c->closing_time) {
                        $bits[] = 'open '.$c->hours_label;
                    }

                    $masses = $c->schedules
                        ->where('event_type', 'Mass')
                        ->take(6)
                        ->map(fn (Schedule $s) => trim(
                            ($s->recurrence ?: optional($s->schedule_date)->format('M j'))
                            .' '.substr((string) $s->start_time, 0, 5)
                        ))
                        ->filter()
                        ->implode('; ');

                    if ($masses) {
                        $bits[] = 'masses: '.$masses;
                    }

                    if ($c->description) {
                        $bits[] = 'about: '.str($c->description)->limit(200);
                    }

                    return '- '.implode(' | ', $bits);
                })
                ->implode("\n");

            return $lines ?: '- (no destinations recorded yet)';
        });
    }

    protected function systemPrompt(): string
    {
        return <<<PROMPT
        You are Giya AI, a pilgrimage assistant for GIYA - a travel companion for
        religious tourism in Metro Cebu, Philippines.

        Answer ONLY from the destination list below. It is the complete set of
        destinations this app knows about.

        Rules:
        - If a church is not in the list, say GIYA does not have it yet. Never
          invent a church, a mass time, or an address.
        - Keep answers short. Two or three sentences, or a short list.
        - Suggest specific destinations by name when it helps.
        - For a Visita Iglesia, propose seven churches from the list, ordered so
          the route is sensible.
        - You may greet in Cebuano ("Maayong buntag", "Salamat") but answer in
          English unless asked otherwise.
        - You cannot book, pay, or change anything. Point the devotee to the Map
          or Plan Hub for those.
        - Never give mass times unless they appear in the list. Tell the devotee
          to confirm with the parish, since schedules change.

        DESTINATIONS:
        {$this->knowledge()}
        PROMPT;
    }

    /**
     * Answer from the database when Groq is unavailable.
     *
     * Not a stub: it matches the question against real destinations, so a
     * missing key or a dropped connection degrades to something useful rather
     * than an error message.
     */
    protected function offlineAnswer(string $question): string
    {
        $q = mb_strtolower($question);

        $matches = Church::with('churchCategory')->active()->get()
            ->filter(fn (Church $c) => str_contains($q, mb_strtolower(explode(' ', $c->name)[0]))
                || str_contains(mb_strtolower($c->name), $q))
            ->take(3);

        if ($matches->isNotEmpty()) {
            return $matches->map(fn (Church $c) => "**{$c->name}** - {$c->category} in {$c->location}. "
                .($c->opening_time ? "Open {$c->hours_label}." : ''))
                ->implode("\n\n")
                ."\n\n_The AI assistant is offline, so this comes straight from the destination records._";
        }

        foreach (['visita', 'seven', 'route', 'plan'] as $word) {
            if (str_contains($q, $word)) {
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

        return "I cannot reach the AI service right now, so I can only answer from the "
            ."destination records. GIYA currently has {$count} destinations across Metro Cebu - "
            ."try asking about one by name, or browse them on the Map."; 
    }
}
