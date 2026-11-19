<?php

namespace App\Console\Commands;

use App\Support\WebPush;
use Illuminate\Console\Command;

/**
 * Makes GIYA's key pair for phone notifications (Web Push / VAPID).
 *
 *   php artisan giya:push-keys
 *
 * Prints three lines to paste into .env and into the hosting environment
 * (Render, Vercel). Make the keys once and keep them: new keys mean every
 * phone that turned notifications on has to turn them on again.
 *
 * It only prints. It never writes .env, so the private key goes exactly
 * where you put it and nowhere else.
 */
class PushKeys extends Command
{
    protected $signature = 'giya:push-keys';

    protected $description = 'Make the key pair for phone notifications (prints the .env lines)';

    public function handle(): int
    {
        if (WebPush::enabled()) {
            $this->warn('Keys are already set. Replacing them turns phone notifications off for everyone until they turn them on again.');
        }

        $keys = WebPush::newKeys();

        $this->line('VAPID_PUBLIC_KEY='.$keys['public']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['private']);
        $this->line('VAPID_SUBJECT='.rtrim((string) config('app.url'), '/'));
        $this->newLine();
        $this->info('Put these in .env and in the hosting environment, then run: php artisan config:clear');
        $this->comment('Keep VAPID_PRIVATE_KEY secret. Do not commit it.');

        return self::SUCCESS;
    }
}
