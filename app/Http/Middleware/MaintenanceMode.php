<?php

namespace App\Http\Middleware;

use App\Support\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While maintenance mode is on, everyone except admins gets the "closed"
 * page. Sign-in stays open so an admin can get in, and Maya can still confirm
 * a payment that was already on its way.
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Maintenance::blocks($request)) {
            return $next($request);
        }

        $backBy = Maintenance::backBy();
        $wait = $backBy ? max(60, (int) now()->diffInSeconds($backBy)) : 3600;

        if ($request->expectsJson()) {
            return response()
                ->json(['ok' => false, 'maintenance' => true, 'message' => Maintenance::message()], 503)
                ->header('Retry-After', (string) $wait);
        }

        return response()
            ->view('maintenance', ['message' => Maintenance::message(), 'backBy' => $backBy], 503)
            ->header('Retry-After', (string) $wait)
            ->header('Cache-Control', 'no-store');
    }
}
