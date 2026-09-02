<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
use App\Models\DevoteePreference;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The language dropdown saved a value for months and nothing read it. These
 * tests are the guard against that happening again: they assert the stored
 * word actually reaches app()->getLocale(), and that every value the form
 * can post has somewhere to land.
 */
class SetLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    /** A request carrying a devotee whose preference row says $language. */
    protected function requestFor(?string $language): Request
    {
        $request = Request::create('/home');

        if ($language === null) {
            return $request;                       // a guest
        }

        $user = new User(['name' => 'Devotee']);
        $user->setRelation('preferences', new DevoteePreference(['language' => $language]));

        $request->setUserResolver(fn () => $user);

        return $request;
    }

    protected function localeAfter(?string $language): string
    {
        (new SetLocale)->handle($this->requestFor($language), fn () => response(''));

        return app()->getLocale();
    }

    public function test_each_language_the_form_offers_sets_its_locale(): void
    {
        $this->assertSame('en',  $this->localeAfter('English'));
        $this->assertSame('ceb', $this->localeAfter('Cebuano'));
        $this->assertSame('fil', $this->localeAfter('Filipino'));
    }

    /**
     * ProfileController validates language against a fixed list. If someone
     * adds a fourth there and not here, it would save fine and silently do
     * nothing - which is the exact bug this whole change exists to fix. So
     * the list is read out of the controller rather than copied.
     */
    public function test_the_middleware_covers_every_value_the_controller_accepts(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ProfileController.php'));

        preg_match_all("/'language'\s*=>\s*\['required', 'in:([^']+)'\]/", $source, $m);

        $this->assertNotEmpty($m[1], 'Could not find the language rule in ProfileController.');

        foreach ($m[1] as $rule) {
            $accepted = explode(',', $rule);

            sort($accepted);
            $mapped = array_keys(SetLocale::LOCALES);
            sort($mapped);

            $this->assertSame(
                $accepted,
                $mapped,
                'ProfileController accepts ['.implode(', ', $accepted).'] but SetLocale maps ['
                    .implode(', ', $mapped).'] - a language it cannot map saves and does nothing.'
            );
        }
    }

    public function test_a_guest_is_left_in_english(): void
    {
        $this->assertSame('en', $this->localeAfter(null));
    }

    public function test_a_devotee_with_no_preference_row_is_left_alone(): void
    {
        $user = new User(['name' => 'Devotee']);
        $user->setRelation('preferences', null);

        $request = Request::create('/home');
        $request->setUserResolver(fn () => $user);

        (new SetLocale)->handle($request, fn () => response(''));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_an_unrecognised_language_does_not_break_the_page(): void
    {
        // A row edited by hand, or left over from an older list.
        $this->assertSame('en', $this->localeAfter('Esperanto'));
    }

    /* ── The files themselves ───────────────────────────────────────── */

    public function test_every_locale_defines_the_same_keys(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten) {
            $out = [];
            foreach ($a as $k => $v) {
                $key = $prefix ? "$prefix.$k" : $k;
                $out = array_merge($out, is_array($v) ? $flatten($v, $key) : [$key]);
            }
            return $out;
        };

        $english = $flatten(require base_path('lang/en/giya.php'));
        $this->assertNotEmpty($english);

        foreach (['ceb', 'fil'] as $locale) {
            $keys = $flatten(require base_path("lang/$locale/giya.php"));

            $this->assertEmpty(
                array_diff($english, $keys),
                "$locale is missing: ".implode(', ', array_diff($english, $keys))
            );
            $this->assertEmpty(
                array_diff($keys, $english),
                "$locale defines keys English does not: ".implode(', ', array_diff($keys, $english))
            );
        }
    }

    public function test_no_translation_is_left_as_the_english(): void
    {
        // Not a hard rule - proper nouns are meant to match - but anything
        // outside this list being identical means a string was missed.
        $sameOnPurpose = [
            'nav.region', 'footer.visita', 'footer.chatbot', 'footer.account',
            'footer.dashboard', 'plan.visita', 'profile.account', 'profile.email',
            'chat.title', 'common.optional',
        ];

        $english = require base_path('lang/en/giya.php');

        foreach (['ceb', 'fil'] as $locale) {
            $other = require base_path("lang/$locale/giya.php");
            $untranslated = [];

            foreach ($english as $group => $strings) {
                foreach ($strings as $key => $value) {
                    $dotted = "$group.$key";
                    if (in_array($dotted, $sameOnPurpose, true)) continue;
                    if (($other[$group][$key] ?? null) === $value) $untranslated[] = $dotted;
                }
            }

            $this->assertEmpty($untranslated, "$locale still in English: ".implode(', ', $untranslated));
        }
    }
}
