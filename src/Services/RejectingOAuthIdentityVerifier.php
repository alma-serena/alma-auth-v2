<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\OAuthIdentityVerifier;
use Alma\Auth\ValueObjects\OAuthUserInfo;

/** Default de producción hasta que el host registre un verificador real. */
final class RejectingOAuthIdentityVerifier implements OAuthIdentityVerifier
{
    public function verify(string $provider, array $credential): OAuthUserInfo
    {
        throw new \RuntimeException('oauth_verifier_not_configured');
    }
}
