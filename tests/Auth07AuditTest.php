<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Contracts\AuditLogger;
use Alma\Auth\Enums\AuthEventType;
use Alma\Auth\Models\AuditLog;
use Alma\Auth\Services\AuthService;
use Alma\Auth\Tests\Fixtures\User;

final class Auth07AuditTest extends TestCase
{
    public function test_log_chain_verifies_and_audit_logger_is_bound(): void
    {
        $this->assertInstanceOf(AuthService::class, $this->app->make(AuditLogger::class));

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);

        $auth->log(AuthEventType::LoginSucceeded->value, ['user_id' => 1]);
        $auth->log(AuthEventType::LoginFailed->value, ['email' => 'x@example.com']);

        $this->assertSame(2, AuditLog::query()->count());
        $this->assertTrue($auth->verifyIntegrity());
    }

    public function test_tampered_payload_fails_verify_integrity(): void
    {
        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $auth->log(AuthEventType::LoginSucceeded->value, ['user_id' => 7]);

        $row = AuditLog::query()->firstOrFail();
        $row->forceFill(['payload' => ['user_id' => 999]])->save();

        $this->assertFalse($auth->verifyIntegrity());
    }

    public function test_sequence_gap_fails_verify_integrity(): void
    {
        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $auth->log(AuthEventType::LoginSucceeded->value, ['user_id' => 1]);
        $auth->log(AuthEventType::LoginFailed->value, ['email' => 'a@b.c']);
        $auth->log(AuthEventType::RefreshTokenIssued->value, ['user_id' => 1]);

        AuditLog::query()->where('seq', 2)->delete();

        $this->assertFalse($auth->verifyIntegrity());
    }

    public function test_missing_hmac_key_fails_fast(): void
    {
        config(['alma-auth.hmac_key' => 'short']);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('32 bytes');
        $auth->log(AuthEventType::LoginFailed->value, []);
    }

    public function test_login_emits_audit_events(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ])->assertOk();

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::LoginFailed->value,
        ]);
        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::LoginSucceeded->value,
        ]);
        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::RefreshTokenIssued->value,
        ]);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $this->assertTrue($auth->verifyIntegrity());
    }

    public function test_refresh_reuse_emits_audit_event(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $login = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_fingerprint' => 'device-a',
        ]);
        $old = $login->json('refresh_token');

        $this->postJson('/api/alma-auth/refresh', [
            'refresh_token' => $old,
            'device_fingerprint' => 'device-a',
        ])->assertOk();

        $this->postJson('/api/alma-auth/refresh', [
            'refresh_token' => $old,
            'device_fingerprint' => 'device-a',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::RefreshReuseDetected->value,
        ]);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $this->assertTrue($auth->verifyIntegrity());
    }
}
