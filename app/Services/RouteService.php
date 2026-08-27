<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Street-level routing through OpenRouteService.
 *
 * Leaflet draws the map; it has never done routing. Turn instructions come from
 * a routing engine, and this is the call to one. The key stays on the server -
 * a key shipped to the browser is a key anyone can read and spend.
 *
 * Results are cached: churches do not move, so the same stops always produce
 * the same roads and the same instructions.
 */
class RouteService
{
    public function configured(): bool
    {
        return filled(config('services.openroute.key'));
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $stops
     */
    public function between(array $stops, string $profile = null): array
    {
        if (count($stops) < 2) {
            return ['ok' => false, 'reason' => 'too_few'];
        }

        if (! $this->configured()) {
            return ['ok' => false, 'reason' => 'no_key'];
        }

        $profile = $profile ?: config('services.openroute.profile', 'foot-walking');

        $coords = collect($stops)
            ->map(fn ($s) => [round((float) $s['lng'], 5), round((float) $s['lat'], 5)])
            ->all();

        $cacheKey = 'ors:'.$profile.':'.md5(json_encode($coords));

        if ($hit = Cache::get($cacheKey)) {
            return $hit + ['cached' => true];
        }

        try {
            $response = Http::withHeaders([
                    'Authorization' => config('services.openroute.key'),
                    'Content-Type'  => 'application/json',
                ])
                ->timeout(15)
                ->post("https://api.openrouteservice.org/v2/directions/{$profile}/geojson", [
                    'coordinates'  => $coords,
                    'instructions' => true,
                    'language'     => 'en',
                    'units'        => 'm',
                ]);

            if (! $response->successful()) {
                Log::warning('ORS returned '.$response->status().': '.$response->body());

                return ['ok' => false, 'reason' => $response->status() === 403 ? 'quota' : 'upstream'];
            }

            $feature = $response->json('features.0');

            if (! $feature) {
                return ['ok' => false, 'reason' => 'no_route'];
            }

            $payload = [
                'ok'       => true,
                'profile'  => $profile,
                // GeoJSON is [lng, lat]; Leaflet wants [lat, lng].
                'geometry' => collect($feature['geometry']['coordinates'])
                                ->map(fn ($c) => [$c[1], $c[0]])->all(),
                'distance' => round(($feature['properties']['summary']['distance'] ?? 0)),
                'duration' => round(($feature['properties']['summary']['duration'] ?? 0)),
                'legs'     => $this->legs($feature),
            ];

            Cache::put($cacheKey, $payload, now()->addDays(30));

            return $payload;
        } catch (\Throwable $e) {
            Log::warning('ORS call failed: '.$e->getMessage());

            return ['ok' => false, 'reason' => 'offline'];
        }
    }

    /**
     * One list of turns per leg, each carrying the coordinate the manoeuvre
     * happens at - that is what lets the app say "turn in 120 m".
     */
    protected function legs(array $feature): array
    {
        $line = $feature['geometry']['coordinates'];
        $legs = [];

        foreach ($feature['properties']['segments'] ?? [] as $segment) {
            $steps = [];

            foreach ($segment['steps'] ?? [] as $step) {
                $at = $line[$step['way_points'][0]] ?? null;

                $steps[] = [
                    'text'     => $step['instruction'] ?? '',
                    'name'     => $step['name'] !== '-' ? ($step['name'] ?? '') : '',
                    'distance' => round($step['distance'] ?? 0),
                    'duration' => round($step['duration'] ?? 0),
                    'type'     => $step['type'] ?? 11,
                    'lat'      => $at ? $at[1] : null,
                    'lng'      => $at ? $at[0] : null,
                ];
            }

            $legs[] = [
                'distance' => round($segment['distance'] ?? 0),
                'duration' => round($segment['duration'] ?? 0),
                'steps'    => $steps,
            ];
        }

        return $legs;
    }
}
