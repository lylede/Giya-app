<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A page left open for hours (a phone locked on a bus) holds an old form
 * token, so its next tap is refused. The page asks here for a fresh token
 * and repeats the tap once. If the devotee is no longer signed in, they are
 * sent to sign in and brought back to the same page afterwards.
 */
class SessionController extends Controller
{
    public function refresh(Request $request): JsonResponse
    {
        if (Auth::check()) {
            return response()->json(['signed_in' => true, 'token' => csrf_token()])
                ->header('Cache-Control', 'no-store');
        }

        // Come back to the page they were on, only within GIYA.
        $back = (string) $request->query('back', '');
        if (str_starts_with($back, url('/').'/') || $back === url('/')) {
            $request->session()->put('url.intended', $back);
        }
        $request->session()->flash('warning', __('giya.session.sign_in_again'));

        return response()->json(['signed_in' => false, 'login_url' => route('login')])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * The admin's "Still there?" countdown ran out: sign out now and show the
     * sign-in page, which brings them back to the same admin page.
     */
    public function idleSignOut(Request $request): \Illuminate\Http\RedirectResponse
    {
        $back = (string) $request->input('back', '');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if (str_starts_with($back, url('/admin'))) {
            $request->session()->put('url.intended', $back);
        }

        return redirect()->route('login')
            ->with('warning', 'You were signed out after 30 minutes without activity. Sign in again to continue.');
    }

    /** The admin layout's "still working" signal; AdminIdleTimeout records it. */
    public function keepAlive(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
