<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Maintenance mode: the site is closed to devotees while the team works on
 * it, and admins carry on as usual.
 *
 * Laravel's own "php artisan down" writes a file on the server. Render wipes
 * that disk on every deploy and gives no terminal on the free plan, so the
 * switch lives in the database and is turned from the admin panel.
 */
class Maintenance
{
    public const DEFAULT_MESSAGE = 'We are making GIYA better. Please come back in a little while.';

    /** Pages a visitor still needs while the site is closed. */
    private const OPEN_PATHS = [
        'login', 'logout', 'forgot-password', 'reset-password', 'reset-password/*',
        'maya/webhook', 'offline', 'up',
    ];

    public static function on(): bool
    {
        return SiteSetting::read('maintenance') === 'on';
    }

    public static function message(): string
    {
        return trim((string) SiteSetting::read('maintenance_message')) ?: self::DEFAULT_MESSAGE;
    }

    /** When the team expects to reopen; null when not set or already past. */
    public static function backBy(): ?Carbon
    {
        $value = SiteSetting::read('maintenance_back_by');

        if (! $value) {
            return null;
        }

        try {
            $when = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $when->isFuture() ? $when : null;
    }

    /** Should this request get the "closed" page? */
    public static function blocks(Request $request): bool
    {
        if (! self::on() || $request->user()?->isAdmin()) {
            return false;
        }

        if ($request->is(...self::OPEN_PATHS)) {
            return false;
        }

        // The sign-in form is a Livewire form. Guests need it so an admin can
        // get in; a devotee who is already signed in does not.
        $livewire = trim(\Livewire\Mechanisms\HandleRequests\EndpointResolver::prefix(), '/');

        if ($request->user() === null && ($request->is($livewire, $livewire.'/*'))) {
            return false;
        }

        return true;
    }
}
