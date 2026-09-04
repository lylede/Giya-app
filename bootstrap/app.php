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

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
