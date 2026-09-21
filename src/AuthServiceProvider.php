<?php

declare(strict_types=1);

namespace Alma\Auth;

use Alma\Auth\Contracts\AuditLogger;
use Alma\Auth\Contracts\PasskeyCeremony;
use Alma\Auth\Contracts\RefreshTokenRepository;
use Alma\Auth\Http\Middleware\RequiresRecentAuth;
use Alma\Auth\Services\AuthService;
use Alma\Auth\Services\EloquentRefreshTokenRepository;
use Alma\Auth\Services\WebAuthnPasskeyCeremony;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/alma-auth.php', 'alma-auth');
        $this->app->singleton(RefreshTokenRepository::class, EloquentRefreshTokenRepository::class);
        $this->app->singleton(PasskeyCeremony::class, WebAuthnPasskeyCeremony::class);
        $this->app->singleton(AuthService::class);
        $this->app->singleton(AuditLogger::class, fn ($app) => $app->make(AuthService::class));
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('alma.recent', RequiresRecentAuth::class);

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'alma-auth');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/alma-auth.php' => config_path('alma-auth.php'),
            ], 'alma-auth-config');
        }
    }
}
