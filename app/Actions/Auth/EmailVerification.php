<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Confirm-your-email for new accounts and changed addresses.
 *
 * Off until GIYA_VERIFY_EMAIL=true, because it needs the live site to send
 * email: switched on without working mail, nobody new could use Giya AI or
 * save a plan. Test "Forgot password" on the live site first.
 *
 * The link is signed and lasts 24 hours. It carries the account id and a
 * hash of the address, so a link for an old address stops working once
 * the email is changed.
 */
class EmailVerification
{
    public const HOURS = 24;

    public static function enabled(): bool
    {
        return (bool) config('app.verify_email');
    }

    public static function pending(?User $user): bool
    {
        return self::enabled() && $user && $user->email_verified_at === null;
    }

    public function link(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHours(self::HOURS), [
            'id' => $user->id, 'hash' => sha1(strtolower($user->email)),
        ]);
    }

    /** Sends the link. Returns false when the email could not be sent. */
    public function send(User $user): bool
    {
        try {
            Mail::send('emails.verify-email', ['url' => $this->link($user), 'name' => $user->firstName()],
                fn ($m) => $m->to($user->email)->subject('GIYA - Confirm your email'));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Marks the account confirmed if the link matches its current address. */
    public function verify(int $id, string $hash): bool
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1(strtolower($user->email)), $hash)) {
            return false;
        }

        if ($user->email_verified_at === null) {
            User::whereKey($user->id)->update(['email_verified_at' => now()]);
        }

        return true;
    }
}
