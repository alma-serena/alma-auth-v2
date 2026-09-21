<?php

declare(strict_types=1);

namespace Alma\Auth\Contracts;

interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, array $context = []): void;

    public function verifyIntegrity(): bool;
}
