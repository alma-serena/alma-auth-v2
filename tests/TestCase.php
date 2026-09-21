<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\AuthServiceProvider;
use Alma\Auth\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config([
            'alma-auth.user_model' => User::class,
            'alma-auth.lockout_max_attempts' => 5,
            'alma-auth.lockout_decay_minutes' => 15,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.locale' => 'es',
            'app.fallback_locale' => 'en',
            'cache.default' => 'array',
        ]);
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            AuthServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
