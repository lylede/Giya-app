<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\ChurchController;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Schedule;
use App\Services\GiyaAssistant;
use App\Support\Recurrence;
use Illuminate\Console\Command;

/**
 * Adds the parishes and shrines of the Roman Catholic Archdiocese
 * of Cebu (database/data/archdiocese_parishes.php, from
 * thearchdioceseofcebu.com/pilgrimages) as GIYA destinations.
 *
 *   php artisan giya:import-parishes --dry-run   show what would happen
 *   php artisan giya:import-parishes             add them
 *
 * Never duplicates a church already in GIYA. A parish counts as already
 * there when an existing destination
 *   - has its pin within 150 m of the parish's pin, or
 *   - has the same name (allowing for "Archdiocesan Shrine of", "Parish",
 *     Sto./Santo, Spanish/English forms...) in the same town, with the pins
 *     within 600 m - or, when a pin is missing, a name of two words or more.
 * Safe to run again; destinations are never changed or removed.
 *
 * Pins: "exact" pins (matched to the church itself on OpenStreetMap) are
 * published. "town" pins (only the town could be found) are added as
 * Draft, so a devotee never navigates to the wrong place: an admin drags
 * the pin onto the church (Move pin) and publishes it.
 *
 * Each town's main church (its poblacion parish) is marked Major, unless
 * the town already has one, so the map can open on one church per town.
 * Cebu City is too big for one: each district's parish is marked Major,
 * including one that is already in GIYA.
 * Each parish's feast day becomes a yearly Feast Day schedule - for an
 * existing destination too, when it has no Feast Day yet. Feasts that move
 * with Easter are left in the description instead.
 */
class ImportParishes extends Command
{
    protected $signature = 'giya:import-parishes
                            {--dry-run : Show what would be added without saving}
                            {--file= : Another data file (used by the tests)}';

    protected $description = 'Add the Archdiocese of Cebu parishes as destinations (no duplicates)';

