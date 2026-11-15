<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
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

    /** Wrong guesses allowed per code; after that a new code must be sent. */
    public const CODE_TRIES = 5;

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

    /**
     * Sends the link and a 6-digit code. Returns false when the email could
     * not be sent. Each send replaces the previous code.
     */
    public function send(User $user): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            User::whereKey($user->id)->update([
                'verify_code'            => Hash::make($code),
                'verify_code_expires_at' => now()->addHours(self::HOURS),
                'verify_code_attempts'   => 0,
            ]);
        } catch (\Throwable $e) {
            // Before "php artisan migrate" the code columns are missing; the link still works.
            report($e);
            $code = null;
        }

        $data = ['url' => $this->link($user), 'name' => $user->firstName(), 'code' => $code];

        try {
            // A plain-text copy beside the designed one: mail with both is less often taken for spam.
            Mail::send(['html' => 'emails.verify-email', 'text' => 'emails.verify-email-text'], $data,
                fn ($m) => $m->to($user->email)->subject('GIYA - Confirm your email'));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Confirms the account with the code from the email.
     *
     * @return string  'ok', 'wrong' (try again), or 'expired' (send a new one)
     */
    public function verifyCode(User $user, string $code): string
    {
        $code = preg_replace('/\D/', '', $code);
        $user = $user->fresh();

        if ($user->email_verified_at !== null) {
            return 'ok';
        }

        if (! $user->verify_code || ! $user->verify_code_expires_at || now()->greaterThan($user->verify_code_expires_at)
            || $user->verify_code_attempts >= self::CODE_TRIES) {
            return 'expired';
        }

        if (strlen($code) !== 6 || ! Hash::check($code, $user->verify_code)) {
            User::whereKey($user->id)->increment('verify_code_attempts');

            return $user->verify_code_attempts + 1 >= self::CODE_TRIES ? 'expired' : 'wrong';
        }

        User::whereKey($user->id)->update([
            'email_verified_at' => now(), 'verify_code' => null, 'verify_code_expires_at' => null, 'verify_code_attempts' => 0,
        ]);

        return 'ok';
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
