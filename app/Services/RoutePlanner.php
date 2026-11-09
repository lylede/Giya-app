<?php

namespace App\Services;

use App\Models\Church;
use App\Support\CebuRegion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Works out a church route for Giya AI: which churches, in which order,
 * and roughly when.
 *
 * The model used to plan routes itself and invented the travel: it put
 * Compostela (north Cebu) 20 minutes from Dumanjug (south west), about four
 * hours apart, and called two Carcar churches 5 km apart "a short walk".
 * A language model cannot measure roads. GIYA knows where every church is,
 * so the route is planned here and the model only writes the welcome and
 * tips around it.
 *
 * Travel times are estimates from the map: the straight distance times a
 * winding factor for Cebu's roads, at a typical Cebu driving speed. They are
 * shown as "about" and rounded to 5 minutes.
 */
class RoutePlanner
{
    /** Roads are longer than a straight line; Cebu's coast and mountain roads more so. */
    public const ROAD_FACTOR = 1.45;

    /** Typical speeds, km/h: provincial roads, and Metro Cebu traffic. */
    public const SPEED = 35;
    public const SPEED_METRO = 20;

    /** Closer than this (road km) is a walk. */
    public const WALK_KM = 0.7;

    /** Minutes at each church: a prayer, a look around, a photo. */
    public const VISIT = 25;
    public const VISIT_VISITA = 20;

    /** Where most pilgrims start: the Basilica del Santo Nino, Cebu City. */
    public const CEBU_CITY = [10.2939, 123.9019];

    /** A message that asks for a route or an itinerary. */
    public static function wants(string $text): bool
    {
        $t = self::plain($text);

        if (preg_match('/\b(itinerary|itineraries|day trip|church tour|church hopping|visita iglesia|visita)\b/', $t)) {
            return true;
        }

        return preg_match('/\b(route|plan|trip|tour|lakaw|biyahe|lakad|ruta)\b/', $t)
            && preg_match('/\b(\d+ ?(hours?|hrs?|h)|hour|half day|whole day|full day|maghapon|buong araw|churches|simbahan|stops)\b/', $t);
    }

    /**
     * The route for this conversation, or null when the message is not
     * asking for one.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{title: string, region: string, stops: array, finish: string, from_city: ?int, ids: int[]}|null
     */
    public function plan(string $question, array $history = []): ?array
    {
        if (! self::wants($question)) {
            return null;
        }

        $t = self::plain($question);
        $visita = (bool) preg_match('/\bvisita\b/', $t);
        $hours = self::hours($t) ?? ($visita ? null : 5.0);
        $start = self::startTime($t);

        $churches = Church::active()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['id', 'name', 'municipality', 'location', 'address', 'latitude', 'longitude', 'is_major', 'is_featured', 'history', 'category_id'])
            ->map(fn (Church $c) => [
                'id'     => $c->id,
                'name'   => $c->name,
                'town'   => self::townName((string) ($c->municipality ?: $c->location)),
                'region' => CebuRegion::of($c->municipality.' '.$c->location.' '.$c->address),
                'lat'    => (float) $c->latitude,
                'lng'    => (float) $c->longitude,
                'bonus'  => ($c->is_major ? 15 : 0) + ($c->is_featured ? 10 : 0) + (filled($c->history) ? 8 : 0),
            ]);

        // "starting from Carcar", "in Argao": a town in the question sets the start and the region.
        $town = $churches->pluck('town')->unique()
            ->filter(function ($name) use ($t) {
                // "Danao City" also matches "danao"; but "Cebu City" needs "cebu city", or every "south cebu" would match it.
                $short = preg_replace('/\\s*city$/i', '', $name);
                $key = strcasecmp($short, 'cebu') === 0 ? $name : $short;

                return $name !== '' && str_contains($t, self::plain($key));
            })
            ->sortByDesc(fn ($name) => strlen($name))->first();

        $lastUser = (string) collect($history)->where('role', 'user')->pluck('content')->last();
        $region = $town
            ? $churches->firstWhere('town', $town)['region']
            : (CebuRegion::asked($question) ?? CebuRegion::asked($lastUser) ?? 'metro');

        $pool = $churches->where('region', $region)->values();
        if ($pool->count() < 2) {
            return null;
        }

        $first = $town
            ? $pool->where('town', $town)->sortByDesc('bonus')->first()
            : $pool->sortBy(fn ($c) => self::km(self::CEBU_CITY, [$c['lat'], $c['lng']]) - $c['bonus'] / 5)->first();

        $metro = $region === 'metro';
        $visit = $visita ? self::VISIT_VISITA : self::VISIT;
        $budget = $hours ? (int) round($hours * 60) : PHP_INT_MAX;
        $max = $visita ? 7 : 8;

        $route = [$first + ['leg' => null]];
        $used = $visit;

        while (count($route) < $max) {
            $here = end($route);
            $next = $pool->reject(fn ($c) => in_array($c['id'], array_column($route, 'id'), true))
                ->map(fn ($c) => $c + ['leg' => self::leg([$here['lat'], $here['lng']], [$c['lat'], $c['lng']], $metro)])
                ->filter(fn ($c) => $used + $c['leg']['minutes'] + $visit <= $budget)
                // Close, and worth the stop: a well-known or documented church wins a small detour.
                ->sortBy(fn ($c) => $c['leg']['minutes'] - $c['bonus'] / 5)
                ->first();

            if (! $next) {
                break;
            }

            $used += $next['leg']['minutes'] + $visit;
            $route[] = $next;
        }

