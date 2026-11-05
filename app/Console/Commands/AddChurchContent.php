<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\ChurchImage;
use App\Services\GiyaAssistant;
use Illuminate\Console\Command;

/**
 * Fills in the history and photo of every destination that has none.
 *
 *   php artisan giya:add-church-content --dry-run   show what would change
 *   php artisan giya:add-church-content             save it
 *
 * Never overwrites: a history an admin wrote, or any photo already there
 * (uploaded or shipped in public/images/churches), is left as it is.
 *
 * History: the researched text in database/data/parish_content.php; for a
 * parish without one, a short "about" paragraph from the Archdiocese of Cebu
 * data (address, vicariate, district, fiesta). Photos: free-licensed
 * Wikimedia Commons photos, saved with their author and licence.
 */
class AddChurchContent extends Command
{
    protected $signature = 'giya:add-church-content
                            {--dry-run : Show what would change without saving}
                            {--content= : Another content file (used by the tests)}
                            {--parishes= : Another parish data file (used by the tests)}';

    protected $description = 'Add researched history and Commons photos to destinations that have none';

    public function handle(): int
    {
        $content  = require ($this->option('content') ?: database_path('data/parish_content.php'));
        $parishes = require ($this->option('parishes') ?: database_path('data/archdiocese_parishes.php'));
        $dry = (bool) $this->option('dry-run');
        $histories = $abouts = $photos = 0;
        $rows = [];

        foreach (Church::with('images')->orderBy('name')->get() as $church) {
            $entry  = $this->find($content, $church);
            $parish = $this->find($parishes, $church);
            $did = [];

            if (blank($church->history)) {
                $text = $entry['history'] ?? ($parish ? self::about($parish) : null);
                if ($text) {
                    $dry || $church->update(['history' => $text, 'updated_at' => now()]);
                    isset($entry['history']) ? $histories++ : $abouts++;
                    $did[] = isset($entry['history']) ? 'history' : 'about';
                }
            }

            if (isset($entry['photo']) && $this->needsPhoto($church)) {
                $p = $entry['photo'];
                $dry || ChurchImage::create([
                    'church_id'   => $church->id,
                    'image_url'   => $p['url'],
                    'caption'     => $church->name,
                    'credit'      => 'Photo: '.$p['author'].', '.$p['licence'].', via Wikimedia Commons',
                    'credit_url'  => $p['page'],
                    'is_primary'  => true,
                    'uploaded_at' => now(),
                    'created_at'  => now(),
                ]);
                $photos++;
                $did[] = 'photo';
            }

            if ($did) {
                $rows[] = [$church->name, (string) ($church->municipality ?: $church->location), implode(', ', $did)];
            }
        }

        if ($rows) {
            $this->table(['Destination', 'Town', 'Added'], $rows);
        }

        $this->info(($dry ? 'Would add: ' : 'Added: ')."$histories researched histories, $abouts short parish descriptions, $photos photos.");

        if (! $dry) {
            GiyaAssistant::forget();   // Giya AI re-reads the new history
        }

        return self::SUCCESS;
    }

    /** The entry for this church: same name, same town. */
    private function find(array $list, Church $church): ?array
    {
        $name = mb_strtolower(trim($church->name));
        $where = mb_strtolower(implode(' ', array_filter([$church->municipality, $church->location, $church->address])));

        foreach ($list as $e) {
            if (mb_strtolower(trim($e['name'])) !== $name) {
                continue;
            }
            $town = mb_strtolower(preg_replace('/\b(city of|city)\b/i', '', $e['town']));
            if (str_contains($where, trim($town))) {
                return $e;
            }
        }

        return null;
    }

    /** No photo yet: nothing in church_images and no shipped file for it. */
    private function needsPhoto(Church $church): bool
    {
        return $church->images->isEmpty() && str_ends_with($church->imagePath(), 'placeholder.svg');
    }

    /** One short, true paragraph from the Archdiocese data. */
    public static function about(array $p): string
    {
        $where = $p['address'] ?: $p['town'];
        $text = "{$p['name']} is a parish of the Archdiocese of Cebu, located in {$where}.";

        if (! empty($p['vicariate'])) {
            $text .= " It belongs to the Vicariate of {$p['vicariate']}"
                .(! empty($p['district']) ? " in Ecclesiastical District {$p['district']}." : '.');
        }

        if (! empty($p['feast'])) {
            $text .= " The parish fiesta is celebrated on {$p['feast']}.";
        }

        return $text;
    }
}
