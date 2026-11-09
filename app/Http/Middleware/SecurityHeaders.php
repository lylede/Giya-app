<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers on every page (security checklist).
 *
 * - nosniff: a file is only ever run as the type the server says it is.
 * - SAMEORIGIN: GIYA cannot be framed by another site (clickjacking).
 * - Referrer-Policy: other sites see only "giya", never a full page address.
 * - Permissions-Policy: location for the map only; no camera, microphone or payment APIs.
 * - HSTS on HTTPS: browsers stay on HTTPS once they have seen the live site.
 *
 * No Content-Security-Policy yet: the pages use inline scripts and Leaflet
 * from a CDN, so a strict one needs those moved first.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=()');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
