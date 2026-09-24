<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\AuditLogger;
use Alma\Auth\Contracts\AuthenticatableUser;
use Alma\Auth\Contracts\CompromisedPasswordChecker;
use Alma\Auth\Contracts\OAuthIdentityVerifier;
use Alma\Auth\Contracts\PasskeyCeremony;
use Alma\Auth\Contracts\RbacPolicy;
use Alma\Auth\Contracts\RefreshTokenRepository;
use Alma\Auth\Enums\AuthEventType;
use Alma\Auth\Models\AuditLog;
use Alma\Auth\Models\ConsentRecord;
use Alma\Auth\Models\EmailChangeRequest;
use Alma\Auth\Models\OAuthIdentity;
use Alma\Auth\Models\Passkey;
use Alma\Auth\Models\Role;
use Alma\Auth\Models\RolePermission;
use Alma\Auth\Models\TrustedDevice;
use Alma\Auth\Models\UserRole;
use Alma\Auth\Support\Base64Url;
use Alma\Auth\ValueObjects\LoginResult;
use Alma\Auth\ValueObjects\RefreshRotationResult;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class AuthService implements AuditLogger, RbacPolicy
{
    private static ?string $dummyPasswordHash = null;

    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private PasskeyCeremony $passkeyCeremony,
        private OAuthIdentityVerifier $oauthVerifier,
        private CompromisedPasswordChecker $compromisedPasswords,
    ) {}

    public function attemptLogin(
        string $email,
        string $password,
        string $ip = '0.0.0.0',
        string $deviceFingerprint = '',
    ): LoginResult {
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

        return $this->finishLogin($user, $ip, $deviceFingerprint);
    }

    public function openAccount(string $email, string $password, string $name, string $ip = '0.0.0.0'): bool
    {
        $email = mb_strtolower(trim($email));
        $name = trim($name);
        if ($name === '') {
            $local = strstr($email, '@', true);
            $name = is_string($local) && $local !== '' ? $local : 'cuenta';
        }

        if ($this->compromisedPasswords->isCompromised($password)) {
            Hash::make($password);
            $this->log(AuthEventType::AccountOpenRejected->value, [
                'email' => $email,
                'ip' => $ip,
                'reason' => 'compromised_password',
            ]);

            return false;
        }

        $existing = $this->resolveUser($email);
        if ($existing !== null) {
            Hash::make($password);
            $this->log(AuthEventType::AccountOpenIgnored->value, [
                'email' => $email,
                'ip' => $ip,
                'reason' => 'already_exists',
            ]);

            return true;
        }

        /** @var class-string<AuthenticatableUser> $model */
        $model = config('alma-auth.user_model');
        try {
            $user = $model::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
        } catch (UniqueConstraintViolationException) {
            Hash::make($password);
            $this->log(AuthEventType::AccountOpenIgnored->value, [
                'email' => $email,
                'ip' => $ip,
                'reason' => 'already_exists',
            ]);

            return true;
        }

        if (! $user instanceof AuthenticatableUser) {
            throw new \RuntimeException('alma/auth: user_model must implement AuthenticatableUser.');
        }

        $this->log(AuthEventType::AccountOpened->value, [
            'email' => $email,
            'ip' => $ip,
            'user_id' => $user->getAuthIdentifier(),
        ]);

        return true;
    }

    /**
     * @param  array{access_token?: string, id_token?: string}  $credential
     */
    public function attemptOAuthLogin(
        string $provider,
        array $credential,
        string $ip = '0.0.0.0',
        string $deviceFingerprint = '',
    ): LoginResult {
        $provider = strtolower(trim($provider));
        if (! $this->isAllowedOAuthProvider($provider)) {
            $this->log(AuthEventType::OAuthLoginFailed->value, [
                'provider' => $provider,
                'ip' => $ip,
                'reason' => 'provider_not_allowed',
            ]);

            return LoginResult::invalid();
        }

        try {
            $info = $this->oauthVerifier->verify($provider, $credential);
        } catch (\Throwable) {
            $this->log(AuthEventType::OAuthLoginFailed->value, [
                'provider' => $provider,
                'ip' => $ip,
                'reason' => 'verification_failed',
            ]);

            return LoginResult::invalid();
        }

        /** @var OAuthIdentity|null $identity */
        $identity = OAuthIdentity::query()
            ->where('provider', $info->provider)
            ->where('provider_user_id', $info->providerUserId)
            ->first();

        if ($identity === null) {
            $this->log(AuthEventType::OAuthLoginFailed->value, [
                'provider' => $provider,
                'ip' => $ip,
                'reason' => 'not_linked',
            ]);

            return LoginResult::invalid();
        }

        $user = $this->resolveUserById($identity->user_id);
        if ($user === null) {
            $this->log(AuthEventType::OAuthLoginFailed->value, [
                'provider' => $provider,
                'ip' => $ip,
                'reason' => 'user_missing',
            ]);

            return LoginResult::invalid();
        }

        return $this->finishLogin($user, $ip, $deviceFingerprint, 'oauth');
    }

    /**
     * @param  array{access_token?: string, id_token?: string}  $credential
     */
    public function linkOAuth(
        AuthenticatableUser $user,
        string $provider,
        array $credential,
    ): ?OAuthIdentity {
        $provider = strtolower(trim($provider));
        if (! $this->isAllowedOAuthProvider($provider)) {
            return null;
        }

        try {
            $info = $this->oauthVerifier->verify($provider, $credential);
        } catch (\Throwable) {
            return null;
        }

        $taken = OAuthIdentity::query()
            ->where('provider', $info->provider)
            ->where('provider_user_id', $info->providerUserId)
            ->where('user_id', '!=', $user->getAuthIdentifier())
            ->exists();

        if ($taken) {
            return null;
        }

        /** @var OAuthIdentity $identity */
        $identity = OAuthIdentity::query()->updateOrCreate(
            [
                'user_id' => $user->getAuthIdentifier(),
                'provider' => $info->provider,
            ],
            [
                'provider_user_id' => $info->providerUserId,
                'linked_at' => now(),
            ],
        );

        $this->log(AuthEventType::OAuthLinked->value, [
            'user_id' => $user->getAuthIdentifier(),
            'provider' => $info->provider,
            'identity_id' => $identity->id,
        ]);

        return $identity;
    }

    public function unlinkOAuth(AuthenticatableUser $user, string $provider): bool
    {
        $provider = strtolower(trim($provider));

        /** @var OAuthIdentity|null $identity */
        $identity = OAuthIdentity::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('provider', $provider)
            ->first();

        if ($identity === null) {
            return false;
        }

        $identity->delete();
        $this->log(AuthEventType::OAuthUnlinked->value, [
            'user_id' => $user->getAuthIdentifier(),
            'provider' => $provider,
        ]);

        return true;
    }

    /**
     * @return list<array{provider: string, linked_at: ?string}>
     */
    public function listOAuthLinks(AuthenticatableUser $user): array
    {
        return OAuthIdentity::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderBy('provider')
            ->get(['provider', 'linked_at'])
            ->map(static fn (OAuthIdentity $i): array => [
                'provider' => $i->provider,
                'linked_at' => $i->linked_at?->toIso8601String(),
            ])
            ->all();
    }

    public function isAllowedOAuthProvider(string $provider): bool
    {
        $allowed = config('alma-auth.oauth_providers', []);
        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        return in_array(strtolower(trim($provider)), array_map('strtolower', $allowed), true);
    }

    private function finishLogin(
        AuthenticatableUser $user,
        string $ip,
        string $deviceFingerprint,
        string $via = 'password',
    ): LoginResult {
        if ($user->hasTwoFactorEnabled()) {
            if ($this->touchTrustedDevice($user, $deviceFingerprint)) {
                $this->log(AuthEventType::LoginSucceeded->value, [
                    'user_id' => $user->getAuthIdentifier(),
                    'ip' => $ip,
                    'requires_2fa' => false,
                    'trusted_device' => true,
                    'via' => $via,
                ]);
                $this->log(AuthEventType::TrustedDeviceUsed->value, [
                    'user_id' => $user->getAuthIdentifier(),
                ]);

                return LoginResult::authenticated($user);
            }

            $this->log(AuthEventType::LoginSucceeded->value, [
                'user_id' => $user->getAuthIdentifier(),
                'ip' => $ip,
                'requires_2fa' => true,
                'via' => $via,
            ]);

            return LoginResult::requiresTwoFactor($user);
        }

        $this->log(AuthEventType::LoginSucceeded->value, [
            'user_id' => $user->getAuthIdentifier(),
            'ip' => $ip,
            'requires_2fa' => false,
            'via' => $via,
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

    public function markStepUpForToken(int|string $tokenId): void
    {
        $minutes = (int) config('alma-auth.step_up_minutes', 10);
        cache()->put(
            'alma_auth_step_up:'.$tokenId,
            now()->timestamp,
            now()->addMinutes(max($minutes, 1)),
        );
    }

    public function confirmStepUp(AuthenticatableUser $user, string $password, int|string $tokenId): bool
    {
        if (! Hash::check($password, $user->getAuthPassword())) {
            $this->log(AuthEventType::StepUpFailed->value, [
                'user_id' => $user->getAuthIdentifier(),
            ]);

            return false;
        }

        $this->markStepUpForToken($tokenId);
        $this->log(AuthEventType::StepUpSucceeded->value, [
            'user_id' => $user->getAuthIdentifier(),
        ]);

        return true;
    }

    /**
     * @return string Plain confirmation code for the host to deliver (email, etc.)
     */
    public function requestEmailChange(AuthenticatableUser $user, string $newEmail): string
    {
        EmailChangeRequest::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $plain = Str::random(32);
        $ttl = (int) config('alma-auth.email_change_ttl_minutes', 60);

        EmailChangeRequest::query()->create([
            'user_id' => $user->getAuthIdentifier(),
            'new_email' => strtolower($newEmail),
            'code_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->log(AuthEventType::EmailChangeRequested->value, [
            'user_id' => $user->getAuthIdentifier(),
            'new_email' => strtolower($newEmail),
        ]);

        return $plain;
    }

    public function confirmEmailChange(AuthenticatableUser $user, string $code): bool
    {
        /** @var EmailChangeRequest|null $pending */
        $pending = EmailChangeRequest::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if ($pending === null || ! $pending->isOpen() || ! hash_equals($pending->code_hash, hash('sha256', $code))) {
            $this->log(AuthEventType::EmailChangeFailed->value, [
                'user_id' => $user->getAuthIdentifier(),
            ]);

            return false;
        }

        $user->setEmailForAlmaAuth($pending->new_email);
        $pending->forceFill(['consumed_at' => now()])->save();

        $this->log(AuthEventType::EmailChanged->value, [
            'user_id' => $user->getAuthIdentifier(),
            'new_email' => $pending->new_email,
        ]);

        $this->revokeAllSessions($user, 'email_changed');

        return true;
    }

    /**
     * @return array{challenge_id: string, publicKey: array<string, mixed>}
     */
    public function beginPasskeyRegistration(AuthenticatableUser $user): array
    {
        $exclude = Passkey::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->pluck('credential_id')
            ->all();

        $options = $this->passkeyCeremony->creationOptions(
            (string) $user->getAuthIdentifier(),
            $user->getEmailForAlmaAuth(),
            $user->getEmailForAlmaAuth(),
            $exclude,
        );

        return $this->storePasskeyChallenge('registration', $options, $user->getAuthIdentifier());
    }

    /**
     * @param  array<string, mixed>  $clientCredential
     */
    public function completePasskeyRegistration(
        AuthenticatableUser $user,
        string $challengeId,
        array $clientCredential,
        string $originHost,
        ?string $name = null,
    ): ?Passkey {
        $cached = $this->pullPasskeyChallenge($challengeId);
        if ($cached === null
            || ($cached['type'] ?? null) !== 'registration'
            || (string) ($cached['user_id'] ?? '') !== (string) $user->getAuthIdentifier()
        ) {
            $this->log(AuthEventType::PasskeyAuthFailed->value, [
                'user_id' => $user->getAuthIdentifier(),
                'reason' => 'registration_challenge',
            ]);

            return null;
        }

        try {
            $verified = $this->passkeyCeremony->verifyAttestation(
                $clientCredential,
                $cached['options'],
                $originHost,
            );
        } catch (\Throwable) {
            $this->log(AuthEventType::PasskeyAuthFailed->value, [
                'user_id' => $user->getAuthIdentifier(),
                'reason' => 'attestation',
            ]);

            return null;
        }

        $hash = hash('sha256', $verified['credential_id']);
        if (Passkey::query()->where('credential_id_hash', $hash)->exists()) {
            $this->log(AuthEventType::PasskeyAuthFailed->value, [
                'user_id' => $user->getAuthIdentifier(),
                'reason' => 'duplicate_credential',
            ]);

            return null;
        }

        $passkey = Passkey::query()->create([
            'user_id' => $user->getAuthIdentifier(),
            'credential_id' => $verified['credential_id'],
            'credential_id_hash' => $hash,
            'public_key' => $verified['public_key'],
            'counter' => $verified['counter'],
            'name' => $name,
            'transports' => $verified['transports'],
            'aaguid' => $verified['aaguid'],
            'user_handle' => $verified['user_handle'],
        ]);

        $this->log(AuthEventType::PasskeyRegistered->value, [
            'user_id' => $user->getAuthIdentifier(),
            'passkey_id' => $passkey->id,
        ]);

        return $passkey;
    }

    /**
     * @return array{challenge_id: string, publicKey: array<string, mixed>}
     */
    public function beginPasskeyLogin(?string $email = null): array
    {
        $allow = [];
        $userId = null;

        if ($email !== null && $email !== '') {
            $user = $this->resolveUser($email);
            if ($user !== null) {
                $userId = $user->getAuthIdentifier();
                $allow = Passkey::query()
                    ->where('user_id', $userId)
                    ->whereNull('revoked_at')
                    ->pluck('credential_id')
                    ->all();
            } else {
                // Anti-enumeración: allowCredentials sintéticos (mismo shape).
                $allow = [Base64Url::encode(hash('sha256', 'alma-auth-fake-pk|'.strtolower($email), true))];
            }
        }

        $options = $this->passkeyCeremony->requestOptions($allow);

        return $this->storePasskeyChallenge('authentication', $options, $userId);
    }

    /**
     * @param  array<string, mixed>  $clientCredential
     */
    public function completePasskeyLogin(
        string $challengeId,
        array $clientCredential,
        string $originHost,
    ): LoginResult {
        $cached = $this->pullPasskeyChallenge($challengeId);
        if ($cached === null || ($cached['type'] ?? null) !== 'authentication') {
            $this->log(AuthEventType::PasskeyAuthFailed->value, [
                'reason' => 'auth_challenge',
            ]);

            return LoginResult::invalid();
        }

        $credentialId = (string) ($clientCredential['id'] ?? '');
        if ($credentialId === '') {
            $this->log(AuthEventType::PasskeyAuthFailed->value, [
                'reason' => 'missing_credential_id',
            ]);

            return LoginResult::invalid();
        }

        /** @var Passkey|null $passkey */
        $passkey = Passkey::query()
            ->where('credential_id_hash', hash('sha256', $credentialId))
            ->whereNull('revoked_at')
            ->first();

        if ($passkey === null) {
            $this->log(AuthEventType::PasskeyAuthFailed->value, [
                'reason' => 'unknown_or_revoked',
            ]);

            return LoginResult::invalid();
        }

        try {
            $verified = $this->passkeyCeremony->verifyAssertion(
                $clientCredential,
                $cached['options'],
                [
                    'credential_id' => $passkey->credential_id,
                    'public_key' => $passkey->public_key,
                    'counter' => $passkey->counter,
                    'transports' => $passkey->transports ?? [],
                    'aaguid' => $passkey->aaguid ?? '00000000-0000-0000-0000-000000000000',
                    'user_handle' => $passkey->user_handle,
                ],
                $originHost,
            );
        } catch (\Throwable) {
            $this->log(AuthEventType::PasskeyAuthFailed->value, [
                'user_id' => $passkey->user_id,
                'reason' => 'assertion',
            ]);

            return LoginResult::invalid();
        }

        $passkey->forceFill(['counter' => $verified['counter']])->save();

        $user = $this->resolveUserById($passkey->user_id);
        if ($user === null) {
            $this->log(AuthEventType::PasskeyAuthFailed->value, [
                'user_id' => $passkey->user_id,
                'reason' => 'user_missing',
            ]);

            return LoginResult::invalid();
        }

        $this->log(AuthEventType::PasskeyAuthenticated->value, [
            'user_id' => $user->getAuthIdentifier(),
            'passkey_id' => $passkey->id,
        ]);

        return LoginResult::authenticated($user);
    }

    /**
     * @return list<array{id: int, name: ?string, created_at: ?string}>
     */
    public function listPasskeys(AuthenticatableUser $user): array
    {
        return Passkey::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->get(['id', 'name', 'created_at'])
            ->map(static fn (Passkey $p): array => [
                'id' => (int) $p->id,
                'name' => $p->name,
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function revokePasskey(AuthenticatableUser $user, int $passkeyId): bool
    {
        /** @var Passkey|null $passkey */
        $passkey = Passkey::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', $passkeyId)
            ->whereNull('revoked_at')
            ->first();

        if ($passkey === null) {
            return false;
        }

        $passkey->forceFill(['revoked_at' => now()])->save();
        $this->log(AuthEventType::PasskeyRevoked->value, [
            'user_id' => $user->getAuthIdentifier(),
            'passkey_id' => $passkey->id,
        ]);

        return true;
    }

    public function markTrustedDevice(
        AuthenticatableUser $user,
        string $fingerprint,
        ?string $name = null,
    ): ?TrustedDevice {
        $fingerprint = trim($fingerprint);
        if ($fingerprint === '') {
            return null;
        }

        $ttl = (int) config('alma-auth.trusted_device_ttl_days', 90);
        $expires = now()->addDays(max($ttl, 1));

        /** @var TrustedDevice $device */
        $device = TrustedDevice::query()->updateOrCreate(
            [
                'user_id' => $user->getAuthIdentifier(),
                'fingerprint' => $fingerprint,
            ],
            [
                'name' => $name,
                'last_used_at' => now(),
                'expires_at' => $expires,
                'revoked_at' => null,
            ],
        );

        $this->log(AuthEventType::TrustedDeviceMarked->value, [
            'user_id' => $user->getAuthIdentifier(),
            'device_id' => $device->id,
        ]);

        return $device;
    }

    public function isTrustedDevice(AuthenticatableUser $user, string $fingerprint): bool
    {
        return $this->findActiveTrustedDevice($user, $fingerprint) !== null;
    }

    /**
     * @return list<array{id: int, name: ?string, fingerprint: string, last_used_at: ?string, expires_at: ?string}>
     */
    public function listTrustedDevices(AuthenticatableUser $user): array
    {
        return TrustedDevice::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->get()
            ->map(static fn (TrustedDevice $d): array => [
                'id' => (int) $d->id,
                'name' => $d->name,
                'fingerprint' => $d->fingerprint,
                'last_used_at' => $d->last_used_at?->toIso8601String(),
                'expires_at' => $d->expires_at?->toIso8601String(),
            ])
            ->all();
    }

    public function revokeTrustedDevice(AuthenticatableUser $user, int $deviceId): bool
    {
        /** @var TrustedDevice|null $device */
        $device = TrustedDevice::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', $deviceId)
            ->whereNull('revoked_at')
            ->first();

        if ($device === null) {
            return false;
        }

        $device->forceFill(['revoked_at' => now()])->save();
        $this->log(AuthEventType::TrustedDeviceRevoked->value, [
            'user_id' => $user->getAuthIdentifier(),
            'device_id' => $device->id,
        ]);

        return true;
    }

    public function recordConsent(
        AuthenticatableUser $user,
        string $purpose,
        string $policyVersion,
        ?string $ip = null,
    ): ?ConsentRecord {
        $purpose = strtolower(trim($purpose));
        $policyVersion = trim($policyVersion);
        if ($purpose === '' || $policyVersion === '' || ! $this->isAllowedLegalPurpose($purpose)) {
            return null;
        }

        $record = ConsentRecord::query()->create([
            'user_id' => $user->getAuthIdentifier(),
            'purpose' => $purpose,
            'policy_version' => $policyVersion,
            'ip_hash' => $ip !== null && $ip !== ''
                ? hash('sha256', $ip.'|'.(string) config('alma-auth.hmac_key'))
                : null,
            'accepted_at' => now(),
        ]);

        $this->log(AuthEventType::LegalConsentRecorded->value, [
            'user_id' => $user->getAuthIdentifier(),
            'purpose' => $purpose,
            'policy_version' => $policyVersion,
            'consent_id' => $record->id,
        ]);

        return $record;
    }

    public function hasConsent(
        AuthenticatableUser $user,
        string $purpose,
        ?string $minPolicyVersion = null,
    ): bool {
        $purpose = strtolower(trim($purpose));
        $query = ConsentRecord::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('purpose', $purpose)
            ->orderByDesc('id');

        /** @var ConsentRecord|null $latest */
        $latest = $query->first();
        if ($latest === null) {
            return false;
        }

        if ($minPolicyVersion === null || $minPolicyVersion === '') {
            return true;
        }

        return version_compare($latest->policy_version, $minPolicyVersion, '>=');
    }

    /**
     * @return list<array{id: int, purpose: string, policy_version: string, accepted_at: ?string}>
     */
    public function listConsents(AuthenticatableUser $user): array
    {
        return ConsentRecord::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByDesc('id')
            ->get(['id', 'purpose', 'policy_version', 'accepted_at'])
            ->map(static fn (ConsentRecord $c): array => [
                'id' => (int) $c->id,
                'purpose' => $c->purpose,
                'policy_version' => $c->policy_version,
                'accepted_at' => $c->accepted_at?->toIso8601String(),
            ])
            ->all();
    }

    public function isAllowedLegalPurpose(string $purpose): bool
    {
        $allowed = config('alma-auth.legal_purposes', []);
        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        return in_array(strtolower(trim($purpose)), array_map('strtolower', $allowed), true);
    }

    public function syncRbacCatalog(): void
    {
        $catalog = config('alma-auth.rbac.roles', []);
        if (! is_array($catalog)) {
            return;
        }

        DB::transaction(function () use ($catalog) {
            $keepRoleIds = [];

            foreach ($catalog as $name => $definition) {
                if (! is_string($name) || $name === '') {
                    continue;
                }

                /** @var Role $role */
                $role = Role::query()->updateOrCreate(
                    ['name' => $name],
                    ['description' => is_array($definition) ? ($definition['description'] ?? null) : null],
                );
                $keepRoleIds[] = $role->id;

                $permissions = is_array($definition) ? ($definition['permissions'] ?? []) : [];
                RolePermission::query()->where('role_id', $role->id)->delete();

                foreach ($permissions as $perm) {
                    if (! is_array($perm)) {
                        continue;
                    }
                    $resource = (string) ($perm['resource'] ?? '');
                    $action = (string) ($perm['action'] ?? '');
                    $scope = (string) ($perm['scope'] ?? 'own');
                    if ($resource === '' || $action === '' || ! in_array($scope, ['own', 'any'], true)) {
                        continue;
                    }

                    RolePermission::query()->create([
                        'role_id' => $role->id,
                        'resource' => $resource,
                        'action' => $action,
                        'scope' => $scope,
                    ]);
                }
            }

            if ($keepRoleIds !== []) {
                Role::query()->whereNotIn('id', $keepRoleIds)->delete();
            }
        });
    }

    public function userHasPermission(
        AuthenticatableUser $user,
        string $resource,
        string $action,
        string $scope = 'own',
    ): bool {
        if (! $this->permissionExistsInCatalog($resource, $action, $scope)) {
            return false;
        }

        return DB::table('alma_auth_user_roles as ur')
            ->join('alma_auth_role_permissions as rp', 'ur.role_id', '=', 'rp.role_id')
            ->where('ur.user_id', $user->getAuthIdentifier())
            ->where('rp.resource', $resource)
            ->where('rp.action', $action)
            ->whereIn('rp.scope', [$scope, 'any'])
            ->exists();
    }

    public function assignRole(AuthenticatableUser $user, string $role): void
    {
        $this->syncRbacCatalog();
        /** @var Role|null $roleModel */
        $roleModel = Role::query()->where('name', $role)->first();
        if ($roleModel === null) {
            throw new \InvalidArgumentException("Unknown role [{$role}].");
        }

        UserRole::query()->updateOrCreate(
            [
                'user_id' => $user->getAuthIdentifier(),
                'role_id' => $roleModel->id,
            ],
            [],
        );

        $this->log(AuthEventType::RolesChanged->value, [
            'user_id' => $user->getAuthIdentifier(),
            'role' => $role,
            'action' => 'assigned',
        ]);

        $this->revokeAllSessions($user, 'roles_changed');
    }

    public function revokeRole(AuthenticatableUser $user, string $role): void
    {
        /** @var Role|null $roleModel */
        $roleModel = Role::query()->where('name', $role)->first();
        if ($roleModel === null) {
            return;
        }

        UserRole::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('role_id', $roleModel->id)
            ->delete();

        $this->log(AuthEventType::RolesChanged->value, [
            'user_id' => $user->getAuthIdentifier(),
            'role' => $role,
            'action' => 'revoked',
        ]);

        $this->revokeAllSessions($user, 'roles_changed');
    }

    public function rolesFor(AuthenticatableUser $user): array
    {
        return DB::table('alma_auth_user_roles as ur')
            ->join('alma_auth_roles as r', 'ur.role_id', '=', 'r.id')
            ->where('ur.user_id', $user->getAuthIdentifier())
            ->orderBy('r.name')
            ->pluck('r.name')
            ->map(static fn ($n): string => (string) $n)
            ->values()
            ->all();
    }

    public function revokeAllSessions(AuthenticatableUser $user, string $reason = 'manual'): int
    {
        $refreshCount = $this->refreshTokens->revokeAllForUser($user->getAuthIdentifier());

        $sanctumDeleted = 0;
        if (method_exists($user, 'tokens')) {
            $sanctumDeleted = (int) $user->tokens()->delete();
        }

        $this->log(AuthEventType::SessionRevoked->value, [
            'user_id' => $user->getAuthIdentifier(),
            'reason' => $reason,
            'refresh_revoked' => $refreshCount,
            'access_revoked' => $sanctumDeleted,
        ]);

        return $refreshCount + $sanctumDeleted;
    }

    public function changePassword(
        AuthenticatableUser $user,
        string $currentPassword,
        string $newPassword,
    ): bool {
        if (! Hash::check($currentPassword, $user->getAuthPassword())) {
            return false;
        }

        $user->setAuthPassword($newPassword);
        $this->log(AuthEventType::PasswordChanged->value, [
            'user_id' => $user->getAuthIdentifier(),
        ]);
        $this->revokeAllSessions($user, 'password_changed');

        return true;
    }

    private function permissionExistsInCatalog(string $resource, string $action, string $scope): bool
    {
        $catalog = config('alma-auth.rbac.roles', []);
        if (! is_array($catalog)) {
            return false;
        }

        foreach ($catalog as $definition) {
            if (! is_array($definition)) {
                continue;
            }
            foreach ($definition['permissions'] ?? [] as $perm) {
                if (! is_array($perm)) {
                    continue;
                }
                if (
                    ($perm['resource'] ?? null) === $resource
                    && ($perm['action'] ?? null) === $action
                    && in_array($perm['scope'] ?? 'own', [$scope, 'any'], true)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function touchTrustedDevice(AuthenticatableUser $user, string $fingerprint): bool
    {
        $device = $this->findActiveTrustedDevice($user, $fingerprint);
        if ($device === null) {
            return false;
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    private function findActiveTrustedDevice(AuthenticatableUser $user, string $fingerprint): ?TrustedDevice
    {
        $fingerprint = trim($fingerprint);
        if ($fingerprint === '') {
            return null;
        }

        /** @var TrustedDevice|null $device */
        $device = TrustedDevice::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('fingerprint', $fingerprint)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        return $device;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{challenge_id: string, publicKey: array<string, mixed>}
     */
    private function storePasskeyChallenge(string $type, array $options, int|string|null $userId): array
    {
        $challengeId = (string) Str::uuid();
        $ttl = (int) config('alma-auth.passkey_challenge_ttl_minutes', 5);
        cache()->put(
            $this->passkeyChallengeKey($challengeId),
            [
                'type' => $type,
                'user_id' => $userId,
                'options' => $options,
            ],
            now()->addMinutes(max($ttl, 1)),
        );

        return [
            'challenge_id' => $challengeId,
            'publicKey' => $options,
        ];
    }

    /**
     * @return array{type: string, user_id: int|string|null, options: array<string, mixed>}|null
     */
    private function pullPasskeyChallenge(string $challengeId): ?array
    {
        $key = $this->passkeyChallengeKey($challengeId);
        $cached = cache()->pull($key);

        return is_array($cached) ? $cached : null;
    }

    private function passkeyChallengeKey(string $challengeId): string
    {
        return 'alma_auth_passkey_chal:'.$challengeId;
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

        $email = mb_strtolower(trim($email));
        $user = $model::query()->whereRaw('lower(email) = ?', [$email])->first();

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
