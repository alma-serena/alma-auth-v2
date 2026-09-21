<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\RefreshTokenRepository;
use Alma\Auth\Models\RefreshToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentRefreshTokenRepository implements RefreshTokenRepository
{
    public function create(array $data): string
    {
        $plain = Str::random(64);

        RefreshToken::query()->create([
            'user_id' => $data['user_id'],
            'family_id' => $data['family_id'],
            'token_hash' => hash('sha256', $plain),
            'device_fingerprint' => $data['device_fingerprint'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'expires_at' => $data['expires_at'],
            'family_created_at' => $data['family_created_at'] ?? now(),
            'revoked' => false,
        ]);

        return $plain;
    }

    public function findByPlainToken(string $plainToken): ?RefreshToken
    {
        return RefreshToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();
    }

    public function revokeFamily(string $familyId): void
    {
        RefreshToken::query()
            ->where('family_id', $familyId)
            ->update(['revoked' => true]);
    }

    public function rotate(string $oldPlainToken, string $deviceFingerprint): string
    {
        return DB::transaction(function () use ($oldPlainToken, $deviceFingerprint) {
            /** @var RefreshToken|null $stored */
            $stored = RefreshToken::query()
                ->where('token_hash', hash('sha256', $oldPlainToken))
                ->lockForUpdate()
                ->first();

            if ($stored === null) {
                throw new \RuntimeException('invalid_refresh_token');
            }

            if ($stored->revoked || $stored->expires_at === null || $stored->expires_at->isPast()) {
                $this->revokeFamily($stored->family_id);
                throw new \RuntimeException('refresh_reuse_or_expired');
            }

            if (
                $deviceFingerprint !== ''
                && $stored->device_fingerprint
                && $stored->device_fingerprint !== $deviceFingerprint
            ) {
                $this->revokeFamily($stored->family_id);
                throw new \RuntimeException('device_fingerprint_mismatch');
            }

            $familyTtl = (int) config('alma-auth.refresh_family_ttl_days', 90);
            $familyCreated = $stored->family_created_at ?? $stored->created_at;
            if ($familyCreated !== null && $familyCreated->diffInDays(now()) >= $familyTtl) {
                $this->revokeFamily($stored->family_id);
                throw new \RuntimeException('refresh_family_expired');
            }

            $stored->update(['revoked' => true]);

            $tokenTtl = (int) config('alma-auth.refresh_token_ttl_days', 30);

            return $this->create([
                'user_id' => $stored->user_id,
                'family_id' => $stored->family_id,
                'device_fingerprint' => $deviceFingerprint !== '' ? $deviceFingerprint : $stored->device_fingerprint,
                'expires_at' => now()->addDays($tokenTtl),
                'family_created_at' => $familyCreated,
            ]);
        });
    }

    public function revokeAllForUser(int|string $userId): int
    {
        return RefreshToken::query()
            ->where('user_id', $userId)
            ->where('revoked', false)
            ->update(['revoked' => true]);
    }
}
