<?php

namespace App\Console\Commands;

use App\Services\MayaService;
use Illuminate\Console\Command;

/**
 * Maya webhooks are registered through the API, not through a dashboard, so
 * without this there is a manual curl in the setup notes that somebody will
 * get wrong the week of the defense.
 *
 *   php artisan giya:maya-webhooks                     list what is registered
 *   php artisan giya:maya-webhooks --register=<url>    point Maya at a URL
 *
 * The URL has to be reachable from the public internet. On a laptop that
 * means a tunnel (ngrok, cloudflared) - Maya cannot post to 127.0.0.1. The
 * app works without any webhook at all, because the return from Maya verifies
 * too; the webhook is what catches the devotee who closes the tab mid-payment.
 */
class MayaWebhooks extends Command
{
    protected $signature = 'giya:maya-webhooks {--register= : Public URL of /maya/webhook}';

    protected $description = 'List or register the Maya payment webhooks';

    /** The events worth hearing about. CHECKOUT_* are Maya's deprecated set. */
    private const EVENTS = [
        'PAYMENT_SUCCESS',
        'PAYMENT_FAILED',
        'PAYMENT_EXPIRED',
        'PAYMENT_CANCELLED',
    ];

    public function handle(): int
    {
        $maya = MayaService::fromConfig();

        if (! $maya->isConfigured()) {
            $this->error('No Maya keys. Set MAYA_PUBLIC_KEY and MAYA_SECRET_KEY in .env.');

            return self::FAILURE;
        }

        if ($url = $this->option('register')) {
            if (! str_starts_with($url, 'https://')) {
                $this->warn('Maya will only post to https. A tunnel gives you one.');
            }

            foreach (self::EVENTS as $event) {
                $result = $maya->registerWebhook($event, $url);

                $result['ok']
                    ? $this->info("registered  $event")
                    : $this->error("failed      $event  ".json_encode($result['body']));
            }

            $this->newLine();
        }

        $registered = $maya->listWebhooks();

        if (! $registered) {
            $this->line('No webhooks registered. Payments still verify when the devotee returns.');

            return self::SUCCESS;
        }

        $this->table(
            ['Event', 'Callback URL', 'ID'],
            array_map(fn ($w) => [
                $w['name'] ?? '?', $w['callbackUrl'] ?? '?', $w['id'] ?? '?',
            ], $registered)
        );

        return self::SUCCESS;
    }
}
