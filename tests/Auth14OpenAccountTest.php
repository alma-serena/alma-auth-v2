<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Models\AuditLog;
use Alma\Auth\Tests\Fixtures\User;
use Illuminate\Support\Facades\Hash;

final class Auth14OpenAccountTest extends TestCase
{
    public function test_register_opens_an_account_and_that_account_can_log_in(): void
    {
        $response = $this->postJson('/api/alma-auth/register', [
            'email' => 'Mesa@Example.com',
            'password' => 'una-clave-larga',
            'name' => 'Mesa',
        ]);

        $response->assertStatus(202)->assertExactJson(['status' => 'accepted']);

        $user = User::query()->where('email', 'mesa@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Mesa', $user->name);
        $this->assertTrue(Hash::check('una-clave-larga', $user->password));

        $login = $this->postJson('/api/alma-auth/login', [
            'email' => 'mesa@example.com',
            'password' => 'una-clave-larga',
        ]);
        $login->assertOk()->assertJsonPath('status', 'authenticated');

        $this->assertSame(
            1,
            AuditLog::query()->where('event_type', 'account.opened')->count()
        );
    }

    public function test_a_second_register_matches_the_first_response_and_keeps_the_password(): void
    {
        $first = $this->postJson('/api/alma-auth/register', [
            'email' => 'mesa@example.com',
            'password' => 'una-clave-larga',
        ]);
        $second = $this->postJson('/api/alma-auth/register', [
            'email' => 'MESA@example.com',
            'password' => 'otra-clave-larga',
        ]);

        $first->assertStatus(202);
        $second->assertStatus(202);
        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, User::query()->count());

        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('una-clave-larga', $user->password));
        $this->assertFalse(Hash::check('otra-clave-larga', $user->password));
    }

    public function test_a_compromised_password_does_not_open_an_account(): void
    {
        $response = $this->postJson('/api/alma-auth/register', [
            'email' => 'mesa@example.com',
            'password' => 'compromised-password',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_short_password_is_rejected(): void
    {
        $response = $this->postJson('/api/alma-auth/register', [
            'email' => 'mesa@example.com',
            'password' => 'corta',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, User::query()->count());
    }
}
