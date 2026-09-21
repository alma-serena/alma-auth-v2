<?php

declare(strict_types=1);

namespace Alma\Auth\Contracts;

use Alma\Auth\ValueObjects\OAuthUserInfo;

/**
 * Verifica una credencial emitida por el proveedor OAuth (access/id token).
 * El host puede adaptar Socialite u otro SDK; los tests usan Fake.
 */
interface OAuthIdentityVerifier
{
    /**
     * @param  array{access_token?: string, id_token?: string}  $credential
     *
     * @throws \RuntimeException When verification fails
     */
    public function verify(string $provider, array $credential): OAuthUserInfo;
}
