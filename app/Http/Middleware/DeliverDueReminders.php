<?php

namespace App\Http\Middleware;

use App\Support\Reminders;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds any due "1 week before" reminders for the signed-in devotee.
 *
 * Runs on every web request (the page they open, and the bell's 90-second
 * check), at most once every few minutes per devotee - see Reminders. A
 * reminder failing must never cost the devotee the page they asked for, so
 * any error is reported and swallowed.
 */
class DeliverDueReminders
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = Auth::user()) {
            try {
                Reminders::deliverIfDue($user);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }
}
