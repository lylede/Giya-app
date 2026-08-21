<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150', 'unique:devotees,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required'      => 'Full name is required.',
            'email.unique'       => 'This email address is already registered.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        $user = app(RegisterAction::class)->execute($data, $request);

        return redirect()->route('home')
            ->with('success', "Welcome to GIYA, {$user->firstName()}!");
    }
}
