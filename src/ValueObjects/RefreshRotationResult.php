<?php

declare(strict_types=1);

namespace Alma\Auth\ValueObjects;

final class RefreshRotationResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $refreshToken = null,
        public readonly int|string|null $userId = null,
        public readonly ?string $failure = null,
    ) {}

    public static function ok(string $refreshToken, int|string $userId): self
    {
        return new self(success: true, refreshToken: $refreshToken, userId: $userId);
    }

    public static function failed(string $failure): self
    {
        return new self(success: false, failure: $failure);
    }
}
