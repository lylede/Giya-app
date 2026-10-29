<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

/**
 * The three welcome slides a visitor sees the first time they open GIYA.
 *
 * "Seen it" is a cookie on that phone or browser, not a database row: the
 * slides are for people who have no account yet. Skip or Get Started sets it,
 * and from then on the start page opens normally.
 */
class OnboardingController extends Controller
{
    public const COOKIE = 'giya_onboarded';

    /** Where the buttons may send someone afterwards (no open redirects). */
    private const NEXT = ['register', 'login', 'root'];

    public static function seen(Request $request): bool
    {
        return $request->cookie(self::COOKIE) === '1';
    }

    public function show(): View
    {
        return view('onboarding');
    }

    public function finish(Request $request): RedirectResponse
    {
        $next = in_array($request->query('next'), self::NEXT, true) ? $request->query('next') : 'root';

        // One year; the visitor can always clear it to see the slides again.
        return redirect()->route($next)->withCookie(Cookie::make(self::COOKIE, '1', 60 * 24 * 365));
    }
}
