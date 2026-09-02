<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the devotee's chosen language into effect.
 *
 * devotee_preferences.language has always been saved and never read: the
 * dropdown in Preferences stored "Cebuano" and the interface carried on in
 * English. This is the piece that was missing.
 *
 * The column holds the language's English name because that is what the form
 * posts and what the panel reads in the database. Laravel wants an ISO code
 * for its lang/ directories, so the two are mapped here rather than migrating
 * a column that other screens already display verbatim.
 */
class SetLocale
{
    /** Column value => lang/ directory. */
    public const LOCALES = [
        'English'  => 'en',
        'Cebuano'  => 'ceb',
        'Filipino' => 'fil',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);           // guests read English
        }

        // preferencesOrDefault() would write a row on every guest-ish request;
        // this only reads, and falls through to English when there is no row.
        $language = $user->preferences?->language;

        if ($language && isset(self::LOCALES[$language])) {
            app()->setLocale(self::LOCALES[$language]);
        }

        return $next($request);
    }
}
