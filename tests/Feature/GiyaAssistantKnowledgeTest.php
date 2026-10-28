<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Schedule;
use App\Models\User;
use App\Services\GiyaAssistant;
use Database\Seeders\ChurchHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Giya AI answers about every church and its history.
 *
 * The model itself cannot run in a test, so these check what it is GIVEN:
 * the request GIYA sends to Groq. If the history is not in the request, the
 * model cannot answer from it.
 */
class GiyaAssistantKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private function church(string $name, string $category, array $extra = []): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => $category], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create(array_merge([
            'name' => $name, 'category_id' => $cat->id, 'location' => 'Cebu City',
            'latitude' => 10.29, 'longitude' => 123.90, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], $extra));
    }

    /** Fake Groq, ask, and return the system prompt GIYA sent. */
    private function promptFor(string $question, array $history = []): string
    {
        config(['services.groq.key' => 'gsk-test', 'services.groq.model' => 'openai/gpt-oss-120b']);

        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'An answer.']]],
        ])]);

        $result = app(GiyaAssistant::class)->ask($question, $history);
        $this->assertTrue($result['ok']);

        $sent = null;
        Http::assertSent(function (Request $request) use (&$sent) {
            $sent = $request->data();

            return true;
        });

        return $sent['messages'][0]['content'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->church('Simala Shrine', 'Shrine', [
            'location' => 'Lindogon, Sibonga, Cebu',
            'description' => 'A castle-like shrine in Sibonga.',
            'history' => "Established in 1998 by the Marian Monks of Eucharistic Adoration.\n\nThe image is reported to have wept in 1998.",
        ]);

        $basilica = $this->church('Basilica del Santo Niño', 'Basilica', [
            'history' => 'Founded on April 28, 1565 by Andrés de Urdaneta. '.str_repeat('Long history text. ', 40).'ENDMARKER',
        ]);

        Schedule::create([
            'church_id' => $basilica->id, 'event_name' => 'Sunday Mass', 'event_type' => 'Mass',
            'start_time' => '06:00', 'recurrence' => 'Sundays', 'is_recurring' => true, 'created_at' => now(),
        ]);
        Schedule::create([
            'church_id' => $basilica->id, 'event_name' => 'Fiesta Señor Santo Niño', 'event_type' => 'Feast Day',
            'schedule_date' => '2027-01-17', 'start_time' => '09:00', 'created_at' => now(),
        ]);

        $this->church('Redemptorist Church', 'Church');
    }

    public function test_a_named_church_is_sent_with_its_whole_history_and_all_schedules(): void
    {
        $prompt = $this->promptFor('What is the history of the Santo Niño basilica?');

        $this->assertStringContainsString('ENDMARKER', $prompt, 'The full history was not sent.');
        $this->assertStringContainsString('Feast Day: Fiesta Señor Santo Niño', $prompt);
        $this->assertStringContainsString('Sunday Mass', $prompt);
    }

    public function test_every_church_is_listed_but_unnamed_ones_only_in_short(): void
    {
        $prompt = $this->promptFor('Tell me about Simala');

        $this->assertStringContainsString('The image is reported to have wept in 1998', $prompt);
        $this->assertStringContainsString('Basilica del Santo Niño', $prompt);
        $this->assertStringContainsString('Founded on April 28, 1565', $prompt, 'The short entry should start the history.');
        $this->assertStringNotContainsString('ENDMARKER', $prompt, 'An unnamed church was sent in full.');
    }

    public function test_a_follow_up_question_keeps_the_church_from_the_previous_turn(): void
    {
        $prompt = $this->promptFor('When was it built?', [
            ['role' => 'user', 'content' => 'Tell me about the Basilica del Santo Niño'],
            ['role' => 'assistant', 'content' => 'It is the oldest church in the Philippines.'],
        ]);

        $this->assertStringContainsString('ENDMARKER', $prompt);
    }

    public function test_a_church_without_history_says_so_and_the_rules_allow_general_background(): void
    {
        $prompt = $this->promptFor('Tell me about the Redemptorist Church');

        $this->assertStringContainsString('History: not recorded in GIYA', $prompt);
        $this->assertStringContainsString('general background, not from GIYA', $prompt);
    }

    public function test_gpt_oss_is_asked_to_think_briefly(): void
    {
        $this->promptFor('Hello');

        Http::assertSent(fn (Request $r) => $r['model'] === 'openai/gpt-oss-120b'
            && $r['reasoning_effort'] === 'low'
            && $r['max_tokens'] >= 800 && $r['max_tokens'] <= 1000);
    }

    /** Free plan: ~8,000 tokens a minute per model. A busy main model must not mean "offline". */
    public function test_a_busy_main_model_hands_the_question_to_the_fallback(): void
    {
        config(['services.groq.key' => 'gsk-test', 'services.groq.model' => 'openai/gpt-oss-120b',
                'services.groq.fallback_model' => 'openai/gpt-oss-20b']);

        Http::fake(['api.groq.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'Rate limit reached', 'code' => 'rate_limit_exceeded']], 429)
            ->push(['choices' => [['message' => ['content' => 'Simala is in Sibonga.']]]])]);

        $result = app(GiyaAssistant::class)->ask('Where is Simala?');

        $this->assertTrue($result['ok']);
        $this->assertSame('Simala is in Sibonga.', $result['reply']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['model'] === 'openai/gpt-oss-20b');
    }

    public function test_when_both_models_are_busy_the_devotee_is_told_it_is_busy_not_offline(): void
    {
        config(['services.groq.key' => 'gsk-test', 'services.groq.fallback_model' => 'openai/gpt-oss-20b']);
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429)]);

        $result = app(GiyaAssistant::class)->ask('who are you');

        $this->assertFalse($result['ok']);
        $this->assertSame('rate_limited', $result['reason']);
        $this->assertStringContainsString('a lot of questions', $result['reply']);
        $this->assertStringNotContainsString('cannot reach', $result['reply']);
    }

    public function test_a_bad_key_is_not_retried_on_another_model(): void
    {
        config(['services.groq.key' => 'gsk-test', 'services.groq.fallback_model' => 'openai/gpt-oss-20b']);
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['code' => 'invalid_api_key']], 401)]);

        $this->assertSame('bad_key', app(GiyaAssistant::class)->ask('hello')['reason']);
        Http::assertSentCount(1);
    }

    public function test_only_the_last_few_turns_are_resent_and_each_is_cut_short(): void
    {
        $history = [];
        for ($i = 0; $i < 10; $i++) {
            $history[] = ['role' => 'user', 'content' => "question {$i}"];
            $history[] = ['role' => 'assistant', 'content' => str_repeat('long answer ', 200)];
        }

        $this->promptFor('Tell me about Simala', $history);

        Http::assertSent(function (Request $r) {
            $turns = array_slice($r['messages'], 1, -1);   // minus system and the new question

            return count($turns) === 6
                && max(array_map(fn ($m) => mb_strlen($m['content']), $turns)) <= 603;
        });
    }

    public function test_long_dashes_are_cleaned_out_of_ai_replies(): void
    {
        config(['services.groq.key' => 'gsk-test']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' =>
            "**San Guillermo Parish (Catmon Church)** \u{2013} Catmon, northern Cebu.\n\u{2014} Masses 9\u{2013}11 AM\nA shrine\u{2014}built in 1998."]]]])]);

        $reply = app(GiyaAssistant::class)->ask('schedule for catmon church')['reply'];

        $this->assertStringNotContainsString("\u{2013}", $reply);
        $this->assertStringNotContainsString("\u{2014}", $reply);
        $this->assertSame(
            "**San Guillermo Parish (Catmon Church)**, Catmon, northern Cebu.\n- Masses 9-11 AM\nA shrine, built in 1998.",
            $reply
        );
    }

    public function test_the_prompt_tells_the_model_not_to_use_long_dashes(): void
    {
        $this->assertStringContainsString('Never use em dashes or en dashes', $this->promptFor('Hello'));
    }

    public function test_no_long_dashes_remain_in_giyas_own_text(): void
    {
        foreach (['resources', 'lang', 'app', 'database', 'public/assets/js'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->getFilename() === 'leaflet.js' || ! preg_match('/\.(php|js|css)$/', $file->getFilename())) {
                    continue;
                }

                $text = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression('/\x{2013}|\x{2014}|\\\\u201[34]|&[mn]dash;/u', $text, $file->getPathname());
            }
        }
    }

    public function test_the_default_model_is_not_the_retired_one(): void
    {
        $config = file_get_contents(config_path('services.php'));

        $this->assertStringNotContainsString("'llama-3.3-70b-versatile')", $config);
    }

    public function test_a_retired_model_is_reported_as_such_and_the_offline_answer_still_has_history(): void
    {
        config(['services.groq.key' => 'gsk-test', 'services.groq.model' => 'gone-model']);
        Http::fake(['api.groq.com/*' => Http::response([
            'error' => ['message' => 'The model does not exist', 'code' => 'model_not_found'],
        ], 404)]);

        $result = app(GiyaAssistant::class)->ask('What is the history of Simala?');

        $this->assertFalse($result['ok']);
        $this->assertSame('bad_model', $result['reason']);
        $this->assertStringContainsString('Marian Monks of Eucharistic Adoration', $result['reply']);
        $this->assertStringContainsString('The image is reported to have wept in 1998', $result['reply']);
    }

    /** The cache used to keep old details for up to six hours. */
    public function test_editing_a_church_reaches_the_assistant_on_the_next_question(): void
    {
        $this->promptFor('Tell me about Simala');

        Church::where('name', 'Simala Shrine')->first()->update(['history' => 'A brand-new history line.']);

        $this->assertStringContainsString('A brand-new history line.', $this->promptFor('Tell me about Simala'));
    }

    public function test_the_history_seeder_fills_only_empty_histories(): void
    {
        $this->church('Cebu Metropolitan Cathedral', 'Cathedral');
        $this->church("Magellan's Cross Chapel", 'Heritage', ['history' => 'Written by our admin.']);

        $this->seed(ChurchHistorySeeder::class);

        $this->assertStringContainsString('St. Vitalis', Church::where('name', 'Cebu Metropolitan Cathedral')->value('history'));
        $this->assertSame('Written by our admin.', Church::where('name', "Magellan's Cross Chapel")->value('history'));
    }

    public function test_the_church_page_shows_the_history(): void
    {
        $user = User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('churches.show', Church::where('name', 'Simala Shrine')->first()))
            ->assertOk()
            ->assertSee('Marian Monks of Eucharistic Adoration');
    }

    public function test_an_admin_can_save_a_history(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $church = Church::where('name', 'Redemptorist Church')->first();

        $this->actingAs($admin)->post(route('admin.destinations.store'), [
            'church_id' => $church->id, 'name' => $church->name, 'location' => 'Cebu City',
            'category' => 'Church', 'history' => 'The Redemptorists arrived in Cebu in 1906.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('The Redemptorists arrived in Cebu in 1906.', $church->fresh()->history);
    }
}
