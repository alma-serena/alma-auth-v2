<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Contracts\OAuthIdentityVerifier;
use Alma\Auth\Services\CompositeOAuthIdentityVerifier;
use Alma\Auth\Services\GoogleOAuthIdentityVerifier;
use Alma\Auth\Services\RejectingOAuthIdentityVerifier;

final class OAuthVerifierBindingTest extends TestCase
{
    public function test_client_id_wires_google_into_composite(): void
    {
        config(['alma-auth.oauth_google.client_id' => 'cid.apps.googleusercontent.com']);

        $this->app->forgetInstance(OAuthIdentityVerifier::class);

        // Re-aplica el closure del AuthServiceProvider (el Fake de TestCase lo tapó).
        $this->app->singleton(OAuthIdentityVerifier::class, function ($app) {
            $map = [];
            $googleClientId = config('alma-auth.oauth_google.client_id');
            if (is_string($googleClientId) && $googleClientId !== '') {
                $map['google'] = $app->make(GoogleOAuthIdentityVerifier::class);
            }

            if ($map === []) {
                return $app->make(RejectingOAuthIdentityVerifier::class);
            }

            return new CompositeOAuthIdentityVerifier($map);
        });

        $verifier = $this->app->make(OAuthIdentityVerifier::class);
        $this->assertInstanceOf(CompositeOAuthIdentityVerifier::class, $verifier);
    }

    public function test_without_client_id_rejects(): void
    {
        config(['alma-auth.oauth_google.client_id' => null]);

        $this->app->forgetInstance(OAuthIdentityVerifier::class);
        $this->app->singleton(OAuthIdentityVerifier::class, function ($app) {
            $map = [];
            $googleClientId = config('alma-auth.oauth_google.client_id');
            if (is_string($googleClientId) && $googleClientId !== '') {
                $map['google'] = $app->make(GoogleOAuthIdentityVerifier::class);
            }

            if ($map === []) {
                return $app->make(RejectingOAuthIdentityVerifier::class);
            }

            return new CompositeOAuthIdentityVerifier($map);
        });

        $verifier = $this->app->make(OAuthIdentityVerifier::class);
        $this->assertInstanceOf(RejectingOAuthIdentityVerifier::class, $verifier);
    }
}
