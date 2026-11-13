<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Email address is required.',
            'email.email'       => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ]);

        try {
            $user = app(LoginAction::class)->execute($credentials, $request->boolean('remember'), $request);
        } catch (ValidationException $exception) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors($exception->errors());
        }

        // Back to the page that asked them to sign in (a church, a shared
        // route), so they are not left wondering where the thing they clicked
        // went; never to an admin page unless they are an admin.
        return redirect()->to(LoginAction::destination($user))
            ->with('success', 'Welcome back, '.$user->firstName().'!');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('root')->with('success', 'You have been signed out.');
    }
}
