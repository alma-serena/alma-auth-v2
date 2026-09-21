<?php

declare(strict_types=1);

namespace Alma\Auth\Contracts;

use Alma\Auth\Models\RefreshToken;

interface RefreshTokenRepository
{
    /**
     * @param  array{user_id: int|string, family_id: string, device_fingerprint?: ?string, ip_address?: ?string, expires_at: \DateTimeInterface, family_created_at?: \DateTimeInterface}  $data
     * @return string Plain refresh token (shown once)
     */
    public function create(array $data): string;

    public function findByPlainToken(string $plainToken): ?RefreshToken;

    public function revokeFamily(string $familyId): void;

    /**
     * @return string New plain refresh token
     *
     * @throws \RuntimeException When rotation is rejected
     */
    public function rotate(string $oldPlainToken, string $deviceFingerprint): string;
}
