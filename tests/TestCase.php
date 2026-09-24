<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\AuthServiceProvider;
use Alma\Auth\Contracts\CompromisedPasswordChecker;
use Alma\Auth\Contracts\OAuthIdentityVerifier;
use Alma\Auth\Contracts\PasskeyCeremony;
use Alma\Auth\Services\FakeOAuthIdentityVerifier;
use Alma\Auth\Services\FakePasskeyCeremony;
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

        $this->app->instance(PasskeyCeremony::class, new FakePasskeyCeremony);
        $this->app->instance(OAuthIdentityVerifier::class, new FakeOAuthIdentityVerifier);
        $this->app->instance(CompromisedPasswordChecker::class, new class implements CompromisedPasswordChecker
        {
            public function isCompromised(string $password): bool
            {
                return $password === 'compromised-password';
            }
        });

        config([
            'alma-auth.user_model' => User::class,
            'alma-auth.lockout_max_attempts' => 5,
            'alma-auth.lockout_decay_minutes' => 15,
            'alma-auth.hmac_key' => str_repeat('a', 32),
            'alma-auth.passkey_rp_id' => 'localhost',
            'alma-auth.passkey_origins' => ['http://localhost'],
            'alma-auth.oauth_providers' => ['google', 'apple', 'github'],
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
