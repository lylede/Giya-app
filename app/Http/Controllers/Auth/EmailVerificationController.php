<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EmailVerification;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function __construct(protected EmailVerification $verification)
    {
    }

    /** "Check your email", with a button to send the link again. */
    public function notice(Request $request): View|RedirectResponse
    {
        if (! EmailVerification::pending($request->user())) {
            return redirect()->route('home');
        }

        return view('auth.verify-email', ['email' => $request->user()->email]);
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! EmailVerification::pending($request->user())) {
            return redirect()->route('home');
        }

        return $this->verification->send($request->user())
            ? back()->with('success', __('giya.verify.sent'))
            : back()->with('error', __('giya.verify.not_sent'));
    }

    /** The link in the email. Works signed in or not. */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        if (! $this->verification->verify($id, $hash)) {
            return redirect()->route($request->user() ? 'home' : 'login')->with('error', __('giya.verify.bad_link'));
        }

        return redirect()->route($request->user() ? 'home' : 'login')->with('success', __('giya.verify.done'));
    }
}
