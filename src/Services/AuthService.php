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

    public function attemptLogin(string $email, string $password, string $ip = '0.0.0.0'): LoginResult
    {
        if ($this->isLockedOut($email, $ip)) {
            $this->consumeDummyHash($password);

            return LoginResult::invalid();
        }

        $user = $this->resolveUser($email);

        if ($user === null) {
            $this->consumeDummyHash($password);
            $this->registerFailedLogin($email, $ip);

            return LoginResult::invalid();
        }

        if (! Hash::check($password, $user->getAuthPassword())) {
            $this->registerFailedLogin($email, $ip);

            return LoginResult::invalid();
        }

        $this->clearLockout($email, $ip);

        if ($user->hasTwoFactorEnabled()) {
            return LoginResult::requiresTwoFactor($user);
        }

        return LoginResult::authenticated($user);
    }

    public function isLockedOut(string $email, string $ip): bool
    {
        $max = (int) config('alma-auth.lockout_max_attempts', 5);

        return $this->failedAttempts($email, $ip) >= $max;
    }

    public function registerFailedLogin(string $email, string $ip): void
    {
        $key = $this->lockoutCacheKey($email, $ip);
        $decay = (int) config('alma-auth.lockout_decay_minutes', 15);
        $attempts = (int) cache()->get($key, 0) + 1;
        cache()->put($key, $attempts, now()->addMinutes($decay));
    }

    public function clearLockout(string $email, string $ip): void
    {
        cache()->forget($this->lockoutCacheKey($email, $ip));
    }

    public function failedAttempts(string $email, string $ip): int
    {
        return (int) cache()->get($this->lockoutCacheKey($email, $ip), 0);
    }

    private function lockoutCacheKey(string $email, string $ip): string
    {
        return 'alma_auth_lockout:'.hash('sha256', strtolower($email).'|'.$ip);
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
