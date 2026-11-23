<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Appended to the web group so the locale is set before any view
        // renders - a devotee's saved language has to be in effect for the
        // very first string on the page, not from the next request on.
        // First of all: a visitor on an old link (the Render address) is sent
        // to the official one before anything else is worked out for them.
        $middleware->web(prepend: [
            \App\Http\Middleware\RedirectToOfficialSite::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Maya's server posts the webhook. It has no session and therefore no
        // CSRF token, so the check has to be lifted for that one path. What
        // replaces it is in PaymentController::webhook - the body is used only
        // to pick a reference number, and the payment is then re-fetched from
        // Maya with the secret key before anything is written.
        $middleware->validateCsrfTokens(except: [
            'maya/webhook',
        ]);

        // Trusted proxies (Vercel, Render, ngrok) are set in AppServiceProvider
        // from config('app.trusted_proxies'), so they survive config:cache.

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnsureAccountIsActive::class,
            // Admins only: signed out after 30 minutes without activity.
            \App\Http\Middleware\AdminIdleTimeout::class,
            // After the account check: a suspended admin is signed out first.
            \App\Http\Middleware\MaintenanceMode::class,
            \App\Http\Middleware\DeliverDueReminders::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'admin.log' => \App\Http\Middleware\LogAdminActivity::class,
            'confirmed.email' => \App\Http\Middleware\EnsureEmailConfirmed::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A page left open for hours sends an old form token. Instead of the
        // bare "Page Expired" screen: a fetch gets a JSON answer the page can
        // recover from (giya-session.js refreshes the token and tries again),
        // and a form goes back to its page, fresh, or to sign in and back.
        // (Laravel has already turned the token error into a 419 by this point.)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'reason' => 'session_expired', 'message' => __('giya.session.tap_again')], 419);
            }
            $back = url()->previous();
            if (! \Illuminate\Support\Facades\Auth::check()) {
                if (str_starts_with($back, url('/'))) {
                    $request->session()->put('url.intended', $back);
                }

                return redirect()->route('login')->with('warning', __('giya.session.sign_in_again'));
            }

            return redirect()->to($back)->with('warning', __('giya.session.tap_again'));
        });

        // Tell the team (Discord/Slack webhook or email) when the live site breaks.
        $exceptions->report(fn (\Throwable $e) => \App\Support\ErrorAlert::report($e));
    })->create();
