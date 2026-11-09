<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells the team when the live site breaks for someone.
 *
 * Without this, an error on Vercel or Render was written to a log nobody
 * reads, and the team heard about it only if a user complained. Set either
 * or both in .env, Vercel and Render:
 *
 *   ERROR_ALERT_WEBHOOK  a Discord or Slack channel webhook URL
 *   ERROR_ALERT_EMAIL    an address to email (uses the app's mail settings)
 *
 * Only what is needed to find the bug is sent: the error, where it happened,
 * the page path (no query string) and the user's id. Never form data,
 * passwords, emails, or chat messages. The same error alerts at most once
 * every 10 minutes, so a broken page does not flood the channel.
 */
final class ErrorAlert
{
    public const QUIET_MINUTES = 10;

    public static function report(Throwable $e): void
    {
        $webhook = (string) config('services.error_alert.webhook');
        $email = (string) config('services.error_alert.email');

        if (($webhook === '' && $email === '') || app()->runningUnitTests() && ! config('services.error_alert.in_tests')) {
            return;
        }

        $key = 'error-alert:'.sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine());
        if (! Cache::add($key, true, now()->addMinutes(self::QUIET_MINUTES))) {
            return;
        }

        $text = self::text($e);

        try {
            if ($webhook !== '') {
                $body = str_contains($webhook, 'discord') ? ['content' => mb_substr($text, 0, 1900)] : ['text' => $text];
                Http::timeout(4)->post($webhook, $body);
            }
            if ($email !== '') {
                Mail::raw($text, fn ($m) => $m->to($email)->subject('GIYA error: '.class_basename($e)));
            }
        } catch (Throwable) {
            // An alert must never cause a second error.
        }
    }

    public static function text(Throwable $e): string
    {
        // A web request has a route; an artisan command or queued job does not.
        $request = request()->route() ? request() : null;
        $file = str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile());

        return implode("\n", array_filter([
            '**GIYA error** on '.config('app.url').' ('.app()->environment().')',
            class_basename($e).': '.mb_substr($e->getMessage(), 0, 300),
            'at '.$file.':'.$e->getLine(),
            $request ? $request->method().' /'.ltrim($request->path(), '/') : 'console',
            Auth::id() ? 'user #'.Auth::id() : 'guest',
            now()->timezone('Asia/Manila')->format('M j, Y g:i A').' (Manila)',
        ]));
    }
}
