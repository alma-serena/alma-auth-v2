<?php

declare(strict_types=1);

namespace Alma\Auth\Tests\Fixtures;

use Alma\Auth\Contracts\AuthenticatableUser;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable implements AuthenticatableUser
{
    use HasApiTokens;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_secret',
        'two_factor_enabled',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'two_factor_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled;
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function setTwoFactorSecret(?string $encryptedSecret): void
    {
        $this->forceFill(['two_factor_secret' => $encryptedSecret])->save();
    }

    public function setTwoFactorEnabled(bool $enabled): void
    {
        $this->forceFill(['two_factor_enabled' => $enabled])->save();
    }

    public function getEmailForAlmaAuth(): string
    {
        return (string) $this->email;
    }

    public function setEmailForAlmaAuth(string $email): void
    {
        $this->forceFill(['email' => $email])->save();
    }

    public function setAuthPassword(string $plainPassword): void
    {
        $this->forceFill(['password' => $plainPassword])->save();
    }
}
