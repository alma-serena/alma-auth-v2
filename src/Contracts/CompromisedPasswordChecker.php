<?php

declare(strict_types=1);

namespace Alma\Auth\Contracts;

interface CompromisedPasswordChecker
{
    public function isCompromised(string $password): bool;
}
