<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Enums\AuthEventType;
use Alma\Auth\Models\ConsentRecord;
use Alma\Auth\Services\AuthService;
use Alma\Auth\Tests\Fixtures\User;

final class Auth08LegalConsentTest extends TestCase
{
    public function test_record_and_list_consent_hashes_ip_and_audits(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $login = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);
        $token = $login->json('token');

        $this->withToken($token)
            ->postJson('/api/alma-auth/legal/consent', [
                'purpose' => 'privacy_policy',
                'policy_version' => '2026.1',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'consent_recorded');

        $record = ConsentRecord::query()->firstOrFail();
        $this->assertSame('privacy_policy', $record->purpose);
        $this->assertNotNull($record->ip_hash);
        $this->assertStringNotContainsString('.', $record->ip_hash ?? '');

        $this->withToken($token)
            ->getJson('/api/alma-auth/legal/consents')
            ->assertOk()
            ->assertJsonPath('consents.0.policy_version', '2026.1')
            ->assertJsonMissingPath('consents.0.ip_hash');

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::LegalConsentRecorded->value,
        ]);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $this->assertTrue($auth->hasConsent($user->fresh(), 'privacy_policy', '2026.1'));
        $this->assertFalse($auth->hasConsent($user->fresh(), 'privacy_policy', '2027.0'));
        $this->assertTrue($auth->verifyIntegrity());
    }

    public function test_unknown_purpose_is_rejected(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $token = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/alma-auth/legal/consent', [
                'purpose' => 'not_in_catalog',
                'policy_version' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'invalid_purpose');
    }
}