        if (count($route) < 2) {
            return null;
        }

        // The timetable.
        $clock = $start;
        $stops = [];
        foreach ($route as $c) {
            if ($c['leg']) {
                $clock += $c['leg']['minutes'];
            }
            $stops[] = [
                'id' => $c['id'], 'name' => $c['name'], 'town' => $c['town'], 'leg' => $c['leg'],
                'arrive' => self::clock($clock), 'leave' => self::clock($clock + $visit),
            ];
            $clock += $visit;
        }

        $fromCity = $metro ? null : self::leg(self::CEBU_CITY, [$route[0]['lat'], $route[0]['lng']], false)['minutes'];

        $area = Str::title(Str::lower(Str::before(CebuRegion::label($region), ' (')));

        return [
            'title'     => $visita
                ? __('giya.route.title_visita', ['area' => $area])
                : __('giya.route.title', ['hours' => self::number($hours), 'area' => $area]),
            'region'    => $region,
            'stops'     => $stops,
            'finish'    => self::clock($clock),
            'from_city' => $fromCity,
            'ids'       => array_column($stops, 'id'),
        ];
    }

    /** The plan as chat text: the part of the answer that must be exact. */
    public static function text(array $plan): string
    {
        $lines = ['**'.$plan['title'].'**'];

        foreach ($plan['stops'] as $i => $s) {
            if ($s['leg']) {
                $lines[] = '   '.__($s['leg']['walk'] ? 'giya.route.walk' : 'giya.route.drive', [
                    'time' => self::duration($s['leg']['minutes']),
                    'km'   => $s['leg']['km'] < 1 ? max(100, (int) round($s['leg']['km'] * 1000, -2)).' m' : self::number($s['leg']['km']).' km',
                ]);
            }
            $lines[] = ($i + 1).'. '.$s['arrive'].' to '.$s['leave'].': **'.$s['name'].'**, '.$s['town'];
        }

        $lines[] = '';
        $lines[] = __('giya.route.finish', ['time' => $plan['finish']]);
        if ($plan['from_city']) {
            $lines[] = __('giya.route.from_city', ['time' => self::duration($plan['from_city'])]);
        }
        $lines[] = '_'.__('giya.route.note').'_';

        return implode("\n", $lines);
    }

    /** One stretch between two churches: road km, minutes, and whether it is a walk. */
    public static function leg(array $a, array $b, bool $metro): array
    {
        $km = self::km($a, $b) * self::ROAD_FACTOR;

        if ($km < self::WALK_KM) {
            return ['km' => round($km, 2), 'minutes' => max(5, (int) (ceil($km / 4.5 * 60 / 5) * 5)), 'walk' => true];
        }

        $minutes = $km / ($metro ? self::SPEED_METRO : self::SPEED) * 60;

        return ['km' => round($km), 'minutes' => max(5, (int) (round($minutes / 5) * 5)), 'walk' => false];
    }

    /** Straight-line distance in km. */
    public static function km(array $a, array $b): float
    {
        [$lat1, $lng1] = array_map('deg2rad', $a);
        [$lat2, $lng2] = array_map('deg2rad', $b);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($h)));
    }

    /** "5 hours", "3.5 hrs", "5-hour", "half day", "whole day", "lima ka oras" is not parsed. */
    public static function hours(string $t): ?float
    {
        $t = self::plain($t);
        if (preg_match('/\b(\d+(?: \d)?) ?(hours?|hrs?|h|oras)\b/', $t, $m)) {
            return max(1.0, min(10.0, (float) str_replace(' ', '.', $m[1])));
        }
        if (preg_match('/\bhalf day\b/', $t)) {
            return 4.0;
        }
        if (preg_match('/\b(whole day|full day|maghapon|buong araw)\b/', $t)) {
            return 8.0;
        }

        return null;
    }

    /** Minutes after midnight the trip starts: "at 9am", "start 1:30 pm"; 8:00 AM when not said. */
    public static function startTime(string $t): int
    {
        if (preg_match('/\b(\d{1,2})(?: (\d{2}))? ?(am|pm)\b/', self::plain($t), $m)) {
            $h = ((int) $m[1]) % 12 + ($m[3] === 'pm' ? 12 : 0);

            return $h * 60 + (int) ($m[2] ?? 0);
        }

        return 8 * 60;
    }

    public static function clock(int $minutes): string
    {
        $minutes %= 24 * 60;

        return date('g:i A', mktime(intdiv($minutes, 60), $minutes % 60));
    }

    public static function duration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return trim(($h ? $h.' hr ' : '').($m ? $m.' min' : ''));
    }

    private static function number(float|int|null $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    }

    /**
     * The town as a pilgrim says it: "City of Carcar" -> "Carcar",
     * "Lindogon, Sibonga, Cebu" -> "Sibonga", "P. Burgos St, Cebu City" -> "Cebu City".
     */
    public static function townName(string $town): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $town)),
            fn ($p) => $p !== '' && ! preg_match('/^cebu$/i', $p)));
        $town = end($parts) ?: '';

        return trim(preg_replace('/^city of\s+/i', '', $town));
    }

    private static function plain(string $text): string
    {
        return ' '.trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text)))).' ';
    }
}
