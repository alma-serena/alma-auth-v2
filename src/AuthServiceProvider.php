<?php

declare(strict_types=1);

namespace Alma\Auth;

use Alma\Auth\Services\AuthService;
use Illuminate\Support\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/alma-auth.php', 'alma-auth');
        $this->app->singleton(AuthService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'alma-auth');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/alma-auth.php' => config_path('alma-auth.php'),
            ], 'alma-auth-config');
        }
    }
}
