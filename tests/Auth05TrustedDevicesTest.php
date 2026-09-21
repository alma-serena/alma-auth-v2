<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Enums\AuthEventType;
use Alma\Auth\Models\TrustedDevice;
use Alma\Auth\Services\AuthService;
use Alma\Auth\Tests\Fixtures\User;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

final class Auth05TrustedDevicesTest extends TestCase
{
    public function test_trust_device_skips_subsequent_2fa_and_revoke_restores_challenge(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);

        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_enabled' => true,
        ]);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);

        $login = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_fingerprint' => 'laptop-a',
        ]);
        $login->assertOk()->assertJsonPath('status', '2fa_required');

        $this->assertTrue($auth->verifyTwoFactorChallenge($user->fresh(), $google2fa->getCurrentOtp($secret)));
        $device = $auth->markTrustedDevice($user->fresh(), 'laptop-a', 'MacBook');
        $this->assertNotNull($device);

        $this->assertDatabaseHas('alma_auth_trusted_devices', [
            'user_id' => $user->id,
            'fingerprint' => 'laptop-a',
            'name' => 'MacBook',
        ]);
        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::TrustedDeviceMarked->value,
        ]);

        $skip = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_fingerprint' => 'laptop-a',
        ]);
        $skip->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonStructure(['token', 'refresh_token']);

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::TrustedDeviceUsed->value,
        ]);

        $token = $skip->json('token');
        $list = $this->withToken($token)->getJson('/api/alma-auth/devices');
        $list->assertOk()->assertJsonPath('devices.0.fingerprint', 'laptop-a');
        $deviceId = (int) $list->json('devices.0.id');

        $this->withToken($token)
            ->deleteJson('/api/alma-auth/devices/'.$deviceId)
            ->assertOk()
            ->assertJsonPath('status', 'device_revoked');

        $again = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_fingerprint' => 'laptop-a',
        ]);
        $again->assertOk()->assertJsonPath('status', '2fa_required');

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::TrustedDeviceRevoked->value,
        ]);

        $this->assertTrue($auth->verifyIntegrity());
    }

    public function test_http_2fa_verify_can_mark_trusted_device(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);

        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_enabled' => true,
        ]);

        $login = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $this->withToken($login->json('token'))
            ->postJson('/api/alma-auth/2fa/verify', [
                'code' => $google2fa->getCurrentOtp($secret),
                'device_fingerprint' => 'phone-1',
                'trust_device' => true,
                'device_name' => 'Pixel',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'authenticated');

        $this->assertDatabaseHas('alma_auth_trusted_devices', [
            'fingerprint' => 'phone-1',
            'name' => 'Pixel',
        ]);

        $this->flushHeaders();

        $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_fingerprint' => 'phone-1',
        ])->assertOk()->assertJsonPath('status', 'authenticated');
    }

    public function test_empty_fingerprint_never_skips_2fa(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);

        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_enabled' => true,
        ]);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $this->assertNull($auth->markTrustedDevice($user, '', 'x'));

        $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ])->assertOk()->assertJsonPath('status', '2fa_required');
    }

    public function test_expired_device_requires_2fa_again(): void
    {
        $secret = (new Google2FA)->generateSecretKey(32);

        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_enabled' => true,
        ]);

        TrustedDevice::query()->create([
            'user_id' => $user->id,
            'fingerprint' => 'old-phone',
            'last_used_at' => now()->subDays(100),
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_fingerprint' => 'old-phone',
        ])->assertOk()->assertJsonPath('status', '2fa_required');
    }

    public function test_revoke_requires_step_up(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $device = TrustedDevice::query()->create([
            'user_id' => $user->id,
            'fingerprint' => 'laptop-a',
            'expires_at' => now()->addDays(30),
        ]);

        $access = $user->createToken('auth', ['*'])->plainTextToken;

        $this->withToken($access)
            ->deleteJson('/api/alma-auth/devices/'.$device->id)
            ->assertForbidden()
            ->assertJsonPath('status', 'step_up_required');
    }
}
