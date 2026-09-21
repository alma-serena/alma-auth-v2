<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Models\RefreshToken;
use Alma\Auth\Tests\Fixtures\User;

final class Auth02RefreshTest extends TestCase
{
    public function test_login_returns_refresh_token_hashed_in_database(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_fingerprint' => 'device-a',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonStructure(['token', 'refresh_token']);

        $plain = $response->json('refresh_token');
        $this->assertIsString($plain);
        $this->assertDatabaseMissing('alma_auth_refresh_tokens', [
            'token_hash' => $plain,
        ]);
        $this->assertDatabaseHas('alma_auth_refresh_tokens', [
            'token_hash' => hash('sha256', $plain),
            'revoked' => false,
            'device_fingerprint' => 'device-a',
        ]);
    }

    public function test_refresh_rotates_and_issues_new_access_token(): void
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

        $oldRefresh = $login->json('refresh_token');

        $rotated = $this->postJson('/api/alma-auth/refresh', [
            'refresh_token' => $oldRefresh,
            'device_fingerprint' => 'device-a',
        ]);

        $rotated->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonStructure(['token', 'refresh_token']);

        $newRefresh = $rotated->json('refresh_token');
        $this->assertNotSame($oldRefresh, $newRefresh);

        $this->assertTrue(
            RefreshToken::query()->where('token_hash', hash('sha256', $oldRefresh))->value('revoked')
        );
        $this->assertFalse(
            (bool) RefreshToken::query()->where('token_hash', hash('sha256', $newRefresh))->value('revoked')
        );
    }

    public function test_reusing_rotated_refresh_revokes_family(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $login = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'device_fingerprint' => 'device-a',
        ]);
        $oldRefresh = $login->json('refresh_token');

        $rotated = $this->postJson('/api/alma-auth/refresh', [
            'refresh_token' => $oldRefresh,
            'device_fingerprint' => 'device-a',
        ]);
        $newRefresh = $rotated->json('refresh_token');

        $reuse = $this->postJson('/api/alma-auth/refresh', [
            'refresh_token' => $oldRefresh,
            'device_fingerprint' => 'device-a',
        ]);
        $reuse->assertUnauthorized()
            ->assertJsonPath('status', 'refresh_reuse_detected');

        $this->assertSame(
            0,
            RefreshToken::query()
                ->where('user_id', $user->id)
                ->where('revoked', false)
                ->count()
        );

        $sibling = $this->postJson('/api/alma-auth/refresh', [
            'refresh_token' => $newRefresh,
            'device_fingerprint' => 'device-a',
        ]);
        $sibling->assertUnauthorized();
    }

    public function test_fingerprint_mismatch_revokes_family(): void
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

        $bad = $this->postJson('/api/alma-auth/refresh', [
            'refresh_token' => $login->json('refresh_token'),
            'device_fingerprint' => 'device-b',
        ]);

        $bad->assertUnauthorized()
            ->assertJsonPath('status', 'device_fingerprint_mismatch');
    }
}
