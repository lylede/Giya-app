<?php

namespace App\Http\Middleware;

use App\Actions\Auth\EmailVerification;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The actions that need a confirmed email (Giya AI, saving a plan, reviews,
 * paying). Browsing never does. Does nothing while verification is off.
 */
class EnsureEmailConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! EmailVerification::pending($request->user())) {
            return $next($request);
        }

        $message = __('giya.verify.needed');

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'reason' => 'unverified', 'mood' => 'sorry', 'reply' => $message], 403);
        }

        return redirect()->route('verification.notice')->with('error', $message);
    }
}
