<?php

namespace App\Console\Commands;

use App\Models\Church;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-import destinations from a CSV file.
 *
 * Intended for loading the full Cebu parish directory without hand-writing
 * a seeder entry per church. Rows are matched on name, so re-running the
 * command updates existing records instead of duplicating them.
 *
 *   php artisan giya:import-churches database/data/churches.csv
 */
class ImportChurches extends Command
{
    protected $signature = 'giya:import-churches
                            {file : Path to the CSV file, relative to the project root}
                            {--dry-run : Parse and validate without writing to the database}';

    protected $description = 'Import churches into the destinations table from a CSV file';

    private const REQUIRED = ['name', 'latitude', 'longitude'];

    private const CATEGORIES = ['Basilica', 'Cathedral', 'Shrine', 'Church', 'Chapel', 'Heritage'];

    public function handle(): int
    {
        $path = base_path($this->argument('file'));

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if (! $header) {
            $this->error('The file appears to be empty.');
            fclose($handle);

            return self::FAILURE;
        }

        $header  = array_map(fn ($h) => strtolower(trim($h)), $header);
        $missing = array_diff(self::REQUIRED, $header);

        if ($missing) {
            $this->error('Missing required column(s): ' . implode(', ', $missing));
            $this->line('Expected header: name,location,category,description,address,latitude,longitude,opening_time,closing_time,rating,is_featured');
            fclose($handle);

            return self::FAILURE;
        }

        $imported = $skipped = 0;
        $problems = [];
        $line     = 1;

        DB::beginTransaction();

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = array_combine($header, array_pad($row, count($header), null));
            $name = trim((string) ($data['name'] ?? ''));
            $lat  = $data['latitude']  !== null && $data['latitude']  !== '' ? (float) $data['latitude']  : null;
            $lng  = $data['longitude'] !== null && $data['longitude'] !== '' ? (float) $data['longitude'] : null;

            if ($name === '') {
                $problems[] = "Line {$line}: missing name";
                $skipped++;
                continue;
            }

            // Cebu province sits roughly within this box. Anything outside it is
            // almost certainly a transposed or mistyped coordinate.
            if ($lat === null || $lng === null
                || $lat < 9.3  || $lat > 11.4
                || $lng < 123.0 || $lng > 124.5) {
                $problems[] = "Line {$line}: {$name} - coordinates ({$lat}, {$lng}) fall outside Cebu province";
                $skipped++;
                continue;
            }

            $category = ucwords(strtolower(trim((string) ($data['category'] ?? 'Church'))));
            if (! in_array($category, self::CATEGORIES, true)) {
                $category = 'Church';
            }

            if (! $this->option('dry-run')) {
                Church::updateOrCreate(['name' => $name], [
                    'location'     => trim((string) ($data['location'] ?? 'Cebu')) ?: 'Cebu',
                    'category'     => $category,
                    'description'  => trim((string) ($data['description'] ?? '')) ?: null,
                    'address'      => trim((string) ($data['address'] ?? '')) ?: null,
                    'latitude'     => $lat,
                    'longitude'    => $lng,
                    'opening_time' => trim((string) ($data['opening_time'] ?? '')) ?: null,
                    'closing_time' => trim((string) ($data['closing_time'] ?? '')) ?: null,
                    'rating'       => (float) ($data['rating'] ?? 0),
                    'is_featured'  => filter_var($data['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_active'    => true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $imported++;
        }

        fclose($handle);

        $this->option('dry-run') ? DB::rollBack() : DB::commit();

        $this->newLine();
        $this->info(($this->option('dry-run') ? 'Would import' : 'Imported') . ": {$imported}");

        if ($skipped) {
            $this->warn("Skipped: {$skipped}");
            foreach (array_slice($problems, 0, 15) as $problem) {
                $this->line("  {$problem}");
            }
            if (count($problems) > 15) {
                $this->line('  … and ' . (count($problems) - 15) . ' more');
            }
        }

        if (! $this->option('dry-run')) {
            $this->newLine();
            $this->info('Total active destinations: ' . Church::active()->count());
        }

        return self::SUCCESS;
    }
}