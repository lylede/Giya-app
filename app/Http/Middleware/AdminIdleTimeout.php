<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administrators are signed out after 30 minutes without activity.
 *
 * The admin panel can change every schedule and account, so a laptop left
 * open should not stay signed in. Pilgrims are not affected: they stay
 * signed in until they sign out, so a long trip is never interrupted.
 *
 * "Activity" is any request the admin makes, plus the keep-alive the admin
 * layout sends while someone is working on a page (typing a long form makes
 * no requests). Background requests (the notification poll) send
 * X-Giya-Background and do not count. An admin is also never let back in
 * by the "Remember me" cookie: that would skip the 30 minutes entirely.
 */
class AdminIdleTimeout
{
    public const IDLE_SECONDS = 30 * 60;

    public const SESSION_KEY = 'admin_seen_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            return $next($request);
        }

        $seen = (int) $request->session()->get(self::SESSION_KEY, 0);
        $idle = $seen > 0 && (time() - $seen) > self::IDLE_SECONDS;

        if ($idle || Auth::viaRemember()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = \App\Http\Controllers\SessionController::SIGNED_OUT_MESSAGE;
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'reason' => 'signed_out', 'message' => $message, 'login_url' => route('login')], 401);
            }
            if ($request->isMethod('GET') && ! $request->routeIs('admin.idle-signed-out')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('login')->with('warning', $message);
        }

        if (! $request->headers->has('X-Giya-Background')) {
            $request->session()->put(self::SESSION_KEY, time());
        }

        return $next($request);
    }
}
