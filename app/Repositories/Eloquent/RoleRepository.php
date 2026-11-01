<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        private readonly Role $model
    ) {}

    /**
     * Find a role by its unique role name.
     */
    public function findByName(string $roleName): ?Role
    {
        return $this->model->newQuery()->where('name', $roleName)->first();
    }

    /**
     * All roles with their permissions and active user count.
     *
     * @return Collection<int, Role>
     */
    public function allWithPermissionsAndUserCount(): Collection
    {
        return $this->model->newQuery()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('id')
            ->get();
    }

    /**
     * Update a role's description and replace its permission set.
     *
     * @param  list<int>  $permissionIds
     */
    public function updateAccess(Role $role, ?string $description, array $permissionIds): Role
    {
        $role->update(['description' => $description]);
        $role->permissions()->sync($permissionIds);

        return $role->load('permissions:id,name')->loadCount('users');
    }
}
