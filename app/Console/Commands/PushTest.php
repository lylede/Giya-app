<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Console\Command;

/**
 * Checks phone notifications end to end and says what is wrong.
 *
 *   php artisan giya:push-test you@example.com
 *
 * Run it where the database is the live one (a laptop whose .env points at
 * Neon, or the Render shell). It checks the keys, lists the devotee's
 * phones, and sends each one a test, printing what Apple / Google answered.
 */
class PushTest extends Command
{
    protected $signature = 'giya:push-test {email : the devotee to send a test to}';

    protected $description = 'Send a test phone notification and show what the push service answered';

    public function handle(): int
    {
        $this->line('Keys: '.(WebPush::enabled() ? '<info>set</info>' : '<error>missing</error> (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY)'));
        $this->line('Subject: '.config('services.webpush.subject'));
        if (! WebPush::enabled()) {
            $this->warn('Add the keys from php artisan giya:push-keys, then php artisan config:clear.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        $subs = PushSubscription::where('user_id', $user->id)->get();
        $this->line("Phones turned on: {$subs->count()}");
        if ($subs->isEmpty()) {
            $this->warn('None. On the phone: open GIYA (iPhone: from the Home Screen icon), Profile > Preferences, turn on Phone notifications.');

            return self::FAILURE;
        }

        $ok = 0;
        foreach ($subs as $sub) {
            $r = WebPush::report($sub, ['title' => 'GIYA test', 'body' => 'Sent from giya:push-test.', 'url' => '/profile']);
            $ok += $r['ok'] ? 1 : 0;
            $this->line(sprintf('  %s  %s  %s %s',
                $r['ok'] ? '<info>sent</info>' : '<error>refused</error>',
                $r['service'], $r['status'] ?? '-', $r['reason']));
        }

        return $ok > 0 ? self::SUCCESS : self::FAILURE;
    }
}
