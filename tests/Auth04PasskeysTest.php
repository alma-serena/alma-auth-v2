<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Enums\AuthEventType;
use Alma\Auth\Models\Passkey;
use Alma\Auth\Services\AuthService;
use Alma\Auth\Services\FakePasskeyCeremony;
use Alma\Auth\Tests\Fixtures\User;

final class Auth04PasskeysTest extends TestCase
{
    public function test_register_requires_step_up_then_persists_and_logs_in(): void
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

        $options = $this->withToken($token)
            ->postJson('/api/alma-auth/passkeys/register/options')
            ->assertOk()
            ->json();

        $challenge = $options['publicKey']['challenge'];
        $credential = FakePasskeyCeremony::fakeAttestationResponse($challenge, 'cred-ada-1');

        $this->withToken($token)
            ->postJson('/api/alma-auth/passkeys/register', [
                'challenge_id' => $options['challenge_id'],
                'credential' => $credential,
                'name' => 'Laptop',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'passkey_registered');

        $this->assertDatabaseHas('alma_auth_passkeys', [
            'user_id' => $user->id,
            'credential_id' => 'cred-ada-1',
            'name' => 'Laptop',
        ]);
        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::PasskeyRegistered->value,
        ]);

        $authOptions = $this->postJson('/api/alma-auth/passkeys/login/options', [
            'email' => 'ada@example.com',
        ])->assertOk()->json();

        $assertion = FakePasskeyCeremony::fakeAssertionResponse(
            $authOptions['publicKey']['challenge'],
            'cred-ada-1',
            (string) $user->id,
        );

        $this->postJson('/api/alma-auth/passkeys/login', [
            'challenge_id' => $authOptions['challenge_id'],
            'credential' => $assertion,
            'device_fingerprint' => 'device-pk',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonStructure(['token', 'refresh_token']);

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::PasskeyAuthenticated->value,
        ]);

        $stored = Passkey::query()->where('credential_id', 'cred-ada-1')->firstOrFail();
        $this->assertSame(1, $stored->counter);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $this->assertTrue($auth->verifyIntegrity());
    }

    public function test_register_without_step_up_is_forbidden(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $access = $user->createToken('auth', ['*'])->plainTextToken;

        $this->withToken($access)
            ->postJson('/api/alma-auth/passkeys/register/options')
            ->assertForbidden()
            ->assertJsonPath('status', 'step_up_required');
    }

    public function test_invalid_assertion_and_revoked_credential_fail(): void
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

        $options = $this->withToken($token)
            ->postJson('/api/alma-auth/passkeys/register/options')
            ->assertOk()
            ->json();

        $this->withToken($token)
            ->postJson('/api/alma-auth/passkeys/register', [
                'challenge_id' => $options['challenge_id'],
                'credential' => FakePasskeyCeremony::fakeAttestationResponse(
                    $options['publicKey']['challenge'],
                    'cred-to-revoke',
                ),
            ])
            ->assertOk();

        $passkeyId = (int) Passkey::query()->where('credential_id', 'cred-to-revoke')->value('id');

        $bad = $this->postJson('/api/alma-auth/passkeys/login/options', [
            'email' => 'ada@example.com',
        ])->json();

        $this->postJson('/api/alma-auth/passkeys/login', [
            'challenge_id' => $bad['challenge_id'],
            'credential' => FakePasskeyCeremony::fakeAssertionResponse(
                'wrong-challenge',
                'cred-to-revoke',
                (string) $user->id,
            ),
        ])->assertUnauthorized()->assertJsonPath('status', 'passkey_invalid');

        $this->withToken($token)
            ->deleteJson('/api/alma-auth/passkeys/'.$passkeyId)
            ->assertOk()
            ->assertJsonPath('status', 'passkey_revoked');

        $again = $this->postJson('/api/alma-auth/passkeys/login/options', [
            'email' => 'ada@example.com',
        ])->json();

        $this->postJson('/api/alma-auth/passkeys/login', [
            'challenge_id' => $again['challenge_id'],
            'credential' => FakePasskeyCeremony::fakeAssertionResponse(
                $again['publicKey']['challenge'],
                'cred-to-revoke',
                (string) $user->id,
            ),
        ])->assertUnauthorized();

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::PasskeyRevoked->value,
        ]);
    }

    public function test_list_passkeys_without_step_up(): void
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

        $options = $this->withToken($token)->postJson('/api/alma-auth/passkeys/register/options')->json();
        $this->withToken($token)->postJson('/api/alma-auth/passkeys/register', [
            'challenge_id' => $options['challenge_id'],
            'credential' => FakePasskeyCeremony::fakeAttestationResponse(
                $options['publicKey']['challenge'],
                'cred-list',
            ),
            'name' => 'Phone',
        ])->assertOk();

        // List must work without step-up: use a fresh token that never received step-up mark.
        $plainToken = $user->createToken('auth', ['*'])->plainTextToken;

        $this->withToken($plainToken)
            ->getJson('/api/alma-auth/passkeys')
            ->assertOk()
            ->assertJsonPath('passkeys.0.name', 'Phone');
    }
}
