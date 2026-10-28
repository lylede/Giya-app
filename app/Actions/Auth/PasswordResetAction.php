<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Forgotten passwords - the one place the rules live.
 *
 * Both the controller and the Livewire component call this, so a fix here is
 * a fix everywhere. (The expiry bug below existed twice because the Livewire
 * component used to carry its own copy.)
 */
class PasswordResetAction
{
    public const TOKEN_TTL_MINUTES = 60;

    /** Reset emails one address + IP may request per window. */
    public const MAX_REQUESTS = 3;

    public const WINDOW_SECONDS = 600;

    /**
     * Email a reset link - if the address belongs to an account.
     *
     * Returns normally whether or not the account exists, so the caller shows
     * the same message either way. Telling a stranger "no account with that
     * email" lets them test a list of addresses against GIYA one by one.
     *
     * @throws ValidationException when the address has asked too often
     * @throws RuntimeException    when the mail could not be sent
     */
    public function sendLink(string $email, ?string $ip = null): void
    {
        $email = Str::lower(trim($email));
        $key   = 'password-reset:'.sha1($email.'|'.$ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS)) {
            throw ValidationException::withMessages([
                'email' => 'Too many reset requests. Try again in '
                    .ceil(RateLimiter::availableIn($key) / 60).' minute(s).',
            ]);
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        if (! User::whereRaw('lower(email) = ?', [$email])->exists()) {
            return;
        }

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email'      => $email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $resetUrl = route('password.reset', ['token' => $token]).'?email='.urlencode($email);

        try {
            Mail::send(
                'emails.reset-password',
                ['resetUrl' => $resetUrl, 'email' => $email],
                fn ($message) => $message->to($email)->subject('GIYA - Password Reset Request')
            );
        } catch (\Throwable $exception) {
            report($exception);
            throw new RuntimeException('Mail could not be sent.', previous: $exception);
        }
    }

    /**
     * Set a new password from a reset link.
     *
     * @throws RuntimeException with a message safe to show the devotee
     */
    public function reset(string $token, string $email, string $password): User
    {
        $email  = Str::lower(trim($email));
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            throw new RuntimeException('This reset link is invalid. Please request a new one.');
        }

        /* Measured as "created + TTL is in the past" rather than with
           diffInMinutes(). Carbon 3 (Laravel 11+) returns a SIGNED difference,
           so now()->diffInMinutes($past) is negative and "> 60" was never true:
           every link ever sent stayed valid forever. */
        if (self::isExpired($record->created_at)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw new RuntimeException('This reset link has expired. Please request a new one.');
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if (! $user) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw new RuntimeException('This reset link is invalid. Please request a new one.');
        }

        DB::transaction(function () use ($user, $password, $email) {
            $user->forceFill([
                'password_hash' => Hash::make($password),
                'updated_at'    => now(),
            ])->save();

            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });

        // Whoever had the old password is signed out everywhere.
        $user->endOtherSessions();

        return $user;
    }

    public static function sentMessage(): string
    {
        return 'If a GIYA account uses that email, a reset link is on its way. It expires in '
            .self::TOKEN_TTL_MINUTES.' minutes.';
    }

    /** Only points at the log file on a developer's machine. */
    public static function mailFailedMessage(): string
    {
        return app()->isLocal()
            ? 'Mail could not be sent. Your reset link was written to storage/logs/laravel.log.'
            : 'We could not send the email just now. Please try again in a few minutes.';
    }

    public static function isExpired(mixed $createdAt): bool
    {
        return Carbon::parse($createdAt)
            ->addMinutes(self::TOKEN_TTL_MINUTES)
            ->isPast();
    }
}
