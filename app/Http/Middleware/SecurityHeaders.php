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
 * - Content-Security-Policy: which sites a page may load scripts, styles,
 *   images and data from. GIYA's scripts are all its own files, so no other
 *   site's script can run; images may come from any HTTPS site (church
 *   photos live on Supabase and Wikimedia); forms may post only to GIYA and
 *   Maya. Inline scripts and onclick handlers are still allowed because the
 *   pages use them; moving them into files would allow a stricter policy.
 *   GIYA_CSP=report only reports problems to the console, GIYA_CSP=off
 *   turns it off, in case a new service is added and gets blocked.
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

        $csp = config('app.csp', 'enforce');
        if ($csp !== 'off' && str_contains((string) $headers->get('Content-Type'), 'text/html')) {
            $headers->set($csp === 'report' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', self::policy());
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    public static function policy(): string
    {
        $supabase = parse_url((string) config('services.supabase.url'), PHP_URL_HOST);
        $maya = 'https://*.paymaya.com https://*.maya.ph';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' https://router.project-osrm.org https://tile.openstreetmap.org".($supabase ? ' https://'.$supabase : ''),
            "worker-src 'self'",
            "manifest-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' {$maya}",
            "frame-ancestors 'self'",
        ]);
    }
}
