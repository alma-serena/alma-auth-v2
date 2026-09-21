<?php

declare(strict_types=1);

namespace Alma\Auth;

use Illuminate\Support\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contratos e bindings: entran por REQ de primitiva, no en génesis.
    }

    public function boot(): void
    {
        // Publicación de config/migraciones: entra por REQ de primitiva.
    }
}
