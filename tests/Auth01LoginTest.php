<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Tests\Fixtures\User;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

final class Auth01LoginTest extends TestCase
{
    public function test_login_without_2fa_returns_full_token(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'two_factor_enabled' => false,
        ]);

        $response = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'authenticated');

        $plain = $response->json('token');
        $this->assertIsString($plain);

        $access = PersonalAccessToken::findToken($plain);
        $this->assertNotNull($access);
        $this->assertTrue($access->can('*'));
    }

    public function test_unknown_email_and_bad_password_share_error_message(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $unknown = $this->postJson('/api/alma-auth/login', [
            'email' => 'missing@example.com',
            'password' => 'whatever',
        ]);

        $wrong = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ]);

        $unknown->assertStatus(422);
        $wrong->assertStatus(422);
        $this->assertSame(
            $unknown->json('errors.email.0'),
            $wrong->json('errors.email.0')
        );
    }

    public function test_login_with_2fa_issues_partial_token_only(): void
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

        $login = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $login->assertOk()->assertJsonPath('status', '2fa_required');
        $partial = $login->json('token');
        $access = PersonalAccessToken::findToken($partial);
        $this->assertNotNull($access);
        $this->assertTrue($access->can('2fa:verify'));
        $this->assertFalse($access->can('*'));

        $blocked = $this->withToken($partial)->postJson('/api/alma-auth/2fa/enroll');
        $blocked->assertForbidden();

        $code = $google2fa->getCurrentOtp($secret);
        $verified = $this->withToken($partial)->postJson('/api/alma-auth/2fa/verify', [
            'code' => $code,
        ]);

        $verified->assertOk()->assertJsonPath('status', 'authenticated');
        $full = PersonalAccessToken::findToken($verified->json('token'));
        $this->assertNotNull($full);
        $this->assertTrue($full->can('*'));
        $this->assertSame(1, $user->fresh()->tokens()->where('name', 'auth')->count());
    }

    public function test_enrollment_requires_confirmation_before_2fa_is_active(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'two_factor_enabled' => false,
        ]);

        $login = $this->postJson('/api/alma-auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);
        $token = $login->json('token');

        $enroll = $this->withToken($token)->postJson('/api/alma-auth/2fa/enroll');
        $enroll->assertOk()->assertJsonPath('status', 'enrollment_started');
        $secret = $enroll->json('secret');
        $this->assertIsString($secret);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $code = (new Google2FA)->getCurrentOtp($secret);
        $confirm = $this->withToken($token)->postJson('/api/alma-auth/2fa/confirm', [
            'code' => $code,
        ]);
        $confirm->assertOk()->assertJsonPath('status', 'two_factor_enabled');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }
}
