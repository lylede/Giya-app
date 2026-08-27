<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    public function showForm()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(
            ['email' => ['required', 'email', 'exists:users,email']],
            ['email.exists' => 'No account found with that email address.']
        );

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => bcrypt($token),
            'created_at' => Carbon::now(),
        ]);

        $resetUrl = url('/reset-password/' . $token . '?email=' . urlencode($request->email));

        Mail::send(
            'emails.reset-password',
            ['resetUrl' => $resetUrl, 'email' => $request->email],
            fn($m) => $m->to($request->email)->subject('GIYA - Password Reset')
        );

        return back()->with('success', 'Reset link sent to ' . $request->email . '. Check your inbox.');
    }
}