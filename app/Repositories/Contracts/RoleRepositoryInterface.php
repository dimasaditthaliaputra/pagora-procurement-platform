<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Role;

interface RoleRepositoryInterface
{
    /**
     * Find a role by its unique role name.
     */
    public function findByName(string $roleName): ?Role;
}
