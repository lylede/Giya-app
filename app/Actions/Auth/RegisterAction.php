<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RegisterAction
{
    /**
     * Accounts one IP may create per hour. Generous on purpose: a school lab
     * or a parish office shares one public IP. It is here to stop a script,
     * not a room of people signing up.
     */
    public const MAX_PER_HOUR = 20;

    public function execute(array $data, Request $request): User
    {
        $key = 'register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages([
                'email' => 'Too many new accounts from this network. Try again later.',
            ]);
        }

        RateLimiter::hit($key, 3600);

        return User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role'          => 'user',
            'status'        => 'Active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
