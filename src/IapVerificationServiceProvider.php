<?php

namespace Asteriskpound\LaravelIapVerification;

use Illuminate\Support\ServiceProvider;

class IapVerificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/iap-verification.php', 'iap-verification');

        $this->app->singleton(IapVerification::class, fn ($app) => new IapVerification(
            $app->make(AppleVerifier::class),
            $app->make(GoogleVerifier::class),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('iap-verification.webhooks.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        }

        $this->publishes([
            __DIR__.'/../config/iap-verification.php' => config_path('iap-verification.php'),
        ], 'iap-verification-config');
    }
}
