<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One official address for GIYA.
 *
 * GIYA runs on more than one host (Render, Vercel), and each has its own
 * link. When GIYA_OFFICIAL_URL is set on a host, anyone who opens that host
 * by another address is sent to the same page on the official one, so old
 * links and bookmarks keep working and everyone ends up in one place.
 *
 * Only pages being opened (GET) are redirected. A form or a payment
 * confirmation already on its way (POST) is let through, because a redirect
 * would drop what it carries.
 */
class RedirectToOfficialSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $official = rtrim(trim((string) config('app.official_url')), '/');

        // "giya.example.com" typed without https:// still works.
        if ($official !== '' && ! str_contains($official, '://')) {
            $official = 'https://'.$official;
        }

        $host = strtolower((string) parse_url($official, PHP_URL_HOST));

        if ($host === '' || ! $request->isMethodSafe() || strtolower($request->getHost()) === $host) {
            return $next($request);
        }

        $path = $request->getPathInfo();
        $query = $request->getQueryString();

        // 302, not 301: browsers remember a 301 for good, and the official
        // address may still change (or be set wrongly once).
        return redirect()->away($official.($path === '/' ? '' : $path).($query ? '?'.$query : ''), 302);
    }
}
