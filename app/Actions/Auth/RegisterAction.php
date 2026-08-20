<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function execute(array $data, Request $request): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role' => 'user',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $user;
    }
}
