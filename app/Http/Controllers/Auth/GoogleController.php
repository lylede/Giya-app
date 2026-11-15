<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EmailVerification;
use App\Actions\Auth\LoginAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;

class GoogleController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = $googleUser->getEmail();

            if (
                ! $email
                || ! filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 150
                || ! ($googleUser->user['email_verified'] ?? false)
            ) {
                throw new RuntimeException('Google did not provide a verified email address.');
            }

            $user = User::where('email', $email)->first();

            if ($user?->isSuspended()) {
                return redirect('/login')->with('error', 'This account has been suspended. Contact the administrator.');
            }

            if (! $user) {
                $name = trim((string) $googleUser->getName());

                $userData = [
                    'name' => mb_substr($name !== '' ? $name : Str::before($email, '@'), 0, 100),
                    'email' => $email,
                    'role' => 'user',
                    'status' => 'Active',
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $passwordHash = Hash::make(Str::random(40));

                if (Schema::hasColumn('devotees', 'password_hash')) {
                    $userData['password_hash'] = $passwordHash;
                } elseif (Schema::hasColumn('devotees', 'password')) {
                    $userData['password'] = $passwordHash;
                } else {
                    throw new RuntimeException('The devotees table has no supported password column.');
                }

                $user = User::create($userData);
            } elseif (EmailVerification::enabled() && $user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->saveQuietly();
            }

            Auth::login($user);
            request()->session()->regenerate();
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();

            return redirect()->to(LoginAction::destination($user));
        } catch (Exception $e) {
            report($e);

            return redirect('/login')->with('error', 'Google auth failed.');
        }
    }
}
