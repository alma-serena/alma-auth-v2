<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\OAuthIdentityVerifier;
use Alma\Auth\ValueObjects\OAuthUserInfo;

/**
 * Verificador de tests. Credencial esperada:
 * access_token = "fake|{provider}|{provider_user_id}|{email?}"
 */
final class FakeOAuthIdentityVerifier implements OAuthIdentityVerifier
{
    public function verify(string $provider, array $credential): OAuthUserInfo
    {
        $token = (string) ($credential['access_token'] ?? $credential['id_token'] ?? '');
        $parts = explode('|', $token);

        if (count($parts) < 3 || $parts[0] !== 'fake' || $parts[1] !== $provider || $parts[2] === '') {
            throw new \RuntimeException('oauth_token_invalid');
        }

        return new OAuthUserInfo(
            provider: $provider,
            providerUserId: $parts[2],
            email: $parts[3] ?? null,
        );
    }
}
