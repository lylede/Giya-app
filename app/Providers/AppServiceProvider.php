<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /* Livewire's CSP-safe build: the sign-in, sign-up and password forms
           work under the Content-Security-Policy without allowing eval. */
        config(['livewire.csp_safe' => config('app.csp', 'enforce') !== 'off']);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /* Livewire's script lives at one address whichever build it serves,
           and browsers keep it for a year. Chrome that had visited GIYA
           before the Content-Security-Policy kept running the old build,
           which the policy blocks, so signing in spun forever. The CSP-safe
           build gets its own file name, so every browser downloads it. */
        if (config('livewire.csp_safe')) {
            \Livewire\Livewire::setScriptRoute(function ($handle) {
                $file = config('app.debug') ? 'livewire.csp.js' : 'livewire.csp.min.js';

                return \Illuminate\Support\Facades\Route::get(\Livewire\Mechanisms\HandleRequests\EndpointResolver::prefix().'/'.$file, $handle);
            });
        }

        /* Hosting: see config/app.php. Set here rather than in bootstrap/app.php
           because env() there is not reliable once the config is cached. */
        if ($proxies = config('app.trusted_proxies')) {
            \Illuminate\Http\Middleware\TrustProxies::at(
                $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies))
            );
        }

        if (config('app.force_https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        /* Rate limits (security checklist). Giya AI calls Groq, whose free
           allowance is shared by every user: one person or script sending
           messages in a loop could use it all up. Over the limit, the chat
           shows Giya resting, the same as when Groq itself is busy. */
        \Illuminate\Support\Facades\RateLimiter::for('giya-chat', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(12)
                ->by('chat:'.($request->user()?->id ?: $request->ip()))
                ->response(fn ($r, array $headers) => response()->json([
                    'ok'          => false,
                    'reason'      => 'busy',
                    'mood'        => 'tired',
                    'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                    'reply'       => __('giya.chat.busy', ['seconds' => (int) ($headers['Retry-After'] ?? 60)]),
                ], 429, $headers));
        });

        // Everyday actions: generous for people, a wall for scripts.
        \Illuminate\Support\Facades\RateLimiter::for('giya-actions', fn (\Illuminate\Http\Request $request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('act:'.($request->user()?->id ?: $request->ip())));

        /* Giya AI caches what it knows about the churches for six hours. Any
           change to a church or a schedule clears that cache, so an admin who
           fixes a mass time or adds a history sees the assistant use it on the
           very next question - not up to six hours later. */
        foreach ([\App\Models\Church::class, \App\Models\Schedule::class] as $model) {
            $model::saved(fn () => \App\Services\GiyaAssistant::forget());
            $model::deleted(fn () => \App\Services\GiyaAssistant::forget());
        }
    }
}
