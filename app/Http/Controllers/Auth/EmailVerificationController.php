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

    /** After sign-up, or a refused sign-in: "check your email", no account needed. */
    public function pending(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('verify_email');

        if (! $email) {
            return redirect()->route('login');
        }

        return view('auth.check-email', ['email' => $email]);
    }

    /** Send the link again to the address from sign-up (only while unconfirmed). */
    public function resendPending(Request $request): RedirectResponse
    {
        $user = \App\Models\User::where('email', (string) $request->session()->get('verify_email'))->first();

        if ($user && $user->email_verified_at === null) {
            $this->verification->send($user)
                ? $request->session()->flash('success', __('giya.verify.sent'))
                : $request->session()->flash('error', __('giya.verify.not_sent'));
        }

        return redirect()->route('verification.pending');
    }

    /** The link in the email. Works signed in or not. */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        if (! $this->verification->verify($id, $hash)) {
            return redirect()->route($request->user() ? 'home' : 'login')->with('error', __('giya.verify.bad_link'));
        }

        $request->session()->forget('verify_email');

        return redirect()->route($request->user() ? 'home' : 'login')->with('success', __('giya.verify.done'));
    }
}
