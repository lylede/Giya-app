<?php

namespace App\Console\Commands;

use App\Models\Church;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds Draft destinations that are the same church as another destination
 * and removes them, keeping the published one.
 *
 *   php artisan giya:clean-drafts            list what it finds (changes nothing)
 *   php artisan giya:clean-drafts --delete   remove the duplicate drafts
 *
 * Two destinations are the same church when they are in the same town and
 * have the same name (allowing for "Parish", "Archdiocesan Shrine of",
 * Sto./Santo, Spanish/English forms - see ImportParishes::nameKey), and their
 * pins, when both have one, are within 1.5 km. A one-word name ("Santo Nino")
 * is shared by too many churches, so it must match exactly.
 *
 * Kept: the published one; between two drafts, the older one.
 * Nothing pilgrims made is lost: schedules, reviews, visits, itinerary stops,
 * favourites and follows on the draft move to the church that is kept
 * (unless that church already has the same one), and its photo too when the
 * kept church has none.
 *
 * Drafts that are not duplicates are listed but never touched: most are real
 * parishes whose pin is only their town, waiting for an admin to place the
 * pin and publish them.
 */
class CleanDraftDuplicates extends Command
{
    protected $signature = 'giya:clean-drafts {--delete : Remove the duplicate drafts (otherwise only list them)}';

    protected $description = 'Remove Draft destinations that duplicate another destination';

    /** Tables with a church_id, and the other column that must stay unique with it. */
    private const LINKED = [
        'schedules'       => 'event_name',  // a Feast Day the kept church already has is not copied
        'feedback'        => null,
        'visit_history'   => null,
        'itinerary_stops' => 'itinerary_id',
        'favorites'       => 'user_id',
        'church_follows'  => 'user_id',
    ];

    public function handle(): int
    {
        $all = Church::orderBy('id')->get(['id', 'name', 'location', 'municipality', 'address', 'latitude', 'longitude', 'is_active', 'created_at']);
        $drafts = $all->where('is_active', false);
        $pairs = [];
        $gone = [];

        foreach ($drafts as $draft) {
            $keep = $this->keeperFor($draft, $all, $gone);
            if ($keep) {
                $pairs[] = [$draft, $keep];
                $gone[] = $draft->id;
            }
        }

        $this->info(count($pairs).' duplicate draft(s) found out of '.$drafts->count().' drafts and '.$all->count().' destinations.');

        if ($pairs) {
            $this->table(['Draft to remove', 'Town', 'Same church as (kept)', 'Kept is'], array_map(fn ($p) => [
                '#'.$p[0]->id.' '.$p[0]->name, $this->town($p[0]), '#'.$p[1]->id.' '.$p[1]->name, $p[1]->is_active ? 'Published' : 'Draft',
            ], $pairs));
        }

        $published = $this->publishedTwice($all->where('is_active', true));
        if ($published) {
            $this->warn('Published twice (not changed - check these by hand):');
            $this->table(['Destination', 'Same as'], $published);
        }

        $left = $drafts->whereNotIn('id', $gone);
        if ($left->isNotEmpty()) {
            $this->line('');
            $this->line($left->count().' other draft(s) are kept. Place each pin on the church and publish it, or delete it in the admin panel:');
            $this->table(['Draft', 'Town'], $left->map(fn ($c) => ['#'.$c->id.' '.$c->name, $this->town($c)])->values()->all());
        }

        if (! $pairs) {
            return self::SUCCESS;
        }

        if (! $this->option('delete')) {
            $this->line('');
            $this->comment('Nothing was changed. Run again with --delete to remove the '.count($pairs).' duplicate draft(s).');

            return self::SUCCESS;
        }

        foreach ($pairs as [$draft, $keep]) {
            DB::transaction(fn () => $this->merge($draft, $keep));
        }

        $this->info('Removed '.count($pairs).' duplicate draft(s). Their schedules, reviews and visits were moved to the church that was kept.');

        return self::SUCCESS;
    }

