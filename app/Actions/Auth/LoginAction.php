<?php

namespace App\Actions\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginAction
{
    public function execute(array $credentials, bool $remember, Request $request): User
    {
        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Incorrect email or password.',
            ]);
        }

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
}
