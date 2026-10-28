<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    /** Wrong passwords allowed per email + IP before a lockout. */
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 60;

    /**
     * The throttle lives here, not only on the route, because the Livewire
     * login form posts to /livewire/update and never touches POST /login.
     */
    public function execute(array $credentials, bool $remember, Request $request): User
    {
        $key = $this->throttleKey($credentials['email'] ?? '', $request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Try again in '
                    .RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Incorrect email or password.',
            ]);
        }

        RateLimiter::clear($key);

        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Contact the administrator.',
            ]);
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        $request->session()->regenerate();

        return $user;
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'login:'.Str::transliterate(Str::lower(trim($email))).'|'.$request->ip();
    }
}
