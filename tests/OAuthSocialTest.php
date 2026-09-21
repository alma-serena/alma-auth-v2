<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Enums\AuthEventType;
use Alma\Auth\Services\AuthService;
use Alma\Auth\Tests\Fixtures\User;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

final class OAuthSocialTest extends TestCase
{
    public function test_link_requires_step_up_then_login_works_without_password(): void
    {
        User::query()->create([
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
            ->postJson('/api/alma-auth/oauth/link', [
                'provider' => 'google',
                'access_token' => 'fake|google|google-sub-1|ada@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'oauth_linked');

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::OAuthLinked->value,
        ]);

        $this->withToken($token)
            ->getJson('/api/alma-auth/oauth/links')
            ->assertOk()
            ->assertJsonPath('links.0.provider', 'google');

        $oauth = $this->postJson('/api/alma-auth/oauth/login', [
            'provider' => 'google',
            'access_token' => 'fake|google|google-sub-1',
            'device_fingerprint' => 'dev-oauth',
        ]);
        $oauth->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonStructure(['token', 'refresh_token']);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $this->assertTrue($auth->verifyIntegrity());
    }

    public function test_unlinked_identity_does_not_login_or_auto_link_by_email(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/alma-auth/oauth/login', [
            'provider' => 'google',
            'access_token' => 'fake|google|never-linked|ada@example.com',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'oauth_invalid');

        $this->assertDatabaseCount('alma_auth_oauth_identities', 0);
        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::OAuthLoginFailed->value,
        ]);
    }

    public function test_oauth_login_respects_2fa(): void
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
        $auth->linkOAuth($user, 'google', [
            'access_token' => 'fake|google|g-2fa',
        ]);

        $this->postJson('/api/alma-auth/oauth/login', [
            'provider' => 'google',
            'access_token' => 'fake|google|g-2fa',
        ])
            ->assertOk()
            ->assertJsonPath('status', '2fa_required');
    }

    public function test_unlink_and_unknown_provider(): void
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
            ->postJson('/api/alma-auth/oauth/link', [
                'provider' => 'not-a-provider',
                'access_token' => 'fake|not-a-provider|x',
            ])
            ->assertStatus(422);

        $this->withToken($token)
            ->postJson('/api/alma-auth/oauth/link', [
                'provider' => 'github',
                'access_token' => 'fake|github|gh-1',
            ])
            ->assertOk();

        $this->withToken($token)
            ->deleteJson('/api/alma-auth/oauth/github')
            ->assertOk()
            ->assertJsonPath('status', 'oauth_unlinked');

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::OAuthUnlinked->value,
        ]);

        $this->postJson('/api/alma-auth/oauth/login', [
            'provider' => 'github',
            'access_token' => 'fake|github|gh-1',
        ])->assertUnauthorized();
    }

    public function test_link_without_step_up_forbidden(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $access = $user->createToken('auth', ['*'])->plainTextToken;

        $this->withToken($access)
            ->postJson('/api/alma-auth/oauth/link', [
                'provider' => 'google',
                'access_token' => 'fake|google|x',
            ])
            ->assertForbidden()
            ->assertJsonPath('status', 'step_up_required');
    }
}
