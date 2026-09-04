<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The devotee-facing pages must actually change language.
 *
 * The failure this guards against is not a missing translation - a missing key
 * falls back to English and is easy to spot in the lang files. It is a string
 * that never reaches the translator at all: hardcoded in a Blade file or in an
 * inline script, so the page renders identically in all three languages and
 * nothing complains. Every screen in this app was in that state once.
 *
 * So the test renders each page as a Cebuano-speaking and then a
 * Filipino-speaking devotee and looks for English that should have been
 * replaced. Only distinctive English is used as evidence - a long phrase whose
 * translation differs - because short words like "Save" and proper nouns like
 * "Metro Cebu" legitimately survive translation, and a church's own name is
 * data rather than interface.
 */
class TranslationCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** Pages a devotee sees, and the language they should see them in. */
    private const PAGES = [
        'home', 'map', 'chatbot', 'profile', 'upgrade',
        'plan.hub', 'plan.create', 'plan.visita', 'plan.index',
    ];

    private int $devoteeCount = 0;

    private function devotee(string $language): User
    {
        $user = User::create([
            'name' => 'Lyle', 'email' => 'lyle' . (++$this->devoteeCount) . '@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $user->preferences()->updateOrCreate([], ['language' => $language]);

        return $user->fresh();
    }

    private function seedChurch(): Church
    {
        $category = ChurchCategory::firstOrCreate(
            ['name' => 'Basilica'],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // Plain ASCII: the marker list is JSON-encoded into the map page, so
        // accented characters arrive escaped and would confuse a str_contains.
        return Church::create([
            'name' => 'Redemptorist Church', 'category_id' => $category->id,
            'location' => 'Cebu City', 'latitude' => 10.2945, 'longitude' => 123.9020,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_three_language_files_hold_exactly_the_same_keys(): void
    {
        $flatten = function (array $rows, string $prefix = '') use (&$flatten) {
            $out = [];
            foreach ($rows as $key => $value) {
                $path = $prefix === '' ? $key : "$prefix.$key";
                $out += is_array($value) ? $flatten($value, $path) : [$path => $value];
            }

            return $out;
        };

        $en  = $flatten(require lang_path('en/giya.php'));
        $ceb = $flatten(require lang_path('ceb/giya.php'));
        $fil = $flatten(require lang_path('fil/giya.php'));

        $this->assertSame([], array_keys(array_diff_key($en, $ceb)), 'Keys missing from lang/ceb');
        $this->assertSame([], array_keys(array_diff_key($en, $fil)), 'Keys missing from lang/fil');
        $this->assertSame([], array_keys(array_diff_key($ceb, $en)), 'Keys in lang/ceb that English does not have');
        $this->assertSame([], array_keys(array_diff_key($fil, $en)), 'Keys in lang/fil that English does not have');

        foreach (['ceb' => $ceb, 'fil' => $fil] as $code => $rows) {
            foreach ($rows as $key => $value) {
                $this->assertNotSame('', trim((string) $value), "Empty value at $key in lang/$code");
            }
        }
    }

    /**
     * The evidence set: English phrases long enough to be interface copy and
     * genuinely different in the target language.
     *
     * @return array<int, string>
     */
    private function englishToLookFor(string $locale): array
    {
        $flatten = function (array $rows) use (&$flatten) {
            $out = [];
            foreach ($rows as $value) {
                $out = array_merge($out, is_array($value) ? $flatten($value) : [$value]);
            }

            return $out;
        };

        $en     = require lang_path('en/giya.php');
        $target = require lang_path("$locale/giya.php");

        $pairs = array_combine($flatten($en), $flatten($target));
        $out   = [];

        foreach ($pairs as $english => $translated) {
            // Short strings and untranslated proper nouns prove nothing.
            if ($english === $translated || mb_strlen($english) < 14) {
                continue;
            }

            // Placeholders are substituted before rendering, so the raw string
            // would never appear either way.
            if (str_contains($english, ':')) {
                continue;
            }

            $out[] = $english;
        }

        return $out;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('localeProvider')]
    public function test_every_devotee_page_renders_in_the_chosen_language(string $language, string $locale): void
    {
        $this->seedChurch();
        $user = $this->devotee($language);

        $leaks = [];

        foreach (self::PAGES as $page) {
            $response = $this->actingAs($user)->get(route($page));
            $response->assertOk();
            $html = $response->getContent();

            foreach ($this->englishToLookFor($locale) as $english) {
                if (str_contains($html, e($english)) || str_contains($html, $english)) {
                    $leaks[] = "$page: \"$english\"";
                }
            }
        }

        $this->assertSame(
            [],
            $leaks,
            "English copy still reaching a $language devotee:\n  " . implode("\n  ", $leaks)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('localeProvider')]
    public function test_a_church_page_renders_in_the_chosen_language(string $language, string $locale): void
    {
        $church = $this->seedChurch();
        $user   = $this->devotee($language);

        $html = $this->actingAs($user)->get(route('churches.show', $church))
            ->assertOk()
            ->getContent();

        $leaks = [];
        foreach ($this->englishToLookFor($locale) as $english) {
            if (str_contains($html, e($english)) || str_contains($html, $english)) {
                $leaks[] = $english;
            }
        }

        $this->assertSame([], $leaks, "English copy on the church page for a $language devotee");
    }

    public function test_the_pages_genuinely_differ_between_languages(): void
    {
        // A page that renders byte-identically in English and Cebuano has not
        // been translated at all - which is exactly how this started.
        $this->seedChurch();

        $english = $this->actingAs($this->devotee('English'))->get(route('home'))->getContent();
        $cebuano = $this->actingAs($this->devotee('Cebuano'))->get(route('home'))->getContent();

        $this->assertNotSame($english, $cebuano);
        $this->assertStringContainsString('Sugdi ang Imong Panaw', $cebuano);
        $this->assertStringNotContainsString('Start Your Journey', $cebuano);
    }

    public static function localeProvider(): array
    {
        return [
            'Cebuano'  => ['Cebuano', 'ceb'],
            'Filipino' => ['Filipino', 'fil'],
        ];
    }
}
