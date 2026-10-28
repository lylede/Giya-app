<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspension takes effect on the next click, not the next sign-in.
 *
 * LoginAction refuses a suspended account, but that only ran at sign-in: a
 * devotee already signed in when an admin suspended them carried on as if
 * nothing had happened, for as long as their session (or remember-me cookie)
 * lasted.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'This account has been suspended. Contact the administrator.';

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 403);
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
