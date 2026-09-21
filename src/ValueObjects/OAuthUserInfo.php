<?php

declare(strict_types=1);

namespace Alma\Auth\ValueObjects;

final class OAuthUserInfo
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerUserId,
        public readonly ?string $email = null,
    ) {}
}
