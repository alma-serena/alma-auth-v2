<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Enums\AuthEventType;
use Alma\Auth\Models\EmailChangeRequest;
use Alma\Auth\Services\AuthService;
use Alma\Auth\Tests\Fixtures\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

final class Auth06StepUpEmailTest extends TestCase
{
    public function test_sensitive_routes_require_step_up_without_recent_mark(): void
    {
        $user = User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $access = $user->createToken('auth', ['*']);
        $token = $access->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/alma-auth/2fa/enroll')
            ->assertForbidden()
            ->assertJsonPath('status', 'step_up_required');

        $this->withToken($token)
            ->postJson('/api/alma-auth/step-up', ['password' => 'wrong'])
            ->assertUnauthorized();

        $this->withToken($token)
            ->postJson('/api/alma-auth/step-up', ['password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('status', 'step_up_ok');

        $enroll = $this->withToken($token)->postJson('/api/alma-auth/2fa/enroll');
        $enroll->assertOk()->assertJsonPath('status', 'enrollment_started');
    }

    public function test_login_marks_step_up_so_enrollment_works(): void
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

        $enroll = $this->withToken($token)->postJson('/api/alma-auth/2fa/enroll');
        $enroll->assertOk();
        $secret = $enroll->json('secret');
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->withToken($token)
            ->postJson('/api/alma-auth/2fa/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('status', 'two_factor_enabled');
    }

    public function test_email_change_requires_step_up_and_confirmation_code(): void
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
            ->postJson('/api/alma-auth/email/change', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('status', 'email_change_requested');

        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::EmailChangeRequested->value,
        ]);

        /** @var AuthService $auth */
        $auth = $this->app->make(AuthService::class);
        $pending = EmailChangeRequest::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotSame('new@example.com', $pending->code_hash);

        // HTTP does not return the code; host gets it from the service / notifier.
        $code = $auth->requestEmailChange($user->fresh(), 'newer@example.com');

        $this->withToken($token)
            ->postJson('/api/alma-auth/email/confirm', ['code' => 'bogus'])
            ->assertStatus(422);

        $this->withToken($token)
            ->postJson('/api/alma-auth/email/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('status', 'email_changed');

        $this->assertSame('newer@example.com', $user->fresh()->email);
        $this->assertDatabaseHas('alma_auth_audit', [
            'event_type' => AuthEventType::EmailChanged->value,
        ]);
        $this->assertTrue($auth->verifyIntegrity());
    }

    public function test_refresh_token_alone_does_not_grant_step_up(): void
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

        $refreshed = $this->postJson('/api/alma-auth/refresh', [
            'refresh_token' => $login->json('refresh_token'),
            'device_fingerprint' => 'device-a',
        ]);
        $newAccess = $refreshed->json('token');

        $access = PersonalAccessToken::findToken($newAccess);
        $this->assertNotNull($access);
        $this->assertNull(Cache::get('alma_auth_step_up:'.$access->id));

        $this->withToken($newAccess)
            ->postJson('/api/alma-auth/2fa/enroll')
            ->assertForbidden()
            ->assertJsonPath('status', 'step_up_required');
    }
}