    private function keeperFor(Church $draft, Collection $all, array $gone): ?Church
    {
        $best = null;

        foreach ($all as $c) {
            if ($c->id === $draft->id || in_array($c->id, $gone, true) || ! $this->same($draft, $c)) {
                continue;
            }
            // A draft is only kept over this one when it is older.
            if (! $c->is_active && $c->id > $draft->id) {
                continue;
            }
            if (! $best || ($c->is_active && ! $best->is_active)) {
                $best = $c;
            }
        }

        return $best;
    }

    private function same(Church $a, Church $b): bool
    {
        $townA = $this->townKey($a);
        $townB = $this->townKey($b);
        if ($townA === '' || $townB === '' || (! str_contains($townA, $townB) && ! str_contains($townB, $townA))) {
            return false;
        }

        $km = $this->km($a, $b);
        if ($km !== null && $km > 1.5) {
            return false;
        }

        if (mb_strtolower(trim($a->name)) === mb_strtolower(trim($b->name))) {
            return true;
        }

        $ka = ImportParishes::nameKey($a->name);
        $kb = ImportParishes::nameKey($b->name);
        if (! $ka || ! $kb) {
            return false;
        }

        $subset = array_diff($ka, $kb) === [] || array_diff($kb, $ka) === [];

        return $subset && min(count($ka), count($kb)) >= 2 || ($ka == $kb && count($ka) === 1 && $km !== null && $km < 0.6);
    }

    /** @return array<int, array{0:string,1:string}> */
    private function publishedTwice(Collection $published): array
    {
        $out = [];
        $list = $published->values();

        foreach ($list as $i => $a) {
            foreach ($list->slice($i + 1) as $b) {
                if ($this->same($a, $b)) {
                    $out[] = ['#'.$a->id.' '.$a->name, '#'.$b->id.' '.$b->name];
                }
            }
        }

        return $out;
    }

    private function merge(Church $draft, Church $keep): void
    {
        foreach (self::LINKED as $table => $uniqueWith) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->where('church_id', $draft->id);

            if ($uniqueWith) {
                // Only rows the kept church does not already have (a user's favourite, a trip's stop, a schedule of the same name).
                $taken = DB::table($table)->where('church_id', $keep->id)->pluck($uniqueWith)->all();
                $rows = $rows->whereNotIn($uniqueWith, $taken);
            }

            $rows->update(['church_id' => $keep->id]);
        }

        // The photo too, when the kept church has none.
        if (! DB::table('church_images')->where('church_id', $keep->id)->exists()) {
            DB::table('church_images')->where('church_id', $draft->id)->update(['church_id' => $keep->id]);
        }

        // Whatever is left (duplicates of what the kept church has) goes with the draft.
        Church::whereKey($draft->id)->delete();
    }

    private function town(Church $c): string
    {
        return (string) ($c->municipality ?: $c->location);
    }

    private function townKey(Church $c): string
    {
        $t = mb_strtolower(\Illuminate\Support\Str::ascii($this->town($c)));
        if (str_contains($t, 'cebu city')) {
            return 'cebu city';
        }
        $t = preg_replace('/\b(city of|city|cebu|philippines)\b|[^a-z -]/', ' ', $t);

        return str_replace(['lapulapu', 'lapu lapu'], 'lapu-lapu', trim(preg_replace('/\s+/', ' ', $t)));
    }

    private function km(Church $a, Church $b): ?float
    {
        if (! $a->latitude || ! $a->longitude || ! $b->latitude || ! $b->longitude) {
            return null;
        }
        $dLat = deg2rad($b->latitude - $a->latitude);
        $dLng = deg2rad($b->longitude - $a->longitude);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a->latitude)) * cos(deg2rad($b->latitude)) * sin($dLng / 2) ** 2;

        return 2 * 6371 * asin(min(1, sqrt($h)));
    }
}
