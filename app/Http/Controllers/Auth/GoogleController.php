<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Continue with Google, for Sign In and Sign Up alike.
 *
 * Google proves the person owns the address, so a Google sign-in counts as
 * a confirmed email. An existing account with the same address is signed
 * in; otherwise a new pilgrim account is made, with a random password they
 * never see (they can set one later with Forgot password).
 *
 * The keys live only in the environment (GOOGLE_CLIENT_ID,
 * GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI), never in the repository.
 */
class GoogleController extends Controller
{
    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectToGoogle(): RedirectResponse
    {
        if (! self::configured()) {
            return redirect()->route('login')->with('error', __('giya.auth.google_off'));
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        // "Cancel" on Google's screen comes back as ?error=access_denied.
        // That is the person changing their mind, not a fault to report.
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('login')->with('error', __('giya.auth.google_cancelled'));
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Mostly an expired or replayed state (the Back button, two tabs).
            report($e);

            return redirect()->route('login')->with('error', __('giya.auth.google_failed'));
        }

        $email = Str::lower(trim((string) $google->getEmail()));

        if ($email === '' || strlen($email) > 150 || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! ($google->user['email_verified'] ?? false)) {
            return redirect()->route('login')->with('error', __('giya.auth.google_unverified'));
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user?->isSuspended()) {
            return redirect()->route('login')->with('error', __('giya.auth.suspended'));
        }

        if (! $user) {
            $name = trim((string) $google->getName());

            $user = User::create([
                'name'              => mb_substr($name !== '' ? $name : Str::before($email, '@'), 0, 100),
                'email'             => $email,
                'password_hash'     => Hash::make(Str::random(40)),
                'role'              => 'user',
                'status'            => 'Active',
                'email_verified_at' => now(),
                // The Sign Up tab says continuing with Google accepts the
                // Terms and the Privacy Policy (RA 10173 consent).
                'terms_accepted_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            try {
                \App\Support\Notifier::welcome($user);
            } catch (Throwable $e) {
                report($e);
            }
        } elseif ($user->email_verified_at === null) {
            // Google has just confirmed the address.
            $user->forceFill(['email_verified_at' => now()])->saveQuietly();
        }

        // Pilgrims stay signed in; admins time out (AdminIdleTimeout).
        Auth::login($user, remember: ! $user->isAdmin());
        $request->session()->regenerate();
        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return redirect()->to(LoginAction::destination($user));
    }
}
