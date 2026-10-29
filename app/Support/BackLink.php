<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * "Back" for a page that can be reached from several places (a church opens
 * from the map, Home, an itinerary, a notification...).
 *
 * The referring page is used only when it is one of GIYA's own pages. Laravel
 * also records data addresses the page fetches in the background (the bell's
 * /notifications check, search), and a back button that opens raw JSON is
 * worse than none. Anything unknown falls back to a fixed parent page.
 */
final class BackLink
{
    /** @return array{0: string, 1: string} [url, label] */
    public static function previous(Request $request, string $fallbackUrl, string $fallbackLabel): array
    {
        // The page the browser actually came from (a real click), else what
        // Laravel last recorded.
        $prev = (string) ($request->headers->get('referer') ?: url()->previous());
        $host = parse_url($prev, PHP_URL_HOST);
        $path = '/'.trim((string) parse_url($prev, PHP_URL_PATH), '/');

        if ($prev === $request->fullUrl() || $host !== $request->getHost()) {
            return [$fallbackUrl, $fallbackLabel];
        }

        $label = match (true) {
            $path === '/home'                       => __('giya.back.home'),
            $path === '/map'                        => __('giya.back.map'),
            $path === '/plan'                       => __('giya.plan.back_hub'),
            $path === '/plan/visita-iglesia'        => __('giya.plan.back_visita'),
            $path === '/plan/my-itineraries'        => __('giya.back.itineraries'),
            (bool) preg_match('#^/plan/\d+$#', $path) => __('giya.back.itinerary'),
            $path === '/profile'                    => __('giya.back.profile'),
            $path === '/chatbot'                    => __('giya.back.chat'),
            default                                 => null,
        };

        return $label ? [$prev, $label] : [$fallbackUrl, $fallbackLabel];
    }
}
