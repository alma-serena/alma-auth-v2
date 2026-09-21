<?php

declare(strict_types=1);

namespace Alma\Auth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuthenticatableUser extends Authenticatable
{
    public function hasTwoFactorEnabled(): bool;

    public function getTwoFactorSecret(): ?string;

    public function setTwoFactorSecret(?string $encryptedSecret): void;

    public function setTwoFactorEnabled(bool $enabled): void;

    public function getEmailForAlmaAuth(): string;

    public function setEmailForAlmaAuth(string $email): void;
}
