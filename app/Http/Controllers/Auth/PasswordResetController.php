<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\PasswordResetAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:devotees,email'],
        ], [
            'email.exists' => 'No GIYA account is registered with that email address.',
        ]);

        try {
            app(PasswordResetAction::class)->sendLink($request->email);
        } catch (\Throwable $exception) {
            return back()->with('warning',
                'Mail could not be sent. Your reset link was written to storage/logs/laravel.log.');
        }

        return back()->with('success',
            "A reset link has been sent to {$request->email}. It expires in " . PasswordResetAction::TOKEN_TTL_MINUTES . ' minutes.');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'exists:devotees,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Passwords do not match.',
        ]);

        try {
            app(PasswordResetAction::class)->reset(
                $request->token,
                $request->email,
                $request->password
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['token' => $exception->getMessage()]);
        }

        return redirect()->route('login')
            ->with('success', 'Password reset successfully. Please sign in.');
    }
}
