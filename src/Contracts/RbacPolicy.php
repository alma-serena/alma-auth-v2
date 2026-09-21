<?php

declare(strict_types=1);

namespace Alma\Auth\Contracts;

interface RbacPolicy
{
    public function syncRbacCatalog(): void;

    public function userHasPermission(
        AuthenticatableUser $user,
        string $resource,
        string $action,
        string $scope = 'own',
    ): bool;

    public function assignRole(AuthenticatableUser $user, string $role): void;

    public function revokeRole(AuthenticatableUser $user, string $role): void;

    /**
     * @return list<string>
     */
    public function rolesFor(AuthenticatableUser $user): array;
}
