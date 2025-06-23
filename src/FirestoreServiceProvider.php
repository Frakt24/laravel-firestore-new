<?php

namespace Frakt24\LaravelFirestore;

use Illuminate\Support\ServiceProvider;
use Frakt24\LaravelFirestore\Firestore;

class FirestoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/firestore.php', 'laravel-firestore'
        );

        $this->app->singleton('firestore', function ($app) {
            return new Firestore($app['config']['laravel-firestore']);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/firestore.php' => config_path('laravel-firestore.php'),
        ], 'laravel-firestore-config');
    }
}
