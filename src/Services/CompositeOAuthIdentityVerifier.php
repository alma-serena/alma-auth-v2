<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\OAuthIdentityVerifier;
use Alma\Auth\ValueObjects\OAuthUserInfo;

/**
 * Delega por nombre de proveedor. Proveedor sin adaptador → fallo.
 *
 * @param  array<string, OAuthIdentityVerifier>  $verifiers
 */
final class CompositeOAuthIdentityVerifier implements OAuthIdentityVerifier
{
    /**
     * @param  array<string, OAuthIdentityVerifier>  $verifiers
     */
    public function __construct(
        private array $verifiers,
    ) {}

    public function verify(string $provider, array $credential): OAuthUserInfo
    {
        $key = strtolower(trim($provider));
        $verifier = $this->verifiers[$key] ?? null;
        if ($verifier === null) {
            throw new \RuntimeException('oauth_provider_unsupported');
        }

        return $verifier->verify($key, $credential);
    }
}
