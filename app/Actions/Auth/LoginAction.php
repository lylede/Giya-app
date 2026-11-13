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

        // Email not confirmed yet (when verification is on): not signed in.
        // Only said after the right password, so it never reveals accounts.
        if (EmailVerification::pending($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('verify_email', $user->email);

            throw ValidationException::withMessages(['email' => __('giya.verify.login_blocked')]);
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        $request->session()->regenerate();

        return $user;
    }

    /**
     * Where to send someone who has just signed in: back to the page that
     * asked them to sign in (a church, a shared route), or to their start
     * page when there was none.
     *
     * An admin page is only a place to go back to for an admin. Otherwise a
     * pilgrim signing in on a browser that had last tried to open /admin was
     * sent there, and met "403 Administrator access only" with no way out.
     */
    public static function destination(User $user): string
    {
        $fallback = $user->isAdmin() ? route('admin.dashboard') : route('home');
        $intended = (string) session()->pull('url.intended', '');

        if (! str_starts_with($intended, url('/').'/')) {
            return $fallback;
        }

        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
        $adminPage = $path === '/admin' || str_starts_with($path, '/admin/');

        return $adminPage && ! $user->isAdmin() ? $fallback : $intended;
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'login:'.Str::transliterate(Str::lower(trim($email))).'|'.$request->ip();
    }
}
