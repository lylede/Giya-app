<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class PasswordResetAction
{
    public const TOKEN_TTL_MINUTES = 60;

    public function sendLink(string $email): void
    {
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $resetUrl = route('password.reset', ['token' => $token]).'?email='.urlencode($email);

        try {
            Mail::send(
                'emails.reset-password',
                ['resetUrl' => $resetUrl, 'email' => $email],
                fn ($message) => $message->to($email)->subject('GIYA — Password Reset Request')
            );
        } catch (\Throwable $exception) {
            report($exception);
            throw new RuntimeException(
                'Mail could not be sent. Your reset link was written to storage/logs/laravel.log.',
                previous: $exception
            );
        }
    }

    public function reset(string $token, string $email, string $password): void
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            throw new RuntimeException('This reset link is invalid. Please request a new one.');
        }

        if (now()->diffInMinutes($record->created_at) > self::TOKEN_TTL_MINUTES) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw new RuntimeException('This reset link has expired. Please request a new one.');
        }

        User::where('email', $email)->update([
            'password_hash' => Hash::make($password),
            'updated_at' => now(),
        ]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }
}
