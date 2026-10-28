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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
