<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\AuditLogger;
use Alma\Auth\Contracts\AuthenticatableUser;
use Alma\Auth\Contracts\RefreshTokenRepository;
use Alma\Auth\Enums\AuthEventType;
use Alma\Auth\Models\AuditLog;
use Alma\Auth\ValueObjects\LoginResult;
use Alma\Auth\ValueObjects\RefreshRotationResult;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class AuthService implements AuditLogger
{
    private static ?string $dummyPasswordHash = null;

    public function __construct(
        private RefreshTokenRepository $refreshTokens,
    ) {}

    public function attemptLogin(string $email, string $password, string $ip = '0.0.0.0'): LoginResult
    {
        if ($this->isLockedOut($email, $ip)) {
            $this->consumeDummyHash($password);
            $this->log(AuthEventType::LoginFailed->value, [
                'email' => $email,
                'ip' => $ip,
                'reason' => 'locked_out',
            ]);

            return LoginResult::invalid();
        }

        $user = $this->resolveUser($email);

        if ($user === null) {
            $this->consumeDummyHash($password);
            $this->registerFailedLogin($email, $ip);
            $this->log(AuthEventType::LoginFailed->value, [
                'email' => $email,
                'ip' => $ip,
                'reason' => 'unknown_user',
            ]);

            return LoginResult::invalid();
        }

        if (! Hash::check($password, $user->getAuthPassword())) {
            $this->registerFailedLogin($email, $ip);
            $this->log(AuthEventType::LoginFailed->value, [
                'email' => $email,
                'ip' => $ip,
                'user_id' => $user->getAuthIdentifier(),
                'reason' => 'bad_password',
            ]);

            return LoginResult::invalid();
        }

        $this->clearLockout($email, $ip);

        if ($user->hasTwoFactorEnabled()) {
            $this->log(AuthEventType::LoginSucceeded->value, [
                'user_id' => $user->getAuthIdentifier(),
                'ip' => $ip,
                'requires_2fa' => true,
            ]);

            return LoginResult::requiresTwoFactor($user);
        }

        $this->log(AuthEventType::LoginSucceeded->value, [
            'user_id' => $user->getAuthIdentifier(),
            'ip' => $ip,
            'requires_2fa' => false,
        ]);

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
            $this->log(AuthEventType::TwoFactorFailed->value, [
                'user_id' => $user->getAuthIdentifier(),
                'reason' => 'enrollment_confirm',
            ]);

            return false;
        }

        $user->setTwoFactorEnabled(true);
        $this->log(AuthEventType::TwoFactorEnabled->value, [
            'user_id' => $user->getAuthIdentifier(),
        ]);

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

        $valid = $this->verifyTotp(Crypt::decryptString($encrypted), $code);
        $this->log(
            $valid ? AuthEventType::TwoFactorVerified->value : AuthEventType::TwoFactorFailed->value,
            ['user_id' => $user->getAuthIdentifier()],
        );

        return $valid;
    }

    public function issueRefreshToken(
        AuthenticatableUser $user,
        string $deviceFingerprint = '',
        ?string $ipAddress = null,
    ): string {
        $ttl = (int) config('alma-auth.refresh_token_ttl_days', 30);

        $plain = $this->refreshTokens->create([
            'user_id' => $user->getAuthIdentifier(),
            'family_id' => (string) Str::uuid(),
            'device_fingerprint' => $deviceFingerprint !== '' ? $deviceFingerprint : null,
            'ip_address' => $ipAddress,
            'expires_at' => now()->addDays($ttl),
            'family_created_at' => now(),
        ]);

        $this->log(AuthEventType::RefreshTokenIssued->value, [
            'user_id' => $user->getAuthIdentifier(),
        ]);

        return $plain;
    }

    public function rotateRefreshToken(string $plainToken, string $deviceFingerprint = ''): RefreshRotationResult
    {
        $stored = $this->refreshTokens->findByPlainToken($plainToken);

        if ($stored === null) {
            return RefreshRotationResult::failed('invalid_refresh_token');
        }

        if ($stored->revoked) {
            $this->refreshTokens->revokeFamily($stored->family_id);
            $this->log(AuthEventType::RefreshReuseDetected->value, [
                'user_id' => $stored->user_id,
                'family_id' => $stored->family_id,
            ]);

            return RefreshRotationResult::failed('refresh_reuse_detected');
        }

        try {
            $newPlain = $this->refreshTokens->rotate($plainToken, $deviceFingerprint);
            $this->log(AuthEventType::RefreshTokenRotated->value, [
                'user_id' => $stored->user_id,
                'family_id' => $stored->family_id,
            ]);

            return RefreshRotationResult::ok($newPlain, $stored->user_id);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'device_fingerprint_mismatch') {
                $this->log(AuthEventType::RefreshReuseDetected->value, [
                    'user_id' => $stored->user_id,
                    'family_id' => $stored->family_id,
                    'reason' => 'fingerprint_mismatch',
                ]);
            }

            return RefreshRotationResult::failed($e->getMessage());
        }
    }

    public function log(string $event, array $context = []): void
    {
        $this->assertHmacKey();

        DB::transaction(function () use ($event, $context) {
            /** @var AuditLog|null $last */
            $last = AuditLog::query()->orderByDesc('seq')->lockForUpdate()->first();
            $seq = ($last?->seq ?? 0) + 1;
            $prevHash = $last?->envelope_hash;
            $ts = now()->toIso8601String();
            $userId = $context['user_id'] ?? null;
            $pseudonym = $this->pseudonymize($userId);

            $envelope = $this->encodeEnvelope([
                'seq' => $seq,
                'event' => $event,
                'ts' => $ts,
                'prev_hash' => $prevHash,
                'user_id' => $userId,
                'actor_pseudonym' => $pseudonym,
                'payload' => $context,
            ]);

            AuditLog::query()->create([
                'event_type' => $event,
                'user_id' => $userId,
                'actor_pseudonym' => $pseudonym,
                'payload' => $context,
                'envelope_hash' => $this->signEnvelope($envelope),
                'prev_hash' => $prevHash,
                'seq' => $seq,
                'ts_signed' => $ts,
            ]);
        });
    }

    public function verifyIntegrity(): bool
    {
        $this->assertHmacKey();

        $prevHash = null;
        $prevSeq = 0;

        foreach (AuditLog::query()->orderBy('seq')->cursor() as $log) {
            if ($log->seq !== $prevSeq + 1) {
                return false;
            }

            if ($prevHash !== null && $log->prev_hash !== $prevHash) {
                return false;
            }

            $expected = $this->encodeEnvelope([
                'seq' => $log->seq,
                'event' => $log->event_type,
                'ts' => $log->ts_signed,
                'prev_hash' => $log->prev_hash,
                'user_id' => $log->user_id,
                'actor_pseudonym' => $log->actor_pseudonym,
                'payload' => $log->payload,
            ]);

            if (! $this->verifyEnvelope($expected, $log->envelope_hash)) {
                return false;
            }

            $prevHash = $log->envelope_hash;
            $prevSeq = $log->seq;
        }

        return true;
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

    private function assertHmacKey(): void
    {
        $key = config('alma-auth.hmac_key');
        if (! is_string($key) || strlen($key) < 32) {
            throw new \RuntimeException(
                'alma/auth: ALMA_AUTH_HMAC_KEY / alma-auth.hmac_key must be a string of at least 32 bytes.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encodeEnvelope(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function signEnvelope(string $envelope): string
    {
        return hash_hmac('sha256', $envelope, (string) config('alma-auth.hmac_key'));
    }

    private function verifyEnvelope(string $envelope, string $hash): bool
    {
        return hash_equals($this->signEnvelope($envelope), $hash);
    }

    private function pseudonymize(mixed $userId): string
    {
        if ($userId === null || $userId === '') {
            return 'anonymous';
        }

        return hash('sha256', (string) $userId.'|'.(string) config('alma-auth.hmac_key'));
    }
}
