<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;

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
        return $this->model->newQuery()->where('nama_role', $roleName)->first();
    }
}
