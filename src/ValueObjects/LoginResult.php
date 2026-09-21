<?php

declare(strict_types=1);

namespace Alma\Auth\ValueObjects;

use Alma\Auth\Contracts\AuthenticatableUser;

final class LoginResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?AuthenticatableUser $user = null,
        public readonly bool $requiresTwoFactor = false,
    ) {}

    public static function authenticated(AuthenticatableUser $user): self
    {
        return new self(success: true, user: $user, requiresTwoFactor: false);
    }

    public static function requiresTwoFactor(AuthenticatableUser $user): self
    {
        return new self(success: true, user: $user, requiresTwoFactor: true);
    }

    public static function invalid(): self
    {
        return new self(success: false);
    }
}
