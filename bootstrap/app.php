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

        // Behind ngrok / Cloudflare / a load balancer, every request appears
        // to come from the proxy unless it is trusted. That broke two things:
        // the Maya webhook IP filter dropped every real webhook, and the login
        // throttle counted all devotees as one IP. Unset = trust nothing.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies))
            );
        }

        $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountIsActive::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
