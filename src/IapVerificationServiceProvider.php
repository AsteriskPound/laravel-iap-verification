<?php

namespace Asteriskpound\LaravelIapVerification;

use Google\AccessToken\Verify;
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

        // Built directly so the container doesn't autowire its optional PSR-6
        // cache argument, which Laravel aliases to symfony/cache's Psr16Adapter.
        $this->app->bind(Verify::class, fn () => new Verify);
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
