<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Enums\AuthEventType;
use Alma\Auth\Models\RefreshToken;
use Alma\Auth\Services\AuthService;
use Alma\Auth\Tests\Fixtures\User;
use Laravel\Sanctum\PersonalAccessToken;

final class Auth10RbacRevocationTest extends TestCase
{
    public function test_rbac_catalog_assignment_and_unknown_permission_denied(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $auth->syncRbacCatalog();
        $auth->assignRole($user, 'user');

        $this->assertTrue($auth->userHasPermission($user, 'profile', 'read', 'own'));
        $this->assertFalse($auth->userHasPermission($user, 'users', 'read', 'any'));
        $this->assertFalse($auth->userHasPermission($user, 'billing', 'charge', 'own'));
        $this->assertSame(['user'], $auth->rolesFor($user));

        $auth->assignRole($user, 'admin');
        $this->assertTrue($auth->userHasPermission($user, 'users', 'read', 'any'));

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::RolesChanged->value,
        ]);
        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::SessionRevoked->value,
        ]);
        $this->assertTrue($auth->verifyIntegrity());
    }

    public function test_password_change_revokes_sessions_and_refresh(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $login = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_fingerprint' => 'dev-1',
        ]);
        $token = $login->json('token');
        $refresh = $login->json('refresh_token');

        $this->withToken($token)
            ->postJson('/api/alma-auth/password/change', [
                'current_password' => 'secret-password',
                'password' => 'new-secret-password',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'password_changed');

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::PasswordChanged->value,
        ]);

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
        $this->assertTrue(
            RefreshToken::query()->where('user_id', $user->id)->where('revoked', false)->doesntExist()
        );

        $this->postJson('/api/alma-auth/refresh', [
            'refresh_token' => $refresh,
            'device_fingerprint' => 'dev-1',
        ])->assertUnauthorized();

        $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'new-secret-password',
        ])->assertOk()->assertJsonPath('status', 'authenticated');
    }

    public function test_email_change_revokes_sessions(): void
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

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $code = $auth->requestEmailChange($user->fresh(), 'new@example.com');

        $this->withToken($token)
            ->postJson('/api/alma-auth/email/confirm', ['code' => $code])
            ->assertOk();

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::SessionRevoked->value,
        ]);
    }

    public function test_manual_session_revoke_requires_step_up(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $access = $user->createToken('auth', ['*'])->plainTextToken;

        $this->withToken($access)
            ->postJson('/api/alma-auth/sessions/revoke')
            ->assertForbidden()
            ->assertJsonPath('status', 'step_up_required');
    }
}
