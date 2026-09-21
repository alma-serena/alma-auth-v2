<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Tests\Fixtures\User;
use Illuminate\Support\Facades\Cache;

final class Auth03LockoutTest extends TestCase
{
    public function test_lockout_blocks_even_with_correct_password_after_max_failures(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $max = (int) config('alma-auth.lockout_max_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->postJson('/api/alma-auth/login', [
                    'email' => 'ada@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertStatus(422);
        }

        $blocked = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('/api/alma-auth/login', [
                'email' => 'ada@example.com',
                'password' => 'secret-password',
            ]);

        $blocked->assertStatus(422);
        $this->assertSame(
            __('alma-auth::messages.invalid_credentials'),
            $blocked->json('errors.email.0')
        );
    }

    public function test_successful_login_clears_lockout_counter(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/alma-auth/login', [
                'email' => 'ada@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(422);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/alma-auth/login', [
                'email' => 'ada@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(422);

        $ok = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/alma-auth/login', [
                'email' => 'ada@example.com',
                'password' => 'secret-password',
            ]);

        $ok->assertOk()->assertJsonPath('status', 'authenticated');

        $key = 'alma_auth_lockout:'.hash('sha256', 'ada@example.com|10.0.0.2');
        $this->assertNull(Cache::get($key));
    }

    public function test_lockout_is_scoped_to_ip_and_email_pair(): void
    {
        User::query()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $max = (int) config('alma-auth.lockout_max_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
                ->postJson('/api/alma-auth/login', [
                    'email' => 'ada@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertStatus(422);
        }

        $otherIp = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
            ->postJson('/api/alma-auth/login', [
                'email' => 'ada@example.com',
                'password' => 'secret-password',
            ]);

        $otherIp->assertOk()->assertJsonPath('status', 'authenticated');
    }

    public function test_unknown_email_failures_also_count_toward_lockout(): void
    {
        $max = (int) config('alma-auth.lockout_max_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
                ->postJson('/api/alma-auth/login', [
                    'email' => 'ghost@example.com',
                    'password' => 'whatever',
                ])
                ->assertStatus(422);
        }

        User::query()->create([
            'name' => 'Ghost',
            'email' => 'ghost@example.com',
            'password' => 'secret-password',
        ]);

        $stillBlocked = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->postJson('/api/alma-auth/login', [
                'email' => 'ghost@example.com',
                'password' => 'secret-password',
            ]);

        $stillBlocked->assertStatus(422);
    }
}
