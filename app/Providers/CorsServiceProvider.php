<?php

namespace App\Providers;

use Fruitcake\Cors\CorsService;
use Illuminate\Support\ServiceProvider;

class CorsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CorsService::class, function ($app) {
            return new CorsService($app['config']->get('cors', []));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
