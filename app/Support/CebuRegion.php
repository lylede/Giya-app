<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Which part of Cebu a town is in, and which part a question asks about.
 *
 * Giya AI used to get the churches grouped by town only, with nothing saying
 * where each town is. Asked for "churches in south Cebu" it guessed, and once
 * suggested Pilar on the Camotes Islands, which is north east. The prompt now
 * groups churches by these regions, so the model picks from the right list.
 *
 * Regions follow how pilgrims travel: the south (both coasts below Metro
 * Cebu), the midwest (Toledo side), the north, and the two island groups
 * reached by boat.
 */
final class CebuRegion
{
    /** In the order the prompt lists them. */
    public const REGIONS = [
        'metro' => [
            'label' => 'METRO CEBU',
            'towns' => ['cebu city', 'mandaue', 'lapu lapu', 'lapulapu', 'talisay', 'cordova', 'consolacion',
                        'liloan', 'minglanilla', 'naga', 'mactan', 'olango'],
        ],
        'south' => [
            'label' => 'SOUTH CEBU (south east and south west coasts)',
            'towns' => ['san fernando', 'carcar', 'sibonga', 'argao', 'dalaguete', 'alcoy', 'boljoon', 'oslob',
                        'santander', 'samboan', 'ginatilan', 'malabuyoc', 'alegria', 'badian', 'moalboal',
                        'alcantara', 'ronda', 'dumanjug', 'barili'],
        ],
        'midwest' => [
            'label' => 'MIDWEST CEBU (Toledo side)',
            'towns' => ['toledo', 'pinamungajan', 'aloguinsan', 'balamban', 'asturias', 'tuburan'],
        ],
        'north' => [
            'label' => 'NORTH CEBU',
            'towns' => ['compostela', 'danao', 'carmen', 'catmon', 'sogod', 'borbon', 'tabogon', 'tabuelan',
                        'bogo', 'san remigio', 'medellin', 'daanbantayan'],
        ],
        'camotes' => [
            'label' => 'CAMOTES ISLANDS (north east, by boat from Danao)',
            'towns' => ['pilar', 'poro', 'tudela', 'san francisco', 'camotes'],
        ],
        'bantayan' => [
            'label' => 'BANTAYAN ISLAND (far north, by boat from Hagnaya, San Remigio)',
            'towns' => ['bantayan', 'madridejos', 'santa fe'],
        ],
    ];

    /** Words a devotee uses for each region, in English, Cebuano and Filipino. */
    private const ASKED = [
        'south'    => '/\b(south\w*|habagatan|timog|katimugan)\b/',
        'north'    => '/\b(north\w*|amihanan|hilaga|hilagang)\b/',
        'midwest'  => '/\b(midwest|mid west|west|western|kasadpan|kanluran|kanlurang)\b/',
        'camotes'  => '/\bcamotes\b/',
        'bantayan' => '/\bbantayan\b/',
        'metro'    => '/\b(metro|downtown|cebu city|city proper)\b/',
    ];

    /** The region key for a town or address; null when the town is unknown. */
    public static function of(?string $where): ?string
    {
        $where = self::plain((string) $where);
        if (trim($where) === '') {
            return null;
        }

        /* Metro last: "Cebu" appears in many addresses ("Lindogon, Sibonga,
           Cebu"), and the town is the better clue. */
        foreach (['south', 'midwest', 'north', 'camotes', 'bantayan', 'metro'] as $key) {
            foreach (self::REGIONS[$key]['towns'] as $town) {
                if (str_contains($where, ' '.$town.' ')) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function label(?string $key): string
    {
        return self::REGIONS[$key]['label'] ?? 'OTHER CEBU';
    }

    /** The region a message asks about ("churches in south cebu"), if any. */
    public static function asked(string $text): ?string
    {
        $text = self::plain($text);

        foreach (self::ASKED as $key => $pattern) {
            if (preg_match($pattern, $text)) {
                return $key;
            }
        }

        return null;
    }

    private static function plain(string $text): string
    {
        return ' '.trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text)))).' ';
    }
}
