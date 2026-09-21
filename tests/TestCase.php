<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\AuthServiceProvider;
use Alma\Auth\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'alma-auth.user_model' => User::class,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.locale' => 'es',
            'app.fallback_locale' => 'en',
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
    }
}
