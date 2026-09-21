<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\AuthenticatableUser;
use Alma\Auth\Contracts\RefreshTokenRepository;
use Alma\Auth\ValueObjects\LoginResult;
use Alma\Auth\ValueObjects\RefreshRotationResult;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class AuthService
{
    private static ?string $dummyPasswordHash = null;

    public function __construct(
        private RefreshTokenRepository $refreshTokens,
    ) {}

    public function attemptLogin(string $email, string $password): LoginResult
    {
        $user = $this->resolveUser($email);

        if ($user === null) {
            $this->consumeDummyHash($password);

            return LoginResult::invalid();
        }

        if (! Hash::check($password, $user->getAuthPassword())) {
            return LoginResult::invalid();
        }

        if ($user->hasTwoFactorEnabled()) {
            return LoginResult::requiresTwoFactor($user);
        }

        return LoginResult::authenticated($user);
    }

    /**
     * Genera secreto TOTP y lo guarda cifrado. 2FA sigue deshabilitado hasta confirm.
     */
    public function startTwoFactorEnrollment(AuthenticatableUser $user): string
    {
        $secret = (new Google2FA)->generateSecretKey(32);
        $user->setTwoFactorSecret(Crypt::encryptString($secret));
        $user->setTwoFactorEnabled(false);

        return $secret;
    }

    public function confirmTwoFactorEnrollment(AuthenticatableUser $user, string $code): bool
    {
        $encrypted = $user->getTwoFactorSecret();
        if ($encrypted === null || $user->hasTwoFactorEnabled()) {
            return false;
        }

        $secret = Crypt::decryptString($encrypted);
        if (! $this->verifyTotp($secret, $code)) {
            return false;
        }

        $user->setTwoFactorEnabled(true);

        return true;
    }

    public function verifyTwoFactorChallenge(AuthenticatableUser $user, string $code): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        $encrypted = $user->getTwoFactorSecret();
        if ($encrypted === null) {
            return false;
        }

        return $this->verifyTotp(Crypt::decryptString($encrypted), $code);
    }

    public function issueRefreshToken(
        AuthenticatableUser $user,
        string $deviceFingerprint = '',
        ?string $ipAddress = null,
    ): string {
        $ttl = (int) config('alma-auth.refresh_token_ttl_days', 30);

        return $this->refreshTokens->create([
            'user_id' => $user->getAuthIdentifier(),
            'family_id' => (string) Str::uuid(),
            'device_fingerprint' => $deviceFingerprint !== '' ? $deviceFingerprint : null,
            'ip_address' => $ipAddress,
            'expires_at' => now()->addDays($ttl),
            'family_created_at' => now(),
        ]);
    }

    public function rotateRefreshToken(string $plainToken, string $deviceFingerprint = ''): RefreshRotationResult
    {
        $stored = $this->refreshTokens->findByPlainToken($plainToken);

        if ($stored === null) {
            return RefreshRotationResult::failed('invalid_refresh_token');
        }

        if ($stored->revoked) {
            $this->refreshTokens->revokeFamily($stored->family_id);

            return RefreshRotationResult::failed('refresh_reuse_detected');
        }

        try {
            $newPlain = $this->refreshTokens->rotate($plainToken, $deviceFingerprint);

            return RefreshRotationResult::ok($newPlain, $stored->user_id);
        } catch (\RuntimeException $e) {
            return RefreshRotationResult::failed($e->getMessage());
        }
    }

    private function resolveUser(string $email): ?AuthenticatableUser
    {
        /** @var class-string<AuthenticatableUser>|null $model */
        $model = config('alma-auth.user_model');
        if ($model === null || $model === '') {
            throw new \RuntimeException('Config alma-auth.user_model is required.');
        }

        $user = $model::query()->where('email', $email)->first();

        return $user instanceof AuthenticatableUser ? $user : null;
    }

    public function resolveUserById(int|string $id): ?AuthenticatableUser
    {
        /** @var class-string<AuthenticatableUser>|null $model */
        $model = config('alma-auth.user_model');
        if ($model === null || $model === '') {
            throw new \RuntimeException('Config alma-auth.user_model is required.');
        }

        $user = $model::query()->find($id);

        return $user instanceof AuthenticatableUser ? $user : null;
    }

    private function consumeDummyHash(string $password): void
    {
        if (self::$dummyPasswordHash === null) {
            self::$dummyPasswordHash = Hash::make('alma-auth-dummy-password');
        }

        Hash::check($password, self::$dummyPasswordHash);
    }

    private function verifyTotp(string $secret, string $code): bool
    {
        return (new Google2FA)->verifyKey($secret, $code);
    }
}