    public function handle(): int
    {
        $parishes = require ($this->option('file') ?: database_path('data/archdiocese_parishes.php'));
        $dry = (bool) $this->option('dry-run');
        $existing = Church::all(['id', 'name', 'location', 'municipality', 'address', 'latitude', 'longitude', 'is_active', 'is_major']);
        $majorTowns = Church::where('is_major', true)->pluck('municipality')->filter()->map(fn ($t) => mb_strtolower($t))->all();
        $rows = [];
        $added = $drafts = $skipped = $feasts = 0;

        foreach ($parishes as $p) {
            $same = $this->duplicateOf($p, $existing);

            $feast = self::feastRule($p['feast'] ?? null);

            if ($same) {
                $skipped++;
                $feastAdded = $feast && $this->addFeast($same, $p, $feast, $dry);
                $feasts += $feastAdded ? 1 : 0;
                $note = $feastAdded ? ' (feast day added)' : '';

                // A Cebu City district parish already in GIYA becomes Major too.
                if (($p['main'] ?? null) === 'district' && $same->exists && ! $same->is_major) {
                    $dry || $same->update(['is_major' => true]);
                    $note .= ' (marked Major)';
                }
                $rows[] = [$p['name'], $p['town'], 'already in GIYA as '.$same->name.$note];
                continue;
            }

            $town = ChurchController::townIn($p['town']) ?? $p['town'];

            /* 'district': Cebu City is too big for one main church, so each
               district's parish is one (Mabolo, Lahug, Talamban...). */
            $major = ($p['main'] ?? null) === 'district'
                || (($p['main'] ?? null) === true && ! in_array(mb_strtolower($town), $majorTowns, true));
            if ($major) {
                $majorTowns[] = mb_strtolower($town);
            }

            $exact = ($p['pin'] ?? null) === 'exact';
            $category = $this->categoryFor($p['name']);

            if (! $dry) {
                $church = Church::create([
                    'category_id'  => ChurchCategory::firstOrCreate(['name' => $category], ['created_at' => now(), 'updated_at' => now()])->id,
                    'name'         => $p['name'],
                    'location'     => $p['town'],
                    'municipality' => $town,
                    'address'      => $p['address'],
                    'description'  => $this->description($p),
                    'latitude'     => $p['lat'] ?? null,
                    'longitude'    => $p['lng'] ?? null,
                    'is_active'    => $exact,
                    'is_major'     => $major,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $existing->push($church);   // a later row can match this one too

                if ($feast) {
                    $this->addFeast($church, $p, $feast, false);
                }
            } else {
                $existing->push(new Church(['name' => $p['name'], 'location' => $p['town'], 'address' => $p['address'],
                    'latitude' => $p['lat'] ?? null, 'longitude' => $p['lng'] ?? null, 'is_active' => $exact]));
            }

            $added++;
            $feasts += $feast ? 1 : 0;
            $drafts += $exact ? 0 : 1;
            $rows[] = [$p['name'], $p['town'], ($exact ? "added ({$category}" : "added as Draft ({$category}")
                .($major ? ', main church' : '').')'.($exact ? '' : ' - check the pin')];
        }

        $this->table(['Parish', 'Town', 'Result'], $rows);

        $verb = $dry ? 'Would add' : 'Added';
        $this->info("{$verb} {$added} destinations (".($added - $drafts)." published with an exact pin, {$drafts} as Draft to check the pin); {$skipped} already in GIYA; {$feasts} feast days as yearly schedules.");

        if (! $dry && $added) {
            GiyaAssistant::forget();
        }

        return self::SUCCESS;
    }

    private function duplicateOf(array $p, $existing): ?Church
    {
        $key = self::nameKey($p['name']);
        $town = mb_strtolower($p['town']);
        $first = mb_strtolower(trim(explode(',', (string) ($p['address'] ?? ''))[0]));
        $brgy = str_contains((string) ($p['address'] ?? ''), ',') && ! str_contains($first, self::townCore($town))
            && ! preg_match('/\d|\bst\.|street|ave|blvd|road/', $first) ? $first : '';

        /* A town pin is only the town, so it says nothing about which
           church this is - only an exact pin is compared by distance. */
        $exact = ($p['pin'] ?? null) === 'exact';

        foreach ($existing as $c) {
            // The same name at the same address is the same church, pin or not.
            if (mb_strtolower(trim($c->name)) === mb_strtolower(trim($p['name']))
                && mb_strtolower(trim((string) $c->address)) === mb_strtolower(trim((string) ($p['address'] ?? '')))) {
                return $c;
            }

            /* Only a published pin is a place to compare with: a Draft's pin
               may be no more than its town (beside the main church). */
            $km = $exact && $c->is_active ? $this->km($p, $c) : null;

            if ($km !== null && $km < 0.15) {
                return $c;
            }

            $cTown = mb_strtolower(implode(' ', array_filter([$c->municipality, $c->location, $c->address])));
            $sameTown = str_contains($cTown, self::townCore($town)) || str_contains($town, self::townCore($cTown));
            $other = self::nameKey($c->name);

            /* Same name in the same town. Many parishes share a name (Sto.
               Nino Parish in Pasil is not the Basilica), so the name only
               counts with pins close together, or - with a pin missing -
               when it is at least two words long. */
            $nameMatch = $sameTown && $other && $key && (self::subset($key, $other) || self::subset($other, $key));
            $shorter = min(count($key), count($other));

            /* Without pins, the barangay must agree as well: Birhen sa
               Guadalupe in Babag is not the Guadalupe shrine. */
            $placeOk = $brgy === '' || str_contains($cTown, $brgy);

            /* A one-word name ("Santo Nino", "Redemptorist") is shared by too
               many churches. Without pins it needs more: the same barangay,
               or both being the town's one basilica or cathedral. */
            $oneWordOk = ($brgy !== '' && str_contains($cTown, $brgy))
                || (in_array($this->categoryFor($p['name']), ['Basilica', 'Cathedral'], true)
                    && $this->categoryFor($p['name']) === $this->categoryFor($c->name));

            // Same name in the same barangay: the same church, even if one pin is off.
            if ($nameMatch && $shorter >= 2 && $brgy !== '' && str_contains($cTown, $brgy)) {
                return $c;
            }

            if ($nameMatch && ($km !== null ? $km < 0.6 : ($shorter >= 2 ? $placeOk : $oneWordOk))) {
                return $c;
            }

            // Another name the parish is known by, e.g. "Redemptorist Church".
            if ($sameTown && in_array(mb_strtolower($c->name), array_map('mb_strtolower', $p['aka'] ?? []), true)) {
                return $c;
            }
        }

        return null;
    }

    /** Words that name the church, without titles, accents or filler. */
    public static function nameKey(string $name): array
    {
        $s = mb_strtolower(\Illuminate\Support\Str::ascii($name));
        $s = strtr($s, [
            'nuestra senora' => 'our lady', 'virgen de los' => 'our lady of', 'virgen de' => 'our lady of',
            'de regla' => 'of the rule', 'sto.' => 'santo', 'sta.' => 'santa', 'st.' => 'saint',
            'santo nino' => 'nino', 'birhen' => 'our lady',
        ]);
        $s = preg_replace('/\bsan\b/', 'saint', $s);
        $s = preg_replace('/[^a-z ]+/', ' ', $s);
        $stop = ['the', 'of', 'de', 'del', 'la', 'sa', 'and', 'parish', 'church', 'archdiocesan', 'arquidiocesano', 'shrine',
                 'santuario', 'national', 'basilica', 'minore', 'quasi', 'chaplaincy', 'chapel', 'cebu', 'saint', 'santo', 'santa'];
        $words = array_filter(explode(' ', $s), fn ($w) => $w !== '' && ! in_array($w, $stop, true));

        return array_values(array_unique(array_map(fn ($w) => rtrim($w, 's') ?: $w, $words)));
    }

    private static function subset(array $a, array $b): bool
    {
        return $a !== [] && array_diff($a, $b) === [];
    }

    /** "Talisay City" -> "talisay", "City of Naga" -> "naga"; Cebu City stays "cebu city". */
    private static function townCore(string $town): string
    {
        $t = mb_strtolower($town);

        if (str_contains($t, 'cebu city')) {
            return 'cebu city';
        }

        $t = preg_replace('/\b(city of|city|cebu|philippines)\b|[^a-z -]/', ' ', $t);
        $t = trim(preg_replace('/\s+/', ' ', $t));

        return str_replace(['lapulapu', 'lapu lapu'], 'lapu-lapu', $t) ?: '#';
    }

    private function km(array $p, Church $c): ?float
    {
        if (empty($p['lat']) || empty($p['lng']) || ! $c->latitude || ! $c->longitude) {
            return null;
        }
        $r = 6371;
        $dLat = deg2rad($c->latitude - $p['lat']);
        $dLng = deg2rad($c->longitude - $p['lng']);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($p['lat'])) * cos(deg2rad($c->latitude)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /**
     * "May 15", "3rd Sunday of January", "Last Saturday of April" -> a yearly
     * rule. Null for feasts that move with Easter or are only a week.
     */
    public static function feastRule(?string $feast): ?array
    {
        if (! $feast) {
            return null;
        }
        $months = array_flip(array_map('strtolower', Recurrence::MONTHS));
        $days = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $nths = ['1st' => 1, 'first' => 1, '2nd' => 2, 'second' => 2, '3rd' => 3, 'third' => 3, '4th' => 4, 'fourth' => 4, 'last' => -1];
        $f = strtolower(trim($feast));

        if (preg_match('/^([a-z]+) (\d{1,2})\b/', $f, $m) && isset($months[$m[1]])) {
            return ['type' => 'yearly', 'month' => $months[$m[1]], 'day' => (int) $m[2]];
        }

        if (preg_match('/^(\w+) ([a-z]+) of ([a-z]+)$/', $f, $m) && isset($nths[$m[1]], $days[$m[2]], $months[$m[3]])) {
            return ['type' => 'yearly', 'nth' => $nths[$m[1]], 'weekday' => $days[$m[2]], 'month' => $months[$m[3]]];
        }

        return null;
    }

    /** A yearly Feast Day for this church, unless it already has one. */
    private function addFeast(Church $church, array $p, array $rule, bool $dry): bool
    {
        if ($church->exists && Schedule::where('church_id', $church->id)->where('event_type', 'Feast Day')->exists()) {
            return false;
        }

        if (! $dry) {
            Schedule::create([
                'church_id'       => $church->id,
                'event_name'      => 'Feast Day - '.$church->name,
                'event_type'      => 'Feast Day',
                'location'        => $church->location ?: $p['town'],
                'is_whole_day'    => true,
                'is_recurring'    => true,
                'recurrence_rule' => $rule,
                'recurrence'      => Recurrence::label($rule),
                'schedule_date'   => Recurrence::next($rule)->toDateString(),
                'status'          => 'Published',
                'notes'           => 'Parish fiesta: '.$p['feast'].".\nSource: The Roman Catholic Archdiocese of Cebu, thearchdioceseofcebu.com/pilgrimages",
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return true;
    }

    private function categoryFor(string $name): string
    {
        return match (true) {
            (bool) preg_match('/basilica/i', $name)          => 'Basilica',
            (bool) preg_match('/cathedral/i', $name)         => 'Cathedral',
            (bool) preg_match('/shrine|santuario/i', $name)  => 'Shrine',
            (bool) preg_match('/chapel|chaplaincy/i', $name) => 'Chapel',
            default                                          => 'Church',
        };
    }

    private function description(array $p): string
    {
        return trim('A Catholic parish of the Archdiocese of Cebu, in the Vicariate of '.$p['vicariate']
            .' (District '.$p['district'].').'
            .($p['feast'] ? ' Feast day: '.$p['feast'].'.' : '')
            ."\nSource: The Roman Catholic Archdiocese of Cebu, thearchdioceseofcebu.com/pilgrimages");
    }
}
