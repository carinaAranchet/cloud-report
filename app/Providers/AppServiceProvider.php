<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use App\Services\Socialite\NextcloudProvider;

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
        Socialite::extend('nextcloud', function ($app) {
        $cfg = $app['config']['services.oidc']; // <-- MISMA CLAVE
        return new NextcloudProvider(
            $app['request'],
            $cfg['client_id'],
            $cfg['client_secret'],
            $cfg['redirect']
        );
    });
    }
}
