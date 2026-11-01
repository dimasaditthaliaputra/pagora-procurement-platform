<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

interface RoleRepositoryInterface
{
    /**
     * Find a role by its unique role name.
     */
    public function findByName(string $roleName): ?Role;

    /**
     * All roles with their permissions and active user count.
     *
     * @return Collection<int, Role>
     */
    public function allWithPermissionsAndUserCount(): Collection;

    /**
     * Update a role's description and replace its permission set.
     *
     * @param  list<int>  $permissionIds
     */
    public function updateAccess(Role $role, ?string $description, array $permissionIds): Role;
}
