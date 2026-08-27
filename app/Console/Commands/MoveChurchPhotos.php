<?php

namespace App\Console\Commands;

use App\Models\ChurchImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Moves church photographs out of storage/ and into public/images/churches.
 *
 *   php artisan giya:move-photos --dry-run
 *   php artisan giya:move-photos
 *
 * storage/app/public is gitignored, so anything in it never reaches a
 * teammate's clone. public/ is committed, so photos travel with the code.
 * Run this once to bring across whatever was uploaded before the change.
 */
class MoveChurchPhotos extends Command
{
    protected $signature = 'giya:move-photos {--dry-run : List what would move without moving it}';

    protected $description = 'Move church photos from storage/ into public/images/churches';

    public function handle(): int
    {
        $rows = ChurchImage::with('church')->get()
            ->filter(fn (ChurchImage $i) => str_starts_with((string) $i->image_url, 'storage/'));

        if ($rows->isEmpty()) {
            $this->info('Nothing to move: no photos are under storage/.');

            return self::SUCCESS;
        }

        $dir = public_path('images/churches');
        File::ensureDirectoryExists($dir);

        $moved = $missing = 0;

        foreach ($rows as $image) {
            $from = storage_path('app/public/'.str_replace('storage/', '', $image->image_url));

            if (! File::exists($from)) {
                $this->warn('Missing on disk: '.$image->image_url);
                $missing++;
                continue;
            }

            $slug = Str::slug($image->church->name ?? 'church-'.$image->church_id);
            $ext  = strtolower(pathinfo($from, PATHINFO_EXTENSION) ?: 'jpg');
            $name = "{$slug}.{$ext}";

            $this->line(($this->option('dry-run') ? '[dry] ' : '')
                .basename($from).'  ->  images/churches/'.$name);

            if (! $this->option('dry-run')) {
                File::copy($from, "{$dir}/{$name}");
                File::delete($from);
                $image->update(['image_url' => "images/churches/{$name}"]);
            }

            $moved++;
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->comment("{$moved} photo(s) would move, {$missing} missing.");

            return self::SUCCESS;
        }

        $this->info("Moved {$moved} photo(s). {$missing} were missing on disk.");
        $this->line('Commit them so your teammates get them:');
        $this->line('  git add public/images/churches');

        return self::SUCCESS;
    }
}
